---
name: we-finalise
description: Procédure Werocket de finalisation d'un site WordPress + Breakdance avant mise en ligne. Se lance sans configuration ni question : elle découvre le site elle-même par le serveur MCP Breakdance du dossier du projet, puis enchaîne les quatorze étapes. Couvre cookies (werocket tools), liens réseaux sociaux, alt des médias, responsive, metas Rank Math, SEO local, GEO, modules Rank Math remplis (Local SEO, Schema/données structurées par CPT, robots.txt, llms.txt), recommandations SEO de Google (indexabilité, canoniques, titles, ancres, images, HTTPS, mobile), formulaires (destinataire, expéditeur formulaire@domaine), liens en dur vers la préprod dans les pages et les modèles Breakdance, favicon, Independent Analytics, et produit un rapport de finalisation. Utilise cette skill dès que l'utilisateur parle de finaliser, livrer, mettre en ligne, préparer la mise en prod, faire la checklist de fin de projet, « passer we-finalise », ou demande une de ces étapes (alt des images, metas Rank Math, données structurées, recommandations Google, formulaires, liens de préprod, favicon, responsive, SEO local) sur un site Werocket.
---

# we-finalise — finalisation d'un site Werocket

Tu exécutes la checklist de fin de projet Werocket sur un site WordPress construit avec Breakdance.
L'objectif : un site propre, techniquement prêt et correctement optimisé pour le SEO local, sans
casser un contenu que le client a validé.

**Tout passe par le serveur MCP du dossier du projet.** Pas de SSH, pas de WP-CLI, pas d'alias à
déclarer, pas d'admin WP dans un navigateur. Lis `references/mcp.md` en premier : il dit comment
trouver ce serveur, dans quel ordre choisir un outil, et comment exécuter du PHP sur le site.

## Le lancement : il n'y a rien à préparer

On te lance, tu pars. Tu ne demandes ni URL, ni identité du client, ni liste de pages, ni fichier de
configuration. Tout cela est **dans le site**, et l'étape 0 va le chercher.

Il n'y a qu'un seul prérequis, et il ne se contourne pas : **le dossier doit avoir son serveur MCP
WordPress**. S'il n'y en a pas, dis-le et arrête-toi (`mcp.md` §1). Tout le reste est facultatif :
Node et Playwright améliorent l'étape 4 si présents, leur absence ne bloque rien.

Puis tu annonces l'étape en cours au fil de l'eau — « Étape 3 sur 13 — médiathèque : 42 images, 17 sans
alt » — pour que l'utilisateur suive sans avoir à demander.

## Cinq règles qui priment sur tout le reste

1. **Le contenu visible n'est pas ta propriété.** Les métadonnées invisibles (alt, légendes,
   descriptions médias, meta title/description, réglages plugins, favicon) s'appliquent directement.
   Les textes visibles s'appliquent directement **sauf** sur les pages clés et les pages légales
   repérées à l'étape 0 : là, tu proposes dans le rapport, tu ne modifies pas. Pourquoi : un client
   qui découvre ses textes réécrits au lancement, c'est un ticket, pas de la valeur.
2. **Un site Breakdance ne stocke pas ses textes dans `post_content`.** Les pages Breakdance ont
   leur contenu dans un arbre JSON. Passe par les outils natifs (`breakdance-get-post-tree`,
   `breakdance-edit-post`) ou, pour un remplacement exact en masse, par la charge
   `scripts/php/bd-text-replace.php`. Jamais par un `post_content` ni par un search-replace :
   l'échappement JSON ferait rater les occurrences. Tu ne changes que du texte, jamais la structure.
   Les **propriétés** qui ne sont pas du texte visible (l'adresse email d'un formulaire, par exemple)
   se corrigent par `breakdance-set-element-form` ou par chemin JSON avec
   `scripts/php/apply-forms.php`, pas par remplacement de texte.
3. **Un réglage activé n'est pas un réglage rempli.** Les trois modules Rank Math — SEO Local,
   Schema, LLMs.txt — ne sont « faits » que quand leurs champs sont renseignés et vérifiés sur le
   rendu, pas quand la case est cochée. Un module à moitié rempli est pire qu'absent : il publie des
   données incomplètes que Google enregistre. Les critères d'acceptation sont dans
   `references/rankmath.md` §0.
4. **Tu corriges ce qui est corrigeable ; tu notes ce qui te manque.** Dès qu'une correction est à ta
   portée avec les informations dont tu disposes, fais-la — ne la reporte pas dans une liste de
   suggestions. Trois exceptions : les textes des pages clés et légales (règle 1), ce qui exige le
   builder ou un jugement visuel, et ce qui dépend d'une information que tu n'as pas. Ce qui est
   interdit, c'est d'inventer une valeur pour éviter de demander.
5. **Tu ne bloques jamais la procédure sur une question.** C'est la règle qui rend le lancement
   simple, et elle prime sur l'envie de bien faire. Une information manquante va dans
   `.we-finalise/questions.md` et dans le rapport final — **pas** dans un message qui attend une
   réponse. Tu continues toutes les étapes qui n'en dépendent pas, et l'étape concernée est marquée
   « en attente » avec précisément ce qui manque. Deux exceptions, et deux seulement : plusieurs
   serveurs MCP candidats dans le dossier, et un bloquant que l'utilisateur doit connaître tout de
   suite (formulaire sans destinataire, site en `noindex`) — que tu signales **sans t'arrêter**.

---

## Étape 0 — Reconnaissance

Tu ne demandes rien. Tu regardes. Dans cet ordre :

1. **Trouve ton serveur MCP** (`mcp.md` §1). Aucun serveur → bloquant, tu t'arrêtes là.
2. **`breakdance-get-instructions`** — une fois pour la session. Le serveur l'exige avant toute
   écriture dans l'arbre.
3. **`breakdance-get-maintenance-mode`** — avant tout le reste, parce que c'est le piège qui
   invalide silencieusement les étapes 4 et 8 : si un mode est actif, **toute URL publique renvoie
   la page de maintenance** aux visiteurs déconnectés, donc à Playwright comme à `curl`. S'il est
   actif : récupère le `bypass_url`, sers-t'en pour tous les accès front, et inscris au rapport qu'il
   faudra désactiver le mode à la mise en ligne. **Ne le désactive pas toi-même** : il protège
   peut-être un site que le client ne veut pas encore public.
4. **`breakdance-site-info`** → `site_url`, `active_plugins`, `home_page_id`, `blog_page_id`,
   `active_header_ids`, `active_footer_ids`, version du builder, `css_prefix`.
5. **`mcp-adapter-discover-abilities`** → le catalogue réel de **ce** site. C'est lui qui décide de
   ce qui est faisable par ability et de ce qui passera par `php-eval`.
6. **`rank-math/get-settings`** → ce qui est déjà en place : modules actifs, `local_business_type`,
   `opening_hours`, `knowledgegraph_*`, et les slugs de post types tels que le plugin les voit.
7. **Un `php-eval` d'ouverture** que tu écris sur le moment : les options (`blog_public`,
   `site_icon`, `admin_email`, `page_on_front`), les post types publics avec leur nombre de contenus
   publiés, l'état de la médiathèque, et les contraintes d'hôte (`mcp.md` §5).
8. **`scripts/php/page-text.php`** → `.we-finalise/pages.json` : le texte lisible de chaque contenu
   publié, extrait de l'arbre Breakdance et de `post_content`. C'est lui qui alimente l'étape 6 —
   et c'est pour cela que cette procédure n'a plus besoin de Playwright pour remplir la fiche
   Local SEO.

   **Puis complète les pages que la charge signale `thin`** (listées dans `_meta.thin_slugs`) en
   appelant `breakdance-preview-post` dessus et en remplaçant leur `text` par le rendu détagué. Un
   arbre ne contient pas ce que résolvent les shortcodes, les boucles et les données dynamiques : sur
   beaucoup de sites Werocket, les mentions légales sont précisément générées par un shortcode, et
   c'est là que se trouve le NAP dont l'étape 6 a besoin. Sauter cette passe, c'est chercher une
   adresse dans une page que l'arbre rend vide.

Puis tu **déduis** le contexte et tu l'écris dans `.we-finalise/context.json`. Ce fichier est un
cache que tu produis, pas une saisie que tu réclames. Voilà d'où vient chaque valeur :

| Valeur | Où tu la prends |
|---|---|
| `site_url`, plugins, accueil, header/footer | `breakdance-site-info` |
| `post_types` | Post types publics avec `published > 0`. **Jamais une liste écrite d'avance** : c'est ici que tu découvres les CPT métier |
| `legal_pages` | Slugs contenant `mention`, `legal`, `cgv`, `cgu`, `confidentialit`, `cookie` |
| `key_pages` | L'accueil (`home_page_id`) plus toute page que l'utilisateur a nommée. C'est le périmètre « texte validé par le client » |
| `client.name` | `blogname`, `knowledgegraph_name`, texte du pied de page |
| `client.legal_name`, `address`, `phone`, `email`, `opening_hours` | Étape 6, par extraction des mentions légales et de la page contact (`site-info.mjs` sur `pages.json`) |
| `client.sector`, `business_type` | Tagline et contenu des pages, puis la table secteur → type de `references/schema.md` §1 |
| `client.city`, `zone` | Le code postal et la ville des mentions légales ; les communes citées dans les pages |
| `client.keywords` | Les `rank_math_focus_keyword` en place, les titres de pages, le secteur et la ville |
| `client.social` | Les liens vers les réseaux trouvés dans le header, le pied de page et les pages (étape 4) |
| `logo_source` | `custom_logo` du thème, sinon l'image du header Breakdance, sinon un média nommé « logo » |
| `prod_url` | Voir ci-dessous — c'est la seule valeur qui peut manquer pour de bon |

### Le domaine de production, seule vraie inconnue

`prod_url` sert à deux choses : l'adresse expéditeur des formulaires (`formulaire@<domaine>`, étape
9) et les URL du schema. Se tromper ici, c'est écrire l'adresse de quelqu'un d'autre.

Regarde l'hôte de `site_url`. S'il ne ressemble pas à une préprod, **c'est le domaine de
production**, et tu n'as rien à demander — le cas le plus fréquent. S'il correspond à un motif de
préprod (`*.werocket.ovh`, `staging.*`, `preprod.*`, `*.o2switch.net`, un sous-domaine d'agence),
cherche le vrai domaine dans le contenu : l'adresse email des mentions légales et de la page contact
le porte presque toujours. Un domaine trouvé là, cohérent entre plusieurs pages, est fiable :
retiens-le et note dans le rapport que tu l'as déduit.

S'il reste ambigu : **la question part dans `questions.md`, la procédure continue.** L'étape 9
n'écrit alors aucune adresse expéditeur — écrire `formulaire@werocket.ovh` serait pire que ne rien
écrire — et l'étape est marquée « en attente du domaine de production ».

### La sauvegarde

Il n'y a plus d'export de base : ni WP-CLI, ni `mysqldump`, ni shell sur ces hébergements
(`mcp.md` §5). Ce qui le remplace est un **journal d'annulation**, `.we-finalise/journal.jsonl`,
décrit en `mcp.md` §6 : avant chaque écriture, la valeur précédente y est inscrite. Les charges
`apply-*` et `fix-*` renvoient déjà leurs couples `from` → `to` : c'est cette sortie que tu
journalises, telle quelle.

Crée le journal maintenant, vide. **Sans journal ouvert, n'écris rien.** Si un plugin de sauvegarde
est actif (visible dans `active_plugins`), inscris au rapport qu'une sauvegarde complète avant
lancement reste la bonne pratique.

Crée enfin le dossier de travail `.we-finalise/` et ajoute-le au `.gitignore`.

---

## Étape 1 — Inventaire

Trois charges utiles en `php-eval` (`mcp.md` §3), avec `post_types` issu de l'étape 0 :

| Charge | Produit | Contenu |
|---|---|---|
| `scripts/php/list-urls.php` | `.we-finalise/urls.json` | Chaque contenu publié (ID, type, titre, slug, URL, `breakdance` oui/non), les modèles Breakdance (header, footer, templates, popups — `template: true`, sans URL), et un `_meta` avec la clé postmeta Breakdance détectée, `blog_public`, `site_icon` |
| `scripts/php/media-audit.php` | `.we-finalise/media.json` | Chaque image : alt, légende, description, URL, et **les contenus qui l'utilisent** (`used_in`, détecté dans `post_content` et dans l'arbre Breakdance) |
| `scripts/php/schema-audit.php` | `.we-finalise/schema.json` | Les post types réellement présents (slug, label, nombre de publiés, `has_archive`, taxonomies, schema Rank Math en place) et l'état du module Schema |

`schema.json` est aussi le filet de sécurité de l'étape 0 : si des post types y apparaissent que
`context.json` ne contient pas, complète-le et relance `list-urls.php` avec la liste complète —
sinon des pages entières échapperont aux étapes 3 à 6.

---

## Étape 2 — Cookies (plugin werocket tools)

Vérifie dans `active_plugins` qu'un plugin dont le nom contient `werocket` est actif. S'il est
installé mais inactif, active-le par `php-eval` (`activate_plugin()`). Puis vérifie que le module
Cookies est activé — la procédure exacte est dans `references/werocket-tools.md`.

Si cette référence ne documente pas encore l'option, **découvre-la** :

```php
global $wpdb;
return $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '%werocket%'");
```

puis lis la valeur de l'option intéressante, et au besoin le code du plugin avec
`agent-connector-for-wp/file-list` et `file-read` sous `wp-content/plugins/`. **Documente ce que tu
trouves dans la référence** pour la prochaine fois.

Si le plugin n'est pas installé, c'est un bloquant : signale-le tout de suite et continue.

Regarde aussi, aux captures de l'étape 4, que le bandeau cookies reste utilisable et ne masque pas
la page : Google déconseille explicitement les interstitiels qui couvrent le contenu à l'arrivée
(étape 8).

---

## Étape 3 — Médiathèque : alt, légende, description

Priorise les images **utilisées** (`used_in` non vide dans `media.json`), puis le reste. Pour chaque
image : **regarde-la réellement** (télécharge-la via son URL, ouvre-la), puis rédige les trois champs
selon `references/redaction-seo-local-geo.md`, section « Médias ». En bref : alt descriptif et
factuel, ≤ 125 caractères, mention de la ville seulement quand elle est pertinente pour cette image,
zéro « image de », zéro bourrage de mots-clés ; légende courte ; description en une ou deux phrases.

Les images décoratives (formes, textures, séparateurs) reçoivent un alt **vide explicite** (`""`),
pas un alt inventé.

Applique avec `scripts/php/apply-media.php`, dont `$args[0]` est
`[{"id":12,"alt":"…","caption":"…","description":"…"}]`. Seuls les champs présents sont écrits : ne
réécris pas un alt existant qui est déjà correct. Journalise la sortie.

---

## Étape 4 — Audit front : responsive, alt rendus, liens sociaux, favicon

Cette étape a deux chemins. Prends le premier qui est disponible ; ils ne s'excluent pas.

### Le HTML rendu par le serveur — toujours disponible

`breakdance-preview-post` avec `include_header_footer: true` sur chaque URL de `urls.json` te donne
le HTML réellement produit. Il suffit pour contrôler, page par page : les `<img>` et leurs `alt`,
les `Hn` dans l'ordre du document, tous les liens avec leur texte, le JSON-LD émis, et les balises
`head` (title, description, canonique, robots, viewport, langue).

### Playwright — si présent, pour ce que le HTML ne montre pas

```bash
node scripts/front-audit.mjs --urls .we-finalise/urls.json --media .we-finalise/media.json --out .we-finalise/front
# staging protégé : --auth user:pass · mode maintenance actif : passe le bypass_url · une page : --only slug
```

Prérequis : `npm i -D playwright && npx playwright install chromium`, une fois. Le script visite
chaque URL à 375, 768, 1024 et 1440 px, capture des pleines pages, et écrit
`.we-finalise/front/report.json` avec en plus du HTML rendu : `overflow` (largeur de scroll >
viewport) par breakpoint, `alt_mismatch` quand l'alt rendu diffère de celui de la médiathèque, les
`social_links` avec leur emplacement, l'état du `favicon` et son code HTTP, le contenu mixte.

**Si Playwright n'est pas installé, ne l'installe pas d'autorité et ne bloque pas** : fais l'étape
avec le HTML rendu, et inscris au rapport que le responsive n'a pas été vérifié visuellement. C'est
le seul angle mort réel des deux chemins.

### Ce que tu en fais

**Regarde les captures**, breakpoint par breakpoint, pas seulement le JSON. Un `overflow: false`
n'exclut pas un texte tronqué, une image écrasée ou un menu illisible. Note chaque problème avec
page + breakpoint + description précise : ces corrections se font dans le builder par un humain,
elles vont au rapport, section « À corriger dans Breakdance ».

**Liens sociaux** : compare chaque lien à `client.social`. Un lien vide, un `#`, un lien vers le
réseau générique (`https://facebook.com`) ou vers le profil d'un autre client est une erreur. Dans
le header ou le pied de page, c'est un modèle Breakdance : corrigeable par `breakdance-edit-post`
sur le modèle concerné. Ce que tu ne peux pas savoir, c'est si un profil bien formé est bien celui
du client : ça va aux questions.

**Alt** : pour chaque `alt_mismatch`, aligne l'élément Breakdance sur l'alt de la médiathèque. Si
l'alt personnalisé du builder est meilleur, c'est la médiathèque qu'il faut corriger (étape 3) :
**la médiathèque est la source de vérité.**

---

## Étape 5 — SEO local et GEO : metas et contenus

Lis `references/redaction-seo-local-geo.md` en entier d'abord. Puis, pour chaque URL publique :

1. **Lis l'état actuel** : le texte rendu (étape 4) et les metas par
   `rank-math/get-post-seo-meta` — qui donne au passage le score SEO du plugin.
2. **Rédige** meta title (≤ 60 caractères, mot-clé + ville, marque en fin), meta description
   (140–155 caractères, bénéfice + localité + appel à l'action) et focus keyword. Applique par
   `scripts/php/apply-seo-meta.php` : `[{"post_id":12,"title":"…","description":"…","focus_keyword":"…"}]`.
   **Les metas s'appliquent partout, pages clés incluses** — elles sont invisibles (règle 1).
3. **Analyse le texte** contre la grille GEO de la référence : réponse directe en tête, `Hn` qui
   posent la question de l'utilisateur, entités locales nommées, FAQ, données concrètes.
4. **Applique les modifications de texte** comme des paires exactes, avec
   `scripts/php/bd-text-replace.php` : `{"dry_run":true,"items":[{"post_id":12,"from":"texte exact actuel","to":"nouveau texte"}]}`.
   Lance-la d'abord telle quelle, lis le résultat, puis rappelle-la avec `"dry_run": false`. La charge écrit dans l'arbre
   Breakdance quand la page en a un, dans `post_content` sinon.

   **Sauf pour les `key_pages` et `legal_pages`** : ces paires ne s'appliquent pas, elles vont au
   rapport, section « Propositions de contenu à valider ».

La charge refuse une paire dont le `from` n'apparaît pas exactement une fois dans le post ciblé.
Vérifie alors ton texte source — guillemets typographiques, espaces insécables, apostrophes `’` vs
`'` — et au besoin dumpe les chaînes de l'arbre (`references/breakdance.md`).

Après toute écriture dans l'arbre : purge le cache (`mcp.md` §7). Journalise.

---

## Étape 6 — Modules Rank Math

Commence par **trouver les informations dans le site** — elles y sont presque toujours :

```bash
node scripts/site-info.mjs --in .we-finalise/pages.json --domain client.fr --out .we-finalise/site-info.json
```

À partir du texte des pages capturé à l'étape 0, il extrait raison sociale, téléphones, adresses,
code postal et ville, SIRET, TVA, RCS, capital, horaires et emails, en donnant pour chacun les pages
où il apparaît et son nombre d'occurrences — les pages légales et contact comptant double.

Il signale surtout les **incohérences** : deux téléphones différents entre le pied de page et les
mentions légales, une adresse qui varie, un email d'agence resté en place. **Tranche-les avant
d'écrire la fiche** : c'est la cohérence NAP qui est en jeu. Une donnée introuvable va aux questions
— elle ne s'invente pas, et un champ vide vaut mieux qu'un champ faux.

Si Node n'est pas disponible, fais la même extraction à la lecture de `pages.json`.

Complète `context.json → client` avec ce qui est confirmé, puis suis `references/rankmath.md` pas à
pas ; les critères qui font qu'un module est *rempli* et pas seulement *activé* sont dans sa §0.
Résumé :

- **Mode avancé** activé, sinon les modules n'apparaissent pas.
- **Modules** `local-seo`, `rich-snippet` et LLMs.txt activés par
  `rank-math/set-module-status`. Vérifie le retour : l'ability refuse un module dont les dépendances
  ne sont pas satisfaites.
- **Identité** par `rank-math/set-website-identity` : `knowledgegraph_type`, `website_name`,
  `knowledgegraph_name`, `local_business_type`.
- **Fiche Local SEO** — adresse, téléphone, email, horaires, zone : **aucune ability ne les couvre**,
  c'est un patch d'option par `php-eval` (`mcp.md` §4 et `rankmath.md`).
- Le `local_business_type` sort de la table secteur → type de `references/schema.md` §1. C'est lui
  qui décide des rich results : `LocalBusiness` générique n'en active presque aucun.
- **robots.txt** : `rank-math/get-robots-txt`. Contrôle qu'il porte un `Sitemap:` vers le sitemap
  Rank Math et **aucun `Disallow: /` résiduel** de la phase de dev.
- **llms.txt** : `rank-math/get-llms-txt` — `enabled`, et le contenu doit lister les bonnes pages.
  Il se nourrit des meta descriptions : si elles sont vides, fais l'étape 5 d'abord.
- **`blog_public` à `1`** : un site laissé en « demander aux moteurs de ne pas indexer » est
  l'erreur de mise en ligne la plus fréquente et la plus coûteuse. `rank-math/get-robots-txt` le
  renvoie dans `public` ; `rank-math/fix-site-seo` avec `test_id: "blog_public"` le corrige.

Après écriture dans les options Rank Math : purge du cache **et** `flush_rewrite_rules()`
(`mcp.md` §7), sinon le sitemap et `llms.txt` ne reflètent pas les réglages.

---

## Étape 7 — Données structurées (Schema)

Lis `references/schema.md` avant d'écrire quoi que ce soit : `rankmath.md` §4 donne la mécanique,
`schema.md` donne le seul choix qui compte — quel type sur quel contenu. Trois principes y
gouvernent tout : une entité d'entreprise unique et bien sous-typée, un type juste par CPT, et
**aucune donnée inventée**. Un `aggregateRating` sans avis réels ou des horaires approximatifs sont
la seule partie de cette procédure qui peut coûter une pénalité manuelle au client : une propriété
absente est neutre, une propriété fausse est un risque.

1. **Audit** — `schema.json` de l'étape 1, plus `rank-math/get-post-schema` sur un exemplaire de
   chaque type. Tu obtiens l'état du module, le `local_business_type` en place, les post types
   réellement présents avec leur nombre de publiés et leur schema par défaut actuel.
2. **Sous-type de l'entreprise** — compare `local_business_type` à la table de `schema.md` §1.
   `LocalBusiness` générique, ou un type absent alors que le secteur en a un précis, c'est la
   correction la plus rentable de l'étape ; elle se fait par la fiche Local SEO (étape 6). Cas
   particuliers dans la référence : praticien seul sous son nom, service sans zone physique.
3. **Mapping CPT → schema** — pour chaque post type publié, choisis le type avec la table de
   `schema.md` §2, et applique par `rank-math/set-post-type-seo-settings` :
   `{post_types: {page: {default_rich_snippet: "off"}}}`. Le réflexe à casser est l'`article` que
   Rank Math met par défaut partout : sur une page de service ou un CPT « réalisations », c'est un
   contresens. **`off` vaut toujours mieux qu'un type approximatif.** Les CPT techniques (slider,
   popup, formulaire) passent en `off` et doivent être `noindex`.
4. **Pages prestation** — ce sont elles qui rapportent. Un `Service` par page, `provider.@id`
   pointant sur l'entité de l'accueil, `areaServed` tiré de `client.zone`, et **pas d'`offers` sans
   tarifs affichés** (`schema.md` §3). Relève le `@id` réellement émis dans le JSON-LD de l'accueil
   avant de le recopier : il varie selon la version de Rank Math.
5. **Application** — `scripts/php/apply-schema.php` avec le plan décrit en `rankmath.md` §4, en
   `"dry_run": true` d'abord : **lis le diff `from` → `to`**, puis `"dry_run": false`. `replace: true` efface les
   `rank_math_schema_*` existants du contenu : ne l'utilise que si l'audit ne montre rien d'écrit à
   la main par un humain. Journalise.
6. **Validation sur le JSON-LD rendu**, pas sur la config. Purge le cache, puis applique la grille de
   `schema.md` §5 au HTML de `breakdance-preview-post` sur l'accueil et un exemplaire de chaque CPT :
   une seule entité d'entreprise, `BreadcrumbList` sur les pages profondes, aucun `%placeholder%`
   non résolu, aucun `Article` sur une page de service, aucun graphe concurrent émis par le thème ou
   WooCommerce.

Reporte le sous-type retenu, le mapping appliqué, et surtout ce que tu as **volontairement laissé de
côté** faute de données (avis, tarifs, horaires) : cette liste est ce que le client doit fournir pour
aller plus loin.

---

## Étape 8 — Conformité aux recommandations Google

Lis `references/google-seo.md`. C'est le contrôle qui reprend le guide de démarrage SEO de Google sur
ce qui est vérifiable à la livraison : indexabilité, URL et canoniques, titles et descriptions,
textes d'ancrage, images, HTTPS, mobile, données structurées.

Commence par `rank-math/audit-site-seo` : le plugin fait une partie du travail et rend des constats
avec un `test_id` que `rank-math/fix-site-seo` sait corriger. Traite-les, puis fais le contrôle
propre à cette procédure — l'audit du plugin ne juge ni le SEO local, ni la justesse d'un type de
schema, ni la qualité d'un alt :

```bash
node scripts/google-check.mjs --site <site_url> --prod <prod_url>
# statut HTTP des liens internes (plus lent) : --links
```

Le script s'appuie sur le rapport front de l'étape 4 et interroge `robots.txt` et le sitemap. Il
classe chaque constat en `bloquant`, `à corriger`, `à vérifier`, `info`, avec **la façon de le
résoudre** : `auto` → tu le corriges maintenant ; `humain` → builder ou jugement visuel, donc
rapport ; `question` → il manque une information du client, donc `questions.md` (règle 5).

Sans Node, fais le même contrôle sur le HTML rendu et sur `rank-math/get-robots-txt`.

**Traite les `bloquant` d'abord et signale-les tout de suite** : un `Disallow: /` résiduel, un
`noindex` sur une page à indexer, un site encore en `http://` ou sans `meta viewport` annulent le
reste du travail. Le piège le plus fréquent et le moins visible : un `Disallow: /wp-content/` hérité
du développement — Google ne peut alors ni charger le CSS ni le JS, donc ne voit pas les pages comme
un visiteur.

Deux points demandent ton jugement plutôt que le script :

- **Publicités et interstitiels** — regarde les captures. Un pop-up qui couvre le contenu à l'arrivée
  est explicitement déconseillé par Google, bandeau cookies compris (étape 2).
- **Originalité du contenu** — un texte recopié d'un autre site, y compris d'un concurrent du même
  secteur, y compris fourni par le client, est le seul cas de duplication qui compte vraiment. Si tu
  as un doute sur un paragraphe, **signale-le plutôt que de le réécrire**.

Ce que le script ne peut pas juger et qui va au rapport : Search Console à créer et sitemap à
soumettre **après** la mise en ligne sur le domaine de production, performances réelles, et la
promotion du site.

Les liens absolus vers la préprod que ce contrôle signale ne sont que ceux des pages **rendues** :
l'inventaire complet en base est l'étape 12.

Enfin, ne perds pas de temps — et ne le facture pas — sur ce que Google dit explicitement de ne pas
travailler : `meta keywords`, longueur « idéale » du contenu, ordre des `Hn` pour le classement,
mots-clés dans le domaine, contenu dupliqué interne présenté comme une pénalité, E-E-A-T présenté
comme un facteur de classement. Les formulations exactes sont dans `google-seo.md`.

---

## Étape 9 — Formulaires : destinataire et expéditeur

Lis `references/formulaires.md`. Un formulaire cassé ne se voit pas : le visiteur envoie, la page
affiche « merci », personne ne reçoit rien — et le client perd des clients sans le savoir. Deux
contrôles ne se négocient pas : **il y a un destinataire**, et **le `From` est
`formulaire@<domaine de production>`**.

Audit par `scripts/php/forms-audit.php`, avec le domaine de prod en `$args[0]`. Il couvre les
formulaires du FormBuilder Breakdance, les plugins de formulaires actifs (Contact Form 7 et WPForms
sont lus ; les autres sont signalés à vérifier à la main), les shortcodes posés dans les pages, le
plugin SMTP et son expéditeur, et `admin_email`. Les noms de propriétés du FormBuilder variant selon
les versions, l'audit remonte le **chemin JSON** de chaque valeur : c'est ce chemin que tu recopies
pour corriger. `breakdance-get-form-submissions` complète utilement le tableau : un formulaire qui
n'a jamais rien reçu depuis des semaines est un indice.

Anomalies remontées, toutes bloquantes sauf mention contraire :

- `destinataire_manquant` — les messages sont perdus ;
- `destinataire_dynamique` — le `To` est construit sur un champ du visiteur : le message part au
  visiteur, pas au client ;
- `from_non_conforme` / `from_absent` — mauvais domaine expéditeur, donc spam ou rejet DMARC ;
- `admin_email_suspect` — adresse d'agence ou de staging restée en place.

**Le domaine se dérive de `prod_url`, jamais du staging.** Si le domaine de production n'a pas pu
être établi à l'étape 0, **n'écris aucune adresse expéditeur** : marque l'étape « en attente » et
mets la question au rapport. Écrire `formulaire@werocket.ovh` — l'adresse de l'agence — est pire que
ne rien écrire.

Corriger dans cet ordre (le détail est dans la référence) :

1. **Le plugin SMTP d'abord** : `from_email` = `formulaire@<domaine>` plus « Force From Email »
   couvre tout le site d'un coup. Pas de plugin SMTP actif = bloquant à signaler, les mails partent
   par `mail()` avec une délivrabilité faible.
2. **Puis chaque formulaire**, pour que rien ne contredise le réglage global. Pour un formulaire
   Breakdance, `breakdance-set-element-form` est le bon outil — il connaît le format. Pour corriger
   une adresse par chemin JSON sans toucher au reste, ou pour CF7 et le SMTP,
   `scripts/php/apply-forms.php` :

```json
{
  "dry_run": true,
  "smtp": { "from_email": "formulaire@client.fr", "from_name": "Cabinet X", "force": true },
  "breakdance": [
    { "post_id": 42, "path": "root.children.0…actions.0.email_from", "value": "formulaire@client.fr" },
    { "post_id": 42, "path": "root.children.0…actions.0.email_to", "value": "contact@client.fr" }
  ],
  "cf7": [ { "form_id": 5, "recipient": "contact@client.fr", "sender": "Cabinet X <formulaire@client.fr>" } ]
}
```

La charge refuse un chemin inexistant, une cible qui est une structure et non une valeur, et toute
valeur qui n'est pas une adresse email valide. Un `from` **absent** de l'arbre ne se crée pas par
chemin : il se règle par le plugin SMTP (point 1) ou dans le builder.

3. **Tester pour de vrai.** La configuration en base ne prouve pas qu'un mail arrive. Envoie un
   message depuis le front avec une adresse réelle, vérifie la réception, vérifie que « répondre »
   part vers l'adresse du visiteur (`Reply-To`), et regarde l'en-tête `From` du message reçu. Sans
   accès à la boîte du client, note « à confirmer par le client » — **pas** « vérifié ». Sur une
   préprod l'envoi peut être bloqué : refaire le test après mise en ligne, et l'inscrire dans les
   vérifications de mise en ligne du rapport.

Un formulaire sans destinataire est un bloquant : signale-le dès que tu le trouves, sans attendre le
rapport et sans t'arrêter.

---

## Étape 10 — Favicon

Si l'étape 4 montre un favicon présent et fonctionnel (HTTP 200) et que `site_icon` est défini,
passe.

Sinon, génère un PNG carré de 512 px depuis le logo et **regarde-le**. Le logo source vient de
`logo_source` (étape 0). Deux chemins selon l'hôte :

- **En local**, si ImageMagick est là : `magick <logo> -trim +repage -resize 450x450 -gravity center
  -extent 512x512 .we-finalise/favicon-512.png`, puis téléverse-le par
  `agent-connector-for-wp/file-write` et enregistre-le en médiathèque par `php-eval`
  (`wp_insert_attachment` + `wp_generate_attachment_metadata`).
- **Sur le serveur**, par `php-eval` avec GD (`imagecreatetruecolor`, `imagecopyresampled`) —
  `Imagick` est souvent absent (`mcp.md` §5).

Puis `update_option('site_icon', <id>)`.

**Un logotype horizontal réduit en carré est illisible.** Dans ce cas, isole le symbole si le logo en
a un ; sinon inscris au rapport qu'un favicon dédié est à demander au client. **Ne mets pas un
favicon moche en prod.**

---

## Étape 11 — Independent Analytics

Si `independent-analytics` est absent de `active_plugins`, installe-le et active-le ; s'il est
inactif, active-le. Par `php-eval` :

```php
include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
include_once ABSPATH . 'wp-admin/includes/file.php';
include_once ABSPATH . 'wp-admin/includes/plugin.php';
// puis Plugin_Upgrader avec l'URL du dépôt, ou activate_plugin() si déjà présent
```

Puis autorise le rôle Éditeur à voir les statistiques. La clé d'option n'est pas documentée :
découvre-la, lis sa valeur, ajoute `editor`, réécris-la.

```php
global $wpdb;
return $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'iawp%'");
```

Cherche une option contenant `role`, `permission` ou `access`. **Documente la clé trouvée dans
`references/independent-analytics.md`.**

---

## Étape 12 — Liens en dur vers la préprod

Lis `references/liens-en-dur.md`. Cette étape vient **après toutes les écritures** : elle vérifie
aussi ce que les étapes précédentes ont écrit.

Un lien en dur vers la préprod ne se voit pas pendant la recette — sur le staging, il fonctionne. Il
casse le jour de la mise en ligne, ou renvoie les visiteurs du site de production vers une préprod
obsolète. L'étape 8 ne voit que les pages rendues : un popup non déclenché, un modèle d'archive, un
widget ou une métadonnée lui échappent. Ici, c'est la base entière.

`scripts/php/hardcoded-urls.php` → `.we-finalise/hardcoded-urls.json`. Sans argument, il cherche le
**domaine courant du site** — pendant la finalisation, celui de la préprod — plus les motifs de
préprod connus, ce qui rattrape aussi les restes d'une migration antérieure. Il couvre les pages,
articles et CPT, les **modèles Breakdance** (header, footer, templates, popups, blocs globaux), les
arbres, les menus, les widgets et options sérialisées, les métadonnées ACF et Rank Math, les
termmeta, usermeta et commentaires. Chaque occurrence est classée `lien`, `media` ou `autre`, avec
son emplacement lisible et, dans un arbre Breakdance, le chemin JSON exact.

**Regarde d'abord la liste des modèles concernés** : un lien en dur dans le pied de page est présent
sur toutes les pages du site.

Corrige ensuite les liens de navigation en **relatif**, avec
`scripts/php/fix-hardcoded-urls.php` — en `"dry_run": true` d'abord, **lis le diff**, puis `"dry_run": false`.
Purge le cache. Journalise.

Pourquoi relatif et non le domaine de production : **le site tourne encore sur la préprod.**
Remplacer le domaine maintenant le rendrait inaccessible. Un lien `/contact/` fonctionne sur la
préprod comme en production, et reste juste lors de tous les changements de domaine futurs. Le mode
`swap` avec `to_host` existe pour la bascule, une fois le DNS basculé — pas ici.

Ce que la charge ne convertit pas d'elle-même, et qu'il ne faut pas forcer sans raison : les URL de
**médias** (Breakdance les régénère souvent depuis l'ID d'attachment, et une URL relative peut ne pas
être gérée à l'affichage), et `home`/`siteurl`, qui ne peuvent pas être relatifs.

Vérifie enfin deux ou trois liens sur le front après correction, et inscris au rapport ce que la base
ne contient pas : CSS et JS compilés (régénérés à la purge), fichiers importés contenant des liens,
tables propres à un plugin, `wp-config.php` et `.htaccess`, et tous les endroits **hors du site** où
l'URL de préprod a pu être communiquée — Google Business Profile, réseaux sociaux, Search Console,
signatures d'email.

---

## Étape 13 — Rapport

Génère `we-finalise-report.md` à la racine du projet à partir de `references/report-template.md`.
Le rapport est le livrable. Il liste :

- ce qui a été vérifié, et **ce qui n'a pas pu l'être**, avec la raison (Playwright absent, mode
  maintenance actif, boîte client inaccessible) ;
- ce qui a été modifié, avec compte et exemples, et le chemin du journal d'annulation ;
- ce qui reste à faire par un humain dans Breakdance ;
- les propositions de contenu en attente de validation client ;
- **les questions**, reprises de `.we-finalise/questions.md`, regroupées et formulées pour être
  envoyées telles quelles au client ;
- les bloquants : plugin manquant, `blog_public` à 0, formulaire sans destinataire, favicon à
  demander, mode maintenance à désactiver à la mise en ligne.

---

## Ordre, interruptions, échecs

Les étapes 0 → 13 s'enchaînent sans confirmation intermédiaire. Dépendances réelles :

- **4 ← 1 et 3** — la comparaison des alt a besoin de l'inventaire et d'une médiathèque à jour.
- **6 ← 0** — l'extraction NAP lit `pages.json`, produit à l'étape 0. (C'est ce qui a changé : elle
  ne dépend plus de Playwright.)
- **7 ← 1 et 6** — l'inventaire des post types, et la fiche Local SEO dont le `@id` est issu.
- **8 ← 4 et 5–7** — elle contrôle leur travail.
- **9 ← 0** — le domaine de production.
- **12 après toutes les écritures** — volontairement.

Si l'utilisateur ne demande qu'une étape (« fais juste les metas Rank Math »), fais l'étape 0
— elle est rapide et sans question —, l'inventaire, l'étape demandée, et un rapport réduit à cette
étape. **Ne saute jamais l'ouverture du journal.**

Quand un appel MCP échoue (timeout, permissions, ability absente), **ne contourne pas en supposant le
résultat** : regarde `exception` (`mcp.md` §3), essaie le niveau inférieur du canal (`mcp.md` §2), et
si rien ne passe, marque l'étape « non vérifiée » dans le rapport en disant laquelle et pourquoi. Une
étape franchement marquée non vérifiée vaut infiniment mieux qu'une étape supposée faite.
