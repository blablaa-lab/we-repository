# Rank Math par le canal MCP

Deux chemins, dans cet ordre (`mcp.md` §2) : les **abilities `rank-math/*`** d'abord — elles écrivent
dans les options à la place du plugin, donc sans que tu aies à connaître la forme de ses tableaux —,
puis le **patch d'option par `php-eval`** pour le seul cas qu'aucune ability ne couvre : la fiche
Local SEO (§3). Lis toujours l'option avant de l'écrire : Rank Math stocke ses réglages dans quelques
gros tableaux sérialisés, et un `update_option` sur la valeur complète écrase tout le tableau.

## Options principales

| Option | Contenu |
|---|---|
| `rank_math_modules` | tableau des slugs de modules actifs |
| `rank-math-options-general` | réglages généraux : `setup_mode`, `robots_txt_content`, réglages llms.txt, etc. |
| `rank-math-options-titles` | titres & metas par défaut **et** fiche Local SEO / Knowledge Graph |
| `rank-math-options-sitemap` | sitemap |
| `rank_math_title` / `rank_math_description` / `rank_math_focus_keyword` | postmeta par contenu |
| `rank_math_schema_<Type>` | postmeta : un schema posé sur ce contenu (voir §4) |

Lecture : `rank-math/get-settings` renvoie l'ensemble des réglages, c'est la source de vérité.
Pour une clé précise, `php-eval` : `return get_option('rank-math-options-titles')['knowledgegraph_name'] ?? null;`

Écriture ciblée d'une clé ou d'un sous-tableau, quand aucune ability ne couvre le réglage : on relit
l'option, on modifie **la seule clé visée**, on réécrit, et on relit pour prouver l'écriture
(`mcp.md` §3). La valeur précédente part au journal (`mcp.md` §6) :

```php
$opt  = get_option('rank-math-options-titles', array());
$from = $opt['local_address'] ?? null;
$opt['local_address'] = array(
  'streetAddress'   => '12 rue X',
  'addressLocality' => 'Lyon',
  'addressRegion'   => 'Auvergne-Rhône-Alpes',
  'postalCode'      => '69001',
  'addressCountry'  => 'FR',
);
update_option('rank-math-options-titles', $opt);
return array('from' => $from, 'to' => get_option('rank-math-options-titles')['local_address']);
```

## 0. Un module activé n'est pas un module rempli

Activer un module Rank Math ne produit rien tant que ses champs sont vides — et un module à moitié
rempli est pire qu'absent : il émet des données incomplètes que Google enregistre. **Les trois
modules ci-dessous ne sont pas cochés « fait » sur leur activation, mais sur ces critères.**

Les valeurs viennent du site, pas de ton imagination : `scripts/site-info.mjs` extrait du texte des
pages (mentions légales, contact, pied de page) les raisons sociales, téléphones, adresses, SIRET,
horaires et emails, avec les pages où chacun apparaît. Ce qui reste introuvable se demande au
client et s'inscrit dans le rapport — jamais inventé.

**SEO Local** — rempli quand :
- [ ] `knowledgegraph_type` et `knowledgegraph_name` renseignés (nom commercial, pas la raison sociale)
- [ ] `local_business_type` = le sous-type précis du secteur (table de `schema.md` §1), pas `LocalBusiness`
- [ ] logo défini, carré ou proche, ≥ 112 px
- [ ] adresse complète : rue, code postal, ville, région, pays
- [ ] téléphone au format international, email, `url` sur le domaine de **prod**
- [ ] horaires renseignés, ou volontairement vides et signalés comme tels (jamais approximatifs)
- [ ] NAP identique au caractère entre la fiche, le pied de page, la page contact et Google Business Profile
- [ ] `local_seo_about_page` et `local_seo_contact_page` pointant sur les bons ID

**Schema (données structurées)** — rempli quand : voir l'étape 7 de la skill et `schema.md`.
En résumé : module `rich-snippet` actif, un type juste sur chaque post type (pas `article` partout),
`Service` sur les pages prestation, et le JSON-LD **rendu** vérifié page par page.

**LLMs.txt** — rempli quand :
- [ ] module actif, et `rank-math/get-llms-txt` renvoie `enabled` avec un contenu non vide (le code
      HTTP réel depuis l'extérieur est contrôlé par `google-check.mjs`, `mcp.md` §2 niveau 4)
- [ ] le fichier liste les pages publiques avec titre, URL **et description** non vides
- [ ] les CPT métier y figurent, les CPT techniques (popup, slider, formulaire) non
- [ ] aucune URL de staging dans le fichier
- [ ] les pages `noindex` en sont absentes

Un `llms.txt` dont les descriptions sont vides signifie que les meta descriptions Rank Math
manquent : ce sont elles qui l'alimentent (étape 5 de la skill), applique-les d'abord.

## 1. Mode avancé

Les modules Local SEO, Schema et LLMs.txt n'apparaissent qu'en mode avancé. `rank-math/get-settings`
donne le `setup_mode` en place ; si ce n'est pas `advanced`, patche la clé par `php-eval` selon
l'idiome ci-dessus, sur `rank-math-options-general` :

```php
$opt = get_option('rank-math-options-general', array());
$from = $opt['setup_mode'] ?? null;
$opt['setup_mode'] = 'advanced';
update_option('rank-math-options-general', $opt);
return array('from' => $from, 'to' => get_option('rank-math-options-general')['setup_mode']);
```

## 2. Modules

Les modules actifs sont dans `rank-math/get-settings` (et dans l'option `rank_math_modules`). Les
slugs exacts sont dans le code du plugin : lis-les avec l'ability `agent-connector-for-wp/file-read`
sur `wp-content/plugins/seo-by-rank-math/includes/modules/class-manager.php` (les valeurs `'id'`),
plutôt que de les deviner. Slugs attendus pour cette procédure : `local-seo`, `sitemap`,
`rich-snippet` (schema), et le module llms.txt (slug à confirmer dans la source, probablement
`llms-txt`).

Activation par l'ability, qui refuse un module dont les dépendances ne sont pas satisfaites — **lis
son retour** :

```
mcp-adapter-execute-ability
  ability_name: "rank-math/set-module-status"
  parameters:   { "modules": { "local-seo": true, "sitemap": true, "rich-snippet": true,
                               "llms-txt": true } }
```

Puis `flush_rewrite_rules();` en `php-eval` : les modules ajoutent des endpoints (llms.txt, sitemap)
qui restent invisibles sans réécriture des règles (`mcp.md` §7). La charge
`scripts/php/apply-schema.php` sait aussi activer `rich-snippet` avec `"module": true` (§4).

## 3. Fiche Local SEO (dans `rank-math-options-titles`)

D'où viennent les valeurs : `node scripts/site-info.mjs --in .we-finalise/pages.json --domain <domaine>`,
sur le texte des pages capturé à l'étape 0 — pas besoin de l'audit front.
Il rend, pour chaque donnée, les valeurs candidates avec les pages où elles apparaissent et leur
nombre d'occurrences, et signale les **incohérences** — deux téléphones différents entre le pied de
page et les mentions légales, une adresse qui varie, un email d'agence resté dans le pied de page.
Ces conflits se tranchent avant d'écrire la fiche : c'est le NAP qui est en jeu.

**C'est le seul bloc de réglages Rank Math qu'aucune ability ne couvre** :
`rank-math/set-website-identity` écrit l'identité Knowledge Graph (`knowledgegraph_type`,
`website_name`, `knowledgegraph_name`, `local_business_type`), mais ni l'adresse, ni le téléphone, ni
l'email, ni les horaires, ni la zone. Ceux-là se patchent clé par clé en `php-eval`, selon l'idiome
du haut de ce fichier (`mcp.md` §4).

Clés habituelles — confirme-les en lisant l'option (`get_option('rank-math-options-titles')`) ou
`includes/settings/titles/local-seo.php` du plugin par `agent-connector-for-wp/file-read`, elles
varient légèrement selon la version :

- `knowledgegraph_type` : `company` (quasiment toujours pour un client Werocket) ou `person`
- `knowledgegraph_name` : `client.name` (nom commercial, pas la raison sociale)
- `knowledgegraph_logo` / `knowledgegraph_logo_id` : URL et ID du logo en médiathèque (carré ou proche, ≥ 112 px)
- `local_business_type` : type Schema.org précis (`Dentist`, `Physician`, `Restaurant`, `Plumber`, `LegalService`, `RealEstateAgent`…) — pas `LocalBusiness` générique quand un sous-type existe, c'est ce qui donne les rich results pertinents
- `local_address` : tableau `streetAddress`, `addressLocality`, `addressRegion`, `postalCode`, `addressCountry` (`FR`)
- `phone` au format international `+33 4 …`, `email`, `url` (URL de prod, pas le staging)
- `opening_hours` : tableau de `{day, time}` (`"Monday"`, `"09:00-18:00"`), `opening_hours_format` à `on` si besoin
- `geo` : `"lat,lng"` si tu l'as (utile pour le Knowledge Graph), `price_range` optionnel
- `local_seo_about_page`, `local_seo_contact_page` : ID des pages À propos / Contact, pour que Rank Math y pose les schemas `AboutPage` / `ContactPage`

Cohérence NAP : nom, adresse et téléphone doivent être **identiques au caractère** à ceux affichés dans le footer, sur la page contact et sur la fiche Google Business Profile du client. Une variante (« rue » vs « r. », numéro sans indicatif) dégrade le SEO local plus qu'un mot-clé manquant.

## 4. Données structurées (module Schema)

Le module s'appelle « Schema (Structured Data) », son slug est `rich-snippet`. Sans lui, Rank Math
n'émet qu'un graphe minimal et aucun réglage par post type n'est pris en compte. **Le choix des
types se fait avec `schema.md`** — ici, seulement la mécanique.

Audit d'abord, jamais d'écriture à l'aveugle :

La charge `scripts/php/schema-audit.php` en `php-eval` (`$args[0]` vide, ou la liste des post types
à forcer, séparés par des virgules) → `.we-finalise/schema.json` :

```php
$args = array('');
<corps de scripts/php/schema-audit.php, sans son `<?php`>
```

Le rapport donne : module actif ou non, `setup_mode`, `local_business_type`, la liste des post
types **réellement présents** (slug, nombre de contenus publiés, `has_archive`, taxonomies, schema
par défaut déjà configuré), les schemas déjà posés contenu par contenu, le compte de l'ancien
format (`rank_math_rich_snippet`), et les clés d'options liées au schema telles qu'elles existent
dans cette version du plugin — c'est cette dernière liste qui tranche en cas de doute sur un nom de
clé.

### Clés d'options, par post type

Le chemin normal est l'ability `rank-math/set-post-type-seo-settings`
(`{post_types: {page: {default_rich_snippet: "off"}}}`) : c'est elle qui écrit les clés ci-dessous.
La table sert à lire l'existant et à savoir ce qui a été écrit — dans `rank-math-options-titles`,
pour chaque post type `<pt>` :

| Clé | Valeurs |
|---|---|
| `pt_<pt>_default_rich_snippet` | `off`, `article`, `book`, `course`, `event`, `jobposting`, `music`, `product`, `recipe`, `restaurant`, `service`, `software`, `video`, `person` |
| `pt_<pt>_default_article_type` | `Article`, `BlogPosting`, `NewsArticle` (uniquement si `article`) |
| `pt_<pt>_default_snippet_name` | gabarit du nom, variables Rank Math (`%title%`, `%sep%`, `%sitename%`) |
| `pt_<pt>_default_snippet_desc` | gabarit de la description (`%excerpt%`, `%seo_description%`) |

La casse des valeurs compte : `off` et non `OFF`, `jobposting` et non `JobPosting`. Une valeur
inconnue est silencieusement ignorée, sans erreur — d'où l'audit après application.

### Schemas par contenu

Le système actuel stocke un schema par postmeta `rank_math_schema_<Type>` (ex.
`rank_math_schema_Service`, `rank_math_schema_FAQPage`), valeur = tableau du schema avec un bloc
`metadata` (`title`, `type`, `shortcode`, `isPrimary`). Sans ce `metadata`, le schema n'apparaît
pas dans l'éditeur et n'est pas marqué comme principal : `apply-schema.php` l'ajoute pour toi.
L'ancien format `rank_math_rich_snippet` + `rank_math_snippet_*` peut cohabiter sur un site migré —
si l'audit en signale, les schemas des deux systèmes s'additionnent dans la page : nettoie l'ancien
plutôt que d'empiler.

### Application

Écris un plan dans `.we-finalise/schema-plan.json` :

```json
{
  "module": true,
  "breadcrumbs": true,
  "post_type_defaults": {
    "page": { "rich_snippet": "off" },
    "post": { "rich_snippet": "article", "article_type": "BlogPosting",
              "snippet_name": "%title% %sep% %sitename%", "snippet_desc": "%excerpt%" },
    "realisation": { "rich_snippet": "off" }
  },
  "posts": [
    { "post_id": 42, "replace": true,
      "schemas": [ { "@type": "Service", "name": "Ostéopathie du sport",
                     "serviceType": "Ostéopathie du sport",
                     "provider": { "@type": "Physician", "name": "Cabinet X",
                                   "@id": "https://www.client.fr/#organization" },
                     "areaServed": [ { "@type": "City", "name": "Lyon" } ] } ] }
  ]
}
```

Puis la charge `scripts/php/apply-schema.php` en `php-eval`, deux fois, en ne changeant que
`dry_run` — le plan complet est le contenu de `$args[0]` :

```php
$args = array('{"dry_run":true, …le plan ci-dessus…}');   // diff simulé
<corps de scripts/php/apply-schema.php, sans son `<?php`>
```

La passe `"dry_run": true` renvoie exactement les couples `from` → `to` qui seront écrits : lis-le
avant d'appliquer, et journalise-le (`mcp.md` §6). `replace: true` supprime les
`rank_math_schema_*` existants du contenu avant d'écrire — c'est ce qu'il faut pour reprendre un
site déjà configuré, mais ça efface un schema posé à la main par un humain : vérifie
`existing_schemas` dans l'audit d'abord.

Les valeurs vont en base telles quelles : un `@type` inexistant chez Schema.org sera écrit sans
broncher et ignoré par Google. La charge ne valide que la présence du `@type`, pas sa réalité.

### Fil d'Ariane

`rank-math/set-breadcrumb-settings` est le chemin propre ; `breadcrumbs: true` dans le plan de
`apply-schema.php` fait la même chose en une passe, en écrivant `breadcrumbs = on` dans
`rank-math-options-general`. Ce réglage alimente le
`BreadcrumbList` du graphe. Le fil n'a pas besoin d'être affiché dans le thème pour que le schema
soit émis ; s'il est affiché par Breakdance, garder les deux cohérents.

### Vérification

Après application : purge du cache **et** `flush_rewrite_rules()` (le bloc est dans `mcp.md` §7),
puis contrôle du JSON-LD **rendu** par `breakdance-preview-post` (procédure et grille dans
`schema.md` §5). La config en base ne prouve rien : un thème ou WooCommerce peut émettre un second
graphe concurrent.

## 5. robots.txt

`rank-math/get-robots-txt` renvoie le contenu servi, plus `exists` (un fichier physique à la racine)
et `public` (la visibilité moteurs). Le contenu éditable est la clé `robots_txt_content` de
`rank-math-options-general`. Attendu pour un site vitrine :

```
User-agent: *
Disallow: /wp-admin/
Allow: /wp-admin/admin-ajax.php

Sitemap: https://www.client.fr/sitemap_index.xml
```

Supprime tout `Disallow: /` hérité de la phase de dev. Le `Sitemap:` doit pointer sur le domaine de prod. Rank Math ne sert son robots.txt que s'il n'existe pas de fichier physique à la racine : c'est le `exists` de `rank-math/get-robots-txt`, confirmable par `agent-connector-for-wp/file-list` sur la racine du site — s'il existe, aligne-le par `file-write` ou fais-le supprimer. `rank-math/fix-site-seo` (`test_id: "robots_txt"`) corrige les cas standard, et `google-check.mjs` dit ce que le fichier renvoie réellement vu de l'extérieur.

## 6. llms.txt

Une fois le module actif, ses réglages sont dans `rank-math-options-general`, clés préfixées `llms_` — liste-les en `php-eval` :

```php
$opt = get_option('rank-math-options-general', array());
return array_intersect_key($opt, array_flip(array_filter(array_keys($opt),
    function ($k) { return strpos($k, 'llms') === 0; })));
```

Inclure les post types publics (pages + articles + CPT métier), exclure les CPT techniques. Vérifie avec `rank-math/get-llms-txt` : le fichier doit lister les pages avec titre, URL et description — les pages `noindex` en sont exclues automatiquement. Si vide, les descriptions manquent : ce sont les meta descriptions Rank Math (étape 5 de la skill) qui alimentent le fichier, applique-les d'abord. Critères d'acceptation complets en §0.

## 7. Indexation

`get_option('blog_public')` doit valoir `1` — `rank-math/get-robots-txt` le renvoie dans `public`, et
`rank-math/fix-site-seo` avec `test_id: "blog_public"` le corrige. Vérifie aussi qu'aucun `noindex`
global : dans `rank-math/get-settings` (ou `get_option('rank-math-options-titles')`), les clés
`pt_page_robots` et `pt_post_robots` ne doivent pas contenir `noindex`.

## 8. Après écriture

`wp_cache_flush()` puis `flush_rewrite_rules()` en `php-eval`, et la purge du plugin de cache repéré
dans `active_plugins` : le bloc complet est dans `mcp.md` §7. Sans la réécriture des règles, le
sitemap et `llms.txt` ne reflètent pas les réglages.
