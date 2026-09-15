# Breakdance : où sont les choses

## Stockage

- Le contenu d'une page/template Breakdance est un **arbre JSON** en postmeta. La clé varie selon les versions (`breakdance_data` ou `_breakdance_data`) : `scripts/php/list-urls.php` la détecte et l'écrit dans l'entrée `_meta` de `urls.json` (`bd_meta_key`). Les outils natifs, eux, n'ont pas besoin que tu la connaisses.
- `post_content` d'une page Breakdance est vide ou obsolète. Ne le lis pas, ne l'écris pas.
- Headers, footers, templates, blocs globaux et popups sont des CPT `breakdance_*` (`breakdance_header`, `breakdance_footer`, `breakdance_template`, `breakdance_block`, `breakdance_popup`). Ils sont listés avec `template: true` dans `urls.json`. Un lien social dans le footer se corrige donc sur le post `breakdance_footer`, pas sur chaque page.
- CSS compilé par Breakdance en cache : un changement de texte ne le concerne pas. Si tu changes un texte et que rien ne bouge côté front, c'est un cache HTTP/plugin, pas Breakdance.

Pour **lire**, tu n'as pas à toucher à cette postmeta : `breakdance-search-posts` liste les contenus
et dit lesquels sont Breakdance, `breakdance-get-post-details` donne les métadonnées d'un contenu,
`breakdance-get-post-tree` renvoie son arbre, `breakdance-get-template-conditions` les conditions
d'affichage d'un modèle, et `breakdance-preview-post` le HTML réellement rendu (`mcp.md` §2,
niveau 1). Ces outils connaissent le format interne du builder ; toi non. Le `php-eval` sur la
postmeta ne sert qu'à ce qu'ils ne font pas : dumper les chaînes d'un arbre pour retrouver un `from`
exact, et écrire une propriété par chemin JSON.

## Écrire dans l'arbre : deux voies, jamais une troisième

Avant le premier appel à un outil qui écrit dans l'arbre, `breakdance-get-instructions` doit avoir
été appelé une fois dans la session — le serveur l'exige (`mcp.md` §2).

- **Du texte visible** → `breakdance-edit-post`, qui sait ce qu'il écrit. Pour un remplacement exact
  en masse (la même chaîne sur plusieurs contenus, ou plusieurs paires en une passe), la charge
  `scripts/php/bd-text-replace.php` exécutée en `php-eval` (`mcp.md` §3) : remplacement exact d'une
  chaîne, refusé si l'occurrence n'est pas unique. C'est la voie pour un titre, un paragraphe, un
  lien social, un alt personnalisé.
- **Une propriété technique** (adresse email d'un formulaire, destinataire, objet) →
  `breakdance-set-element-form` pour un formulaire ; sinon `scripts/php/apply-forms.php`, qui écrit
  à un **chemin JSON** précis relevé par `scripts/php/forms-audit.php`. Un remplacement de texte
  serait ici hasardeux : une adresse email peut apparaître à plusieurs endroits de l'arbre, et une
  propriété vide n'a aucune chaîne à remplacer.
- **Une URL de préprod en dur** → `scripts/php/fix-hardcoded-urls.php`, qui travaille aussi par
  chemin JSON, à partir de l'inventaire de `scripts/php/hardcoded-urls.php`. Il traite les modèles
  (header, footer, popups, blocs globaux) au même titre que les pages.

Dans tous les cas : passe d'essai d'abord — `"dry_run": true` dans le JSON d'entrée de
`bd-text-replace.php`, `"dry_run": true` pour `apply-forms.php` et `fix-hardcoded-urls.php` —, lis
le diff `from` → `to`, journalise-le (`mcp.md` §6), puis applique. Et jamais de modification de
structure.

## Écrire du texte

Par `breakdance-edit-post`, ou par `scripts/php/bd-text-replace.php` pour un remplacement exact en
masse (refus si l'occurrence n'est pas unique) — avec `"dry_run": true` d'abord. Les textes riches
contiennent du HTML inline (`<strong>`, `<br>`) : copie le texte exactement comme il apparaît dans
l'arbre si le remplacement échoue sur le texte rendu. Pour retrouver la chaîne telle qu'elle est
stockée, dumpe les feuilles texte de l'arbre en `php-eval`, filtrées — jamais l'arbre entier
(`mcp.md` §3) :

```php
$raw  = get_post_meta(<ID>, '<bd_meta_key>', true);
$tree = is_string($raw) ? json_decode($raw, true) : $raw;
$out  = array();
array_walk_recursive((array) $tree, function ($v) use (&$out) {
    if (is_string($v) && mb_strlen($v) > 10 && mb_stripos($v, '<début du texte>') !== false) {
        $out[] = $v;
    }
});
return $out;
```

Ne modifie jamais la structure (ajout/suppression d'éléments, changement de type d'élément, réglages responsive) par script. Ces changements vont dans le rapport pour être faits dans le builder.

## Images et alt

L'élément Image de Breakdance utilise par défaut l'alt de la médiathèque, sauf si un alt personnalisé a été saisi sur l'élément. Le test fiable est **côté rendu** : `front-audit.mjs` compare l'alt de chaque `<img>` à l'alt de la médiathèque (`alt_mismatch`) ; sans Playwright, le même contrôle se fait sur le HTML de `breakdance-preview-post`. Un mismatch se corrige en remplaçant l'alt personnalisé dans l'arbre (`breakdance-edit-post`, ou `bd-text-replace.php` avec `from` = l'alt personnalisé) ou, s'il est meilleur, en alignant la médiathèque. Les images de fond CSS (sections avec background) n'ont pas d'alt : normal, rien à faire.

## Trouver le logo

1. `.we-finalise/context.json → logo_source`
2. `php-eval` : `return wp_get_attachment_url((int) get_theme_mod('custom_logo'));`
3. Header Breakdance : `breakdance-get-post-tree` sur l'ID du header actif (`active_header_ids` de
   `breakdance-site-info`), puis cherche les propriétés `url` contenant `logo` ou `.svg`
4. Sinon `media.json` : cherche `logo` dans `file`/`title`.

## Cache après écriture

Le bloc de purge est dans `mcp.md` §7 : `wp_cache_flush()` en `php-eval`, plus la purge du plugin de
cache repéré dans `active_plugins` (`breakdance-site-info`), plus `flush_rewrite_rules()` après une
écriture dans les options Rank Math. Ne le recopie pas ici.

Cache serveur (Varnish, cache hébergeur type o2switch/Infomaniak) : `breakdance-preview-post` rend la page côté serveur et ne le voit pas. Si le front public ne reflète pas un changement confirmé en base, ajoute `?nocache=1` à l'URL pour vérifier, et note dans le rapport que le cache hébergeur devra être purgé à la mise en ligne.
