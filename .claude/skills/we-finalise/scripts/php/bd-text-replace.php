<?php
/**
 * Remplacement exact de texte dans l'arbre Breakdance (breakdance_data) ou, à défaut, dans post_content.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('{"dry_run":true,"items":[{"post_id":12,"from":"Lorem","to":"Texte"}]}');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON {dry_run: bool, items: [{post_id, from, to}]}
 * Règle : `from` doit apparaître exactement une fois dans le post ciblé, sinon la paire est refusée.
 * Ne touche jamais la structure : seules les feuilles texte de l'arbre sont modifiées.
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
$payload = json_decode($args[0] ?? '{}', true);
if (!is_array($payload)) { return array('error' => 'JSON invalide'); }
// Passe d'essai sûre par défaut : sans décision explicite, on n'écrit rien.
// `dry_run` et `dry` sont acceptés ; il faut un `false` explicite pour appliquer.
$dry = array_key_exists('dry_run', $payload) ? !empty($payload['dry_run'])
     : (array_key_exists('dry', $payload) ? !empty($payload['dry']) : true);
$items = $payload['items'] ?? [];
$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");

function wf_count_leaves(&$node, $from) {
    $n = 0;
    if (is_string($node)) return substr_count($node, $from);
    if (is_array($node)) foreach ($node as &$v) $n += wf_count_leaves($v, $from);
    return $n;
}
function wf_replace_leaves(&$node, $from, $to) {
    if (is_string($node)) { $node = str_replace($from, $to, $node); return; }
    if (is_array($node)) foreach ($node as &$v) wf_replace_leaves($v, $from, $to);
}

$results = [];
foreach ($items as $it) {
    $id = (int) ($it['post_id'] ?? 0); $from = (string) ($it['from'] ?? ''); $to = (string) ($it['to'] ?? '');
    if (!$id || $from === '') { $results[] = ['post_id' => $id, 'status' => 'refused', 'reason' => 'post_id ou from manquant']; continue; }

    $raw = $bd_key ? get_post_meta($id, $bd_key, true) : '';
    if ($raw !== '' && $raw !== null) {
        $env = null;
        $tree = wf_bd_decode($raw, $env);
        if ($tree === null) { $results[] = ['post_id' => $id, 'status' => 'refused', 'reason' => 'breakdance_data illisible']; continue; }
        $count = wf_count_leaves($tree, $from);
        if ($count !== 1) { $results[] = ['post_id' => $id, 'status' => 'refused', 'reason' => "from trouvé $count fois (attendu 1)", 'from' => $from]; continue; }
        if (!$dry) {
            wf_replace_leaves($tree, $from, $to);
            $new = wf_bd_encode($tree, $env, $raw);
            update_post_meta($id, $bd_key, is_string($new) ? wp_slash($new) : $new);
        }
        $results[] = ['post_id' => $id, 'status' => $dry ? 'ok (dry)' : 'ok', 'target' => 'breakdance'];
    } else {
        $post = get_post($id);
        if (!$post) { $results[] = ['post_id' => $id, 'status' => 'refused', 'reason' => 'post introuvable']; continue; }
        $count = substr_count($post->post_content, $from);
        if ($count !== 1) { $results[] = ['post_id' => $id, 'status' => 'refused', 'reason' => "from trouvé $count fois (attendu 1)", 'from' => $from]; continue; }
        if (!$dry) wp_update_post(['ID' => $id, 'post_content' => wp_slash(str_replace($from, $to, $post->post_content))]);
        $results[] = ['post_id' => $id, 'status' => $dry ? 'ok (dry)' : 'ok', 'target' => 'post_content'];
    }
}
echo wp_json_encode(['bd_meta_key' => $bd_key, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
