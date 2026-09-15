<?php
/**
 * Audit des URL en dur vers un domaine de préprod, dans toute la base.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('staging.client.fr,preprod.client.fr');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : hosts à chercher, séparés par des virgules. Vide = host de `home` (pendant la
 *            finalisation, le site tourne encore sur la préprod) + motifs de préprod connus.
 *
 * Sortie : JSON { current_host, searched, occurrences[], summary }
 * Chaque occurrence porte son emplacement lisible, sa classe (lien / media / autre) et, pour un
 * arbre Breakdance, le chemin JSON exact de la valeur — réutilisable par fix-hardcoded-urls.php.
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

$home_host = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
$asked = array_filter(array_map(function ($h) {
    $h = strtolower(trim($h));
    return $h === '' ? '' : (string) (parse_url(strpos($h, '//') !== false ? $h : "https://$h", PHP_URL_HOST) ?: $h);
}, explode(',', $args[0] ?? '')));

// Motifs qui trahissent une préprod oubliée, même après une bascule.
const WF_PREPROD = ['staging.', 'preprod.', 'pre-prod.', 'dev.', 'test.', 'recette.', 'demo.',
                    '.werocket.ovh', '.o2switch.net', '.wpengine.com', '.kinsta.cloud',
                    '.wpserveur.net', '.temp.domains', '.myraidbox.de', 'localhost', '.local'];

$hosts = $asked ?: [$home_host];
$extra = [];
if (!$asked) {
    // Autres hosts présents en base qui ressemblent à de la préprod.
    $rows = $wpdb->get_col(
        "SELECT DISTINCT option_value FROM {$wpdb->options} WHERE option_name IN ('home','siteurl')");
    foreach ($rows as $v) {
        $h = strtolower((string) parse_url($v, PHP_URL_HOST));
        if ($h && !in_array($h, $hosts, true)) { $hosts[] = $h; }
    }
    foreach (WF_PREPROD as $pat) { $extra[] = $pat; }
}
$hosts = array_values(array_unique(array_filter($hosts)));

/** Une URL pointant vers un fichier n'est pas un lien de navigation. */
function wf_classify($url) {
    $path = (string) parse_url($url, PHP_URL_PATH);
    if (preg_match('/\.(jpe?g|png|gif|webp|avif|svg|ico|mp4|webm|mov|mp3|wav|pdf|zip|rar|docx?|xlsx?|pptx?|csv|woff2?|ttf|eot|css|js)$/i', $path)) {
        return 'media';
    }
    if (preg_match('#/wp-json/|/wp-admin/|/xmlrpc\.php#i', $path)) { return 'autre'; }
    return 'lien';
}

/**
 * Toutes les URL d'un des hosts cherchés présentes dans une chaîne.
 * Le texte est normalisé d'abord : le JSON échappe les slashes (`https:\/\/`) et les paramètres
 * d'URL les encodent (`%3A%2F%2F`). Chercher la forme normalisée évite trois regexes fragiles ;
 * la correction, elle, traite chaque forme dans son écriture d'origine.
 */
function wf_urls_in($text, array $hosts) {
    if (!is_string($text) || $text === '') { return []; }
    $norm = str_replace(['\\/', '%3A%2F%2F', '%2F'], ['/', '://', '/'], $text);
    $found = [];
    foreach ($hosts as $needle) {
        if ($needle === '' || stripos($norm, $needle) === false) { continue; }
        $q = preg_quote($needle, '#');
        if (preg_match_all('#(?:https?://|//)[a-z0-9.\-]*' . $q . '[^\s"\'<>()\[\],;]*#i', $norm, $m)) {
            foreach (array_unique($m[0]) as $u) {
                $found[] = ['url' => $u, 'class' => wf_classify($u), 'host' => $needle];
            }
        } else {
            // Le host apparaît sans schéma (texte, attribut data, fichier de config) : signalé,
            // mais il n'y a pas d'URL à réécrire automatiquement.
            $found[] = ['url' => $needle, 'class' => 'autre', 'bare_host' => true, 'host' => $needle];
        }
    }
    return $found;
}

/** Feuilles texte d'un arbre décodé, avec leur chemin JSON. */
function wf_walk_leaves($node, array $path, array &$out) {
    if (is_string($node)) { $out[implode('.', $path)] = $node; return; }
    if (is_array($node)) { foreach ($node as $k => $v) { wf_walk_leaves($v, array_merge($path, [(string) $k]), $out); } }
}

$occ = [];
$like = function ($needle) use ($wpdb) { return '%' . $wpdb->esc_like($needle) . '%'; };
$all_needles = array_merge($hosts, $extra);

// ---- 1. posts : post_content et post_excerpt ---------------------------------------------
foreach ($all_needles as $needle) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, post_type, post_title, post_status, post_content, post_excerpt
           FROM {$wpdb->posts}
          WHERE post_status NOT IN ('auto-draft','inherit','trash')
            AND (post_content LIKE %s OR post_excerpt LIKE %s)", $like($needle), $like($needle)));
    foreach ($rows as $r) {
        foreach (['post_content' => $r->post_content, 'post_excerpt' => $r->post_excerpt] as $col => $val) {
            foreach (wf_urls_in($val, [$needle]) as $u) {
                $occ[] = array_merge($u, ['where' => "{$r->post_type} « {$r->post_title} » (id {$r->ID})",
                    'kind' => 'post', 'table' => 'posts', 'column' => $col,
                    'post_id' => (int) $r->ID, 'post_type' => $r->post_type, 'status' => $r->post_status]);
            }
        }
    }
}

// ---- 2. postmeta : inclut breakdance_data (pages ET modèles), ACF, Rank Math --------------
$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");
foreach ($all_needles as $needle) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT m.meta_id, m.post_id, m.meta_key, m.meta_value, p.post_type, p.post_title, p.post_status
           FROM {$wpdb->postmeta} m
           JOIN {$wpdb->posts} p ON p.ID = m.post_id
          WHERE m.meta_value LIKE %s
            AND p.post_status NOT IN ('auto-draft','trash')
            AND m.meta_key NOT LIKE '\\_oembed%'", $like($needle)));
    foreach ($rows as $r) {
        $label = "{$r->post_type} « {$r->post_title} » (id {$r->post_id})";
        if ($bd_key && $r->meta_key === $bd_key) {
            // Arbre Breakdance : on veut le chemin JSON de chaque feuille fautive.
            $env = null;
            $tree = wf_bd_decode($r->meta_value, $env);
            if (is_array($tree)) {
                $leaves = []; wf_walk_leaves($tree, [], $leaves);
                foreach ($leaves as $path => $text) {
                    foreach (wf_urls_in($text, [$needle]) as $u) {
                        $occ[] = array_merge($u, ['where' => $label, 'kind' => 'breakdance',
                            'table' => 'postmeta', 'key' => $r->meta_key, 'path' => $path,
                            'post_id' => (int) $r->post_id, 'post_type' => $r->post_type,
                            'template' => strpos($r->post_type, 'breakdance_') === 0]);
                    }
                }
                continue;
            }
        }
        if ($r->meta_key === '_menu_item_url') {
            foreach (wf_urls_in($r->meta_value, [$needle]) as $u) {
                $occ[] = array_merge($u, ['where' => "lien de menu (item {$r->post_id})", 'kind' => 'menu',
                    'table' => 'postmeta', 'key' => $r->meta_key, 'post_id' => (int) $r->post_id]);
            }
            continue;
        }
        foreach (wf_urls_in($r->meta_value, [$needle]) as $u) {
            $occ[] = array_merge($u, ['where' => $label, 'kind' => 'postmeta', 'table' => 'postmeta',
                'key' => $r->meta_key, 'post_id' => (int) $r->post_id, 'post_type' => $r->post_type]);
        }
    }
}

// ---- 3. options ---------------------------------------------------------------------------
foreach ($all_needles as $needle) {
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options}
          WHERE option_value LIKE %s
            AND option_name NOT LIKE '\\_transient%'
            AND option_name NOT LIKE '\\_site\\_transient%'
            AND option_name NOT LIKE '%\\_cache'
            AND option_name NOT LIKE 'rewrite\\_rules'", $like($needle)));
    foreach ($rows as $r) {
        $expected = in_array($r->option_name, ['home', 'siteurl'], true);
        foreach (wf_urls_in($r->option_value, [$needle]) as $u) {
            $occ[] = array_merge($u, ['where' => "option « {$r->option_name} »", 'kind' => 'option',
                'table' => 'options', 'key' => $r->option_name,
                'expected' => $expected, 'class' => $expected ? 'autre' : $u['class']]);
        }
    }
}

// ---- 4. termmeta, usermeta, commentaires -------------------------------------------------
foreach ($all_needles as $needle) {
    foreach ([[$wpdb->termmeta, 'term_id', 'meta_key', 'meta_value', 'termmeta'],
              [$wpdb->usermeta, 'user_id', 'meta_key', 'meta_value', 'usermeta']] as [$table, $idcol, $kcol, $vcol, $kind]) {
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT $idcol AS oid, $kcol AS k, $vcol AS v FROM $table WHERE $vcol LIKE %s", $like($needle)));
        foreach ($rows as $r) {
            foreach (wf_urls_in($r->v, [$needle]) as $u) {
                $occ[] = array_merge($u, ['where' => "$kind « {$r->k} » (id {$r->oid})", 'kind' => $kind,
                    'table' => $kind, 'key' => $r->k, 'oid' => (int) $r->oid]);
            }
        }
    }
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT comment_ID, comment_content FROM {$wpdb->comments} WHERE comment_content LIKE %s", $like($needle)));
    foreach ($rows as $r) {
        foreach (wf_urls_in($r->comment_content, [$needle]) as $u) {
            $occ[] = array_merge($u, ['where' => "commentaire {$r->comment_ID}", 'kind' => 'comment',
                'table' => 'comments', 'oid' => (int) $r->comment_ID]);
        }
    }
}

// ---- Synthèse -----------------------------------------------------------------------------
$by = function ($field) use ($occ) {
    $out = [];
    foreach ($occ as $o) { $k = (string) ($o[$field] ?? '?'); $out[$k] = ($out[$k] ?? 0) + 1; }
    arsort($out); return $out;
};
$templates = array_values(array_unique(array_map(function ($o) { return $o['where']; },
    array_filter($occ, function ($o) { return !empty($o['template']); }))));

echo wp_json_encode([
    'current_host'  => $home_host,
    'searched'      => $hosts,
    'patterns'      => $extra,
    'bd_meta_key'   => $bd_key,
    'total'         => count($occ),
    'summary'       => ['par_classe' => $by('class'), 'par_type' => $by('kind'), 'par_emplacement' => $by('where')],
    'templates_touches' => $templates,
    'occurrences'   => $occ,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
