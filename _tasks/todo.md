# Tâche — we-finalise : lancement en une commande, exécution via le MCP du projet

## Problème

La skill fonctionne, mais son lancement coûte trop cher à l'utilisateur :

1. **Un fichier de configuration à remplir à la main.** L'étape 0 réclame `we-finalise.json` avec
   une vingtaine de champs (identité client, adresse, horaires, mots-clés, pages clés, réseaux
   sociaux…) avant de faire quoi que ce soit.
2. **Une infrastructure locale à installer.** `wp` (WP-CLI) avec alias SSH, un `wp-cli.yml` par
   projet, `jq`, ImageMagick, Playwright, `ssh`/`scp` en état de marche.
3. **Des questions au fil de l'eau**, et un `wp_alias` qui n'existe que si on l'a créé.

Or l'inventaire montre que **la grande majorité de ces champs est déjà dans le site** : titre,
raison sociale, ville, adresse, téléphone, email, post types, pages légales, logo, favicon, liens
sociaux. On les demandait par défaut de canal pour aller les chercher.

## La découverte qui change la conception

Les dossiers de sites ont chacun un serveur MCP (`@automattic/mcp-wordpress-remote`). J'ai
inventorié ce qu'il expose réellement sur un site en production — **134 abilities**, pas une
simple REST API :

| Famille | Ce qu'elle donne |
|---|---|
| `breakdance/*` (48, exposés comme outils directs) | `get-post-tree`, `edit-post`, `preview-post`, `search-posts`, `set-element-form`, `get-maintenance-mode`, `site-info`, templates, popups… |
| `rank-math/*` (27) | `get-settings`, `set-website-identity`, `set-module-status`, `set-post-type-seo-settings`, `set-sitemap-settings`, `get-robots-txt`, `get-llms-txt`, `audit-site-seo`, `fix-site-seo`, `get-post-seo-meta`, `get-post-schema` |
| `agent-connector-for-wp/*` (11) | `php-eval`, `file-read`/`write`/`list`, `search-media` |
| `scf/*` (48) | post types, taxonomies, champs |

Ce que cela invalide dans la skill actuelle : la ligne 12 de `SKILL.md` affirme qu'un MCP
WordPress ne peut pas remplacer WP-CLI pour la postmeta `breakdance_data`, les options sérialisées
Rank Math et l'export de base. **Les deux premières sont fausses avec ce serveur-là** :
`breakdance-get-post-tree`/`edit-post` traitent l'arbre nativement, et `php-eval` exécute du PHP
dans le WordPress chargé — donc `get_option`, `update_option`, `update_post_meta`, `$wpdb`.

## Deux contraintes de l'hôte, vérifiées et structurantes

Mesurées sur un site réel, pas supposées :

- **WP-CLI n'est pas installé** sur le serveur (`the 'wp' binary was not found`).
- **`exec`, `shell_exec`, `proc_open`, `popen` sont dans `disable_functions`.** Les abilities
  `wp-cli`, `shell-exec` et `process-exec` sont donc inutilisables, et il n'y a ni `mysqldump`.

Conséquences : **`php-eval` est le seul canal d'exécution serveur** — et **l'étape 0 ne peut plus
faire `wp db export`**. La sauvegarde change de nature : un **journal d'annulation**
(`.we-finalise/journal.jsonl`) qui enregistre la valeur précédente *avant* chaque écriture. Pour ce
qu'écrit cette procédure — des metas, des feuilles de texte, des clés d'options — un journal
réversible vaut mieux qu'un dump de 200 Mo qu'on ne restaurera jamais pour annuler un alt.

## Conception retenue

**Canal unique : le serveur MCP du dossier.** Quatre niveaux, dans cet ordre de préférence :

1. Outils Breakdance natifs — l'arbre, les formulaires, le rendu.
2. Abilities Rank Math — les réglages, plutôt que de patcher des options sérialisées à la main.
3. `php-eval` — tout le reste. Les 11 `scripts/php/*.php` existants sont **déjà** du PHP écrit
   pour `wp eval-file` : ils deviennent des charges utiles `php-eval`, logique inchangée.
4. Node local — seulement ce qui doit être vu depuis un navigateur (responsive) ou depuis
   l'extérieur (robots.txt, sitemap). Optionnel, dégradé proprement si absent.

**Zéro question au lancement.** `we-finalise.json` devient un cache produit par la skill, pas une
saisie. Ce qui reste réellement inconnaissable (domaine de prod quand le site est sur une préprod,
horaires absents du site, avis, tarifs) part dans une **liste de questions groupées au rapport** —
la procédure ne s'arrête jamais dessus.

## Plan

- [x] 1. `references/mcp.md` — la référence de transport : comment trouver le serveur du dossier,
      les quatre niveaux, le catalogue des abilities qui servent, les idiomes `php-eval`
      (injection d'entrée, `wp_slash`, retour JSON), les contraintes d'hôte mesurées, le journal
      d'annulation. C'est le fichier qui porte toute la connaissance nouvelle.
- [x] 2. `scripts/php/*.php` — porter les 11 charges utiles PHP : entrée injectée en tête,
      `STDERR`/`exit` remplacés par un retour structuré (interdits en contexte web). Convention
      retenue à l'usage : on garde `$args[0]` et `echo wp_json_encode()`, que `php-eval` capture
      déjà — le portage se réduit au contexte d'exécution. Supprimer les 13 wrappers bash.
- [x] 3. Nouvelle étape 0 — reconnaissance automatique : `breakdance-site-info`,
      `get-maintenance-mode` (piège : le mode maintenance masque tout le front),
      `rank-math/get-settings`, `php-eval` d'inventaire. Remplit le contexte, ouvre le journal.
- [x] 4. `SKILL.md` — réécrire chaque étape sur le nouveau canal, garder l'ordre et les
      dépendances, ajouter la règle « on ne bloque jamais sur une question ».
- [x] 5. Extraction du texte des pages par `php-eval` → `site-info.mjs` n'a plus besoin de
      Playwright. Supprime la dépendance 6 ← 4.
- [x] 6. Mettre à jour les références qui portent encore du WP-CLI : `breakdance.md`,
      `rankmath.md`, `werocket-tools.md`, `liens-en-dur.md`, `independent-analytics.md`,
      `report-template.md`.
- [x] 7. Re-packager le bundle, vérifier : `php -l` sur chaque charge utile, `diff` SKILL.md
      bundle/hors bundle, renvois `§N` intacts, aucun `wp @` résiduel.
- [x] 8. `_tasks/lessons.md` — la leçon : vérifier ce qu'un canal expose avant d'écrire qu'il ne
      sait pas faire.

## Bilan

Le lancement est passé de « remplir un JSON de vingt champs, installer WP-CLI et un alias SSH,
répondre aux questions » à **« on lance, ça part »**. Un seul prérequis reste, et il ne se
contourne pas : le dossier doit avoir son serveur MCP.

### Ce qui a été fait

- **`references/mcp.md`** (nouveau) — la référence de transport : trouver le serveur du dossier,
  les quatre niveaux du canal, le catalogue des abilities utiles, les idiomes `php-eval`, ce qui
  n'a que ce chemin, les contraintes d'hôte, le journal d'annulation, la purge de cache.
- **`SKILL.md`** réécrit — étape 0 devenue « Reconnaissance » sans aucune question, chaque étape
  rebranchée sur le MCP, et une cinquième règle : *on ne bloque jamais sur une question*.
- **12 charges `scripts/php/*.php`** portées vers `php-eval` (`page-text.php` est nouvelle).
  **13 wrappers bash supprimés.**
- **9 références** purgées du WP-CLI, `report-template.md` doté d'une section « Questions au
  client » et d'une ligne « Non vérifié ».
- **`package-skill.sh`** (nouveau) — l'arborescence devient la source, le bundle un produit
  vérifié. Le risque de divergence consigné dans `lessons.md` est supprimé structurellement.
- **`README.md`** remis en accord ; **`lessons.md`** enrichi de trois leçons.

### Cinq problèmes trouvés en exécutant, pas en relisant

Tout a été vérifié contre un site réel par le MCP. Cinq choses ne se voyaient pas à la lecture :

1. **L'arbre Breakdance 3.x est doublement encodé.** `_breakdance_data` décode vers une enveloppe
   `{"tree_json_string":"<JSON de l'arbre>"}`. **Aucune** des cinq charges qui touchent l'arbre ne
   le savait : elles opéraient sur l'enveloppe, ne trouvaient aucune feuille et annonçaient
   « 0 occurrence » sans rien faire — un échec muet. Corrigé par une paire `wf_bd_decode` /
   `wf_bd_encode` qui gère les deux dispositions. C'était le bug le plus grave de la session.
2. **Le pare-feu de l'hébergeur rejette la balise d'ouverture PHP** où qu'elle apparaisse dans la
   charge, avec un `406 Not Acceptable` qui ne parle pas du PHP envoyé. Ce qui passe, en revanche :
   les accents, 14 Ko de code, le SQL, `UNION SELECT`, `<script>`. `package-skill.sh` refuse
   désormais d'empaqueter une charge qui contiendrait cette chaîne hors première ligne.
3. **`dry` contre `dry_run`** — trois charges lisaient `dry_run`, une lisait `dry`, et `SKILL.md`
   annonçait `dry` pour les quatre : envoyer la mauvaise clé **appliquait pour de vrai**. Corrigé
   au-delà de l'alignement : la passe d'essai est maintenant **sûre par défaut**, il faut un
   `"dry_run": false` explicite pour écrire. Vérifié sur les trois cas (clé absente, clé mal
   orthographiée, clé explicite).
4. **Un arbre ne contient pas ce que résolvent les shortcodes.** Sur le site test, les mentions
   légales — là où se trouve le NAP — rendaient 58 caractères depuis l'arbre et 3 291 depuis
   `breakdance-preview-post`. D'où le drapeau `thin` de `page-text.php` et la passe de complément.
5. **WP-CLI n'existe pas sur ces serveurs, et aucune commande shell non plus** (`exec`,
   `shell_exec`, `proc_open` désactivés). D'où la disparition de l'export de base, remplacé par le
   journal d'annulation.

### Ce qui reste ouvert

- Les abilities d'**écriture** (`apply-media`, `apply-seo-meta`, `apply-schema`, `apply-forms`,
  `fix-hardcoded-urls`) n'ont été vérifiées qu'en passe d'essai : je n'ai pas appliqué de
  modification sur un site client en production. À valider sur une vraie préprod.
- Les clés de la fiche **Local SEO** relevées sur un site : `local_address` (tableau),
  `opening_hours` (tableau), `email`, `local_business_type`, `local_seo_contact_page`. La clé du
  téléphone reste à confirmer sur un site où elle est renseignée — elle était absente ici.
- `apply-media.php` et `apply-seo-meta.php` n'ont pas de passe d'essai. Volontaire pour l'instant
  (écritures de champs simples, journalisées), à revoir si l'usage montre le contraire.
