# Rédaction SEO local, médias et GEO

Tu rédiges pour des TPE/PME locales (santé, artisanat, commerce, services). Le lecteur cherche un professionnel **près de chez lui** et veut être rassuré vite. Écris en français, sans anglicismes inutiles, avec les espaces insécables avant `:` `;` `!` `?` et les apostrophes typographiques `’`.

## Vocabulaire

- **SEO local** : positionnement sur des requêtes « métier + ville / quartier ». Levier : cohérence NAP, pages qui nomment la zone, fiche Local SEO Rank Math, avis.
- **GEO (Generative Engine Optimization)** : faire en sorte que les moteurs génératifs (Google AI Overviews, ChatGPT, Perplexity, Claude) citent le site quand on leur pose la question que le client résout. Levier : contenu qui répond directement, structuré en questions/réponses, entités nommées, données concrètes, données structurées, llms.txt. Il n'existe pas de « préconisations GEO » officielles de Google : on s'appuie sur ce que Google dit du contenu utile (people-first) et sur ce qu'on observe des citations dans les réponses génératives. Les recommandations officielles vérifiables sont dans `google-seo.md`.

## Médias

**Alt** (`_wp_attachment_image_alt`) — ce qu'un lecteur d'écran dirait, ce que Google Images indexe.
- Décris ce qu'on voit, factuellement, ≤ 125 caractères. Sujet, action, contexte. Pas de « image de », « photo de », « illustration ».
- La ville ou le lieu n'y va que si l'image le montre ou le concerne : « Cabinet d'ostéopathie, salle de soin, Lyon 6e » oui ; « Main qui tient un stylo Lyon » non.
- Une image = un alt : pas de liste de mots-clés, pas de répétition de l'alt sur dix photos.
- Image décorative (forme, texture, séparateur, fond) → alt vide `""` **explicite** dans ton JSON. Inventer un alt à une texture nuit à l'accessibilité.
- Logo → « Logo <Nom commercial> ». Portrait → « <Prénom Nom>, <métier> à <ville> ».

**Légende** (`post_excerpt`) — courte, humaine, visible si le thème l'affiche. Peut être omise pour les visuels de décor. Ne recopie pas l'alt.

**Description** (`post_content` de l'attachment) — 1–2 phrases, utile pour la page attachment et l'indexation : contexte, lieu, ce que ça montre du service.

Exemple — photo d'un praticien manipulant une patiente allongée :
- alt : `Ostéopathe manipulant le dos d’une patiente allongée, cabinet de Villeurbanne`
- légende : `Séance d’ostéopathie au cabinet de Villeurbanne`
- description : `Manipulation douce du dos lors d’une consultation d’ostéopathie à Villeurbanne. Le cabinet accueille adultes, sportifs et femmes enceintes.`

## Meta title et meta description (Rank Math)

**Title** ≤ 60 caractères. Structure : `Mot-clé principal à Ville | Marque` ou `Marque – Mot-clé à Ville` pour l'accueil. Une seule idée. La marque en fin, sauf accueil. Pas de « Bienvenue », pas de « Accueil ».
- Accueil : `Ostéopathe à Lyon 6e – Cabinet Marie Dupont`
- Page service : `Ostéopathie du sport à Lyon | Cabinet Dupont`
- Article : `Mal de dos après le sport : que faire ? | Cabinet Dupont`

**Description** 140–155 caractères. Bénéfice concret + localité + preuve ou différenciant + appel à l'action. C'est une accroche, pas un résumé.
- `Ostéopathe à Lyon 6e, Marie Dupont soulage douleurs de dos, cervicales et troubles du sport. Cabinet accessible, prise de rendez-vous en ligne.`

**Focus keyword** : une requête réelle (`ostéopathe lyon 6`), pas une phrase. Une par page, distincte entre pages : deux pages sur le même mot-clé se cannibalisent.

Vérifie l'unicité : aucun title ni description identiques sur deux pages du site.

## Contenu des pages : grille SEO local + GEO

Analyse chaque page rendue (`front/report.json → text`) sur ces points, et propose des modifications **de texte uniquement** (jamais de structure Breakdance) :

1. **Réponse directe en tête** : le premier bloc de texte doit dire qui, quoi, où, pour qui — en deux phrases. Un moteur génératif cite ce bloc tel quel. « Cabinet d'ostéopathie à Lyon 6e, Marie Dupont accompagne adultes, sportifs et femmes enceintes depuis 2015. » bat « Bienvenue sur notre site ».
2. **Un seul H1**, qui contient le mot-clé principal et la ville — pour la clarté et les lecteurs d'écran : Google précise que l'ordre et le nombre des `Hn` n'affectent pas le classement (voir `google-seo.md`). Les H2 posent la question du visiteur (« Quand consulter un ostéopathe ? », « Combien coûte une séance à Lyon ? ») plutôt que des titres abstraits (« Nos prestations »).
3. **Entités locales nommées** : ville, quartier, communes de la zone (`client.zone`), repères (métro, parking, quartier). Une ou deux fois, naturellement — pas dans chaque paragraphe.
4. **Données concrètes** : prix ou fourchette, durée d'une séance, délais, horaires, années d'expérience, diplômes, nombre de clients/projets. Les moteurs génératifs favorisent les pages qui donnent des chiffres.
5. **FAQ** : si la page a une section questions/réponses, vérifie que les questions sont formulées comme les gens les posent. Si elle n'en a pas et que la page est une page service, propose 3–5 Q/R dans le rapport (ajout de section = structure → humain).
6. **Preuve et confiance** : qui est le professionnel, ses qualifications, adresse complète, moyen de contact visible, mentions d'avis. Une page service anonyme est peu citée. Le cadre E-E-A-T est utile pour guider cette rédaction, mais Google est explicite : ce n'est **pas** un facteur de classement direct — ne le présente pas au client comme un réglage à activer.
7. **Lisibilité** : phrases courtes, un paragraphe = une idée, pas de jargon non expliqué.
8. **Interdits** : bourrage de mots-clés, texte caché, répétition de la ville dans chaque phrase, promesses médicales ou légales non tenables (« guérit », « garanti »), superlatifs sans preuve (« le meilleur »).

Pour chaque modification, écris la paire exacte `from` / `to`. `from` doit être copié depuis le texte rendu au caractère près (apostrophes `’`, espaces insécables). Si un changement dépasse le remplacement d'un texte (ajout de section, nouveau H2, réordonnancement), il va dans le rapport, pas dans `content-updates.json`.

## Articles / CPT (Gutenberg)

Mêmes règles. Vérifie en plus : date et auteur visibles, chapô qui répond à la question du titre, un lien interne vers la page service concernée, une mention géographique si le sujet est local.
