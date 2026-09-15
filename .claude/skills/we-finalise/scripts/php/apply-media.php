<?php
/**
 * Applique alt / caption / description.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('[{"id":42,"alt":"Façade du cabinet"}]');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON [{id, alt?, caption?, description?}]
 * Seuls les champs présents sont modifiés. alt "" est une valeur valide (image décorative).
 */
$items = json_decode($args[0] ?? '[]', true);
if (!is_array($items)) { return array('error' => 'JSON invalide'); }
$done = 0; $errors = [];
foreach ($items as $it) {
    $id = (int) ($it['id'] ?? 0);
    if (!$id || get_post_type($id) !== 'attachment') { $errors[] = "attachment $id introuvable"; continue; }
    if (array_key_exists('alt', $it)) update_post_meta($id, '_wp_attachment_image_alt', wp_slash(sanitize_text_field($it['alt'])));
    $upd = ['ID' => $id];
    if (array_key_exists('caption', $it))     $upd['post_excerpt'] = wp_slash($it['caption']);
    if (array_key_exists('description', $it)) $upd['post_content'] = wp_slash($it['description']);
    if (array_key_exists('title', $it))       $upd['post_title']   = wp_slash($it['title']);
    if (count($upd) > 1) { $r = wp_update_post($upd, true); if (is_wp_error($r)) { $errors[] = "$id : " . $r->get_error_message(); continue; } }
    $done++;
}
echo wp_json_encode(['updated' => $done, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
