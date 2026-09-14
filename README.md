# We-repository

Ce repo est mon dépôt de base personnel, conçu pour initialiser n'importe quel nouveau projet rapidement et avec les bonnes bases.

## Ce qu'il contient

- **CLAUDE.md** — Directives de workflow pour Claude Code : mode planification par défaut, stratégie de sous-agents, boucle d'auto-amélioration, vérification avant clôture
- **_tasks/** — Suivi des tâches (`todo.md` : plan cochable + bilan) et capitalisation des leçons (`lessons.md`, alimenté après chaque correction)
- **_docs/** — `prd.md` et `architecture.md` : amorces de documents à remplir au démarrage du projet
- **.claude/commands/** — Commandes slash du projet. `session-end` clôture une session : résumé, leçons, mise à jour du todo
- **.claude/skills/** — Skills du projet. `we-finalise` : procédure de finalisation d'un site WordPress + Breakdance avant mise en ligne (cookies, médiathèque, responsive, metas et données structurées Rank Math, favicon, analytics, rapport de livraison)

## La skill we-finalise

Deux fichiers côte à côte dans `.claude/skills/we-finalise/` :

- `SKILL.md` — la procédure elle-même, en 11 étapes (0 à 10). C'est le fichier que Claude Code charge et le seul à éditer pour faire évoluer la méthode
- `we-finalise.skill` — le bundle distribuable (zip) qui embarque ce même `SKILL.md` plus `scripts/` (WP-CLI via SSH, audit front Playwright) et `references/` (Rank Math, Breakdance, données structurées, rédaction SEO local / GEO, template de rapport)

**Les deux doivent rester synchronisés.** Après une modification, re-packager le bundle et recopier le `SKILL.md` à côté — sinon la procédure chargée et les scripts livrés divergent.

## Utilisation

Cloner `we-repository` comme point de départ pour un nouveau projet, puis :

1. Mettre à jour ce README avec le contexte du nouveau projet
2. Compléter le CLAUDE.md avec les commandes de build/test/lint une fois le stack choisi
3. Remplir `_docs/prd.md` et `_docs/architecture.md`
4. Vider `_tasks/todo.md` (il contient la dernière tâche du dépôt de base) et y planifier les premières étapes
