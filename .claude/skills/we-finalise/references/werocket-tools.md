# Plugin werocket tools — module Cookies

> À compléter par l'équipe : ce fichier est lu par la skill à l'étape 2. Tant qu'il n'est pas renseigné, la skill procède par découverte (voir bas de page) et doit documenter ici ce qu'elle a trouvé.

## Identification

- Slug du plugin (dans `active_plugins`, renvoyé par `breakdance-site-info`) : `<à renseigner, ex. werocket-tools>`
- Répertoire : `wp-content/plugins/<slug>/`

## Module Cookies

- Option WordPress qui porte l'activation du module : `<à renseigner, ex. werocket_tools_modules>`
- Valeur / clé pour « activé » : `<à renseigner, ex. {"cookies": true} ou "cookies" dans un tableau>`
- Vérification : `php-eval` → `return get_option('<option>');`
- Activation : `php-eval` qui **relit l'option, modifie la seule clé visée, réécrit, puis relit pour
  prouver l'écriture** (`mcp.md` §3). Un `update_option` sur la valeur complète écraserait les
  autres réglages du plugin :

```php
$opt = get_option('<option>', array());
if (!is_array($opt)) { return array('error' => 'option non tabulaire', 'value' => $opt); }
$from = $opt['<clé>'] ?? null;
$opt['<clé>'] = <valeur>;
update_option('<option>', $opt);
return array('from' => $from, 'to' => get_option('<option>')['<clé>'] ?? null);
```

- Réglages du module (texte du bandeau, lien politique de confidentialité, services bloqués) : `<option et clés>`
- Contrôle côté front : sélecteur CSS/ID du bandeau à chercher dans le HTML rendu : `<à renseigner>`

## Découverte (quand la section ci-dessus est vide)

```php
global $wpdb;
return array(
  'plugins' => array_values(array_filter(get_option('active_plugins', array()),
                 function ($p) { return stripos($p, 'werocket') !== false; })),
  'options' => $wpdb->get_col(
                 "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%werocket%'"),
);
```

Puis lis la valeur de chaque option candidate (`get_option`) et, si le nom des clés reste obscur,
le code du plugin par les abilities `agent-connector-for-wp/file-list` et `file-read` sous
`wp-content/plugins/<slug>/` : cherche les appels `get_option`, `update_option` et
`register_setting`, et les fichiers qui parlent de cookies.

Identifie l'option et la clé du module, active, puis vérifie que le bandeau apparaît dans le HTML
rendu de l'accueil (`breakdance-preview-post` avec `include_header_footer: true` — le bandeau est
posé par le plugin, pas par l'arbre). Documente le résultat dans les sections ci-dessus avant de
passer à l'étape suivante.
