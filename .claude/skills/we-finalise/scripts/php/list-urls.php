<?php
/**
 * Inventaire des contenus publiés + templates Breakdance.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('page,post,realisation');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : liste de post types séparés par des virgules (ex. "page,post,realisation").
 * Sortie : JSON [{id,type,title,slug,url,breakdance,template}]
 * La ligne finale « _meta » porte en plus « warnings » : les post types demandés inexistants.
 */
global $wpdb;
$types = array_filter(array_map('trim', explode(',', $args[0] ?? 'page,post')));

// Clé meta Breakdance détectée dynamiquement (breakdance_data / _breakdance_data selon versions).
$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");
$bd_ids = $bd_key ? array_map('intval', $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> ''", $bd_key
))) : [];

$out = []; $warnings = [];
foreach ($types as $type) {
    if (!post_type_exists($type)) { $warnings[] = "post type inconnu : $type"; continue; }
    $q = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC']);
    foreach ($q as $p) {
        $out[] = [
            'id' => $p->ID, 'type' => $type, 'title' => $p->post_title, 'slug' => $p->post_name,
            'url' => get_permalink($p), 'breakdance' => in_array($p->ID, $bd_ids, true), 'template' => false,
        ];
    }
}
// Templates / headers / footers / popups / blocks Breakdance : tous les CPT commençant par "breakdance_".
foreach (get_post_types([], 'names') as $pt) {
    if (strpos($pt, 'breakdance_') !== 0) continue;
    foreach (get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1]) as $p) {
        $out[] = ['id' => $p->ID, 'type' => $pt, 'title' => $p->post_title, 'slug' => $p->post_name,
                  'url' => null, 'breakdance' => true, 'template' => true];
    }
}
$out[] = ['id' => 0, 'type' => '_meta', 'title' => 'infos', 'slug' => null, 'url' => home_url('/'),
          'breakdance' => (bool) $bd_key, 'template' => false, 'bd_meta_key' => $bd_key,
          'blog_public' => (int) get_option('blog_public'), 'site_icon' => (int) get_option('site_icon'),
          'warnings' => $warnings];
echo wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
