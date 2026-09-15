# URL en dur vers la préprod

Un lien écrit en dur avec le domaine de préprod ne se voit pas pendant la recette : sur le
staging, il fonctionne. Il casse le jour de la mise en ligne — ou pire, il continue de fonctionner
et renvoie les visiteurs du site de production vers la préprod, où ils voient un site qui n'est
plus à jour, indexable, et parfois protégé par un mot de passe.

## Les deux moments, et pourquoi on ne les confond pas

À la finalisation, **le site tourne encore sur la préprod**. Remplacer le domaine de préprod par
celui de production le rendrait immédiatement inaccessible : le domaine de prod ne pointe pas
encore dessus, et `home`/`siteurl` renverraient vers le vide.

| Moment | Opération | Ce qu'elle traite |
|---|---|---|
| **Finalisation** (site en préprod) | `"mode": "relative"` | Les liens internes deviennent `/contact/` : valides sur la préprod **et** en production. C'est la correction qui ferme le sujet définitivement. |
| **Bascule** (DNS basculé) | `"mode": "swap"` + `"to_host": "www.client.fr"` | Ce qui doit rester absolu : `home`, `siteurl`, URL de médias, images Open Graph, canoniques saisies à la main. |

Un lien relatif est immunisé contre tous les changements de domaine à venir. C'est pour cela qu'on
le préfère au search-replace, même quand on connaît déjà le domaine de production.

## Où elles se cachent

`scripts/php/hardcoded-urls.php`, exécutée en `php-eval` (`mcp.md` §3), cherche dans toute la base,
pas seulement dans les pages :

- **pages, articles, CPT** : `post_content` et `post_excerpt` ;
- **modèles Breakdance** : header, footer, templates d'archive et de single, popups, blocs
  globaux. C'est l'angle mort classique — un lien en dur dans le footer est présent sur **toutes**
  les pages, et un popup non déclenché pendant la recette n'apparaît dans aucune capture d'écran ;
- **arbres Breakdance des pages** : propriété d'un bouton, d'un lien, ou HTML inline dans un bloc
  de texte ;
- **menus** : les liens personnalisés (`_menu_item_url`) ;
- **widgets et options de thème** : souvent sérialisés, donc invisibles à une recherche naïve ;
- **métadonnées** : ACF, image Open Graph de Rank Math, champs personnalisés ;
- **termmeta, usermeta, commentaires**.

L'audit classe chaque trouvaille :

- `lien` — navigation : c'est ce qu'on convertit en relatif ;
- `media` — fichier (image, PDF, police, CSS, JS) : **pas** converti par défaut, voir plus bas ;
- `autre` — `home`/`siteurl`, `/wp-json/`, `/wp-admin/`, ou host cité sans schéma dans un texte.

## Pourquoi les médias ne sont pas convertis par défaut

Dans un arbre Breakdance, une image porte en général une `url` **et** un `id` d'attachment :
Breakdance régénère l'URL depuis l'ID, si bien que la propriété `url` obsolète est souvent
inoffensive — mais une URL relative peut, selon l'élément, ne pas être gérée à l'affichage. Le
risque de casser un visuel est réel, le gain nul : les URL de médias sont réécrites proprement par
le search-replace de la bascule.

Si tu as une raison de les traiter quand même : `"classes": ["lien","media"]` dans le JSON d'entrée,
après avoir regardé les pages concernées.

## Procédure

Les deux charges s'exécutent en `php-eval`, chacune avec son entrée dans `$args[0]` (`mcp.md` §3) :

```php
// 1. Audit → .we-finalise/hardcoded-urls.json
$args = array('');                 // vide = host courant + motifs de préprod connus
<corps de scripts/php/hardcoded-urls.php, sans son `<?php`>
```

```php
// 2. Correction : passe d'essai, puis "dry_run": false. "audit" = le JSON produit en 1.
$args = array('{"dry_run":true,"mode":"relative","classes":["lien"],"audit":{…}}');
<corps de scripts/php/fix-hardcoded-urls.php, sans son `<?php`>
```

Lis le diff de la passe d'essai, journalise-le (`mcp.md` §6), applique, puis purge le cache — le bloc
est dans `mcp.md` §7.

Sans argument, l'audit cherche **le domaine courant du site** — pendant la finalisation, c'est
précisément celui de la préprod — plus les motifs de préprod connus (`staging.`, `preprod.`,
`dev.`, `.werocket.ovh`, `.o2switch.net`, `localhost`…), ce qui rattrape aussi les restes d'une
migration antérieure. Pour chercher un domaine précis, mets-le dans `$args[0]` :
`$args = array('ancien-domaine.fr');` (plusieurs hosts se séparent par des virgules).

Le correctif ne redétecte rien : il travaille sur le fichier d'audit et **relit la valeur avant
d'écrire**. Si un contenu a changé entre l'audit et la correction, l'occurrence est refusée avec
la raison — relance l'audit plutôt que de forcer.

Après correction, vérifie sur le front que les liens fonctionnent toujours : un lien relatif mal
formé (`contact/` au lieu de `/contact/`) donnerait une 404 relative à la page courante. Le script
garantit le `/` initial, y compris pour la racine, mais la vérification coûte une minute.

## Ce que cet audit ne voit pas

À inscrire dans le rapport, parce que la base ne les contient pas :

- **CSS et JS compilés** : Breakdance et les plugins de cache écrivent des fichiers dans
  `wp-content/uploads` qui peuvent contenir des URL absolues. Ils sont régénérés à la purge du
  cache — purger après la bascule suffit ;
- **fichiers importés** : un PDF ou un document qui contient un lien vers la préprod ;
- **tables propres à un plugin** (formulaires, réservations, tables custom) : l'audit couvre les
  tables WordPress standard, pas celles-là ;
- **configuration hors base** : `wp-config.php` (`WP_HOME`, `WP_SITEURL`), redirections dans
  `.htaccess`, réglages CDN ;
- **services externes** : Google Business Profile, réseaux sociaux, Search Console, campagnes,
  signatures d'email — partout où l'URL de préprod a pu être communiquée.
