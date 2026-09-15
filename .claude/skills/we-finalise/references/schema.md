# Données structurées : quoi poser, où, et sur quel CPT

La mécanique (abilities `rank-math/*`, clés d'options, postmeta par `php-eval`) est dans `rankmath.md`.
Ce fichier répond à la seule question qui compte ensuite : **quel type de schema sur quel contenu**.

## Le principe

Un site vitrine local n'a pas besoin de beaucoup de schemas. Il a besoin de trois choses, et rien de plus :

1. **une entité d'entreprise unique**, posée une fois, avec le bon sous-type et un NAP exact ;
2. **un type juste sur chaque famille de contenu** (les CPT), pas un `Article` par défaut partout ;
3. **zéro donnée inventée.**

Le troisième point n'est pas une précaution de style. Un `aggregateRating` sans avis réels, des
`openingHours` approximatifs ou un `priceRange` deviné sont des données fausses publiées sous une
forme que Google lit littéralement — c'est la seule partie de la finalisation qui peut coûter une
pénalité manuelle. **Si la donnée n'est pas dans `.we-finalise/context.json` ou visible sur la page,
elle ne va pas dans le schema.** Une propriété absente est neutre ; une propriété fausse est un risque.

Corollaire : un schema ne décrit que ce que la page montre. Un `FAQPage` dont les questions ne sont
pas visibles à l'écran est une violation explicite des règles de Google, pas une astuce.

## 1. Le sous-type de l'entreprise

Rank Math pose l'entité d'entreprise à partir de `local_business_type` (Local SEO, voir
`rankmath.md` §3). Ce champ décide de la moitié de la valeur SEO du site : `LocalBusiness`
générique n'active presque aucun rich result, un sous-type précis oui.

| Secteur du client | `local_business_type` |
|---|---|
| Ostéopathe, kiné, médecin, sage-femme, infirmier | `Physician` |
| Dentiste | `Dentist` |
| Psychologue, thérapeute, diététicien | `MedicalBusiness` |
| Coiffeur, barbier, esthéticienne, spa, onglerie | `HealthAndBeautyBusiness` (ou `BeautySalon`, `HairSalon`) |
| Restaurant, traiteur, food truck | `Restaurant` (`FoodEstablishment` si ni sur place ni carte) |
| Boulangerie, boucherie, épicerie, caviste | `Store` (ou `Bakery`, `LiquorStore`) |
| Plombier, électricien, chauffagiste, serrurier | `Plumber`, `Electrician`, `HVACBusiness`, `Locksmith` |
| Maçon, couvreur, menuisier, peintre, rénovation | `HomeAndConstructionBusiness` (ou `GeneralContractor`, `RoofingContractor`) |
| Paysagiste, jardinier, élagage | `LandscapingBusiness` |
| Avocat, notaire, huissier | `LegalService` (`Attorney` pour un avocat) |
| Comptable, expert-comptable, gestion de paie | `AccountingService` |
| Agence immobilière, mandataire | `RealEstateAgent` |
| Assurance, courtier, conseiller financier | `InsuranceAgency`, `FinancialService` |
| Auto-école | `DrivingSchool` |
| Garage, carrosserie, contrôle technique | `AutoRepair`, `AutoBodyShop` |
| Salle de sport, coach sportif, yoga | `SportsActivityLocation`, `HealthClub` |
| Photographe, vidéaste | `ProfessionalService` (`Photograph` existe mais est un type de contenu, pas d'entreprise) |
| Agence web, marketing, conseil, freelance B2B | `ProfessionalService` — et non `Organization` |
| Vétérinaire | `VeterinaryCare` |
| Pompes funèbres | `FuneralHome` |
| Hôtel, chambres d'hôtes, gîte | `LodgingBusiness`, `BedAndBreakfast` |
| Artisan sans local recevant du public | sous-type métier, avec `areaServed` et sans `openingHours` |

Deux cas particuliers :

- **Praticien seul sous son propre nom** (ostéopathe, avocat, coach) : garder
  `knowledgegraph_type: company` avec le sous-type métier, et poser en plus un `Person` sur la page
  « À propos » relié par `worksFor`. Une entité `Person` seule prive le site du panneau local.
- **Site sans zone physique** (SaaS, e-commerce pur, service 100 % à distance) : pas de
  `LocalBusiness` du tout. `Organization` + `WebSite`, et on ne remplit ni adresse ni horaires.

Le sous-type doit exister dans la liste de Rank Math. S'il n'y est pas, prendre le parent le plus
proche plutôt qu'inventer une chaîne : un type inconnu de Schema.org est ignoré en bloc.

## 2. Mapping CPT → type de schema

Le réflexe à casser : Rank Math met `article` par défaut sur tous les post types. Un CPT
« réalisations » en `Article` raconte à Google que le chantier est un billet de blog.

| CPT / contenu | `rich_snippet` | Précisions |
|---|---|---|
| `page` (pages courantes) | `off` | Le `WebPage` du graphe suffit. Un `Article` sur une page de service est un contresens. |
| Page d'accueil | `off` | L'entité d'entreprise vient de Local SEO, pas d'un schema de page. |
| `page` — page contact | `off` | `local_seo_contact_page` fait poser `ContactPage` par Rank Math. |
| `page` — page à propos | `off` | Idem via `local_seo_about_page` ; ajouter `Person` si praticien seul. |
| Pages prestation / service | `off` + `Service` par contenu | Voir §3, c'est le gain principal sur un site vitrine. |
| `post` (blog, actualités) | `article` + `article_type: BlogPosting` | `NewsArticle` seulement pour un vrai média de presse. |
| `realisation`, `projet`, `chantier`, `portfolio` | `off` + `CreativeWork` par contenu | Ou `Service` si la page vend la prestation plus qu'elle ne montre le cas. |
| `temoignage`, `avis` | `off` | Voir §4 : jamais de `Review` détaché du contenu vérifiable. |
| `equipe`, `praticien`, `membre` | `off` + `Person` par contenu | `jobTitle`, `worksFor` vers l'entité, `image` du portrait. |
| `produit`, `boutique` (WooCommerce) | `product` | WooCommerce le pose déjà : vérifier qu'il n'y a pas doublon avec Rank Math. |
| `formation`, `cours` | `course` | `Course` exige `provider` ; `hasCourseInstance` si dates. |
| `evenement`, `agenda` | `event` | `startDate` et `location` obligatoires — sinon `off`. |
| `faq` (CPT dédié) | `off` + `FAQPage` sur la page qui les affiche | Le schema va sur la page visible, pas sur chaque entrée. |
| `recette` | `recipe` | |
| `offre`, `emploi` | `jobposting` | Périme vite : à ne poser que si le client tient les offres à jour. |
| CPT technique (slider, popup, bloc, formulaire) | `off` + `noindex` | Ne doit apparaître ni dans le sitemap, ni dans llms.txt. |
| `breakdance_*` | ignorer | Templates du builder, pas du contenu. |

Un CPT inconnu de cette table : regarder ce que la page **montre** et choisir le type
Schema.org le plus proche ; à défaut `off`. `off` est toujours meilleur qu'un type approximatif.

## 3. `Service` sur les pages prestation

C'est la modélisation qui rapporte le plus sur un site vitrine local, et Rank Math ne la fait pas
tout seul. Une page « Ostéopathie du sport à Lyon » mérite :

```json
{ "@type": "Service",
  "name": "Ostéopathie du sport",
  "serviceType": "Ostéopathie du sport",
  "description": "Reprise avec une phrase de la meta description de la page.",
  "provider": { "@type": "Physician", "name": "Nom commercial", "@id": "https://www.client.fr/#organization" },
  "areaServed": [ { "@type": "City", "name": "Lyon" }, { "@type": "City", "name": "Villeurbanne" } ],
  "url": "https://www.client.fr/osteopathie-du-sport/" }
```

Règles :

- `provider.@id` pointe sur l'entité posée par Local SEO — c'est ce `@id` qui relie le service à
  l'entreprise au lieu de créer une deuxième entreprise fantôme. Vérifier l'`@id` réellement émis
  dans le JSON-LD de la page d'accueil avant de le recopier (`#organization`, `#localbusiness` ou
  l'URL du site selon la version de Rank Math).
- `areaServed` vient de `client.zone`, jamais d'une liste de villes inventée.
- Pas d'`offers` ni de `priceRange` sans tarifs publics affichés sur la page.
- Le `name` reprend l'intitulé de la prestation, pas le titre SEO complet avec la marque.

## 4. Les quatre pièges

- **`aggregateRating` / `Review`** : interdits sans avis réels, vérifiables et affichés sur la page.
  Les avis Google ne peuvent pas être remontés en schema sur le site du client. Un `Review` inventé
  ou recopié d'une plateforme est la faute la plus sanctionnée du lot.
- **`FAQPage`** : les questions et réponses doivent être visibles dans la page, mot pour mot. Un
  seul `FAQPage` par page.
- **Doublons** : un thème, WooCommerce ou un plugin d'avis peuvent déjà émettre du JSON-LD. Deux
  `LocalBusiness` sur la même page valent moins que zéro. Vérifier le JSON-LD rendu, pas seulement
  la config Rank Math.
- **`openingHours`** : si `client.opening_hours` est vide, ne rien poser. Des horaires faux
  envoient des clients devant une porte fermée.

## 5. Validation

Le seul juge est le JSON-LD réellement rendu. `breakdance-preview-post` avec
`include_header_footer: true` donne le HTML réellement produit, `<script type="application/ld+json">`
compris : c'est là que tu lis le graphe émis, sans navigateur et sans être trompé par le mode
maintenance ou un cache HTTP. Purge le cache avant (`mcp.md` §7).

À contrôler, page d'accueil puis un exemplaire de chaque CPT :

- une seule entité d'entreprise, avec le sous-type attendu et le NAP exact ;
- `BreadcrumbList` présent sur les pages profondes ;
- le type attendu sur chaque CPT, et **aucun** `Article` sur une page de service ;
- aucune propriété vide, aucun `%placeholder%` non résolu (signe d'une variable Rank Math invalide) ;
- aucun `aggregateRating` que le client ne puisse justifier.

Reporter dans le rapport : le type d'entreprise retenu, le mapping CPT → schema appliqué, les
schemas posés page par page, et ce qui a été volontairement laissé de côté faute de données
(avis, tarifs, horaires) — cette dernière liste est ce que le client doit fournir pour aller
plus loin.
