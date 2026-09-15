<?php
/**
 * Audit médiathèque : chaque image avec alt / légende / description / URL / utilisations.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   `code` = le corps de ce fichier sans sa balise d'ouverture PHP. Cette charge ne prend aucune entrée.
 * Sortie : JSON [{id,file,url,alt,caption,description,width,height,used_in:[{post_id,type,title}]}]
 */
global $wpdb;
$bd_key = $wpdb->get_var("SELECT meta_key FROM {$wpdb->postmeta} WHERE meta_key LIKE '%breakdance_data%' LIMIT 1");

// Corpus de recherche chargé une fois : post_content des contenus publiés + blobs Breakdance.
$corpus = [];
$rows = $wpdb->get_results("SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts}
    WHERE post_status = 'publish' AND post_type NOT IN ('attachment','revision','nav_menu_item')");
foreach ($rows as $r) $corpus[$r->ID] = ['type' => $r->post_type, 'title' => $r->post_title, 'text' => $r->post_content];
if ($bd_key) {
    $metas = $wpdb->get_results($wpdb->prepare("SELECT pm.post_id, pm.meta_value, p.post_type, p.post_title
        FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = %s AND p.post_status = 'publish'", $bd_key));
    foreach ($metas as $m) {
        if (!isset($corpus[$m->post_id])) $corpus[$m->post_id] = ['type' => $m->post_type, 'title' => $m->post_title, 'text' => ''];
        $corpus[$m->post_id]['text'] .= "\n" . $m->meta_value;
    }
}
// Options thème/site (logo, header via customizer) comptent aussi comme utilisation.
$site_icon = (int) get_option('site_icon'); $custom_logo = (int) get_theme_mod('custom_logo');

$atts = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'post_status' => 'inherit', 'numberposts' => -1]);
$out = [];
foreach ($atts as $a) {
    $url  = wp_get_attachment_url($a->ID);
    $file = basename($url);
    $stem = preg_replace('/\.[a-z0-9]+$/i', '', $file); // sans extension → matche aussi les tailles -300x200 et .webp
    $meta = wp_get_attachment_metadata($a->ID);
    $used = [];
    $needles = ['"id":' . $a->ID . ',', '"id":' . $a->ID . '}', 'wp-image-' . $a->ID, $stem];
    foreach ($corpus as $pid => $c) {
        foreach ($needles as $n) {
            if (strpos($c['text'], $n) !== false) { $used[] = ['post_id' => $pid, 'type' => $c['type'], 'title' => $c['title']]; break; }
        }
    }
    if ($a->ID === $site_icon)   $used[] = ['post_id' => 0, 'type' => 'site_icon', 'title' => 'Favicon'];
    if ($a->ID === $custom_logo) $used[] = ['post_id' => 0, 'type' => 'custom_logo', 'title' => 'Logo (customizer)'];
    $out[] = [
        'id' => $a->ID, 'file' => $file, 'url' => $url,
        'alt' => (string) get_post_meta($a->ID, '_wp_attachment_image_alt', true),
        'caption' => $a->post_excerpt, 'description' => $a->post_content, 'title' => $a->post_title,
        'width' => $meta['width'] ?? null, 'height' => $meta['height'] ?? null,
        'used_in' => $used,
    ];
}
echo wp_json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
