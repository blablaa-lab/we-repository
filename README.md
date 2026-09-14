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
_docs/
  prd.md                           Amorce du PRD, à remplir au démarrage
  architecture.md                  Amorce du document d'architecture
_tasks/
  todo.md                          Plan cochable de la tâche en cours + bilan
  lessons.md                       Leçons tirées des erreurs réellement commises
.claude/
  commands/
    session-end.md                 Commande /session-end
  skills/
    we-finalise/
      SKILL.md                     La procédure — le fichier chargé par Claude Code
      we-finalise.skill            Le bundle distribuable (SKILL.md + scripts/ + references/)
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

| Commande | Effet |
|---|---|
| `/we-finalise` | Lance la procédure de finalisation (voir plus bas). Se déclenche aussi sur « finaliser », « mettre en ligne », « checklist de fin de projet », ou une étape isolée (« fais juste les metas Rank Math »). |
| `/session-end` | Clôture la session : résumé, leçons ajoutées à `_tasks/lessons.md`, tâches cochées dans `_tasks/todo.md`. |

### Maintenance du bundle de la skill

`scripts/` et `references/` n'existent **que dans `we-finalise.skill`** (une archive zip) ; le dépôt
ne versionne à côté que le `SKILL.md`, parce que c'est ce fichier-là que Claude Code charge. Les deux
copies du `SKILL.md` doivent rester identiques.

```bash
# 1. Décompresser le bundle pour éditer scripts/ et references/
unzip -o .claude/skills/we-finalise/we-finalise.skill -d /tmp/we-finalise-src

# 2. Éditer /tmp/we-finalise-src/we-finalise/{SKILL.md,scripts/,references/}

# 3. Re-packager (le zip doit contenir le dossier we-finalise/ à sa racine)
(cd /tmp/we-finalise-src && rm -f we-finalise.skill \
  && zip -qr we-finalise.skill we-finalise -x '*.DS_Store')
cp /tmp/we-finalise-src/we-finalise.skill .claude/skills/we-finalise/

# 4. Resynchroniser le SKILL.md du dépôt
cp /tmp/we-finalise-src/we-finalise/SKILL.md .claude/skills/we-finalise/

# 5. Vérifier que les deux sont bien identiques
unzip -p .claude/skills/we-finalise/we-finalise.skill we-finalise/SKILL.md \
  | diff - .claude/skills/we-finalise/SKILL.md && echo "synchronisés"
```

Sans l'étape 5, la procédure chargée et les scripts livrés divergent silencieusement.

### Prérequis d'un projet qui utilise we-finalise

```bash
wp --info                                     # WP-CLI, avec un alias SSH dans wp-cli.yml
jq --version                                  # jq
node --version                                # Node ≥ 18
magick -version                               # ImageMagick (étape favicon)
npm i -D playwright && npx playwright install chromium   # audit front (étape 4)
```

---

# La skill we-finalise

Checklist de fin de projet exécutée sur un site WordPress construit avec **Breakdance**. Objectif :
un site propre, techniquement prêt et correctement optimisé pour le SEO local, **sans casser un
contenu que le client a validé**.

Accès au site : **WP-CLI via SSH** (`wp @<alias>`) pour les données et les réglages, **Playwright en
local** pour tout ce qui doit être vu côté front. Pas d'admin WP en navigateur. Un serveur MCP
WordPress, s'il y en a un, sert à explorer — pas à écrire : la postmeta `breakdance_data`, les
options sérialisées de Rank Math et l'export de base sont hors de portée de la REST API.

## Les quatre règles

1. **Le contenu visible n'est pas ta propriété.** Les métadonnées invisibles (alt, metas, réglages)
   s'appliquent directement. Les textes des pages clés (`key_pages`) et des pages légales sont
   *proposés dans le rapport*, jamais modifiés.
2. **Un site Breakdance ne stocke pas ses textes dans `post_content`**, mais dans la postmeta
   `breakdance_data` (JSON). Tout passe par `bd-text-replace.sh` — jamais `wp post update`, jamais
   `wp search-replace`.
3. **Un réglage activé n'est pas un réglage rempli.** Local SEO, Schema et LLMs.txt ne comptent que
   quand leurs champs sont renseignés *et* vérifiés sur le rendu.
4. **Corriger ce qui est corrigeable, demander ce qui manque.** Ni inventer une valeur, ni bloquer
   l'étape en attendant : poser les questions groupées et continuer le reste.

## Configuration — `we-finalise.json`

Créé à l'étape 0, à la racine du projet.

```json
{
  "wp_alias": "@staging",
  "site_url": "https://staging.client.fr",
  "prod_url": "https://www.client.fr",
  "client": {
    "name": "Nom commercial",
    "legal_name": "Raison sociale",
    "sector": "ostéopathe",
    "business_type": "Physician",
    "city": "Lyon",
    "zone": ["Lyon", "Villeurbanne"],
    "address": { "street": "", "postal_code": "", "region": "", "country": "FR" },
    "phone": "", "email": "", "opening_hours": [],
    "keywords": ["ostéopathe lyon"],
    "social": { "facebook": "", "instagram": "", "linkedin": "" }
  },
  "key_pages": ["accueil"],
  "legal_pages": ["mentions-legales", "politique-de-confidentialite"],
  "post_types": ["page", "post"],
  "logo_source": ""
}
```

`prod_url` est l'URL de **production** : c'est d'elle que sont dérivés l'expéditeur des formulaires
(`formulaire@<domaine>`) et les URL du schema. La dériver du staging donnerait le domaine de
l'hébergeur ou de l'agence. L'alias doit exister dans le `wp-cli.yml` du projet :

```yaml
@staging:
  ssh: user@host/chemin/vers/wordpress
```

## Les 14 étapes

| # | Étape | Ce qu'elle fait |
|---|---|---|
| 0 | Intake et sauvegarde | Config `we-finalise.json`, test de connexion, **export de la base avant toute écriture** |
| 1 | Inventaire | URL, templates Breakdance, médiathèque, post types présents |
| 2 | Cookies | Plugin *werocket tools* actif et module Cookies activé |
| 3 | Médiathèque | Alt, légende et description de chaque image, en la regardant vraiment |
| 4 | Audit front | Responsive à 375/768/1024/1440 px, alt rendus, liens sociaux, favicon, `<head>`, Hn, liens |
| 5 | SEO local et GEO | Meta title / description / focus keyword, puis contenus selon la grille GEO |
| 6 | Modules Rank Math | Mode avancé, Local SEO rempli depuis les données du site, robots.txt, llms.txt, `blog_public` |
| 7 | Données structurées | Sous-type d'entreprise, mapping CPT → schema, `Service` sur les pages prestation |
| 8 | Recommandations Google | Indexabilité, canoniques, titles, ancres, images, HTTPS, mobile |
| 9 | Formulaires | Un destinataire existe, `From` en `formulaire@<domaine de prod>`, SMTP, test réel |
| 10 | Favicon | Génération depuis le logo, contrôle visuel, `site_icon` |
| 11 | Independent Analytics | Installation, activation, accès du rôle Éditeur |
| 12 | Liens en dur | Toute la base passée au crible, liens internes rendus relatifs |
| 13 | Rapport | `we-finalise-report.md` : le livrable |

**Dépendances** : 4 a besoin de 1 et 3 · 6 a besoin du texte capturé en 4 · 7 a besoin de 1 et 6 ·
8 contrôle 5 à 7 · 9 a besoin de `prod_url` · 12 vient **après toutes les écritures**, puisqu'elle
vérifie aussi ce que les étapes précédentes ont écrit. Une étape demandée seule reste précédée de
l'intake, du backup et de l'inventaire.

## Scripts

Les `.sh` sont des enveloppes : elles exécutent leur homologue de `scripts/php/` dans le WordPress
distant via `_wp-eval.sh` — sauf `make-favicon.sh`, qui travaille en local avant l'import. Les `.mjs`
tournent en local. Les quatre scripts qui réécrivent des structures — `bd-text-replace.sh`,
`apply-schema.sh`, `apply-forms.sh`, `fix-hardcoded-urls.sh` — acceptent `--dry-run` : lire le diff
`from` → `to` avant d'appliquer.

### Audit (ne modifient rien)

| Script | Usage | Sortie |
|---|---|---|
| `list-urls.sh` | `@alias page post [cpt...]` | `.we-finalise/urls.json` |
| `media-audit.sh` | `@alias` | `.we-finalise/media.json` |
| `schema-audit.sh` | `@alias [cpt,cpt2]` | `.we-finalise/schema.json` |
| `forms-audit.sh` | `@alias [--domain client.fr] [--strict]` | `.we-finalise/forms.json` |
| `hardcoded-urls.sh` | `@alias [host1,host2]` | `.we-finalise/hardcoded-urls.json` |
| `front-audit.mjs` | `--urls … [--media …] [--out …] [--viewports 375,768,1024,1440] [--auth user:pass] [--only slug]` | `.we-finalise/front/report.json` + screenshots |
| `site-info.mjs` | `[--in …] [--domain client.fr] [--out …]` | `.we-finalise/site-info.json` |
| `google-check.mjs` | `--site https://staging… [--prod https://www…] [--links]` | `.we-finalise/google-check.json` |

### Écriture

| Script | Usage | Effet |
|---|---|---|
| `apply-media.sh` | `@alias media-updates.json` | Alt / légende / description des attachments |
| `apply-seo-meta.sh` | `@alias seo-meta.json` | Meta title, description, focus keyword Rank Math |
| `bd-text-replace.sh` | `@alias content-updates.json [--dry-run]` | Remplacement exact de texte dans l'arbre Breakdance |
| `apply-schema.sh` | `@alias schema-plan.json [--dry-run]` | Module Schema, défauts par CPT, schemas par contenu |
| `apply-forms.sh` | `@alias forms-updates.json [--dry-run]` | Destinataires et expéditeurs, par chemin JSON |
| `fix-hardcoded-urls.sh` | `@alias [--dry-run] [--mode relative\|swap] [--to-host …] [--classes lien,media] [--include-home]` | Liens de préprod → relatifs (`swap` = bascule DNS faite) |
| `make-favicon.sh` | `@alias <logo.png\|logo.svg\|URL> [--bg "#ffffff"] [--apply]` | Favicon 512×512, import et `site_icon` |

`bd-text-replace.sh` refuse une paire dont le `from` n'apparaît pas exactement une fois dans le post
ciblé. `apply-forms.sh` refuse un chemin inexistant ou une valeur qui n'est pas un email valide.
Après toute écriture dans `breakdance_data` : `wp @alias cache flush` puis purge du plugin de cache.

## Références

Lues par la skill au moment où elles servent, dans `references/` du bundle.

| Fichier | Contenu |
|---|---|
| `rankmath.md` | Options Rank Math en WP-CLI, mode avancé, modules, Local SEO, robots.txt, llms.txt. §0 = critères d'acceptation |
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

- **`.we-finalise/`** — dossier de travail local : JSON intermédiaires et captures d'écran.
  À ajouter au `.gitignore` du projet.
- **`we-finalise-report.md`** — le livrable, à la racine du projet : ce qui a été vérifié, ce qui a
  été modifié, ce qui reste à faire par un humain dans Breakdance, les propositions de contenu en
  attente de validation client, et les bloquants.
- **`we-finalise-backup-<date>.sql`** — l'export de base de l'étape 0, resté sur le serveur.

---

## Démarrer un nouveau projet

Cloner `we-repository` comme point de départ, puis :

1. Mettre à jour ce README avec le contexte du nouveau projet
2. Compléter `CLAUDE.md` avec les commandes de build / test / lint une fois le stack choisi
3. Remplir `_docs/prd.md` et `_docs/architecture.md`
4. Vider `_tasks/todo.md` (il contient la dernière tâche du dépôt de base) et y planifier les
   premières étapes
