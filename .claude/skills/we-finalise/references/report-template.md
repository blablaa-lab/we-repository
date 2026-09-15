# Rapport de finalisation — <Nom du site>

Date : <YYYY-MM-DD> · Environnement : <site_url> · Journal d'annulation : `.we-finalise/journal.jsonl` (<n> écritures) · Exécuté par : we-finalise

## Synthèse

<3–5 lignes : état général, nombre de pages traitées, bloquants éventuels.>

| Étape | État | Détail |
|---|---|---|
| Cookies (werocket tools) | ✅ / ⚠️ / ❌ / ⏭ non vérifié | |
| Liens réseaux sociaux | | <n> liens vérifiés, <n> corrigés |
| Médiathèque (alt / légende / description) | | <n> images, <n> mises à jour, <n> décoratives |
| Alt Breakdance = médiathèque | | <n> écarts corrigés |
| Responsive | | <n> pages × 4 breakpoints, <n> problèmes → section Breakdance |
| Meta title / description | | <n> pages |
| Contenus SEO local / GEO | | <n> remplacements appliqués, <n> propositions en attente |
| Rank Math : mode avancé, Local SEO, robots.txt, llms.txt | | |
| Données structurées (module Schema) | | type d'entreprise `<type>`, <n> post types mappés, <n> schemas posés |
| Indexation (`blog_public`, noindex) | | |
| Recommandations Google | | <n> bloquants, <n> corrigés, <n> renvoyés au builder |
| Liens en dur vers la préprod | | <n> occurrences, <n> liens passés en relatif, <n> médias laissés |
| Formulaires (destinataire, expéditeur) | | <n> formulaires, <n> corrigés, test d'envoi : reçu / à confirmer |
| Favicon | | |
| Independent Analytics | | |

**Non vérifié** : <ce qui n'a pas pu être contrôlé, et pourquoi — Playwright absent (responsive non
jugé visuellement), mode maintenance actif, boîte du client inaccessible (réception des formulaires),
ability absente ou appel MCP en échec sur telle étape. Une étape franchement marquée non vérifiée
vaut mieux qu'une étape supposée faite.>

## Bloquants

<Liste. Vide si aucun. Ex. : plugin werocket tools absent ; blog_public = 0 ; favicon à demander au client.>

## Modifications appliquées

### Médiathèque
<Compte + 3 exemples avant/après. Lien vers `.we-finalise/media-updates.json`.>

### Metas Rank Math
<Tableau page → title → description → focus keyword.>

### Contenus (hors pages clés)
<Tableau page → from → to, ou lien vers `.we-finalise/content-updates.json`.>

### Réglages
<Rank Math (modules, fiche Local SEO, robots.txt, llms.txt), favicon, analytics, cookies — avec, pour chacun, l'appel utilisé (outil Breakdance, ability `rank-math/*`, ou charge `php-eval`) et le couple `from` → `to` tel qu'il est parti au journal.>

### Données structurées
<Type d'entreprise retenu et pourquoi (secteur → sous-type). Tableau post type → schema appliqué → nombre de contenus concernés. Schemas posés contenu par contenu (page → @type). Extrait du JSON-LD rendu sur l'accueil et sur un exemplaire de chaque CPT, comme preuve. Enfin : ce qui a été volontairement laissé de côté faute de données vérifiables — avis / `aggregateRating`, tarifs, horaires — et donc ce que le client doit fournir pour aller plus loin.>

### Liens en dur vers la préprod
<Nombre d'occurrences par emplacement, en nommant les **modèles** concernés (header, footer, templates, popups) puisqu'ils affectent tout le site. Liens de navigation passés en relatif : compte et exemples. Médias laissés volontairement absolus, à traiter au search-replace de la bascule. Ce que l'audit ne couvre pas et qui reste à faire : CSS/JS compilés (purge du cache), fichiers importés, tables propres à un plugin, `wp-config.php` et `.htaccess`, et les endroits hors site où l'URL de préprod a été communiquée (Google Business Profile, réseaux sociaux, Search Console, signatures d'email).>

### Conformité aux recommandations Google
<Ce qui a été corrigé (indexabilité, robots.txt, canoniques, titles/descriptions en doublon, ancres vagues, contenu mixte, langue). Ce qui part au builder. Ce qui attend une information du client. Rappeler ce qui ne se vérifie qu'après mise en ligne : Search Console, soumission du sitemap, inspection d'URL sur le domaine de production. Si le client a demandé des travaux que Google déclare sans effet (meta keywords, longueur de contenu, densité de mots-clés), le dire ici avec la formulation de `google-seo.md` plutôt que de les exécuter.>

### Formulaires
<Tableau : formulaire (page + élément) → destinataire → expéditeur → état. Réglage SMTP appliqué. Résultat du test d'envoi réel : reçu, ou « à confirmer par le client », ou « à refaire après mise en ligne » si le staging bloque l'envoi. Formulaires de plugins non lus automatiquement (Gravity, Fluent, Forminator, Ninja) et donc vérifiés à la main : lesquels.>

## À corriger dans Breakdance (humain)

<Par page et breakpoint : description précise du problème, screenshot associé (`.we-finalise/front/shots/…`). Liens sociaux non corrigeables par script. Sections FAQ à ajouter.>

## Propositions de contenu à valider (pages clés et légales)

<Par page : texte actuel → texte proposé → pourquoi (règle de la grille). Rien n'a été appliqué sur ces pages.>

## Questions au client

<Reprise de `.we-finalise/questions.md`, regroupée et formulée pour être envoyée telle quelle. Aucune
n'a bloqué la procédure : pour chacune, dire ce qui manque, quelle étape reste en attente, et ce qui
sera écrit dès la réponse. Les habituelles (`mcp.md` §8) : domaine de production si le site est en
préprod, adresse de réception des formulaires, horaires d'ouverture, avis et tarifs — qui ne
s'inventent pas dans le schema —, favicon dédié si le logo est illisible en carré, et les accès hors
site (Search Console, Google Business Profile, DNS, boîte `formulaire@<domaine>`).>

## Vérifications à faire à la mise en ligne

- Basculer les URL restées absolues : la charge `scripts/php/fix-hardcoded-urls.php` en `php-eval` avec `{"mode":"swap","to_host":"<domaine>","include_home":true}` (médias, Open Graph, canoniques saisies à la main), puis robots.txt (`Sitemap:`), fiche Local SEO (`url`)
- Relancer `scripts/php/hardcoded-urls.php` après bascule : il ne doit plus rien trouver
- Purger le cache hébergeur
- Soumettre le sitemap dans la Search Console, vérifier `/robots.txt` et `/llms.txt` sur le domaine de prod
- Relancer `google-check.mjs` sur le domaine de production (canoniques, liens absolus, sitemap déclaré)
- Lancer une inspection d'URL Search Console sur l'accueil et une page service
- Repasser le JSON-LD au test des résultats enrichis sur le domaine de prod (les `@id` et `url` du schema contiennent l'URL du site)
- Se connecter avec un compte éditeur pour confirmer l'accès aux statistiques
- Refaire un envoi de test depuis chaque formulaire et confirmer la réception avec le client
- Vérifier que la boîte ou l'alias `formulaire@<domaine>` existe, et demander SPF/DKIM à l'hébergeur
