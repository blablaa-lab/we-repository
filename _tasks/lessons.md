# Leçons

Règles tirées d'erreurs réellement commises. À relire en début de session.

## Scripts shell livrés dans une skill

- **macOS livre bash 3.2**, pas bash 4. Interdits : `${var,,}` / `${var^^}`, `declare -A`,
  `mapfile`, `readarray`, `&>>`. Utiliser `tr` pour la casse et des variables préfixées pour
  simuler un tableau associatif. Vérifier avec `bash --version` avant d'écrire, et toujours
  `bash -n` sur chaque script.
- Un script destiné à un site client doit être testé **par un harnais local** avant livraison :
  stubber les fonctions WordPress (`get_option`, `get_post_meta`…) et faire tourner le PHP sur un
  faux jeu de données. Un `php -l` ne prouve que la syntaxe, pas la logique — les deux fois où j'ai
  écrit un harnais, il a révélé des faux positifs que la relecture n'avait pas vus.

## Structures de données appartenant à un plugin

- Ne pas présumer la structure interne d'un builder ou d'un plugin (arbre du FormBuilder
  Breakdance, clés d'options Rank Math) : les noms de propriétés changent d'une version à l'autre.
  **Faire découvrir les chemins par le script**, les rendre dans l'audit, et écrire ensuite au
  chemin exact. Un script qui refuse un chemin inexistant vaut mieux qu'un script qui crée une clé
  que le plugin ne lira jamais.
- Toute écriture dans une structure appartenant à un plugin passe par un `--dry-run` qui rend les
  couples `from` → `to`, et refuse plutôt que de deviner.

## Validation d'emails et de domaines

- `Nom <adresse@domaine>` est un format d'expéditeur **légitime** (Contact Form 7 l'écrit toujours
  ainsi). Extraire l'adresse entre `<>` avant toute comparaison, sinon on signale une anomalie qui
  n'existe pas.
- Dériver un domaine d'une URL de staging est un piège : `client.werocket.ovh` donne
  `werocket.ovh`, soit l'adresse de l'agence. Toujours partir de l'URL de **production**, et
  avertir bruyamment quand un sous-domaine a été réduit.

## Skills packagées

- **Les sources ne doivent jamais vivre uniquement dans le bundle.** Tant que `references/` et
  `scripts/` n'existaient que dans le zip, le `SKILL.md` chargé par Claude Code en était une copie
  à recopier à la main : les deux divergeaient en silence. Corrigé structurellement — l'arborescence
  `.claude/skills/we-finalise/` est la source, et `./package-skill.sh` produit le bundle après
  vérification, en refusant de l'écrire s'il ne correspond pas aux sources. Un invariant tenu par
  un script vaut mieux qu'une consigne de vigilance.
- Après une renumérotation de sections, vérifier programmatiquement tous les renvois
  `fichier.md §N` : un renvoi cassé est invisible à la relecture. `package-skill.sh` le fait.

## Ne pas documenter l'incapacité d'un canal sans l'avoir interrogé

- `SKILL.md` affirmait noir sur blanc qu'un serveur MCP WordPress ne pouvait pas remplacer WP-CLI
  pour trois choses (postmeta `breakdance_data`, options sérialisées Rank Math, export de base),
  « puisqu'il passe par la REST API ». **Deux des trois étaient fausses** : le serveur exposait
  134 abilities, dont `breakdance/get-post-tree`, `breakdance/edit-post` et surtout
  `agent-connector-for-wp/php-eval`, qui exécute du PHP dans le WordPress chargé. L'affirmation
  avait été déduite de la *catégorie* de l'outil (« c'est de la REST API ») au lieu d'être mesurée.
  **Règle : avant d'écrire qu'un canal ne sait pas faire quelque chose, appeler `tools/list` et
  `discover-abilities` et lire la réponse.** Le coût de la vérification était de deux minutes ;
  celui de l'erreur, une procédure entière bâtie sur du SSH inutile.
- Le corollaire tient aussi : ce qu'un catalogue annonce n'est pas ce que l'hôte permet. L'ability
  `wp-cli` existait mais le binaire `wp` était absent ; `shell-exec` existait mais `proc_open` était
  dans `disable_functions`. **Tester l'appel, pas seulement lire le schéma.**

## Ce qu'on demande à l'utilisateur

- Un champ de configuration réclamé au lancement est une dette : sur les vingt champs du
  `we-finalise.json`, **deux seulement n'étaient pas dans le site** (le domaine de production quand
  le site est sur une préprod, et les horaires quand ils n'y figurent pas). Les dix-huit autres
  étaient demandés faute d'avoir cherché le canal pour aller les lire. Avant d'ajouter une question
  à une procédure, chercher où la réponse est déjà écrite.
- Une question qui bloque et une question qui attend dans un rapport n'ont pas le même coût. Par
  défaut : **collecter, continuer, regrouper à la fin**. Ne bloquer que si continuer serait
  dangereux — écrire `formulaire@<domaine de l'agence>` parce qu'on n'a pas le domaine de prod, par
  exemple : là, on n'écrit rien et on le dit.
