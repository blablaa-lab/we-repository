<?php
/**
 * Applique la configuration des données structurées Rank Math.
 *
 * Exécution : ability MCP `agent-connector-for-wp/php-eval`.
 *   $args = array('{"dry_run":false,"module":true,"post_type_defaults":{"page":{"rich_snippet":"off"}}}');
 *   `code` = la ligne $args ci-dessus, suivie du corps de ce fichier sans sa balise d'ouverture PHP.
 * $args[0] : JSON {
 *   "dry_run": false,
 *   "module": true,                         // active le module rich-snippet
 *   "breadcrumbs": true,                    // active le fil d'Ariane (BreadcrumbList)
 *   "post_type_defaults": {                 // défauts par post type
 *     "post": { "rich_snippet": "article", "article_type": "BlogPosting",
 *               "snippet_name": "%title% %sep% %sitename%", "snippet_desc": "%excerpt%" },
 *     "page": { "rich_snippet": "off" }
 *   },
 *   "posts": [                              // schemas posés contenu par contenu
 *     { "post_id": 12, "replace": true,
 *       "schemas": [ { "@type": "Service", "name": "...", "serviceType": "..." } ] }
 *   ]
 * }
 * Sortie : JSON du diff appliqué (ou simulé).
 */
$in = json_decode($args[0] ?? '{}', true);
if (!is_array($in)) { return array('error' => 'JSON invalide'); }
// Passe d'essai sûre par défaut : sans décision explicite, on n'écrit rien.
// `dry_run` et `dry` sont acceptés ; il faut un `false` explicite pour appliquer.
$dry = array_key_exists('dry_run', $in) ? !empty($in['dry_run'])
     : (array_key_exists('dry', $in) ? !empty($in['dry']) : true);
$log = ['dry_run' => $dry, 'module' => null, 'breadcrumbs' => null, 'post_types' => [], 'posts' => [], 'errors' => []];

/** Écrit une option tableau sans écraser les clés voisines. */
$patch_option = function ($name, array $changes) use ($dry) {
    $current = get_option($name, []);
    if (!is_array($current)) { $current = []; }
    $diff = [];
    foreach ($changes as $k => $v) {
        if (($current[$k] ?? null) === $v) { continue; }
        $diff[$k] = ['from' => $current[$k] ?? null, 'to' => $v];
        $current[$k] = $v;
    }
    if ($diff && !$dry) { update_option($name, $current); }
    return $diff;
};

// 1. Module Schema (Structured Data).
if (!empty($in['module'])) {
    $modules = get_option('rank_math_modules', []);
    if (!is_array($modules)) { $modules = []; }
    if (in_array('rich-snippet', $modules, true)) {
        $log['module'] = 'déjà actif';
    } else {
        $modules[] = 'rich-snippet';
        if (!$dry) { update_option('rank_math_modules', array_values(array_unique($modules))); }
        $log['module'] = 'activé';
    }
}

// 2. Fil d'Ariane — alimente le BreadcrumbList du graphe.
if (array_key_exists('breadcrumbs', $in)) {
    $log['breadcrumbs'] = $patch_option('rank-math-options-general',
        ['breadcrumbs' => !empty($in['breadcrumbs']) ? 'on' : 'off']);
}

// 3. Défauts par post type.
$map = [
    'rich_snippet' => 'default_rich_snippet',
    'article_type' => 'default_article_type',
    'snippet_name' => 'default_snippet_name',
    'snippet_desc' => 'default_snippet_desc',
];
foreach (($in['post_type_defaults'] ?? []) as $type => $conf) {
    if (!post_type_exists($type)) { $log['errors'][] = "post type inexistant : $type"; continue; }
    $changes = [];
    foreach ($conf as $key => $value) {
        if (!isset($map[$key])) { $log['errors'][] = "clé inconnue pour $type : $key"; continue; }
        $changes["pt_{$type}_{$map[$key]}"] = $value;
    }
    $log['post_types'][$type] = $patch_option('rank-math-options-titles', $changes);
}

// 4. Schemas par contenu. Clé postmeta : rank_math_schema_<Type>.
foreach (($in['posts'] ?? []) as $item) {
    $id = (int) ($item['post_id'] ?? 0);
    if (!$id || !get_post($id)) { $log['errors'][] = "post $id introuvable"; continue; }
    $entry = ['post_id' => $id, 'removed' => [], 'written' => []];

    if (!empty($item['replace'])) {
        foreach (get_post_meta($id) as $key => $_) {
            if (strpos($key, 'rank_math_schema_') !== 0) { continue; }
            $entry['removed'][] = $key;
            if (!$dry) { delete_post_meta($id, $key); }
        }
    }
    foreach (($item['schemas'] ?? []) as $i => $schema) {
        $bare = ltrim($schema['@type'] ?? '', '\\');
        if ($bare === '') { $log['errors'][] = "post $id : schema sans @type"; continue; }
        // Rank Math attend un bloc `metadata` : sans lui le schema n'apparaît pas dans l'éditeur
        // et n'est pas marqué comme principal.
        $schema['metadata'] = array_merge([
            'title'     => $bare,
            'type'      => 'template',
            'shortcode' => 's-' . substr(md5($id . $bare . $i), 0, 12),
            'isPrimary' => $i === 0,
        ], $schema['metadata'] ?? []);
        $key = 'rank_math_schema_' . $bare;
        $entry['written'][] = $key;
        if (!$dry) { update_post_meta($id, $key, $schema); }
    }
    $log['posts'][] = $entry;
}

if (!$dry) { wp_cache_flush(); }
echo wp_json_encode($log, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
