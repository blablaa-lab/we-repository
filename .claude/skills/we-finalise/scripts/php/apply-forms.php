<?php
/**
 * Corrige les formulaires : destinataires et expéditeurs.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('{"dry_run":true,"cf7":[{"form_id":5,"recipient":"contact@client.fr"}]}');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON {
 *   "dry_run": true,
 *   "breakdance": [ { "post_id": 42, "path": "root.children.0...email_from", "value": "formulaire@client.fr" } ],
 *   "cf7":        [ { "form_id": 5, "recipient": "contact@client.fr", "sender": "Cabinet <formulaire@client.fr>" } ],
 *   "smtp":       { "from_email": "formulaire@client.fr", "from_name": "Cabinet X", "force": true }
 * }
 * Les chemins Breakdance viennent tels quels de forms-audit.php. Un chemin inexistant est refusé :
 * créer une propriété que le builder ne lit pas ne réglerait rien.
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
if (!is_array($in)) { return array('error' => 'JSON invalide'); }
// Passe d'essai sûre par défaut : sans décision explicite, on n'écrit rien.
// `dry_run` et `dry` sont acceptés ; il faut un `false` explicite pour appliquer.
$dry = array_key_exists('dry_run', $in) ? !empty($in['dry_run'])
     : (array_key_exists('dry', $in) ? !empty($in['dry']) : true);
$log = ['dry_run' => $dry, 'breakdance' => [], 'cf7' => [], 'smtp' => null, 'errors' => []];

/** Valide « adresse@domaine » ou « Nom <adresse@domaine> ». */
function wf_valid_email($raw) {
    $s = trim((string) $raw);
    if (preg_match('/<([^>]+)>/', $s, $m)) { $s = trim($m[1]); }
    // Plusieurs destinataires séparés par des virgules : chacun doit être valide.
    foreach (explode(',', $s) as $one) {
        if (!is_email(trim($one))) { return false; }
    }
    return $s !== '';
}

// ---- Breakdance : écriture ciblée dans l'arbre JSON ---------------------------------------
$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");
$by_post = [];
foreach (($in['breakdance'] ?? []) as $item) { $by_post[(int) ($item['post_id'] ?? 0)][] = $item; }

foreach ($by_post as $post_id => $items) {
    if (!$post_id || !$bd_key) { $log['errors'][] = "post $post_id : arbre Breakdance introuvable"; continue; }
    $raw = get_post_meta($post_id, $bd_key, true);
    if ($raw === '' || $raw === null) { $log['errors'][] = "post $post_id : breakdance_data vide"; continue; }
    $env = null;
        $tree = wf_bd_decode($raw, $env);
    if (!is_array($tree)) { $log['errors'][] = "post $post_id : breakdance_data illisible"; continue; }

    $touched = false;
    foreach ($items as $item) {
        $path  = (string) ($item['path'] ?? '');
        $value = (string) ($item['value'] ?? '');
        $segs  = array_filter(explode('.', $path), 'strlen');
        if (!$segs) { $log['errors'][] = "post $post_id : chemin vide"; continue; }
        if (!wf_valid_email($value)) { $log['errors'][] = "post $post_id · $path : « $value » n'est pas une adresse valide"; continue; }

        // Descente jusqu'au parent, sans jamais créer de clé.
        $ref = &$tree; $ok = true;
        $last = array_pop($segs);
        foreach ($segs as $seg) {
            if (!is_array($ref) || !array_key_exists($seg, $ref)) { $ok = false; break; }
            $ref = &$ref[$seg];
        }
        if (!$ok || !is_array($ref) || !array_key_exists($last, $ref)) {
            $log['errors'][] = "post $post_id · $path : chemin inexistant (l'arbre a changé depuis l'audit ?)";
            unset($ref); continue;
        }
        if (is_array($ref[$last])) {
            $log['errors'][] = "post $post_id · $path : la cible est une structure, pas une valeur — refusé";
            unset($ref); continue;
        }
        $log['breakdance'][] = ['post_id' => $post_id, 'path' => $path,
                                'from' => (string) $ref[$last], 'to' => $value];
        if (!$dry) { $ref[$last] = $value; $touched = true; }
        unset($ref);
    }
    if ($touched && !$dry) {
        $new = wf_bd_encode($tree, $env, $raw);
        update_post_meta($post_id, $bd_key, is_string($new) ? wp_slash($new) : $new);
    }
}

// ---- Contact Form 7 : postmeta _mail -----------------------------------------------------
foreach (($in['cf7'] ?? []) as $item) {
    $id = (int) ($item['form_id'] ?? 0);
    if (!$id || get_post_type($id) !== 'wpcf7_contact_form') { $log['errors'][] = "cf7 : form $id introuvable"; continue; }
    $mail = get_post_meta($id, '_mail', true);
    if (!is_array($mail)) { $log['errors'][] = "cf7 : form $id sans _mail"; continue; }
    $entry = ['form_id' => $id, 'changes' => []];
    foreach (['recipient' => 'recipient', 'sender' => 'sender'] as $in_key => $meta_key) {
        if (!isset($item[$in_key])) { continue; }
        if (!wf_valid_email($item[$in_key])) { $log['errors'][] = "cf7 : form $id · $in_key invalide"; continue; }
        $entry['changes'][$meta_key] = ['from' => $mail[$meta_key] ?? null, 'to' => $item[$in_key]];
        $mail[$meta_key] = $item[$in_key];
    }
    if ($entry['changes'] && !$dry) { update_post_meta($id, '_mail', $mail); }
    $log['cf7'][] = $entry;
}

// ---- WP Mail SMTP : expéditeur global, la voie la plus fiable -----------------------------
if (!empty($in['smtp'])) {
    $s = $in['smtp'];
    if (isset($s['from_email']) && !wf_valid_email($s['from_email'])) {
        $log['errors'][] = 'smtp : from_email invalide';
    } else {
        $o = get_option('wp_mail_smtp', []);
        if (!is_array($o)) { $o = []; }
        $before = ['from_email' => $o['mail']['from_email'] ?? null, 'from_name' => $o['mail']['from_name'] ?? null,
                   'force' => !empty($o['mail']['from_email_force'])];
        if (isset($s['from_email'])) { $o['mail']['from_email'] = $s['from_email']; }
        if (isset($s['from_name']))  { $o['mail']['from_name']  = $s['from_name']; }
        if (isset($s['force']))      { $o['mail']['from_email_force'] = (bool) $s['force']; }
        $log['smtp'] = ['from' => $before, 'to' => ['from_email' => $o['mail']['from_email'] ?? null,
                        'from_name' => $o['mail']['from_name'] ?? null,
                        'force' => !empty($o['mail']['from_email_force'])]];
        if (!$dry) { update_option('wp_mail_smtp', $o); }
    }
}

if (!$dry) { wp_cache_flush(); }
echo wp_json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
