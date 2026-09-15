<?php
/**
 * Audit des formulaires : destinataire présent, expéditeur conforme, config d'envoi.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('client.fr');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : domaine de production attendu (ex. "client.fr") — sert à valider les "from".
 *
 * Les noms de propriétés du FormBuilder Breakdance varient selon les versions : on ne les
 * devine pas. On repère les nœuds de type formulaire, puis on remonte le **chemin JSON** de
 * chaque feuille dont la clé évoque un email, un destinataire ou un objet. Ces chemins sont
 * ensuite réutilisables tels quels par apply-forms.php.
 */
global $wpdb;
$domain = strtolower(trim($args[0] ?? ''));
$expected_from = $domain !== '' ? "formulaire@{$domain}" : null;

$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");

// Clés dont la valeur nous intéresse, et découpage par rôle.
const WF_ROLE = [
    'to'      => '/^(email_)?(to|recipient|recipients|destinataire|send_to|mail_to)$/i',
    'from'    => '/^(email_)?(from|from_email|sender|sender_email|reply_from)$/i',
    'replyto' => '/reply[_-]?to/i',
    'subject' => '/subject|objet/i',
    'name'    => '/^(from_name|sender_name|email_from_name)$/i',
];

/** Extrait l'adresse d'un « Nom <adresse> », format que CF7 et Breakdance utilisent couramment. */
function wf_email_of($raw) {
    $s = trim((string) $raw);
    if (preg_match('/<([^>]+)>/', $s, $m)) { $s = trim($m[1]); }
    return strtolower($s);
}

function wf_role_of($key) {
    foreach (WF_ROLE as $role => $re) { if (preg_match($re, (string) $key)) { return $role; } }
    // Toute autre clé contenant "mail" reste utile à voir, sans rôle assigné.
    return preg_match('/mail|email/i', (string) $key) ? 'other' : null;
}

/** Parcours récursif : collecte les feuilles scalaires porteuses d'un rôle email, avec leur chemin. */
function wf_collect($node, array $path, array &$out) {
    if (!is_array($node)) { return; }
    foreach ($node as $k => $v) {
        $here = array_merge($path, [(string) $k]);
        if (is_array($v)) { wf_collect($v, $here, $out); continue; }
        if (!is_scalar($v) && $v !== null) { continue; }
        $role = wf_role_of($k);
        if ($role === null) { continue; }
        $out[] = ['path' => implode('.', $here), 'key' => (string) $k, 'role' => $role,
                  'value' => is_bool($v) ? ($v ? 'true' : 'false') : (string) $v];
    }
}

/** Repère les sous-arbres de type formulaire et renvoie [chemin du nœud => type]. */
function wf_find_forms($node, array $path, array &$found) {
    if (!is_array($node)) { return; }
    $type = null;
    foreach (['type', 'name', 'slug'] as $k) {
        if (isset($node[$k]) && is_string($node[$k]) && preg_match('/form/i', $node[$k])) { $type = $node[$k]; break; }
    }
    if ($type !== null) { $found[implode('.', $path)] = $type; }
    foreach ($node as $k => $v) { if (is_array($v)) { wf_find_forms($v, array_merge($path, [(string) $k]), $found); } }
}

// ---- 1. Formulaires Breakdance -------------------------------------------------------------
$bd_forms = [];
if ($bd_key) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT p.ID, p.post_type, p.post_title, m.meta_value
           FROM {$wpdb->postmeta} m
           JOIN {$wpdb->posts} p ON p.ID = m.post_id
          WHERE m.meta_key = %s AND m.meta_value <> '' AND p.post_status = 'publish'", $bd_key));
    foreach ($rows as $r) {
        $tree = json_decode($r->meta_value, true);
        if (!is_array($tree)) { continue; }
        $found = []; wf_find_forms($tree, [], $found);
        if (!$found) { continue; }
        // On ne garde que les nœuds formulaire les plus hauts : un enfant d'un formulaire
        // déjà retenu (champ, bouton) n'est pas un formulaire de plus.
        $tops = [];
        foreach (array_keys($found) as $p) {
            $is_child = false;
            foreach (array_keys($found) as $q) { if ($q !== $p && $q !== '' && strpos($p, $q . '.') === 0) { $is_child = true; break; } }
            if (!$is_child) { $tops[] = $p; }
        }
        foreach ($tops as $p) {
            $sub = $tree;
            foreach (array_filter(explode('.', $p), 'strlen') as $seg) { $sub = $sub[$seg] ?? null; }
            $fields = []; wf_collect($sub, array_filter(explode('.', $p), 'strlen'), $fields);
            $bd_forms[] = ['post_id' => (int) $r->ID, 'post_type' => $r->post_type, 'title' => $r->post_title,
                           'node_path' => $p, 'element_type' => $found[$p], 'fields' => $fields];
        }
    }
}

// ---- 2. Plugins de formulaires tiers ------------------------------------------------------
$active = array_map(function ($f) { return strtolower(dirname($f) !== '.' ? dirname($f) : $f); },
                    (array) get_option('active_plugins', []));
$known = ['contact-form-7' => 'Contact Form 7', 'wpforms-lite' => 'WPForms', 'wpforms' => 'WPForms',
          'gravityforms' => 'Gravity Forms', 'fluentform' => 'Fluent Forms',
          'forminator' => 'Forminator', 'ninja-forms' => 'Ninja Forms', 'formidable' => 'Formidable'];
$third_party = [];
foreach ($known as $slug => $label) { if (in_array($slug, $active, true)) { $third_party[$slug] = ['label' => $label, 'forms' => []]; } }

// Contact Form 7 : destinataires dans la postmeta _mail du CPT wpcf7_contact_form.
if (isset($third_party['contact-form-7'])) {
    foreach (get_posts(['post_type' => 'wpcf7_contact_form', 'numberposts' => -1, 'post_status' => 'any']) as $f) {
        $mail = get_post_meta($f->ID, '_mail', true);
        $third_party['contact-form-7']['forms'][] = ['id' => $f->ID, 'title' => $f->post_title,
            'to' => is_array($mail) ? ($mail['recipient'] ?? null) : null,
            'from' => is_array($mail) ? ($mail['sender'] ?? null) : null,
            'subject' => is_array($mail) ? ($mail['subject'] ?? null) : null];
    }
}
// WPForms : configuration JSON dans post_content du CPT wpforms.
if (isset($third_party['wpforms-lite']) || isset($third_party['wpforms'])) {
    $slug = isset($third_party['wpforms']) ? 'wpforms' : 'wpforms-lite';
    foreach (get_posts(['post_type' => 'wpforms', 'numberposts' => -1, 'post_status' => 'any']) as $f) {
        $conf = json_decode($f->post_content, true);
        $notifs = $conf['settings']['notifications'] ?? [];
        $entry = ['id' => $f->ID, 'title' => $f->post_title, 'notifications' => []];
        foreach ((array) $notifs as $n) {
            $entry['notifications'][] = ['to' => $n['email'] ?? null, 'from' => $n['sender_address'] ?? null,
                                         'subject' => $n['subject'] ?? null, 'replyto' => $n['replyto'] ?? null];
        }
        $third_party[$slug]['forms'][] = $entry;
    }
}

// ---- 3. Shortcodes de formulaires posés dans les pages -----------------------------------
$shortcodes = [];
foreach (['contact-form-7', 'wpforms', 'gravityform', 'fluentform', 'forminator_form', 'ninja_form', 'formidable'] as $sc) {
    $n = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_content LIKE %s",
        '%[' . $sc . '%'));
    if ($n) { $shortcodes[$sc] = $n; }
}

// ---- 4. Configuration d'envoi -------------------------------------------------------------
$smtp_plugins = ['wp-mail-smtp' => 'WP Mail SMTP', 'easy-wp-smtp' => 'Easy WP SMTP',
                 'post-smtp' => 'Post SMTP', 'fluent-smtp' => 'FluentSMTP',
                 'wp-smtp' => 'WP SMTP', 'gmail-smtp' => 'Gmail SMTP'];
$smtp = [];
foreach ($smtp_plugins as $slug => $label) { if (in_array($slug, $active, true)) { $smtp[$slug] = $label; } }
$smtp_from = null;
if (isset($smtp['wp-mail-smtp'])) {
    $o = get_option('wp_mail_smtp', []);
    $smtp_from = ['from_email' => $o['mail']['from_email'] ?? null, 'from_name' => $o['mail']['from_name'] ?? null,
                  'mailer' => $o['mail']['mailer'] ?? null,
                  'force_from_email' => !empty($o['mail']['from_email_force'])];
}
if (isset($smtp['fluent-smtp'])) {
    $o = get_option('fluentmail-settings', []);
    $smtp_from = ['connections' => array_values(array_map(function ($c) {
        return ['from_email' => $c['sender_email'] ?? null, 'from_name' => $c['sender_name'] ?? null,
                'provider' => $c['provider'] ?? null, 'force_from_email' => $c['force_from_name'] ?? null];
    }, (array) ($o['connections'] ?? [])))];
}

// ---- 5. Verdict ---------------------------------------------------------------------------
$issues = [];
$check_from = function ($value, $where) use ($expected_from, $domain, &$issues) {
    $v = wf_email_of($value);
    if ($v === '') { return; }
    if ($expected_from === null) { return; }
    if ($v === $expected_from) { return; }
    $host = strpos($v, '@') !== false ? substr($v, strrpos($v, '@') + 1) : '';
    $issues[] = ['type' => 'from_non_conforme', 'where' => $where, 'value' => (string) $value,
                 'expected' => $expected_from,
                 'reason' => ($host !== '' && $host !== $domain)
                     ? "domaine expéditeur « $host » ≠ « $domain » : l'envoi sera refusé ou classé en spam"
                     : "préfixe attendu « formulaire@ »"];
};
foreach ($bd_forms as $f) {
    $roles = [];
    foreach ($f['fields'] as $fl) { $roles[$fl['role']][] = $fl; }
    $tos = array_filter($roles['to'] ?? [], function ($x) { return trim($x['value']) !== ''; });
    if (!$tos) {
        $issues[] = ['type' => 'destinataire_manquant', 'where' => "post {$f['post_id']} · {$f['node_path']}",
                     'reason' => 'aucun destinataire non vide : les messages du formulaire sont perdus'];
    }
    foreach ($tos as $fl) {
        if (preg_match('/\[[^\]]+\]|\{\{|%%/', $fl['value'])) {
            $issues[] = ['type' => 'destinataire_dynamique', 'where' => "post {$f['post_id']} · {$fl['path']}",
                         'value' => $fl['value'],
                         'reason' => 'destinataire construit sur un champ du visiteur : le message part au visiteur, pas au client'];
        }
    }
    foreach (($roles['from'] ?? []) as $fl) { $check_from($fl['value'], "post {$f['post_id']} · {$fl['path']}"); }
    if (empty($roles['from'])) {
        $issues[] = ['type' => 'from_absent', 'where' => "post {$f['post_id']} · {$f['node_path']}",
                     'reason' => 'aucune propriété expéditeur trouvée : WordPress utilisera wordpress@<host>'];
    }
}
foreach ($third_party as $slug => $tp) {
    foreach ($tp['forms'] as $f) {
        $notifs = $f['notifications'] ?? [$f];
        foreach ($notifs as $n) {
            $to_raw = (string) ($n['to'] ?? '');
            if (preg_match('/\[[^\]]+\]/', $to_raw)) {
                $issues[] = ['type' => 'destinataire_dynamique', 'where' => "$slug · form {$f['id']} ({$f['title']})",
                             'value' => $to_raw,
                             'reason' => 'destinataire construit sur un champ du visiteur : le message part au visiteur, pas au client'];
            }
            if (trim($to_raw) === '') {
                $issues[] = ['type' => 'destinataire_manquant', 'where' => "$slug · form {$f['id']} ({$f['title']})",
                             'reason' => 'notification sans destinataire'];
            }
            $check_from($n['from'] ?? '', "$slug · form {$f['id']} ({$f['title']})");
        }
    }
}
if ($domain !== '') {
    $admin = wf_email_of(get_option('admin_email'));
    $ahost = strpos($admin, '@') !== false ? substr($admin, strrpos($admin, '@') + 1) : '';
    if ($ahost !== '' && $ahost !== $domain && !preg_match('/gmail|outlook|hotmail|yahoo|free\.fr|orange\.fr|wanadoo/i', $ahost)) {
        $issues[] = ['type' => 'admin_email_suspect', 'where' => 'option admin_email', 'value' => $admin,
                     'reason' => "domaine « $ahost » : reste probablement du staging ou de l'agence"];
    }
}

echo wp_json_encode([
    'domain'          => $domain ?: null,
    'expected_from'   => $expected_from,
    'home'            => home_url('/'),
    'admin_email'     => get_option('admin_email'),
    'bd_meta_key'     => $bd_key,
    'breakdance_forms'=> $bd_forms,
    'third_party'     => $third_party,
    'shortcodes'      => $shortcodes,
    'smtp_plugins'    => $smtp,
    'smtp_from'       => $smtp_from,
    'issues'          => $issues,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
