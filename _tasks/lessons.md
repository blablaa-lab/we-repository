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

- `SKILL.md` et le bundle `.skill` (zip) contiennent le même fichier : après toute modification,
  re-zipper **et** recopier le `SKILL.md` à côté, puis vérifier par `diff` que les deux sont
  identiques. Sinon la procédure chargée et les scripts livrés divergent silencieusement.
- Après une renumérotation de sections, vérifier programmatiquement tous les renvois
  `fichier.md §N` : un renvoi cassé est invisible à la relecture.
