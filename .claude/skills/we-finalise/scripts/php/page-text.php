<?php
/**
 * Texte lisible de chaque contenu publié, extrait de l'arbre Breakdance et de post_content.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('page,post,realisation');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : liste de post types séparés par des virgules (défaut "page,post").
 * Sortie : JSON {pages:[{post_id,type,title,slug,url,text,thin}], _meta:{…,thin_slugs}}
 *
 * Limite assumée : un arbre ne contient pas ce que résolvent les shortcodes, les boucles et les
 * données dynamiques. Les pages dont le texte est trop court sortent avec `thin: true` et sont
 * listées dans `_meta.thin_slugs` : complète-les par `breakdance-preview-post`.
 *
 * Alimente `scripts/site-info.mjs` (extraction NAP de l'étape 6) sans passer par un navigateur :
 * c'est ce qui affranchit l'étape 6 de Playwright. Le format `pages[].{text,url,slug}` est celui
 * qu'attend site-info.mjs — ne le change pas sans le mettre à jour lui aussi.
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
$types = array_filter(array_map('trim', explode(',', $args[0] ?? 'page,post')));
if (!$types) { return array('error' => 'aucun post type fourni'); }

$MAX_PAR_PAGE = 20000; // Un site entier de texte brut ne doit pas revenir en un seul appel.
$SEUIL_MAIGRE = 200;   // En dessous, l'arbre ne porte visiblement pas le texte de la page.

$bd_key = $wpdb->get_var(
    "SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1"
);

/**
 * Les feuilles d'un arbre Breakdance mêlent du texte visible et des valeurs techniques
 * (slugs, couleurs, identifiants, URL, unités CSS). On ne garde que ce qui ressemble à
 * de la prose : c'est suffisant pour une extraction NAP, et ça évite de noyer les regex.
 */
function wf_pt_is_prose($s) {
    $s = trim($s);
    if ($s === '' || strlen($s) < 3) return false;
    if (strlen($s) > 5000) return false;
    if (preg_match('~^(https?://|//|#|/|data:|\{|\[)~i', $s)) return false;
    if (preg_match('~^#?[0-9a-f]{3,8}$~i', $s)) return false;                 // couleur
    if (preg_match('~^-?[\d.]+\s*(px|em|rem|%|vh|vw|s|ms|fr|deg)?$~i', $s)) return false;
    if (strpos($s, chr(92)) !== false) return false;  // nom de classe PHP : EssentialElements\Container

    // La prose a des espaces. Un jeton isolé n'en a pas : c'est presque toujours une valeur
    // technique de l'arbre (slug d'élément, easing « power2.out », nom de contrôle) ou une
    // étiquette de formulaire. On ne le garde que s'il porte une donnée exploitable.
    if (!preg_match('~\s~u', $s)) {
        if (strpos($s, '@') !== false) return true;               // email
        return (bool) preg_match('~^[+()\d.\-]{5,}$~', $s);       // téléphone, code postal, SIRET
    }
    return true;
}

function wf_pt_collect($node, &$acc) {
    if (is_string($node)) {
        if (wf_pt_is_prose($node)) $acc[] = $node;
        return;
    }
    if (is_array($node)) foreach ($node as $v) wf_pt_collect($v, $acc);
}

$pages = array();
$warnings = array();

foreach ($types as $type) {
    if (!post_type_exists($type)) { $warnings[] = "post type inconnu : $type"; continue; }

    $posts = get_posts(array(
        'post_type'        => $type,
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'suppress_filters' => true,
    ));

    foreach ($posts as $p) {
        $parts = array();

        // Le titre compte : la raison sociale y figure souvent.
        if ($p->post_title !== '') $parts[] = $p->post_title;

        if ($bd_key) {
            $raw = get_post_meta($p->ID, $bd_key, true);
            if (is_string($raw) && $raw !== '') {
                $env = null;
                $tree = wf_bd_decode($raw, $env);
                if (is_array($tree)) wf_pt_collect($tree, $parts);
            }
        }

        // Contenus Gutenberg / classiques, et pages Breakdance qui gardent un post_content.
        if ($p->post_content !== '') {
            $txt = wp_strip_all_tags(strip_shortcodes($p->post_content), true);
            if (trim($txt) !== '') $parts[] = $txt;
        }

        // Dédoublonnage : un arbre répète beaucoup de chaînes identiques.
        $seen = array(); $keep = array();
        foreach ($parts as $s) {
            $s = trim(preg_replace('~\s+~u', ' ', $s));
            $k = mb_strtolower($s);
            if ($s === '' || isset($seen[$k])) continue;
            $seen[$k] = true; $keep[] = $s;
        }

        $text = implode("\n", $keep);
        $truncated = false;
        if (mb_strlen($text) > $MAX_PAR_PAGE) {
            $text = mb_substr($text, 0, $MAX_PAR_PAGE);
            $truncated = true;
        }

        // Un arbre peut ne contenir presque aucun texte : shortcode, boucle, données dynamiques
        // (une page de mentions légales générée par un plugin, typiquement). Le texte existe
        // alors seulement au rendu. On le signale plutôt que de rendre une page vide en silence :
        // l'appelant complète ces pages-là par `breakdance-preview-post` (voir mcp.md §2).
        $maigre = mb_strlen($text) < $SEUIL_MAIGRE;

        $pages[] = array(
            'post_id'   => (int) $p->ID,
            'type'      => $type,
            'title'     => $p->post_title,
            'slug'      => $p->post_name,
            'url'       => get_permalink($p->ID),
            'text'      => $text,
            'truncated' => $truncated,
            'thin'      => $maigre,
        );
    }
}

echo wp_json_encode(array(
    'pages' => $pages,
    '_meta' => array(
        'bd_meta_key'   => $bd_key,
        'types'         => $types,
        'pages_count'   => count($pages),
        'thin_slugs'    => array_values(array_map(
            function ($x) { return $x['slug']; },
            array_filter($pages, function ($x) { return $x['thin']; })
        )),
        'seuil_maigre'  => $SEUIL_MAIGRE,
        'max_par_page'  => $MAX_PAR_PAGE,
        'warnings'      => $warnings,
    ),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
