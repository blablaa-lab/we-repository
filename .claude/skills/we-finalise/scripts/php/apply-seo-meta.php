<?php
/**
 * Applique les metas Rank Math.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('[{"post_id":12,"title":"…","description":"…"}]');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON [{post_id, title?, description?, focus_keyword?}]
 * Clés Rank Math : rank_math_title, rank_math_description, rank_math_focus_keyword.
 */
$items = json_decode($args[0] ?? '[]', true);
if (!is_array($items)) { return array('error' => 'JSON invalide'); }
$done = 0; $errors = [];
foreach ($items as $it) {
    $id = (int) ($it['post_id'] ?? 0);
    if (!$id || !get_post($id)) { $errors[] = "post $id introuvable"; continue; }
    if (isset($it['title']))         update_post_meta($id, 'rank_math_title', wp_slash(sanitize_text_field($it['title'])));
    if (isset($it['description']))   update_post_meta($id, 'rank_math_description', wp_slash(sanitize_text_field($it['description'])));
    if (isset($it['focus_keyword'])) update_post_meta($id, 'rank_math_focus_keyword', wp_slash(sanitize_text_field($it['focus_keyword'])));
    $done++;
}
echo wp_json_encode(['updated' => $done, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
