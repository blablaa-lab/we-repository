# We-repository

Dépôt de base personnel : le point de départ de tout nouveau projet, et le dépôt de référence de la
skill **we-finalise** — la procédure Werocket de finalisation d'un site WordPress + Breakdance avant
mise en ligne.

Il apporte deux choses à un projet neuf : une méthode de travail pour Claude Code (directives,
suivi de tâches, capitalisation des leçons) et un outillage de livraison prêt à l'emploi.

## Structure

```
CLAUDE.md                          Directives de workflow pour Claude Code
README.md                          Ce fichier
package-skill.sh                   Vérifie l'arborescence de la skill, puis produit son bundle
_docs/
  prd.md                           Amorce du PRD, à remplir au démarrage
  architecture.md                  Amorce du document d'architecture
_tasks/
  todo.md                          Plan cochable de la tâche en cours + bilan
  lessons.md                       Leçons tirées des erreurs réellement commises
.claude/
  skills/
    we-finalise/
      SKILL.md                     La procédure — le fichier chargé par Claude Code
      references/                  Les 11 références, lues au moment où elles servent
      scripts/php/                 Les 12 charges utiles PHP, exécutées par `php-eval`
      scripts/*.mjs                Les 3 scripts Node locaux et optionnels
      we-finalise.skill            Le bundle distribuable, produit par `package-skill.sh`
```

### Ce que contient chaque fichier

- **`CLAUDE.md`** — mode planification par défaut, stratégie de sous-agents, boucle
  d'auto-amélioration, vérification avant clôture, principes de simplicité et d'impact minimal.
- **`_tasks/todo.md`** — le plan de la tâche en cours, en cases à cocher, suivi d'un bilan. Remis à
  zéro à chaque nouvelle tâche.
- **`_tasks/lessons.md`** — les règles écrites après une correction de l'utilisateur, pour ne pas
  refaire la même erreur. À relire en début de session.
- **`_docs/`** — PRD et architecture, à compléter une fois le périmètre et le stack décidés.

## Commandes utiles

### Dans Claude Code

`/we-finalise` lance la procédure de finalisation, documentée plus bas. Elle se déclenche aussi sans
la commande, dès qu'il est question de « finaliser », « livrer », « mettre en ligne », « faire la
checklist de fin de projet », ou d'une étape isolée (« fais juste les metas Rank Math »).

### Maintenance du bundle de la skill

L'arborescence `.claude/skills/we-finalise/` est la **source versionnée** : `SKILL.md`,
`references/` et `scripts/`. Le `we-finalise.skill` en est un **produit**, reconstruit par
`package-skill.sh` — il ne peut donc plus la contredire en silence.

```bash
./package-skill.sh            # vérifie, puis empaquette
./package-skill.sh --check    # vérifie seulement, n'écrit rien
```

Ce que la vérification contrôle : aucune mécanique d'ancien canal résiduelle dans `SKILL.md` et les
références (`mcp.md` est exclu de cette recherche — il documente légitimement ce que l'hôte ne
permet pas), la syntaxe des charges PHP (`php -l`) et des scripts Node (`node --check`), l'absence
d'`exit()` et de `STDERR` dans les charges (interdits en contexte web, `mcp.md` §3), la cohérence
des renvois `fichier.md §N`, et l'existence de chaque charge citée. **En échec, le bundle n'est pas
reconstruit.** Après empaquetage, la liste des fichiers du zip est comparée à l'arborescence source :
c'est la garantie anti-divergence.

### Prérequis d'un projet qui utilise we-finalise

Un seul prérequis, et il ne se contourne pas : **le dossier du projet doit avoir son serveur MCP
WordPress** (`@automattic/mcp-wordpress-remote`, branché sur le plugin MCP Adapter du site). S'il
est absent, la skill le dit et s'arrête à l'étape 0.

Tout le reste est facultatif. L'absence est signalée au rapport, elle ne bloque aucune étape :

```bash
node --version                                           # Node — étapes 6 et 8
npm i -D playwright && npx playwright install chromium   # audit front visuel — étape 4
magick -version                                          # ImageMagick — favicon en local, étape 10
```

Il n'y a plus rien d'autre à installer : ni WP-CLI local, ni `wp-cli.yml`, ni alias SSH, ni `jq`.

---

# La skill we-finalise

Checklist de fin de projet exécutée sur un site WordPress construit avec **Breakdance**. Objectif :
un site propre, techniquement prêt et correctement optimisé pour le SEO local, **sans casser un
contenu que le client a validé**.

**Tout passe par le serveur MCP du dossier du projet.** Pas de SSH, pas de WP-CLI, pas d'alias à
déclarer, pas d'admin WP dans un navigateur. Trois niveaux, du plus haut au plus bas — on prend
toujours le plus haut qui sait faire le travail :

1. les **outils `breakdance-*`** natifs, appelables directement : l'arbre, les modèles, les
   formulaires, le HTML rendu ;
2. les **abilities `rank-math/*`**, appelées par `mcp-adapter-execute-ability` : les réglages, plutôt
   que de patcher des options sérialisées à la main ;
3. **`agent-connector-for-wp/php-eval`** pour tout le reste : options, postmeta, `$wpdb`, GD.

Node en local ne sert qu'à ce qui doit être vu depuis un navigateur ou depuis l'extérieur du site,
et il est **optionnel**. La référence de transport est `references/mcp.md` : c'est le fichier que la
skill lit en premier.

## Les cinq règles

1. **Le contenu visible n'est pas ta propriété.** Les métadonnées invisibles (alt, légendes,
   descriptions, metas, réglages de plugins, favicon) s'appliquent directement. Les textes visibles
   aussi, **sauf** sur les pages clés et les pages légales repérées à l'étape 0 : là, ils sont
   *proposés dans le rapport*, jamais modifiés.
2. **Un site Breakdance ne stocke pas ses textes dans `post_content`**, mais dans un arbre JSON en
   postmeta. Tout passe par les outils natifs (`breakdance-get-post-tree`, `breakdance-edit-post`)
   ou, pour un remplacement exact en masse, par la charge `bd-text-replace.php` — jamais par un
   search-replace, dont l'échappement JSON ferait rater les occurrences. Une propriété qui n'est pas
   du texte visible (l'adresse d'un formulaire) se corrige par `breakdance-set-element-form` ou par
   chemin JSON, pas par remplacement de texte.
3. **Un réglage activé n'est pas un réglage rempli.** Local SEO, Schema et LLMs.txt ne comptent que
   quand leurs champs sont renseignés *et* vérifiés sur le rendu. Un module à moitié rempli est pire
   qu'absent : il publie des données incomplètes que Google enregistre.
4. **Corriger ce qui est corrigeable, noter ce qui manque.** Dès qu'une correction est à portée, elle
   se fait — elle ne part pas dans une liste de suggestions. Ce qui est interdit, c'est d'inventer
   une valeur pour éviter de demander.
5. **Ne jamais bloquer la procédure sur une question.** Une information manquante va dans
   `.we-finalise/questions.md` et dans le rapport final, pas dans un message qui attend une réponse ;
   les étapes qui n'en dépendent pas continuent. Deux exceptions : plusieurs serveurs MCP candidats
   dans le dossier, et un bloquant que l'utilisateur doit connaître tout de suite (formulaire sans
   destinataire, site en `noindex`) — signalé **sans s'arrêter**.

## Ce que l'étape 0 découvre, et où

Il n'y a rien à préparer et aucun fichier à remplir : la skill ne demande ni URL, ni identité du
client, ni liste de pages. Tout cela est dans le site. L'étape 0 l'interroge et écrit elle-même son
contexte dans `.we-finalise/context.json` — un cache qu'elle produit, pas une saisie qu'elle réclame.

Ses appels d'ouverture : `breakdance-get-instructions` (exigé par le serveur avant toute écriture
dans l'arbre), `breakdance-get-maintenance-mode` — un mode actif renvoie la page de maintenance à
tout visiteur déconnecté, donc à Playwright comme à `curl`, et invaliderait silencieusement les
étapes 4 et 8 —, `breakdance-site-info`, `mcp-adapter-discover-abilities` (le catalogue réel de *ce*
site), `rank-math/get-settings`, un `php-eval` d'inventaire, puis `page-text.php`.

| Valeur | Où elle est prise |
|---|---|
| `site_url`, plugins actifs, accueil, header/footer | `breakdance-site-info` |
| `post_types` | Les post types publics ayant au moins un contenu publié — **jamais** une liste écrite d'avance : c'est là que se découvrent les CPT métier |
| `legal_pages` | Les slugs contenant `mention`, `legal`, `cgv`, `cgu`, `confidentialit`, `cookie` |
| `key_pages` | L'accueil, plus toute page que l'utilisateur a nommée |
| `client.name` | `blogname`, `knowledgegraph_name`, texte du pied de page |
| `client.legal_name`, adresse, téléphone, email, horaires | Extraits du texte des pages (mentions légales, contact) à l'étape 6 |
| `client.sector`, `business_type` | Tagline et contenu des pages, puis la table secteur → type de `schema.md` §1 |
| `client.city`, `zone` | Code postal et ville des mentions légales, communes citées dans les pages |
| `client.keywords` | Les `rank_math_focus_keyword` en place, les titres de pages, le secteur et la ville |
| `client.social` | Les liens réseaux trouvés dans le header, le pied de page et les pages (étape 4) |
| `logo_source` | `custom_logo` du thème, sinon l'image du header Breakdance, sinon un média nommé « logo » |
| `prod_url` | L'hôte de `site_url` s'il ne ressemble pas à une préprod ; sinon le domaine porté par les emails des mentions légales et de la page contact |

**La seule valeur qui peut manquer pour de bon est le domaine de production**, quand le site tourne
encore sur une préprod (`*.werocket.ovh`, `staging.*`, `preprod.*`, un sous-domaine d'agence) et que
le contenu ne le porte nulle part. Il sert à deux choses : l'expéditeur des formulaires
(`formulaire@<domaine>`, étape 9) et les URL du schema. S'il reste ambigu, la question part dans
`questions.md`, l'étape 9 n'écrit **aucune** adresse expéditeur — écrire `formulaire@werocket.ovh`,
soit le domaine de l'agence, serait pire que ne rien écrire — et la procédure continue.

### Au lieu d'une sauvegarde : un journal d'annulation

Il n'y a plus d'export de base : ni WP-CLI, ni `mysqldump`, ni commande shell sur ces hébergements
(`mcp.md` §5). À la place, `.we-finalise/journal.jsonl` reçoit une ligne JSON par écriture, avec la
valeur précédente ; les charges `apply-*` et `fix-*` renvoient déjà leurs couples `from` → `to`, et
c'est cette sortie qui est journalisée telle quelle. **Sans journal ouvert, la skill n'écrit rien.**

Pourquoi c'est mieux ici qu'un dump, et pas seulement faute de mieux : ce que cette procédure écrit,
ce sont des metas, des feuilles de texte et des clés d'options, une à une. Le journal permet de
revenir en arrière écriture par écriture, et il se lit. Si un plugin de sauvegarde est actif, le
rapport rappelle qu'une sauvegarde complète avant lancement reste la bonne pratique.

## Les 14 étapes

| # | Étape | Ce qu'elle fait |
|---|---|---|
| 0 | Reconnaissance | Découverte du site, `context.json`, ouverture du journal d'annulation |
| 1 | Inventaire | URL et modèles Breakdance, médiathèque, post types présents |
| 2 | Cookies (plugin werocket tools) | Plugin *werocket tools* actif et module Cookies activé |
| 3 | Médiathèque : alt, légende, description | Les trois champs de chaque image, en la regardant vraiment |
| 4 | Audit front : responsive, alt rendus, liens sociaux, favicon | Le HTML rendu par le serveur, plus Playwright s'il est là (375/768/1024/1440 px) |
| 5 | SEO local et GEO : metas et contenus | Meta title / description / focus keyword, puis les contenus selon la grille GEO |
| 6 | Modules Rank Math | Mode avancé, Local SEO rempli depuis les données du site, robots.txt, llms.txt, `blog_public` |
| 7 | Données structurées (Schema) | Sous-type d'entreprise, mapping CPT → schema, `Service` sur les pages prestation |
| 8 | Conformité aux recommandations Google | Indexabilité, canoniques, titles, ancres, images, HTTPS, mobile |
| 9 | Formulaires : destinataire et expéditeur | Un destinataire existe, `From` en `formulaire@<domaine de prod>`, SMTP, test réel |
| 10 | Favicon | Génération depuis le logo, contrôle visuel, `site_icon` |
| 11 | Independent Analytics | Installation, activation, accès du rôle Éditeur |
| 12 | Liens en dur vers la préprod | Toute la base passée au crible, liens de navigation rendus relatifs |
| 13 | Rapport | `we-finalise-report.md` : le livrable |

**Dépendances** : 4 ← 1 et 3 · 6 ← 0, parce que l'extraction des informations d'entreprise lit
`pages.json`, produit à l'étape 0 — elle ne dépend plus de Playwright · 7 ← 1 et 6 · 8 ← 4 et 5 à 7,
dont elle contrôle le travail · 9 ← 0 pour le domaine de production · 12 vient **après toutes les
écritures**, puisqu'elle vérifie aussi ce que les étapes précédentes ont écrit. Une étape demandée
seule reste précédée de l'étape 0 et de l'inventaire, et l'ouverture du journal ne se saute jamais.

## Scripts

Les enveloppes shell ont disparu avec l'ancien canal. Restent **12 charges utiles PHP**, exécutées
par l'ability `agent-connector-for-wp/php-eval`, et **3 scripts Node** qui tournent en local.

Une charge s'exécute en envoyant comme `code` la ligne d'entrée suivie du corps du fichier, sans son
`<?php` :

```php
$args = array('{"dry_run":true, …}');
<corps du fichier>
```

Chaque charge lit donc son entrée dans `$args[0]` — une chaîne JSON, ou une chaîne simple pour
celles qui n'attendent qu'une liste — et rend son résultat en JSON dans le champ `output` de la
réponse de l'ability. Les charges ne sont **jamais déposées sur le serveur** : un fichier `.php` sous
`wp-content/uploads/` est le marqueur le plus classique d'un site compromis. Les charges d'écriture
acceptent une passe d'essai — `"dry_run": true`, et `"dry": true` pour `bd-text-replace.php` — qui
rend le diff `from` → `to` sans rien écrire : on la lance, on lit le diff, puis on applique.

### Audit (ne modifient rien)

| Charge | `$args[0]` | Sortie |
|---|---|---|
| `php/list-urls.php` | `"page,post,realisation"` | Contenus publiés et modèles Breakdance, plus un `_meta` (clé postmeta Breakdance détectée, `blog_public`, `site_icon`) → `.we-finalise/urls.json` |
| `php/media-audit.php` | *aucune entrée* | Chaque image : alt, légende, description, URL, et les contenus qui l'utilisent (`used_in`) → `.we-finalise/media.json` |
| `php/page-text.php` | `"page,post,realisation"` | Le texte lisible de chaque contenu publié, extrait de l'arbre Breakdance et de `post_content` → `.we-finalise/pages.json` |
| `php/schema-audit.php` | `"page,post"` *(optionnel)* | Post types réellement présents, état du module Schema, `local_business_type`, schemas en place → `.we-finalise/schema.json` |
| `php/forms-audit.php` | `"client.fr"` — le domaine de production attendu | Formulaires Breakdance, CF7 / WPForms, shortcodes, plugin SMTP, `admin_email`, anomalies, et le **chemin JSON** de chaque valeur |
| `php/hardcoded-urls.php` | `"staging.client.fr,preprod.client.fr"` — vide = hôte courant + motifs de préprod connus | Occurrences classées `lien` / `media` / `autre`, avec emplacement lisible et chemin JSON → `.we-finalise/hardcoded-urls.json` |

### Écriture

| Charge | `$args[0]` | Effet |
|---|---|---|
| `php/apply-media.php` | `[{"id":42,"alt":"…","caption":"…","description":"…"}]` | Alt / légende / description des attachments. Seuls les champs présents sont écrits ; `"alt": ""` est une valeur valide (image décorative) |
| `php/apply-seo-meta.php` | `[{"post_id":12,"title":"…","description":"…","focus_keyword":"…"}]` | `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword` |
| `php/bd-text-replace.php` | `{"dry":true,"items":[{"post_id":12,"from":"…","to":"…"}]}` | Remplacement exact dans l'arbre Breakdance, ou dans `post_content` à défaut. Ne touche que les feuilles texte, jamais la structure |
| `php/apply-schema.php` | `{"dry_run":true,"module":true,"breadcrumbs":true,"post_type_defaults":{…},"posts":[…]}` | Module `rich-snippet`, fil d'Ariane, schema par défaut de chaque post type, schemas posés contenu par contenu |
| `php/apply-forms.php` | `{"dry_run":true,"smtp":{…},"breakdance":[{"post_id":42,"path":"…","value":"…"}],"cf7":[…]}` | Destinataires et expéditeurs, par chemin JSON |
| `php/fix-hardcoded-urls.php` | `{"dry_run":true,"mode":"relative","classes":["lien"],"audit":{…}}` | Liens de préprod → relatifs. `"mode":"swap"` avec `to_host` existe pour la bascule DNS, pas avant |

Trois refus valent mieux qu'une écriture approximative : `bd-text-replace.php` refuse une paire dont
le `from` n'apparaît pas exactement une fois dans le post ciblé ; `apply-forms.php` refuse un chemin
inexistant, une cible qui est une structure et non une valeur, et toute valeur qui n'est pas une
adresse email valide ; `fix-hardcoded-urls.php` relit la valeur avant d'écrire et refuse
l'occurrence si l'URL n'y est plus. Le plan de cette dernière vient de l'audit — elle ne redétecte
rien.

Après toute écriture dans l'arbre ou dans les options : purge du cache par `php-eval`
(`mcp.md` §7), et `flush_rewrite_rules()` en plus après les options Rank Math, dont dépendent le
sitemap et `llms.txt`. Chaque application est journalisée. Le favicon n'a pas de charge dédiée :
ImageMagick en local s'il est présent, sinon GD par `php-eval` (`Imagick` est souvent absent).

### Node — en local, optionnels

| Script | Usage | Sortie |
|---|---|---|
| `front-audit.mjs` | `--urls .we-finalise/urls.json [--media …] [--out .we-finalise/front] [--viewports 375,768,1024,1440] [--auth user:pass] [--only slug1,slug2]` | `<out>/report.json` + `<out>/shots/<slug>-<largeur>.png` |
| `site-info.mjs` | `[--in .we-finalise/pages.json] [--domain client.fr] [--out .we-finalise/site-info.json]` | Raison sociale, téléphones, adresses, SIRET, TVA, RCS, horaires, emails — chacun avec les pages où il apparaît, son nombre d'occurrences, et les incohérences NAP |
| `google-check.mjs` | `--site https://… [--prod https://…] [--in .we-finalise/front/report.json] [--links]` | `.we-finalise/google-check.json` : chaque constat classé `bloquant` / `à corriger` / `à vérifier` / `info`, avec une `resolution` `auto`, `humain` ou `question` |

Aucun des trois n'écrit sur le site. S'ils manquent, les étapes 4, 6 et 8 se font sur le HTML rendu
par `breakdance-preview-post` et sur les abilities Rank Math ; seul le jugement visuel du responsive
est alors perdu, et il part au rapport comme « non vérifié ».

## Références

Lues par la skill au moment où elles servent, dans `references/`.

| Fichier | Contenu |
|---|---|
| `mcp.md` | Le canal : trouver le serveur du dossier, les quatre niveaux, les abilities qui servent, les idiomes `php-eval`, les contraintes de l'hôte, le journal d'annulation, la purge du cache |
| `rankmath.md` | Les options Rank Math et leurs clés, mode avancé, modules, Local SEO, robots.txt, llms.txt. §0 = critères d'acceptation |
| `schema.md` | Table secteur → sous-type d'entreprise, mapping CPT → schema, `Service`, pièges, grille de validation |
| `google-seo.md` | Le guide de démarrage SEO de Google, réduit à ce qui est vérifiable à la livraison — et ce qu'il ne faut *pas* travailler |
| `redaction-seo-local-geo.md` | Comment rédiger alt, metas et contenus : SEO local et GEO |
| `formulaires.md` | Où sont stockés destinataire et expéditeur, ordre de correction, test qui compte, délivrabilité |
| `breakdance.md` | Où sont les choses : stockage, écriture dans l'arbre, images et alt, logo, cache |
| `liens-en-dur.md` | Les deux moments (finalisation vs bascule), où se cachent les URL, ce que l'audit ne voit pas |
| `werocket-tools.md` | Plugin werocket tools : identification et module Cookies |
| `independent-analytics.md` | Installation et accès du rôle Éditeur |
| `report-template.md` | Le gabarit du rapport de finalisation |

## Ce que la procédure produit

- **`.we-finalise/`** — dossier de travail local, à ajouter au `.gitignore` du projet :
  `context.json` (le contexte déduit à l'étape 0), `pages.json`, les JSON d'audit, les captures
  d'écran, et `questions.md` (les informations manquantes, regroupées).
- **`.we-finalise/journal.jsonl`** — le journal d'annulation : une ligne par écriture, avec la valeur
  précédente. C'est ce qui remplace l'export de base, impossible sur ces hébergements.
- **`we-finalise-report.md`** — le livrable, à la racine du projet : ce qui a été vérifié et ce qui
  n'a **pas** pu l'être avec la raison, ce qui a été modifié et le chemin du journal, ce qui reste à
  faire par un humain dans Breakdance, les propositions de contenu en attente de validation client,
  les questions formulées pour être envoyées telles quelles, et les bloquants.

---

## Démarrer un nouveau projet

Cloner `we-repository` comme point de départ, puis :

1. Mettre à jour ce README avec le contexte du nouveau projet
2. Compléter `CLAUDE.md` avec les commandes de build / test / lint une fois le stack choisi
3. Remplir `_docs/prd.md` et `_docs/architecture.md`
4. Vider `_tasks/todo.md` (il contient la dernière tâche du dépôt de base) et y planifier les
   premières étapes
