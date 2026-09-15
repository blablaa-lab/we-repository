<?php
/**
 * Corrige les URL en dur relevées par hardcoded-urls.php.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('{"dry_run":true,"mode":"relative","audit":{…}}');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON {
 *   "dry_run": true,
 *   "mode": "relative",            // "relative" : https://preprod/x → /x  (valable en préprod ET en prod)
 *                                  // "swap"     : https://preprod/x → https://prod/x  (bascule)
 *   "to_host": "www.client.fr",    // requis en mode swap
 *   "classes": ["lien"],           // classes d'occurrences à traiter ; "media" à ajouter sciemment
 *   "kinds": ["post","breakdance","postmeta","menu","option","termmeta","usermeta"],
 *   "include_home": false,         // n'autorise home/siteurl qu'en swap, et sur demande explicite
 *   "audit": { … }                 // le contenu de .we-finalise/hardcoded-urls.json
 * }
 *
 * Le plan vient de l'audit : on ne redétecte rien. Avant chaque écriture, la valeur actuelle est
 * relue et l'URL doit toujours y être — sinon l'occurrence est refusée (le contenu a bougé).
 */

/**
 * L'arbre Breakdance est stocké de deux façons selon la version du builder :
 *   - anciennes versions : la postmeta décode directement vers l'arbre (`{"root":…}`) ;
 *   - Breakdance 3.x     : elle décode vers une **enveloppe** `{"tree_json_string":"<JSON de
 *     l'arbre>"}` — l'arbre est une chaîne JSON *dans* le JSON.
 *
 * Décoder un seul niveau sur une version 3.x donne l'enveloppe : on ne trouve alors aucune feuille
 * et la charge signale « 0 occurrence » sans rien faire — un échec muet, le pire des trois.
 * Ces deux fonctions gèrent les deux dispositions. `$env` retient l'enveloppe pour que le
 * réencodage rende exactement la même forme que celle lue.
 */
function wf_bd_decode($raw, &$env) {
    $env = null;
    $d = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($d)) return null;
    if (isset($d['tree_json_string']) && is_string($d['tree_json_string'])) {
        $inner = json_decode($d['tree_json_string'], true);
        if (is_array($inner)) { $env = $d; return $inner; }
        return null; // enveloppe présente mais arbre illisible : on refuse plutôt que d'écrire.
    }
    return $d;
}

function wf_bd_encode($tree, $env, $raw) {
    if (is_array($env)) {
        // Flags par défaut pour l'arbre interne : c'est la forme que le builder écrit lui-même
        // (slashes et unicode échappés). On ne réécrit pas sa convention de sérialisation.
        $env['tree_json_string'] = wp_json_encode($tree);
        return wp_json_encode($env);
    }
    return is_string($raw) ? wp_json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $tree;
}

global $wpdb;
$in = json_decode($args[0] ?? '{}', true);
if (!is_array($in) || empty($in['audit']['occurrences'])) {
    return array('error' => "Plan invalide : il faut le JSON d'audit dans la clé « audit ».");
}
// Passe d'essai sûre par défaut : sans décision explicite, on n'écrit rien.
// `dry_run` et `dry` sont acceptés ; il faut un `false` explicite pour appliquer.
$dry = array_key_exists('dry_run', $in) ? !empty($in['dry_run'])
     : (array_key_exists('dry', $in) ? !empty($in['dry']) : true);
$mode    = $in['mode'] ?? 'relative';
$to_host = strtolower(trim((string) ($in['to_host'] ?? '')));
$classes = $in['classes'] ?? ['lien'];
$kinds   = $in['kinds'] ?? ['post', 'breakdance', 'postmeta', 'menu', 'option'];
$inc_home = !empty($in['include_home']);
if (!in_array($mode, ['relative', 'swap'], true)) { return array('error' => "mode inconnu : $mode"); }
if ($mode === 'swap' && $to_host === '') { return array('error' => 'mode swap : to_host requis'); }

$log = ['dry_run' => $dry, 'mode' => $mode, 'changes' => [], 'skipped' => [], 'errors' => []];

/** Cible d'une URL selon le mode. Une URL réduite à la racine devient « / », jamais « ». */
function wf_target($url, $mode, $to_host) {
    if ($mode === 'swap') {
        return preg_replace('#^(https?:)?//[^/]+#i', 'https://' . $to_host, $url);
    }
    $rel = preg_replace('#^(https?:)?//[^/]+#i', '', $url);
    return ($rel === '' || $rel[0] !== '/') ? '/' . ltrim((string) $rel, '/') : $rel;
}

/** Remplace une URL dans un texte, quelle que soit son écriture (brute, JSON-échappée, encodée). */
function wf_replace_all_forms($text, $from, $to) {
    $forms = [
        [$from, $to],
        [str_replace('/', '\\/', $from), str_replace('/', '\\/', $to)],
        [str_replace([':', '/'], ['%3A', '%2F'], $from), str_replace([':', '/'], ['%3A', '%2F'], $to)],
    ];
    foreach ($forms as [$f, $t]) {
        if ($f !== '' && strpos($text, $f) !== false) { $text = str_replace($f, $t, $text); }
    }
    return $text;
}

/** Écrit une feuille de l'arbre à un chemin donné. Renvoie false si le chemin n'existe plus. */
function wf_set_leaf(&$tree, $path, callable $fn) {
    $segs = array_filter(explode('.', $path), 'strlen');
    if (!$segs) { return false; }
    $last = array_pop($segs);
    $ref = &$tree;
    foreach ($segs as $s) {
        if (!is_array($ref) || !array_key_exists($s, $ref)) { return false; }
        $ref = &$ref[$s];
    }
    if (!is_array($ref) || !array_key_exists($last, $ref) || !is_string($ref[$last])) { return false; }
    $ref[$last] = $fn($ref[$last]);
    return true;
}

/** Applique une fonction à toutes les chaînes d'une valeur désérialisée. Refuse les objets. */
function wf_map_strings($value, callable $fn, &$has_object) {
    if (is_object($value)) { $has_object = true; return $value; }
    if (is_string($value)) { return $fn($value); }
    if (is_array($value)) {
        foreach ($value as $k => $v) { $value[$k] = wf_map_strings($v, $fn, $has_object); }
    }
    return $value;
}

// --- Filtrage et regroupement des occurrences -----------------------------------------------
$groups = [];
foreach ($in['audit']['occurrences'] as $o) {
    $kind = $o['kind'] ?? '?';
    $class = $o['class'] ?? '?';
    $url = $o['url'] ?? '';
    $reason = null;
    if (!empty($o['bare_host']))                       { $reason = 'host sans schéma : rien à réécrire automatiquement'; }
    elseif (!in_array($kind, $kinds, true))            { $reason = "type « $kind » hors périmètre"; }
    elseif (!in_array($class, $classes, true))         { $reason = "classe « $class » non demandée"; }
    elseif (!empty($o['expected']) && !$inc_home)      { $reason = 'home/siteurl : à traiter à la bascule, pas ici'; }
    elseif ($kind === 'option' && in_array($o['key'] ?? '', ['home', 'siteurl'], true) && !$inc_home) {
        $reason = 'home/siteurl protégés';
    } elseif ($mode === 'relative' && $kind === 'option' && in_array($o['key'] ?? '', ['home', 'siteurl'], true)) {
        $reason = 'home/siteurl ne peuvent pas être relatifs';
    }
    if ($reason) { $log['skipped'][] = ['where' => $o['where'] ?? '?', 'url' => $url, 'reason' => $reason]; continue; }
    $key = implode('|', [$o['table'] ?? '', $o['column'] ?? '', $o['key'] ?? '', $o['post_id'] ?? ($o['oid'] ?? 0)]);
    $groups[$key][] = $o;
}

// --- Application ----------------------------------------------------------------------------
foreach ($groups as $items) {
    $first = $items[0];
    $table = $first['table'];
    $rewrite = function ($text) use ($items, $mode, $to_host) {
        foreach ($items as $o) {
            $text = wf_replace_all_forms($text, $o['url'], wf_target($o['url'], $mode, $to_host));
        }
        return $text;
    };
    $record = function ($o) use ($mode, $to_host, &$log) {
        $log['changes'][] = ['where' => $o['where'], 'kind' => $o['kind'], 'from' => $o['url'],
                             'to' => wf_target($o['url'], $mode, $to_host)] + (isset($o['path']) ? ['path' => $o['path']] : []);
    };

    if ($table === 'posts') {
        $id = (int) $first['post_id']; $col = $first['column'];
        $post = get_post($id);
        if (!$post) { $log['errors'][] = "post $id introuvable"; continue; }
        $current = $col === 'post_excerpt' ? $post->post_excerpt : $post->post_content;
        $new = $rewrite($current);
        if ($new === $current) { $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => 'URL absente du contenu actuel (contenu modifié depuis l\'audit ?)']; continue; }
        foreach ($items as $o) { $record($o); }
        if (!$dry) { wp_update_post(['ID' => $id, $col => wp_slash($new)]); }

    } elseif ($first['kind'] === 'breakdance') {
        $id = (int) $first['post_id']; $mk = $first['key'];
        $raw = get_post_meta($id, $mk, true);
        $env = null;
        $tree = wf_bd_decode($raw, $env);
        if (!is_array($tree)) { $log['errors'][] = "post $id : breakdance_data illisible"; continue; }
        $touched = false;
        foreach ($items as $o) {
            $ok = wf_set_leaf($tree, $o['path'], function ($leaf) use ($o, $mode, $to_host) {
                return wf_replace_all_forms($leaf, $o['url'], wf_target($o['url'], $mode, $to_host));
            });
            if (!$ok) { $log['skipped'][] = ['where' => $o['where'], 'url' => $o['url'],
                'reason' => "chemin {$o['path']} absent de l'arbre (modifié depuis l'audit ?)"]; continue; }
            $record($o); $touched = true;
        }
        if ($touched && !$dry) {
            $new = wf_bd_encode($tree, $env, $raw);
            update_post_meta($id, $mk, is_string($new) ? wp_slash($new) : $new);
        }

    } elseif ($table === 'postmeta') {
        $id = (int) $first['post_id']; $mk = $first['key'];
        $current = get_post_meta($id, $mk, true);
        $has_object = false;
        $new = wf_map_strings($current, $rewrite, $has_object);
        if ($has_object) { $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => "meta « $mk » contient un objet sérialisé : à corriger à la main"]; continue; }
        if ($new === $current) { $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => 'valeur inchangée (meta modifiée depuis l\'audit ?)']; continue; }
        foreach ($items as $o) { $record($o); }
        if (!$dry) { update_post_meta($id, $mk, is_string($new) ? wp_slash($new) : $new); }

    } elseif ($table === 'options') {
        $name = $first['key'];
        $current = get_option($name);
        $has_object = false;
        $new = wf_map_strings($current, $rewrite, $has_object);
        if ($has_object) { $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => "option « $name » contient un objet sérialisé : à corriger à la main"]; continue; }
        if ($new === $current) { $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => 'valeur inchangée'];  continue; }
        foreach ($items as $o) { $record($o); }
        if (!$dry) { update_option($name, $new); }

    } else {
        $log['skipped'][] = ['where' => $first['where'], 'url' => $first['url'],
            'reason' => "table « $table » non prise en charge par le script : à corriger à la main"];
    }
}

if (!$dry && $log['changes']) { wp_cache_flush(); }
$log['summary'] = ['modifiees' => count($log['changes']), 'ignorees' => count($log['skipped']),
                   'erreurs' => count($log['errors'])];
echo wp_json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
