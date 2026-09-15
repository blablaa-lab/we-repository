# Independent Analytics — accès des éditeurs

> À compléter à la première exécution. Le réglage « qui peut voir les statistiques » existe dans les réglages du plugin ; sa clé d'option n'est pas documentée ici tant que la skill ne l'a pas relevée.

## Installation / activation

Regarde `active_plugins` (`breakdance-site-info`) : si `independent-analytics/…` y est, il est
actif ; s'il est présent sous `wp-content/plugins/` mais absent de la liste, il suffit de l'activer.
Par `php-eval` :

```php
include_once ABSPATH . 'wp-admin/includes/plugin.php';
$file = 'independent-analytics/iawp.php';
if (!file_exists(WP_PLUGIN_DIR . '/' . $file)) {
    // absent : installation depuis le dépôt
    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    include_once ABSPATH . 'wp-admin/includes/file.php';
    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    $api = plugins_api('plugin_information', array('slug' => 'independent-analytics'));
    $up  = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
    $up->install($api->download_link);
}
$res = activate_plugin($file);
return is_wp_error($res) ? array('error' => $res->get_error_message())
                         : array('active' => is_plugin_active($file));
```

Le nom du fichier principal varie selon la version : liste-le avec
`agent-connector-for-wp/file-list` sur `wp-content/plugins/independent-analytics/` plutôt que de le
supposer.

## Autoriser le rôle Éditeur

- Option : `<à renseigner>` (préfixe `iawp_`)
- Format : `<à renseigner, ex. tableau de slugs de rôles>`
- Écriture : `php-eval` qui relit l'option, ajoute `editor`, réécrit, puis relit — jamais un
  `update_option` à l'aveugle sur la valeur complète (`mcp.md` §3) :

```php
$roles = get_option('<option>', array());
if (!is_array($roles)) { return array('error' => 'format inattendu', 'value' => $roles); }
$from = $roles;
if (!in_array('editor', $roles, true)) { $roles[] = 'editor'; update_option('<option>', $roles); }
return array('from' => $from, 'to' => get_option('<option>'));
```

Découverte :

```php
global $wpdb;
$names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'iawp%'");
$hits  = array();
foreach ($names as $n) {
    if (preg_match('/role|permission|access|allow|capab/i', $n)) { $hits[$n] = get_option($n); }
}
return array('options' => $names, 'candidats' => $hits);
```

Si aucun nom ne parle, lis le code : `agent-connector-for-wp/file-list` sur
`wp-content/plugins/independent-analytics/`, puis `file-read` sur les fichiers de réglages et de
capacités, à la recherche de `iawp_` suivi de `role`, `permission` ou `access`.

Vérification : `php-eval` → `return get_users(array('role' => 'editor', 'fields' => array('ID','user_login')));`
puis, si un compte éditeur existe, le plugin doit lui montrer le menu Analytics — impossible à
tester par le canal MCP, note-le dans le rapport comme « à confirmer en se connectant avec un compte
éditeur ».
