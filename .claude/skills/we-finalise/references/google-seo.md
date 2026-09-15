# Recommandations Google vérifiables à la finalisation

Source : [Guide de démarrage SEO de Google](https://developers.google.com/search/docs/fundamentals/seo-starter-guide?hl=fr).
Ce fichier ne reformule pas le guide entier : il ne garde que ce qui est **vérifiable sur un site
qu'on livre**, avec l'action correspondante. `scripts/google-check.mjs` automatise la majeure
partie de ces contrôles quand Node est disponible ; sinon les mêmes constats se lisent dans le HTML
de `breakdance-preview-post` et dans `rank-math/get-robots-txt` (`mcp.md` §2). Les résolutions
notées « auto » ci-dessous s'appliquent par les abilities `rank-math/*` ou par `php-eval`, jamais à
la main dans l'admin ; `rank-math/audit-site-seo` et `rank-math/fix-site-seo` en couvrent déjà une
partie.

Deux principes de Google qui cadrent tout le reste : le site est fait **pour les gens d'abord**
(people-first), et l'objectif est que Google **comprenne** le site comme un visiteur le voit — pas
qu'on lui envoie des signaux.

## 1. Google trouve-t-il les pages ?

| Contrôle | Attendu | Résolution |
|---|---|---|
| `blog_public` | `1` — le site n'est pas en « demander aux moteurs de ne pas indexer » | auto |
| `noindex` par page | aucun `noindex` résiduel sur une page à indexer | auto |
| `robots.txt` | pas de `Disallow: /`, une ligne `Sitemap:` sur le domaine de prod | auto |
| CSS et JS explorables | aucun `Disallow` sur `/wp-content/`, `/wp-includes/`, les thèmes ou plugins | auto |
| Sitemap | répond en 200, contient `<urlset>` ou `<sitemapindex>` | auto |
| Liens internes | chaque page publiée est liée depuis au moins une autre page | humain |

Le point que Google souligne et qu'on oublie : **si le CSS et le JavaScript sont bloqués, Google ne
comprend pas les pages.** Un `Disallow: /wp-content/` hérité d'un « durcissement » de dev coûte plus
que tout ce qu'on gagne ailleurs.

## 2. Organisation du site et URL

| Contrôle | Attendu | Résolution |
|---|---|---|
| URL | des mots utiles, pas d'identifiant (`?p=123`, `/2/6772…`) | humain |
| Regroupement | pages d'un même thème dans un même dossier | humain |
| Canonique | présente, absolue, en `https`, auto-référente sauf duplication assumée | auto |
| Duplication | pas deux URL au même contenu sans redirection 301 ni canonique | auto |
| `title` / `description` | aucun doublon entre deux pages du site | auto |

Une URL déjà en ligne ne se change pas sans redirection 301 : le gain d'une URL plus jolie ne vaut
pas la perte des liens existants. À la finalisation d'un site neuf, en revanche, c'est le bon
moment.

## 3. Contenu

Google : « le texte est facile à lire et bien organisé », original, à jour, et écrit pour des gens.
Ce qui se vérifie ici :

- pas de faute d'orthographe ni de grammaire (relecture, pas de script) ;
- contenu original — pas de texte recopié d'un autre site, y compris d'un site du même secteur ;
- contenu long découpé en sections avec des titres ;
- les questions que les visiteurs se posent réellement sont traitées, avec leurs variantes de
  vocabulaire (Google comprend les synonymes : inutile de lister toutes les variantes) ;
- pas de publicité intrusive ni d'interstitiel qui gêne la lecture ;
- des liens vers des ressources utiles, avec des **textes d'ancrage descriptifs** — « Voir nos
  tarifs d'ostéopathie », pas « En savoir plus » ni « Cliquez ici » ;
- `rel="nofollow"` sur les liens issus de contenu généré par les utilisateurs (commentaires) ;
- une page presque vide n'a rien à indexer : si la matière manque, **demander au client**.

## 4. Apparence dans les résultats

- **`title`** : propre à chaque page, clair, concis, descriptif, avec le nom du site. Pas de
  « Accueil », « Bienvenue », « Sans titre ». Google ne fixe pas de longueur ; au-delà d'environ
  60 caractères le titre est tronqué à l'affichage.
- **meta description** : propre à chaque page, une à deux phrases reprenant les points les plus
  pertinents. Google peut composer l'extrait à partir du contenu de la page : la description est
  une proposition, pas une garantie.

## 5. Images

- `alt` descriptif sur chaque image porteuse de sens (étape 3 de la skill) ;
- images **nettes** : une image affichée plus grande que sa résolution est floue — Google demande
  explicitement de la qualité ;
- image placée **près du texte qui la concerne** : le texte voisin aide Google à comprendre l'image ;
- nom de fichier descriptif à l'import (`salle-de-soin-lyon.jpg`, pas `IMG_1234.jpg`). Ne pas
  renommer un média déjà en ligne sans redirection.

## 6. Vidéos

Vidéo de qualité, sur une page dédiée avec du texte pertinent, titre et description descriptifs.
Mêmes règles que pour les titres de pages.

## 7. Données structurées

Du JSON-LD **valide** rend la page éligible aux résultats enrichis. Le détail est dans `schema.md`
et à l'étape 7 de la skill. Un JSON-LD invalide est ignoré en bloc — et deux graphes concurrents
(thème + Rank Math) valent moins que zéro.

## 8. Technique

| Contrôle | Attendu | Résolution |
|---|---|---|
| HTTPS | tout le site, sans contenu mixte (`http://` sur une page `https://`) | auto |
| Mobile | `meta viewport` présente, aucun débordement horizontal | humain |
| Langue | attribut `lang` sur `<html>` | auto |
| hreflang | seulement si le site est multilingue, avec réciprocité | auto |

## 9. Promotion

Hors périmètre technique, mais à dire au client à la livraison : réseaux sociaux, newsletter,
URL sur les supports imprimés, bouche-à-oreille — que Google décrit comme « l'un des moyens les
plus efficaces et les plus durables ». Éviter la sur-promotion et toute pratique qui ressemble à
une manipulation des résultats.

## Ce sur quoi Google dit de **ne pas** perdre de temps

À connaître pour ne pas facturer du vide et ne pas promettre n'importe quoi au client :

- **`meta keywords`** : Google ne l'utilise pas. Si elle est présente, la supprimer — c'est un
  signal de site mal entretenu, pas un gain.
- **Longueur du contenu** : aucune longueur minimale ou maximale « magique ». Ni 300 mots, ni
  1 500. Ce qui compte est que la page réponde.
- **Répétition de mots-clés** : l'accumulation va « à l'encontre des règles Google concernant le
  spam ». Ce n'est pas neutre : c'est nuisible.
- **Ordre et nombre des `Hn`** : sans effet sur le classement — « ils peuvent être dans le
  désordre » — et il n'existe pas de nombre idéal de titres. On soigne quand même la hiérarchie,
  mais **pour les lecteurs d'écran**, et il faut le dire ainsi au client.
- **Mots-clés dans le nom de domaine ou l'URL** : « pratiquement aucun effet » sur le classement.
  L'extension (`.com`, `.fr`, `.guru`) ne compte que pour cibler un pays.
- **Sous-domaine ou sous-répertoire** : choisir ce qui arrange le projet, sans enjeu SEO.
- **Contenu accessible via plusieurs URL** : « ce n'est pas un problème » et il n'y a **pas de
  pénalité** pour contenu dupliqué interne — c'est seulement inefficace. Ne pas vendre cela comme
  un danger. (Copier le contenu d'autres sites, c'est autre chose.)
- **PageRank** : Google utilise de nombreux signaux ; la recherche « ne se limite pas aux liens ».
- **E-E-A-T** : Google est explicite — ce n'est **pas** un facteur de classement direct. C'est un
  cadre d'évaluation de la qualité. Utile pour guider la rédaction (qui parle, avec quelles
  qualifications, quelles sources), à ne pas présenter comme un réglage à activer.

## Ce qui ne se vérifie pas depuis un poste de travail

À inscrire dans le rapport plutôt qu'à cocher :

- Search Console : propriété à créer, sitemap à soumettre, inspection d'URL à lancer **après** la
  mise en ligne, sur le domaine de production ;
- performances réelles et Core Web Vitals sur données de terrain ;
- qualité perçue du contenu par les visiteurs.
