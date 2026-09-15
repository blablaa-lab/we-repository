<?php
/**
 * Audit des données structurées : post types réellement présents + config Schema Rank Math.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('page,post,realisation');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : optionnel, liste de post types à forcer dans le rapport (séparés par des virgules).
 * Sortie : JSON { module_active, breadcrumbs, local_business_type, knowledgegraph_type,
 *                post_types: [...], existing_schemas: [...], option_keys: [...] }
 */
global $wpdb;

$modules = get_option('rank_math_modules', []);
$titles  = get_option('rank-math-options-titles', []);
$general = get_option('rank-math-options-general', []);
if (!is_array($modules)) { $modules = []; }
if (!is_array($titles))  { $titles  = []; }
if (!is_array($general)) { $general = []; }

$forced = array_filter(array_map('trim', explode(',', $args[0] ?? '')));

// Post types candidats : publics, hors natifs techniques et hors CPT d'infrastructure Breakdance.
$skip = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset',
         'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part',
         'wp_global_styles', 'wp_navigation', 'wp_font_family', 'wp_font_face'];

$post_types = [];
foreach (get_post_types(['public' => true], 'objects') as $slug => $obj) {
    if (in_array($slug, $skip, true) || strpos($slug, 'breakdance_') === 0) { continue; }
    $post_types[$slug] = [
        'slug'        => $slug,
        'label'       => $obj->labels->name ?? $slug,
        'builtin'     => (bool) $obj->_builtin,
        'published'   => (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'", $slug)),
        'has_archive' => (bool) $obj->has_archive,
        'taxonomies'  => array_values(get_object_taxonomies($slug)),
        'rank_math'   => [
            'rich_snippet' => $titles["pt_{$slug}_default_rich_snippet"] ?? null,
            'article_type' => $titles["pt_{$slug}_default_article_type"] ?? null,
            'snippet_name' => $titles["pt_{$slug}_default_snippet_name"] ?? null,
            'snippet_desc' => $titles["pt_{$slug}_default_snippet_desc"] ?? null,
            'robots'       => $titles["pt_{$slug}_robots"] ?? null,
        ],
    ];
}
// Post types demandés explicitement mais non publics : on les signale quand même.
foreach ($forced as $slug) {
    if (isset($post_types[$slug])) { continue; }
    $post_types[$slug] = post_type_exists($slug)
        ? ['slug' => $slug, 'label' => $slug, 'builtin' => false, 'published' => 0,
           'has_archive' => false, 'taxonomies' => [], 'rank_math' => [], 'note' => 'post type non public']
        : ['slug' => $slug, 'note' => 'post type inexistant'];
}

// Schemas déjà posés contenu par contenu (nouveau système : postmeta rank_math_schema_<Type>).
$rows = $wpdb->get_results(
    "SELECT p.ID, p.post_type, p.post_title, m.meta_key
       FROM {$wpdb->postmeta} m
       JOIN {$wpdb->posts} p ON p.ID = m.post_id
      WHERE m.meta_key LIKE 'rank\\_math\\_schema\\_%'
        AND p.post_status = 'publish'
      ORDER BY p.post_type, p.ID"
);
$existing = [];
foreach ($rows as $r) {
    $existing[] = ['id' => (int) $r->ID, 'type' => $r->post_type, 'title' => $r->post_title,
                   'schema' => substr($r->meta_key, strlen('rank_math_schema_'))];
}
// Ancien système, encore présent sur les sites migrés.
$legacy = (int) $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rank_math_rich_snippet' AND meta_value <> ''"
);

// Clés d'options liées au schema : la nomenclature varie selon la version du plugin.
$option_keys = array_values(array_filter(array_keys($titles), function ($k) {
    return strpos($k, 'rich_snippet') !== false || strpos($k, 'snippet_') !== false
        || strpos($k, 'schema') !== false || strpos($k, 'knowledgegraph') !== false
        || strpos($k, 'local_') !== false;
}));

echo wp_json_encode([
    'module_active'        => in_array('rich-snippet', $modules, true),
    'modules'             => array_values($modules),
    'setup_mode'          => $general['setup_mode'] ?? null,
    'breadcrumbs'         => $general['breadcrumbs'] ?? null,
    'knowledgegraph_type' => $titles['knowledgegraph_type'] ?? null,
    'local_business_type' => $titles['local_business_type'] ?? null,
    'homepage_id'         => (int) get_option('page_on_front'),
    'post_types'          => array_values($post_types),
    'existing_schemas'    => $existing,
    'legacy_snippets'     => $legacy,
    'option_keys'         => $option_keys,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
