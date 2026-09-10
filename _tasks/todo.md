# Tâche — we-finalise : formulaires + modules Rank Math remplis

## Objectif
1. Vérifier tous les formulaires du site : destinataire présent et `From email`
   au format `formulaire@<domaine de prod>`, domaine dérivé par analyse de l'URL.
2. Poser comme règle que les trois modules Rank Math (LLMs.txt, SEO Local, Schema)
   sont *remplis* et non seulement activés, à partir d'infos trouvées sur le site.

## Plan

- [x] 1. `scripts/php/forms-audit.php` + `scripts/forms-audit.sh`
      Découvre tous les formulaires : nœuds Breakdance (recherche par type + chemins
      JSON des valeurs email), plugins de formulaires tiers actifs, config d'envoi
      (plugin SMTP, `admin_email`). Contrôle destinataire non vide et conformité du
      `From` à `formulaire@<domaine>`. → `.we-finalise/forms.json`
- [x] 2. `scripts/php/apply-forms.php` + `scripts/apply-forms.sh`
      Écriture par chemin JSON dans `breakdance_data` (set d'une valeur à un chemin
      précis, pas un remplacement de texte), `--dry-run` d'abord.
- [x] 3. `scripts/site-info.mjs`
      Extraction des infos client depuis le texte des pages déjà capturé par
      front-audit : emails, téléphones, adresses, SIRET, horaires, réseaux.
      → `.we-finalise/site-info.json`. Alimente SEO Local et détecte les écarts NAP.
- [x] 4. `references/formulaires.md` (nouveau)
      Doctrine : destinataire obligatoire, `From` = `formulaire@domaine` et pourquoi,
      Reply-To, dérivation du domaine, test d'envoi réel, délivrabilité (SPF/DKIM).
- [x] 5. `references/rankmath.md`
      Critères d'acceptation par module : ce qui fait qu'un module est « rempli ».
- [x] 6. `SKILL.md`
      Nouvelle règle sur les modules Rank Math remplis. Étape 6 : phase d'extraction
      des infos depuis le site. Nouvelle étape 8 « Formulaires ». Renumérotation
      8→9 (favicon), 9→10 (analytics), 10→11 (rapport). Config : ajout de `prod_url`.
- [x] 7. `references/report-template.md` : section formulaires
- [x] 8. Question MCP : documenter ce que le MCP WordPress peut et ne peut pas faire
      par rapport à WP-CLI, pour trancher la méthode
- [x] 9. Re-packager le bundle + vérifications (lint, références croisées, intégrité)

## Bilan

Tout appliqué et vérifié. La skill passe de 11 à 12 étapes (0 à 11).

**Formulaires (nouvelle étape 8)**
- `scripts/php/forms-audit.php` + `scripts/forms-audit.sh` : audit par **découverte de chemins
  JSON** plutôt que par structure présumée — les noms de propriétés du FormBuilder Breakdance
  varient selon les versions. Couvre aussi CF7 (postmeta `_mail`), WPForms (JSON du
  `post_content`), les shortcodes posés en page, le plugin SMTP et `admin_email`.
  Anomalies : destinataire manquant, destinataire dynamique (`[votre-email]` dans le `To`),
  `from` non conforme ou absent, `admin_email` suspect.
- `scripts/php/apply-forms.php` + `scripts/apply-forms.sh` : écriture au chemin JSON exact,
  refus si le chemin n'existe pas, si la cible est une structure, ou si la valeur n'est pas une
  adresse valide. Gère aussi CF7 et l'expéditeur global WP Mail SMTP.
- `references/formulaires.md` : la règle (`From` = `formulaire@<domaine>`, `Reply-To` = visiteur,
  jamais le visiteur en `From` — DMARC), la dérivation du domaine, l'ordre de correction
  (SMTP d'abord), le test d'envoi réel, la délivrabilité, les critères d'acceptation.
- Config : ajout de `prod_url`, sans quoi le `From` porterait le domaine du staging.

**Modules Rank Math remplis (règle + étape 6)**
- Troisième règle qui prime : « un réglage activé n'est pas un réglage rempli ».
- `rankmath.md` §0 : critères d'acceptation cochables pour SEO Local, Schema et LLMs.txt.
- `scripts/site-info.mjs` : extrait du texte déjà capturé par front-audit la raison sociale, les
  téléphones, adresses, CP/ville, SIRET, TVA, RCS, capital, horaires et emails — avec les pages
  d'origine, le nombre d'occurrences (pages légales et contact comptées double), les **conflits**
  NAP et les emails hors domaine.

**Vérifications faites**
- Logique d'audit testée sur un faux arbre Breakdance via harnais PHP : 2 formulaires détectés,
  chemins exacts, destinataire vide et `from` de staging remontés.
- Deux faux positifs trouvés et corrigés : `Nom <email>` (format légitime de CF7) déclaré non
  conforme ; `[field_email]` en destinataire désormais signalé comme `destinataire_dynamique`.
- `apply-forms` testé en dry-run et en écriture réelle : les trois refus fonctionnent, et
  l'écriture ne touche que la feuille visée (structure et `email_to` voisins intacts).
- `site-info.mjs` testé sur un rapport front réaliste : trois faux positifs trouvés et corrigés
  (`00019 SIREN` pris pour un code postal, `RCS Lyon Téléphone`, mot « capital » dans la valeur).
  Le téléphone divergent entre pied de page et mentions légales est bien remonté en conflit,
  l'email d'agence bien signalé.
- **Bug de portabilité corrigé** : `${host,,}` exigeait bash 4, or macOS livre bash 3.2
  (vérifié : 3.2.57). Remplacé par `tr`. Dérivation du domaine testée sur 9 URL réelles.
- Piège identifié et traité : un staging du type `client.werocket.ovh` dérivait
  `formulaire@werocket.ovh`, l'adresse de l'agence. Avertissement explicite ajouté.
- Numérotation de `schema.md` alignée sur la convention `## N.` ; les 9 renvois `fichier.md §N`
  vérifiés programmatiquement, tous valides.
- Lint complet (8 shell, 7 PHP, 2 mjs), 20 fichiers cités tous présents, bundle ré-extrait
  identique à la source, `SKILL.md` synchronisé.

**Question MCP — répondu dans la skill**
Le MCP configuré sur les sites est `@automattic/mcp-wordpress-remote` (MCP WordPress générique
via le plugin MCP Adapter), pas un MCP Breakdance. Il passe par la REST API, donc il ne peut pas
remplacer WP-CLI pour cette procédure : `breakdance_data` est une postmeta non exposée en REST,
les options Rank Math sont sérialisées et s'écrivent clé par clé avec `wp option patch`, et
l'export de base de l'étape 0 n'a pas d'équivalent. Documenté en tête de `SKILL.md` comme
complément d'exploration, WP-CLI restant la voie d'écriture.

**Non vérifié**
Les scripts n'ont pas tourné contre un WordPress réel : aucun site joignable dans cette session,
et les MCP WordPress ne sont configurés que sur les projets de site, pas sur ce starter. Les
structures dépendantes du site (arbre du FormBuilder Breakdance, clés WPForms/FluentSMTP) sont
donc traitées par découverte plutôt que par constante, mais restent à confirmer au premier
passage client.
