# Tâche — we-finalise : recommandations SEO Google

## Objectif
Intégrer les recommandations du guide de démarrage SEO de Google
(developers.google.com/search/docs/fundamentals/seo-starter-guide) sous forme de contrôles
vérifiables à la finalisation, et poser la règle : corriger ce qui est corrigeable, poser
la question quand une information manque.

## Plan

- [x] 1. Récupérer et analyser le guide Google (WebFetch) — recommandations, interdits,
      et la liste de ce dont Google dit de ne pas se préoccuper
- [x] 2. Étendre `scripts/front-audit.mjs` : canonique, meta robots/keywords/viewport, lang,
      hreflang, tous les `Hn`, types JSON-LD, contenu mixte, dimensions naturelles vs rendues
      des images, tous les liens avec texte et zone
- [x] 3. `scripts/google-check.mjs` : applique les règles Google sur le rapport front,
      interroge robots.txt et le sitemap, classe en bloquant / à corriger / à vérifier / info
      et attribue à chaque constat sa résolution (auto / humain / question)
- [x] 4. `references/google-seo.md` : les contrôles vérifiables, et la section « ce sur quoi
      Google dit de ne pas perdre de temps »
- [x] 5. `SKILL.md` : règle 4 (corriger vs demander), nouvelle étape 8, renumérotation
      8→9 formulaires, 9→10 favicon, 10→11 analytics, 11→12 rapport
- [x] 6. Aligner `redaction-seo-local-geo.md` sur les précisions de Google (Hn, E-E-A-T)
- [x] 7. `references/report-template.md` : section conformité + vérifications de mise en ligne
- [x] 8. Re-packager le bundle + vérifications

## Bilan

La skill passe de 12 à 13 étapes (0 à 12) et de trois à quatre règles cadres.

**Règle 4 — corriger ou demander**
« Tu corriges ce qui est corrigeable ; tu demandes ce qui te manque. » Toute correction à portée
se fait immédiatement plutôt que d'aller dans une liste de suggestions. Trois exceptions
seulement : textes des pages clés et légales, ce qui exige le builder ou un jugement visuel, et
ce qui dépend d'une information absente — auquel cas la question est posée, **regroupée en une
seule fois**, et tout ce qui n'en dépend pas continue pendant ce temps. Les deux sorties
interdites sont nommées : inventer une valeur pour éviter de demander, ou bloquer l'étape en
attendant une réponse.

**Étape 8 — conformité Google**
`google-check.mjs` contrôle : indexabilité (`noindex`, `Disallow: /`, CSS/JS bloqués, sitemap),
URL et canoniques, doublons de title/description, titles génériques, descriptions manquantes,
`meta keywords` à supprimer, structure des `Hn`, ancres vagues et liens vides, pages orphelines,
liens absolus vers le staging (agrégés), images étirées ou surdimensionnées, noms de fichiers non
parlants, HTTPS et contenu mixte, viewport, `lang`, JSON-LD invalide ou entités d'entreprise
concurrentes. Chaque constat porte sa résolution : `auto`, `humain`, ou `question`.

**La partie souvent négligée : ce que Google dit de NE PAS travailler**
`google-seo.md` la documente avec les formulations du guide — `meta keywords` inutile, aucune
longueur de contenu « magique », ordre des `Hn` sans effet sur le classement, mots-clés dans le
domaine « pratiquement aucun effet », pas de pénalité pour duplication interne, et E-E-A-T qui
n'est **pas** un facteur de classement direct. Deux passages de `redaction-seo-local-geo.md` ont
été corrigés en conséquence : la consigne « un seul H1 » est désormais justifiée par
l'accessibilité et non par le classement, et E-E-A-T est présenté comme un cadre d'évaluation.

**Vérifications faites**
- Extraction des types JSON-LD testée sur un `@graph` Rank Math réaliste avec `@type` multiples
  et entités imbriquées : les six types sont bien remontés.
- `google-check.mjs` exécuté contre un faux site servi en local (robots.txt avec
  `Disallow: /wp-content/`, sitemap sur un autre domaine, page en `noindex`, titles en doublon,
  `meta keywords`, ancre « En savoir plus », lien `#`, image 400px étirée sur 1200px, contenu
  mixte, viewport et `lang` absents) : 27 constats, tous justes.
- **Deux bugs trouvés et corrigés à ce test** : les liens internes du staging produisaient un
  constat par lien (inexploitable sur un vrai site, un menu en génère des centaines) — désormais
  agrégés en un seul constat avec le compte ; et l'URL du sitemap déclarée dans robots.txt était
  mal ramenée sur le site audité, ce qui la faisait passer pour injoignable.
- Lint complet (8 shell, 7 PHP, 3 mjs), 22 fichiers cités tous présents, 11 renvois `§N`
  vérifiés, `description` du frontmatter ramenée sous la limite (969 caractères après un premier
  jet à 1031), bundle ré-extrait identique à la source.

**Non vérifié**
`google-check.mjs` n'a pas tourné contre un vrai site WordPress : les seuils (image étirée à 80 %,
page pauvre à 200 caractères) sont raisonnés, pas calibrés sur du réel. À ajuster au premier
passage client si le bruit est trop élevé.
