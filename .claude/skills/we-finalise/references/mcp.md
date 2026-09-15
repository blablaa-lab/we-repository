# Le canal MCP — comment cette skill parle au site

Toute la procédure passe par **le serveur MCP du dossier du projet**. Pas de SSH, pas de WP-CLI
local, pas d'alias à déclarer, pas d'admin WP dans un navigateur.

Ce fichier est la référence de transport : où trouver le serveur, dans quel ordre choisir un outil,
comment exécuter du PHP sur le site, et ce qui reste hors de portée.

---

## §1. Trouver le serveur du dossier

Chaque dossier de site a son propre serveur MCP (`@automattic/mcp-wordpress-remote` branché sur le
plugin MCP Adapter du site). Le nom du serveur varie d'un projet à l'autre
(`www-arteose-fr-arteose-wordpress`, `villadelisle-mcp`, `tb2sarl-werocket-ovh-tb2s-wordpress`…) :
**ne le devine pas, lis-le dans tes outils.**

Dans ta liste d'outils, cherche celui dont le nom **finit par `__breakdance-site-info`**. Son
préfixe est le serveur du projet :

```
mcp__www-arteose-fr-arteose-wordpress__breakdance-site-info
└──────────────── préfixe = ton serveur ──────────────┘
```

Dans la suite de la documentation, les outils sont notés sans ce préfixe : `breakdance-site-info`
veut dire `mcp__<serveur>__breakdance-site-info`.

**Si aucun outil ne correspond**, le dossier n'a pas de serveur MCP attaché. C'est un bloquant et
la seule chose que tu ne peux pas contourner : dis-le à l'utilisateur, indique qu'il faut ajouter
le serveur MCP WordPress de ce site au dossier, et arrête-toi là. N'essaie pas de retomber sur
WP-CLI en SSH : cette procédure n'en a plus les scripts.

**Si plusieurs serveurs correspondent** (deux sites ouverts dans le même dossier), demande lequel
avant d'écrire quoi que ce soit — c'est la seule question qui mérite de bloquer le démarrage.

---

## §2. Les quatre niveaux, dans cet ordre

Le même résultat est souvent atteignable par plusieurs chemins. Prends toujours le plus haut de
cette liste qui sait faire le travail : plus on descend, plus on écrit à la main dans des
structures qui appartiennent à un plugin.

### Niveau 1 — Les outils Breakdance natifs (48, appelables directement)

Pour tout ce qui touche l'arbre, les modèles, les formulaires, le rendu. Ils connaissent le format
interne du builder ; toi non.

| Besoin | Outil |
|---|---|
| Infos du site, plugins actifs, header/footer actifs, accueil | `breakdance-site-info` |
| Le mode maintenance est-il actif ? | `breakdance-get-maintenance-mode` |
| Lister / chercher des contenus, savoir s'ils sont Breakdance | `breakdance-search-posts` |
| Métadonnées d'un contenu | `breakdance-get-post-details` |
| Lire l'arbre d'un contenu | `breakdance-get-post-tree` |
| Modifier l'arbre (texte, propriétés) | `breakdance-edit-post` |
| Le HTML réellement rendu d'une page | `breakdance-preview-post` |
| Champs et actions d'un formulaire | `breakdance-set-element-form` |
| Soumissions de formulaire reçues | `breakdance-get-form-submissions` |
| Conditions d'affichage d'un modèle | `breakdance-get-template-conditions` |
| Réglages globaux (couleurs, typo) | `breakdance-get-global-settings` |

`breakdance-preview-post` accepte `include_header_footer` : c'est ainsi qu'on voit le HTML complet
d'une page, header et footer compris, sans passer par un navigateur.

Avant tout appel à un outil qui **écrit** dans l'arbre (`breakdance-edit-post`, `html-to-page`,
`insert-stylesheet`), le serveur exige d'avoir appelé `breakdance-get-instructions` au moins une
fois. Fais-le à l'étape 0, une fois pour toute la session.

### Niveau 2 — Les abilities, via `mcp-adapter-execute-ability`

Les familles `rank-math/*`, `scf/*` et `agent-connector-for-wp/*` ne sont pas exposées comme outils
directs : elles s'appellent toutes par le même outil.

```
mcp-adapter-execute-ability
  ability_name: "rank-math/get-settings"
  parameters:   { }
```

Deux outils d'accompagnement : `mcp-adapter-discover-abilities` (la liste complète, sans schéma) et
`mcp-adapter-get-ability-info` avec `ability_name` (le schéma d'entrée et de sortie d'une seule).
**Le catalogue varie d'un site à l'autre** selon les plugins installés : à l'étape 0, appelle
`mcp-adapter-discover-abilities` et fonde-toi sur ce qu'il renvoie, pas sur la liste ci-dessous.
Devant une ability que tu n'as jamais appelée, lis son schéma avant de l'appeler — les noms de
champs ne se devinent pas.

Les abilities Rank Math qui servent à cette procédure :

| Besoin | Ability | Remarque |
|---|---|---|
| Lire tous les réglages | `rank-math/get-settings` | La source de vérité : modules actifs, slugs de post types, clés `pt_<pt>_*`, `local_business_type`, `opening_hours` |
| Identité Knowledge Graph | `rank-math/set-website-identity` | `knowledgegraph_type`, `website_name`, `knowledgegraph_name`, `local_business_type` — **pas** l'adresse ni le téléphone (voir §4) |
| Activer / désactiver un module | `rank-math/set-module-status` | `{modules: {"local-seo": true, "rich-snippet": true}}`. Refuse un module dont les dépendances ne sont pas satisfaites |
| Schema par défaut d'un post type | `rank-math/set-post-type-seo-settings` | `{post_types: {page: {default_rich_snippet: "off"}}}` — remplace le patch d'option de l'ancienne procédure |
| Robots global, séparateur de titre | `rank-math/set-global-seo-settings` | |
| Sitemap : inclusions, images | `rank-math/set-sitemap-settings` | Exige le module `sitemap` actif |
| Fil d'Ariane | `rank-math/set-breadcrumb-settings` | |
| SEO de l'accueil | `rank-math/set-homepage-seo` | |
| robots.txt | `rank-math/get-robots-txt` | Renvoie aussi `public` (la visibilité moteurs) et `exists` (fichier physique) |
| llms.txt | `rank-math/get-llms-txt` | `enabled`, `url`, et le contenu généré |
| Metas d'un contenu (lecture) | `rank-math/get-post-seo-meta` | Avec le score SEO. **Écriture : voir §4** |
| Schema d'un contenu (lecture) | `rank-math/get-post-schema` | |
| Audit SEO du plugin | `rank-math/audit-site-seo` | Score + constats avec `test_id` et pistes de correction |
| Correction automatique d'un constat | `rank-math/fix-site-seo` | `test_id` parmi `blog_public`, `sitemaps`, `schema`, `noindex`, `robots_txt`, `opengraph`, `permalink_structure`, `site_description`, `focus_keywords`, `post_titles` |
| Analyse d'un contenu | `rank-math/analyze-post-content` | |

`rank-math/audit-site-seo` et `fix-site-seo` sont des raccourcis utiles mais **pas un substitut**
aux étapes 6 à 8 : l'audit du plugin ne juge ni le SEO local, ni la justesse d'un type de schema,
ni la qualité d'un alt. Lance-le, traite ses constats, continue la procédure.

### Niveau 3 — `php-eval`, pour tout le reste

```
mcp-adapter-execute-ability
  ability_name: "agent-connector-for-wp/php-eval"
  parameters:   { code: "<du PHP>" }
```

Le PHP s'exécute **dans le WordPress chargé** : `get_option`, `update_option`, `get_post_meta`,
`update_post_meta`, `$wpdb`, `wp_insert_attachment`, tout l'API est là. C'est ce qui remplace
`wp eval-file` de l'ancienne procédure — voir §3 pour les idiomes et §4 pour la liste de ce qui
n'a que ce chemin.

### Niveau 4 — Node en local, pour ce qui doit être vu de l'extérieur

Deux choses seulement échappent au serveur, parce qu'elles supposent un navigateur ou un regard
extérieur au site :

- `scripts/front-audit.mjs` — Playwright : le rendu à 375/768/1024/1440 px, les débordements, les
  captures. **Optionnel** : si Playwright n'est pas installé, le rendu HTML de
  `breakdance-preview-post` couvre les alt, les titres, les liens, le JSON-LD et les metas ; seul
  le jugement visuel du responsive manque, et il part au rapport comme « non vérifié ».
- `scripts/google-check.mjs` — `fetch` sur `robots.txt`, le sitemap et le statut HTTP des liens.

Aucun des deux n'écrit sur le site.

---

## §3. Les idiomes `php-eval`

### Ce que renvoie l'ability

```json
{ "output": "<tout ce que le code a echo'é>",
  "result": <la valeur return'ée, normalisée en JSON>,
  "result_type": "array",
  "exception": null }
```

Les charges utiles de `scripts/php/` finissent par `echo wp_json_encode(...)` : leur sortie est
donc dans **`output`**, à parser en JSON. Un `return` arrive dans `result`, déjà structuré — c'est
le plus pratique pour du code court écrit sur le moment.

**Regarde toujours `exception`.** Une charge qui échoue renvoie `output: ""` et une exception
renseignée ; prendre un `output` vide pour un résultat vide est l'erreur qui fait écrire « 0 image
sans alt » sur un site qui en a quarante.

### Écrire du PHP à la volée

Pour une lecture ponctuelle, écris le PHP directement et termine par `return` :

```php
return array(
  'blog_public' => get_option('blog_public'),
  'site_icon'   => (int) get_option('site_icon'),
  'admin_email' => get_option('admin_email'),
);
```

### Appeler une charge utile de `scripts/php/`

Chaque fichier lit son entrée dans `$args[0]`, une chaîne JSON. Pour l'exécuter, le `code` que tu
envoies est la ligne d'entrée suivie du corps du fichier, sans son `<?php` :

```php
$args = array('{"dry_run":true,"items":[…]}');
<corps du fichier>
```

C'est tout. **N'installe pas les charges sur le serveur** — ni par `file-write` dans `uploads`, ni
ailleurs. Un fichier `.php` déposé sous `wp-content/uploads/` est le marqueur le plus classique d'un
site compromis : les scanners de sécurité le remontent, certains hébergeurs le suppriment ou bloquent
le compte, et le gain serait nul puisqu'il faut de toute façon avoir le contenu du fichier sous la
main pour l'écrire. Les charges `apply-*` et `fix-*` se lancent deux fois (passe d'essai puis
application) : envoie-les deux fois, en ne changeant que `"dry_run"`.

### Règles qui évitent les dégâts

- **`wp_slash` avant toute écriture** de chaîne en base : `update_post_meta` et `wp_update_post`
  passent par `wp_unslash`. Les charges utiles le font déjà ; le PHP que tu écris à la volée doit
  le faire aussi.
- **Ne renvoie jamais un gros blob.** Un `breakdance_data` complet fait des centaines de kilooctets.
  Renvoie des identifiants, des compteurs, des chemins JSON, des extraits — pas l'arbre.
- **Pas de `exit`, pas de `STDERR`.** On est en contexte web : `exit` tue la requête sans rien
  renvoyer et `STDERR` n'existe pas. Un échec se signale par `return array('error' => '…')`.
- **Jamais la balise d'ouverture PHP dans le `code`.** Le corps du fichier s'envoie sans elle, et
  elle ne doit pas non plus apparaître dans une chaîne ou un commentaire : le pare-feu de
  l'hébergeur y voit une injection de code et rejette la requête (voir §5). Si une charge doit
  vraiment manipuler cette chaîne, construis-la par concaténation.
- **La passe d'essai est sûre par défaut.** Les charges `bd-text-replace`, `apply-forms`,
  `apply-schema` et `fix-hardcoded-urls` lisent `dry_run` (et acceptent `dry` comme alias). **Clé
  absente ou mal orthographiée = passe d'essai** : il faut un `"dry_run": false` explicite pour
  écrire. Écris donc toujours la séquence en deux appels — `true`, tu lis le diff `from` → `to`,
  puis `false`. Une faute de frappe ne peut plus appliquer une modification par accident.
- **Une écriture à la fois, et qui se relit.** Écris, puis relis la valeur écrite dans le même
  appel et renvoie-la : c'est la seule preuve que l'écriture a pris.

---

## §4. Ce qui n'a que le chemin `php-eval`

Aucune ability ne couvre ces cas. C'est la liste à connaître, parce que chercher une ability qui
n'existe pas fait perdre plus de temps qu'écrire les six lignes de PHP.

| Besoin | Le PHP |
|---|---|
| **Fiche Local SEO** : adresse, téléphone, email, horaires, zone | Patch de l'option sérialisée `rank-math-options-titles`, clé par clé. `set-website-identity` ne couvre que l'identité Knowledge Graph. Voir `rankmath.md` |
| **Metas Rank Math d'un contenu** (écriture) | `update_post_meta($id, 'rank_math_title'|'rank_math_description'|'rank_math_focus_keyword', …)`. La lecture a son ability, l'écriture non |
| **Schema d'un contenu** (écriture) | `update_post_meta($id, 'rank_math_schema_<Type>', …)` |
| **Alt, légende, description d'un média** | `update_post_meta($id, '_wp_attachment_image_alt', …)`, `post_excerpt`, `post_content` |
| **Options WordPress** : `blog_public`, `site_icon`, `admin_email`, `page_on_front` | `get_option` / `update_option` |
| **Plugins** : état, activation | `get_option('active_plugins')`, `activate_plugin()` |
| **Réglages d'un plugin tiers** (werocket tools, Independent Analytics, SMTP) | `get_option` / `update_option` sur sa clé, à découvrir |
| **Balayage de la base** (liens en dur, usages de médias) | `$wpdb` direct |
| **Favicon** | GD (`imagecreatetruecolor`) puis `wp_insert_attachment` ; `Imagick` est souvent absent |

Pour l'arbre Breakdance, préfère malgré tout le niveau 1 : `breakdance-edit-post` sait ce qu'il
écrit. Les charges `php/bd-text-replace.php` et `php/apply-forms.php` restent utiles pour un
remplacement de texte exact en masse et pour écrire une propriété par chemin JSON — deux choses que
les outils natifs ne font pas en une passe.

---

## §5. Les contraintes de l'hôte — mesurées, pas supposées

Sur l'hébergement type de ces sites :

- **WP-CLI n'est pas installé.** L'ability `agent-connector-for-wp/wp-cli` existe mais répond
  « the `wp` binary was not found ». Ne construis rien dessus.
- **`exec`, `shell_exec`, `proc_open`, `popen`, `system` sont dans `disable_functions`.** Les
  abilities `shell-exec` et `process-exec` répondent « proc_open() is disabled on this server ».
  Il n'y a donc **ni `mysqldump`, ni commande shell d'aucune sorte**.
- **`Imagick` est souvent absent**, `GD` présent.
- Le dossier `uploads` est accessible en écriture, et `file-read` / `file-write` / `file-list`
  fonctionnent.

### Le pare-feu applicatif, et le piège qu'il tend

Un WAF (openresty/ModSecurity) filtre les requêtes. Mesuré sur un site réel : **la chaîne
d'ouverture PHP (`<` suivi de `?php`), où qu'elle apparaisse dans le `code` — dans une chaîne, dans
un commentaire — fait rejeter l'appel avec un `406 Not Acceptable`.** Le message n'a alors plus rien
à voir avec ton PHP, ce qui rend le diagnostic déroutant :

```
MCP error -32603: WordPress API error (406): <html><head><title>406 Not Acceptable</title>…
```

Ce qui **passe**, vérifié : les accents et l'UTF-8, les charges de 14 Ko, le SQL et même des
signatures d'injection classiques (`UNION SELECT`, `-- `), `<script>` et le HTML inline d'un arbre
Breakdance. Ce n'est donc ni une question de taille, ni de jeu de caractères, ni de SQL.

La règle pratique : **envoie le corps d'une charge sans sa balise d'ouverture, et ne laisse cette
chaîne nulle part dans le texte envoyé.** `package-skill.sh` refuse d'empaqueter une charge qui la
contiendrait ailleurs qu'à la première ligne, justement pour que ce piège ne revienne pas.

Devant un `406`, ne cherche pas l'erreur dans ta logique : cherche ce que le pare-feu a pu prendre
pour du code, réduis la charge par bissection, et note dans le rapport si un hôte s'avère plus
sévère que celui-ci.

Vérifie-le au démarrage plutôt que de le croire — un hôte peut être plus permissif :

```php
return array(
  'disabled'    => ini_get('disable_functions'),
  'imagick'     => class_exists('Imagick'),
  'gd'          => function_exists('imagecreatetruecolor'),
  'uploads_ok'  => is_writable(wp_upload_dir()['basedir']),
);
```

---

## §6. La sauvegarde : un journal, pas un dump

L'ancienne procédure exigeait `wp db export` avant toute écriture. **Ce n'est plus possible** : ni
WP-CLI, ni `mysqldump`, ni shell. Il faut autre chose, et ne rien mettre à la place n'est pas une
option — cette procédure écrit dans des structures de plugins.

Ce qui la remplace : **un journal d'annulation**, `.we-finalise/journal.jsonl`, en local. Avant
chaque écriture, tu relis la valeur actuelle et tu l'inscris dans le journal ; ensuite tu écris.
Une ligne JSON par écriture :

```json
{"ts":"2026-09-15T14:02:11Z","etape":3,"cible":"postmeta","post_id":412,"cle":"_wp_attachment_image_alt","from":"","to":"Façade du cabinet, rue Victor-Hugo à Lyon"}
{"ts":"2026-09-15T14:07:45Z","etape":6,"cible":"option","cle":"rank-math-options-titles","chemin":"email","from":"agence@werocket.fr","to":"contact@client.fr"}
```

Les charges `apply-*` et `fix-*` renvoient déjà leurs couples `from` → `to` : **c'est cette sortie
que tu écris dans le journal**, telle quelle, après chaque application. Tu n'as rien à relire en
plus.

Pourquoi c'est mieux ici qu'un dump, et pas seulement faute de mieux : ce qu'écrit cette procédure,
ce sont des metas, des feuilles de texte et des clés d'options, une à une. Annuler un alt maladroit
avec un dump de la base entière, c'est perdre au passage tout ce qui a été fait de bon depuis. Le
journal permet de revenir en arrière **écriture par écriture**, et il se lit.

Ce que le journal ne couvre pas, et qu'il faut dire dans le rapport : il ne protège pas d'un
plantage de l'hébergeur. Si le site a un plugin de sauvegarde (WP STAGING, UpdraftPlus…), note dans
le rapport qu'une sauvegarde complète depuis ce plugin, avant le lancement, reste la bonne pratique
— et lance-la si tu peux la déclencher par `php-eval`.

---

## §7. Après une écriture : le cache

`wp cache flush` n'existe plus. L'équivalent, par `php-eval` :

```php
wp_cache_flush();
if (function_exists('rocket_clean_domain'))      rocket_clean_domain();      // WP Rocket
if (class_exists('LiteSpeed\Purge'))             do_action('litespeed_purge_all');
if (function_exists('w3tc_flush_all'))           w3tc_flush_all();
if (function_exists('wp_cache_clear_cache'))     wp_cache_clear_cache();     // WP Super Cache
if (class_exists('\Breakdance\Render\Cache'))    do_action('breakdance_clear_cache');
return 'cache purgé';
```

Après une écriture dans les options Rank Math, ajoute `flush_rewrite_rules();` — le sitemap et
`llms.txt` en dépendent.

Regarde `active_plugins` (renvoyé par `breakdance-site-info`) pour savoir quel plugin de cache est
en place, et purge celui-là.

---

## §8. Ce que le MCP ne fait pas, et ne fera pas

À inscrire au rapport, pas à contourner :

- **Le jugement visuel.** Un texte tronqué, une image écrasée, un logotype illisible en favicon :
  il faut regarder. `front-audit.mjs` fournit les captures, toi tu les regardes.
- **La structure d'une page.** Réorganiser des sections, corriger une hiérarchie de titres bancale :
  ça se fait dans le builder, par un humain.
- **Ce que seul le client sait.** Le domaine de production quand le site est sur une préprod, les
  horaires absents du site, les vrais avis, les tarifs, l'adresse de réception des formulaires.
  Ça se demande — groupé, au rapport — et ça ne s'invente jamais.
- **Ce qui vit hors du site.** Search Console, Google Business Profile, les DNS, SPF/DKIM, la boîte
  `formulaire@<domaine>`.

Et un piège propre à ce canal : **le mode maintenance**. S'il est actif, toute URL publique renvoie
la page de maintenance aux visiteurs déconnectés — donc à Playwright, à `google-check.mjs` et à
`curl`. Un audit front lancé sans le savoir décrit la page de maintenance et conclut que le site
est vide. `breakdance-get-maintenance-mode` en premier, toujours.
