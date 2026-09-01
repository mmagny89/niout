# Niout — Plan de bataille

Document de cadrage technique. Traduit les 16 documents de conception du jeu
(dossier Google Drive `Niout`, fichiers `00` à `15`) en un plan de développement
Symfony.

- **Stack** : Symfony 8.1 · Twig · Tailwind CSS 4.3 · PostgreSQL · FrankenPHP/Docker
- **Contrainte** : rendu serveur, zéro React, zéro headless
- **Source de conception** : Google Drive `Niout/`, docs 00–15 + sous-dossier `Sprites/` (18 planches)

Les phases livrées sont résumées ici ; le détail des pièges déjà payés et des
conventions de code qui en découlent vit dans [`CLAUDE.md`](../CLAUDE.md), pour
éviter de le répéter à deux endroits.

---

## 1. Le jeu en un coup d'œil

Jeu de gestion / aventure / RPG léger, Égypte antique du Nouvel Empire
(~1550-1070 av. J.-C.), jouable au navigateur, **sans aucune attente en temps
réel** : le temps avance uniquement quand le joueur déclenche un cycle (une
« quinzaine » = 2 semaines de temps de jeu).

Le joueur incarne une famille chargée par un pharaon de fonder, restaurer ou
sécuriser une ville. Cinq boucles de jeu s'auto-alimentent : exploration,
artisanat/commerce, construction, faveur divine, fil rouge narratif.

| Mode | Principe |
|---|---|
| **Campagne** | 10 missions, chacune liée à un pharaon réel et une ville attestée (Avaris, Megiddo, Malkata…), difficulté croissante par région |
| **Aventure** | Une seule ville (Memphis), jouable sur la durée à travers une succession de règnes, sans fin scriptée |

**Rejouabilité** : génération semi-aléatoire des cartes, héritage familial entre
parties, renommée persistante.

Tous les systèmes sont déjà spécifiés en détail dans les documents Drive. Le
travail de cadrage restant est *technique*, pas du game design.

---

## 2. Choix techniques retenus

| Domaine | Choix |
|---|---|
| Framework | **Symfony 8.1** (dernière version stable, mai 2026), architecture MVC classique — pas d'API séparée, pas de front headless |
| Rendu | Twig côté serveur. Interactivité ponctuelle (déclencher un cycle, popup de case, formulaires) via **Symfony UX Turbo + Stimulus**. Aucun React, aucun front SPA |
| CSS | **Tailwind CSS 4.3** via `symfonycasts/tailwind-bundle` — intégration native AssetMapper, binaire autonome, **pas de Node.js requis** |
| Données | Doctrine ORM, migrations versionnées, **PostgreSQL** |
| Auth | `symfony/security-bundle`, entité `User`, hash argon2, `symfony/form` + validation. Vérification d'email et réinitialisation de mot de passe via `symfony/mailer` |
| Assets | AssetMapper — même mécanisme que Tailwind, un seul système d'assets pour tout le projet |
| Infra | Docker / FrankenPHP (mode worker) + Caddy intégré, Ember pour l'observabilité. Généré par `.claude/scripts/setup-symfony.sh` selon `.claude/rules/stack-conventions.md` |
| Tests | PHPUnit : unitaire, intégration (`KernelTestCase`) et fonctionnel (`WebTestCase`), avec `dama/doctrine-test-bundle` pour l'isolation. Behat reste envisagé pour les scénarios de jeu, où la lisibilité Gherkin profite à la relecture fonctionnelle |

**Pourquoi ce choix.** Le jeu n'a pas besoin de temps réel ni d'un état client
complexe : chaque action se résout côté serveur, le joueur rafraîchit une vue.
Turbo évite le rechargement complet de page sans la complexité d'un SPA, et
AssetMapper + Tailwind évitent toute chaîne de build Node.js.

---

## 3. Compte, famille, partie

La fiction du jeu fournit la bonne hiérarchie de données : un **compte** peut
incarner plusieurs **familles** jouées dans le temps, chaque partie étant une
**run** indépendante et rejouable (cf. doc 00 : « chaque partie est une run
complète, pas une sauvegarde infinie »).

**Tranché** : le nom de famille se choisit **au lancement d'une partie**, pas à
l'inscription. Un compte peut mener **jusqu'à 5 parties en cours simultanément**.

| Entité | État | Rôle | Doc source |
|---|---|---|---|
| `User` | ✅ | Compte joueur — email, mot de passe, statut de vérification, rôles | — |
| `GameSave` | ✅ | Une run : mode, mission en cours, cycle, statut (en cours/échouée) | 00, 14 |
| `Family` | ✅ | Nom choisi au lancement (1 par `GameSave`) et renommée. Héritage et contacts commerciaux en Phase 9 | 13 |
| `City` | ✅ | Nom, difficulté régionale, taille de grille, stock, carte, chantiers, population | 01, 02, 11 |
| `Building` | ✅ | Un bâtiment dressé : son type et son niveau | 01 |
| `Chantier` | ✅ | Travaux en cours : niveau visé, durée, avancement | 01, 05 |
| `Zone`, `Gisement` | ✅ | Une case de la carte d'exploration, ses filons | 02, 08 |
| `Expedition` | ✅ | Un éclaireur ou un émissaire en route vers une case | 04, 10 |
| `StockDeRessource` | ✅ | Une ligne du stock de la ville (ressource → quantité), deben compris | 08 |
| `Employee` | ✅ | Un chef en poste : compétence, salaire, spécialité, la maisonnée qu'il a amenée | 03, 05 |
| `JobOffer` | ✅ | Une annonce affichée et son tirage de candidats, figé | 03 |
| `OrdreDeFabrication` | ✅ | Un lot en cours à l'Atelier, à la Forge ou au Luxe | 08 |
| `RouteCommerciale` | ✅ | Une route ouverte vers un partenaire, et ce qu'on y échange | 12 |
| `OrdreCommercial` | ✅ | Une ligne de l'étal : ressource, sens, prix, volume par convoi | 08, 12 |
| `Convoi` | ✅ | Une caravane ou un navire en chemin, avec sa copie de l'échange | 12 |
| `FaveurDivine` | ✅ | Ce qu'un dieu pense de la ville, et depuis quand on l'a négligé | 07 |
| `DossierDEnquete` | ✅ | Une enquête en cours, ses indices versés, sa conclusion | 10 |
| … (Phase 8+) | — | Medjaÿ, campagne, héritage | 03, 09, 13 |

`Family`, `City` et tout ce qui s'y rattache (`Zone`, `Building`, `Chantier`,
`Expedition`, `Employee`, `JobOffer`, `OrdreDeFabrication`, `RouteCommerciale`,
`FaveurDivine`, `DossierDEnquete`) sont détenus par leur `GameSave` : l'abandon d'une partie, comme
la purge d'un compte, les emporte en cascade.

**Couche de domaine.** Ce qui relève des règles du jeu plutôt que de la
persistance vit dans `src/Game/` : catalogue des missions, dotation royale,
génération de carte, cycle agricole, population… Ces classes ne sont jamais
persistées — elles décrivent le contenu et les règles, pas l'état d'une partie.

---

## 4. Feuille de route

Chaque phase correspond à un ou plusieurs documents déjà entièrement spécifiés —
le travail y est surtout de la traduction en entités Doctrine, contrôleurs et
vues, pas de la conception.

| Phase | Sujet | Docs | État |
|---|---|---|---|
| **0** | Fondations techniques | — | ✅ |
| **1** | Comptes et page d'accueil | — | ✅ |
| **2** | Lancer une partie et bâtir | `01`, `05`, `13` | ✅ |
| **3** | Carte, exploration et ressources | `02`, `04`, `06`, `08` | ✅ |
| **4** | Population : recrutement, chefs et travailleurs | `01`, `02`, `03`, `05`, `13` | ✅ |
| **5** | Artisanat et commerce | `08`, `12`, `01` | ✅ |
| **6** | Faveur divine et événements | `07` | ✅ |
| **7** | Énigmes, enquêtes et fil rouge | `10` | ✅ |
| **8** | Campagne : les 10 missions et leurs objectifs | `09`, `11` | planifiée |
| **9** | Renommée, héritage et succession familiale | `13` | à cadrer |
| **10** | Medjaÿ et combat automatique | `03` | à cadrer |
| **11** | Mode Aventure : Memphis et succession des règnes | `14` | à cadrer |
| **12** | Découpage et intégration des sprites | `15` | à cadrer |

Le document 15 (interface & direction artistique) est **transverse** : chaque
phase l'utilise au fur et à mesure, la phase 12 ne concerne que l'intégration
des images elles-mêmes — **hors planche « tuiles »**, découpée dès la Phase 3
pour l'écran de carte.

**Réordonnancements par rapport à la première version.** Les cycles (doc 05)
sont montés de la Phase 5 initiale à la Phase 2 : ils ne sont pas un système
parmi d'autres mais le battement du jeu, et un chantier qui ne progresse pas
n'est pas démontrable. Le combat (doc 03), inversement, descend en Phase 10 :
optionnel dans les boucles de jeu, il n'a de sens qu'une fois les zones
dangereuses posées. Même logique pour la population (doc 01, 03) : sa brique
minimale — compter les habitants, les nourrir, échouer sans nourriture — est
montée au lot 3.7, le recrutement et les chefs restant en Phase 4.

---

## 5. Les phases, dans l'ordre

Les huit premières (Phases 0 à 7) sont livrées et résumées ici : intention,
lots, règles qui en sortent, pièges payés, ce qu'elles laissent ouvert. Le
détail des conventions qui en découlent vit dans [`CLAUDE.md`](../CLAUDE.md) ;
l'historique complet est dans les messages de commit.

**La Phase 8 est cadrée et pas encore écrite** : elle garde le format détaillé,
lot par lot, jusqu'à sa livraison — comme les Phases 5, 6 et 7 avant elle.

### 5.1 Phase 0 — Fondations techniques  ✅

Dépôt git, stack Docker + squelette Symfony (généré par
`.claude/scripts/setup-symfony.sh --dedicated-server --run`), authentification
de base, thème Tailwind (doc 15 : ocre/sable/terre cuite, accents
lapis-lazuli/or), pipeline CI GitHub Actions, outillage qualité. Stable depuis.

**Versions retenues** (vérifiées le 2026-08-27) : Symfony 8.1.5 · PHP 8.4 ·
FrankenPHP 1.12.7 · Tailwind CSS 4.3.3 · PostgreSQL 18 · Ember 1.6.0. Le stack
répond sur `https://localhost` (certificat auto-signé Caddy).

**Polices** : Marcellus (titrage) et Alegreya Sans (texte), **self-hébergées**
dans `app/assets/fonts/` — pas d'appel runtime à Google Fonts, pour ne pas
transmettre l'IP du visiteur à un tiers.

### 5.2 Phase 1 — Comptes et page d'accueil  ✅

Trois pages et un compte fonctionnel : présentation publique, inscription
(vérification d'email non bloquante, purge à 7 jours), connexion avec mot de
passe oublié.

Quatre écarts au plan initial, tous assumés :

- **Tests fonctionnels en `WebTestCase` plutôt qu'en Behat** — l'outil natif
  couvre les mêmes parcours sans machinerie supplémentaire.
- **URL en français** (`/inscription`, `/connexion`) ; les *noms* de routes
  restent en anglais, référencés par `security.yaml`.
- **Emails en synchrone** — la recette Symfony les route vers Messenger, mais
  aucun worker ne tourne sur ce stack. À rebasculer le jour où il y en aura un.
- **Mot de passe durci à l'inscription**, aligné sur les contraintes déjà
  exigées à la réinitialisation.

**Point de sécurité laissé ouvert** : le formulaire d'inscription révèle qu'une
adresse possède déjà un compte (énumération) — comportement par défaut de
Symfony, le masquer dégraderait l'inscription. Le flux de réinitialisation, lui,
ne fuit rien.

### 5.3 Phase 2 — Lancer une partie et bâtir  ✅

**Intention.** Livrer la plus petite tranche réellement *jouable* : créer une
partie, voir sa ville, lancer un chantier, déclencher un cycle, voir le chantier
avancer puis s'achever. C'est ce qui valide l'architecture d'état par partie et
la résolution de cycle — la fondation de tout le reste.

Livré : modèle de partie (`GameSave`, `Family`, `City`, plafond de 5 parties
actives) ; parcours de création (mode, nom de famille, dotation royale) ; liste
et gestion des parties ; vue de ville avec les 12 bâtiments et leurs conditions
de disponibilité ; cycles et chantiers (calendrier pharaonique, étapes nommées,
accélération ×1,5 en Akhèt) ; stock affiché en permanence dans la barre de jeu.

Deux notes de conception qui ont guidé la suite :

- **L'ordre imposé des missions simplifie le modèle** — un `GameSave` de
  campagne ne porte qu'un numéro de mission incrémenté, aucune notion de région
  débloquée.
- **Le récapitulatif de reprise n'est pas un journal d'événements.** Le jeu
  n'ayant aucun temps réel, rien ne se produit pendant l'absence du joueur : le
  récapitulatif porte sur l'état où la partie a été laissée, pas sur ce qui
  s'est passé depuis.

### 5.4 Phase 3 — Carte, exploration et ressources  ✅

**Intention.** Faire basculer la ville de la dépense à la production : elle tire
ses matériaux de son territoire et sa nourriture de ses champs, plutôt que de
consommer une dotation qui ne se renouvelle pas. Les douze bâtiments du doc 01
deviennent tous atteignables — le Temple par le lin, le Port par la berge.

| Lot | Contenu | |
|---|---|---|
| 3.1 | Généralisation du stock en table `ressource → quantité` | ✅ |
| 3.2 | Génération de la carte selon la géographie réelle | ✅ |
| 3.3 | Découpage des tuiles et écran de carte isométrique | ✅ |
| 3.4 | Reconnaissance par éclaireur | ✅ |
| 3.5 | Ressources de zone, champs et cycle agricole | ✅ |
| 3.6 | Points d'eau, Port et pêche | ✅ |
| 3.7 | Ville et territoire, ajustements de la joueuse | ✅ |

**Les règles qui en sortent**

- **Chaque coût nomme sa ressource réelle**, jamais un générique « bois » ou
  « pierre » : un compteur agrégé cacherait au joueur ce qu'il possède.
- **Le Marché est avancé de la Phase 5** dans sa forme minimale. Sans lui, la
  monnaie n'a aucune source renouvelable et toute partie finit figée.
- **Une case porte jusqu'à deux gisements**, jamais deux fois le même ; un
  minimum de champs et de cases poissonneuses est garanti par région.
- **Un champ ne nourrit qu'à sa récolte**, en quatre étapes nommées. Un champ du
  Nil suit la saison, un champ terrestre son propre compteur.
- **Le poisson est la seule ressource renouvelable** : une pêcherie tarissable
  aurait fait du Port un piège sur une carte à une seule case d'eau.
- **Reconnaître ses abords est gratuit** — ni deben ni vivres à moins de trois
  cases de la ville.
- **La carte est l'écran principal** d'une partie : on ouvre la ville en
  cliquant sa tuile, non l'inverse.

**Écarts assumés aux documents** : un attribut `desertDominant` ensable les
régions que le doc 11 décrit ainsi, le doc 02 ne posant le désert que sur un
bord ; et une case de sable bordant le Nil est exclue du placement de la ville,
les deux règles du doc 02 s'y contredisant.

**Ce qui reste ouvert**

- **Seul le Delta est autosuffisant** en matériaux parmi les dix régions. À
  partir de la mission 2, le commerce (Phase 5) devient une condition de
  jouabilité, pas un confort.
- **L'épuisement des gisements** est réservé par le doc 02 aux régions de
  difficulté 4 et plus, et `PoidsDeTirage::gisementsEpuisables()` existe pour le
  dire — mais n'est appelée nulle part. À trancher quand ces régions arriveront :
  brancher la méthode, ou acter que tout s'épuise.
- **Le doc 15 mériterait une correction** : le prompt de la planche 13 demandait
  des tuiles carrées vues de dessus à fond transparent ; la planche livrée est
  isométrique sur fond opaque.
- **Le modèle de population de ce lot a été entièrement refait en Phase 4** : il
  déduisait les habitants d'une formule de bâtiments sans consulter les docs 01
  et 02, qui les chiffrent. C'est de là que vient la leçon « avant d'inventer,
  vérifier que le document ne dit rien ».

### 5.5 Phase 4 — Population : recrutement, chefs et travailleurs  ✅

**Intention.** Faire cesser la fiction d'une ville que le joueur actionne seul.
Avant cette phase, les bâtiments fonctionnaient parce qu'ils existaient, les
carrières s'exploitaient toutes seules et les champs se moissonnaient sans
personne. Désormais tout réclame des bras — et ces bras ont une famille à
nourrir et un salaire à toucher.

**Deux principes ont structuré la phase.** Le premier vient du doc 03 :
**chiffré en interne, qualitatif à l'affichage** — le moteur manipule des
compétences de 20 à 100, le joueur ne voit que des étoiles et des libellés. Le
second est posé par la joueuse et vaut pour tout le projet : **les chiffres des
documents sont provisoires**. Le critère n'est pas la fidélité au document mais
l'équilibre, et le fait de **pousser le joueur à se servir des mécaniques**.

| Lot | Contenu | |
|---|---|---|
| 4.0 | Le deben devient la monnaie, l'or redevient un métal | ✅ |
| 4.1 | Habitants, actifs, inactifs ; naissances et appel d'habitants | ✅ |
| 4.2 | Le candidat : compétence, traits, spécialité, maisonnée | ✅ |
| 4.3 | L'offre d'emploi : poster, choisir, renvoyer | ✅ |
| 4.4 | Chefs et travailleurs, règle du demi-rendement | ✅ |
| 4.5 | Équipages du territoire et bâtiment gouvernant | ✅ |
| 4.6 | Salaires, masse salariale et calibrage de la phase | ✅ |
| 4.7 | Départs naturels, mécontentement, famine à deux paliers | ✅ |
| 4.8 | Ce que la compétence d'un chef change | ✅ |

**Les règles qui en sortent**

- **La monnaie est le deben** (≈ 91 g), l'Égypte pharaonique n'ayant pas de
  monnaie frappée. L'or est un métal qu'on extrait et qu'on vend.
- **La population se compte en trois nombres** — actifs, enfants, anciens. Le
  bilan tombe une fois l'an ; on naît, mais seulement s'il y a de la place.
- **Peupler passe par le logement, toujours** : l'appel d'habitants comme
  l'embauche d'un chef butent sur le même verrou, et le chef repart avec les
  siens s'il s'en va.
- **Ce sont les chefs qui recrutent** (doc 05). Un bâtiment sans chef ne réclame
  aucun travailleur, donc tourne au plancher : « sans chef, la moitié » est un
  cas de la formule générale `0,5 + 0,5 × (réel / requis)`.
- **Rien ne s'éteint faute d'employés** : embaucher est un investissement, pas
  une taxe — c'est ce qui rend la phase jouable.
- **Le territoire aussi a des salariés**, et chaque exploitation a un bâtiment
  gouvernant dont le niveau élargit l'équipage *et* le rendement.
- **Les salaires tombent avant la production.** L'unité de paiement est le
  bâtiment ou l'exploitation entière ; une unité impayée s'arrête.
- **Le mécontentement a deux causes et un seul mécanisme.** La famine se lit à
  deux paliers : mécontentement à 4 quinzaines, échec à 12.
- **Un chef ne crée jamais un multiplicateur de plus** : il module la qualité de
  direction, aux côtés de l'effectif. Un mauvais chef reste meilleur que pas de
  chef.

**Écarts assumés aux documents**

| Point | Document | Retenu | Pourquoi |
|---|---|---|---|
| Salaire d'un chef | `5 + comp × 0,3` (11-35) | `2 + comp × 0,12` (4-14) | Un seul chef dépassait tout ce qu'une ville du Delta peut gagner. L'écart mauvais/excellent reste de ×3 |
| Travailleurs du Port | 3 | **1** | Un chef et un homme tiennent un quai ; à trois, l'équipage mangeait toute la pêche |
| Bâtiments sans spécialité | — | Ne se dirigent pas | Résidence, Quartier, Auberge : la famille les tient elle-même. Déduction, pas une ligne de document |

**Pièges payés pendant la phase**

- **`ajusterRenommee()` n'était appelé de nulle part.** Toute règle indexée
  dessus serait restée inerte sans qu'aucun test ne le signale. **Avant
  d'indexer une règle sur une valeur, vérifier qu'une source la fait bouger.**
- **Un double comptage de rendement**, introduit au lot 4.4 et retiré au 4.5 :
  deux planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout
  tourne au moins à moitié » que la règle promet.
- **Le rendement propre d'un bâtiment était devenu décoratif** une fois ce
  double comptage retiré — rattrapé au 4.8 : il module désormais le **bonus**
  accordé aux exploitations, jamais leur base.
- **Une offre d'emploi doit figer son tirage**, sans quoi recharger la page
  relancerait les dés jusqu'au cinq étoiles.
- **Semer un `Mt19937` par candidat avec des entiers consécutifs** produit des
  premiers tirages corrélés, ce qui fausse toute mesure de distribution.

**Calibrages vérifiés plutôt que postulés**

- **Démographie**, 200 parties de vingt ans : sans Quartier la population fond
  de 10 à 5, avec un Quartier de niveau 1 elle monte à 13, aucune ville ne
  s'éteint.
- **Économie**, sur la ville d'exemple du lot 4.6 : 15 emplois, ~34 vivres
  produits pour 26 mangés, ~68 deben de revenus pour 39 de salaires.
- **Distribution des traits**, 400 tirages : 46,8 / 38,2 / 15,0 % contre des
  taux visés de 45 / 40 / 15.
- **Espérance de service d'un chef**, 300 tirages : une vingtaine de quinzaines
  pour une ancienneté annoncée de 20.
- **La spirale de mécontentement se redresse** : une ville affamée huit
  quinzaines puis ravitaillée retrouve le calme en huit, sans perdre la partie.

**Ce que la phase abandonne, et ce qu'elle laisse ouvert**

- **Le suivi des âges** permettait à un candidat d'annoncer « deux enfants
  bientôt en âge de travailler ». Le modèle agrégé supprime ce signal : c'est le
  prix de la simplicité demandée.
- **L'ordre de service des bâtiments est alphabétique** quand les bras manquent
  — stable et explicable, mais arbitraire.
- **La dotation avance une année de salaires des bras envoyés**, pas d'un chef,
  qui coûte 200 par an à lui seul. Embaucher avant d'avoir un revenu de Marché
  mène à la faillite en une quinzaine de cycles : tension voulue, mais raide.
- **Une vérification d'équilibrage en conditions réelles** reste à faire : mener
  une partie sur une année complète au navigateur. Les calibrages sont mesurés
  pièce par pièce, leur composition sur une partie entière ne l'est pas.

**Un déplacement d'équilibre à assumer** : la masse salariale dépasse largement
le coût des bâtiments (un Grenier coûte 15 deben, une quinzaine de salaires en
coûte 39). Le poste de dépense principal du jeu cesse d'être la construction
pour devenir l'emploi.

**Hors périmètre, explicitement** : les Medjaÿ et le combat (Phase 10), le
Charrier (Phase 10), le craft et les caravanes (Phase 5), le bonus de
main-d'œuvre d'Akhèt sur le vivier régional, et le kite — sans objet tant que
les prix restent en nombres entiers de deben.

---

### 5.6 Phase 5 — Artisanat et commerce  ✅

**Sources** : docs `08` (ressources, recettes, prix, rivaux), `12` (routes
commerciales par mission), `01` (Atelier, Forge, Entrepôt, Port, stockage).

**Intention.** La ville savait produire des matières premières et les vendre au
Marché local ; elle ne savait ni les **transformer**, ni aller chercher ce que
sa région ne porte pas. C'est le blocage identifié dès le lot 3.5 : **seul le
Delta est autosuffisant** en matériaux parmi les dix régions — à partir de la
mission 2, le commerce cesse d'être un confort pour devenir une condition de
jouabilité.

À la fin de la phase, on peut raconter : *« mon argile part à l'Atelier, où
trois potiers en tirent des jarres pendant deux quinzaines. J'ouvre une route
vers Byblos en y envoyant une première caravane, j'annonce que j'achète du
cèdre à douze deben et que je vends mon lin à cinq. Trois quinzaines plus tard,
un navire entre au port avec le cèdre ; le mien est reparti chargé de lin. »*

**Le principe de commerce universel du doc 08** structure toute la phase :
n'importe quelle ressource peut être achetée ou vendue. Le Marché et l'Entrepôt
sont des points d'échange généralistes, pas des catalogues fermés. La catégorie
d'une ressource ne dit qu'une chose : **où l'obtenir sans commercer**.

| Lot | Contenu | |
|---|---|---|
| 5.0 | Les douze ressources fabriquées, et leurs prix | ✅ |
| 5.1 | Capacité de stockage : plafonner sans périmer | ✅ |
| 5.2 | L'Atelier : des ordres de fabrication | ✅ |
| 5.3 | La Forge : outils et armes | ✅ |
| 5.4 | Les partenaires commerciaux et leurs fourchettes | ✅ |
| 5.5 | Ouvrir une route en y envoyant une caravane | ✅ |
| 5.6 | L'étal : annoncer ce qu'on vend et ce qu'on achète | ✅ |
| 5.7 | Le trafic : caravanes et navires en chemin | ✅ |
| 5.8 | Le craft de luxe, débloqué par l'Entrepôt | ✅ |
| 5.9 | Les chefs de l'Atelier, de la Forge et de l'Entrepôt | ✅ |

**Les règles qui en sortent**

- **Rien de fabriqué ne se trouve sur une carte.** La poterie, les outils et les
  bijoux n'existent que par le travail ou par l'import ; aucune région ne les
  déclare en ressource de zone.
- **Un objet vaut environ 165 % de ce qu'il coûte à produire.** En deçà,
  personne ne fabriquerait ; au-delà, vendre brut n'aurait plus jamais de sens.
  Toute recette ajoutée doit tenir cette marge, et c'est mesuré.
- **Le stock est plafonné, jamais périssable.** Le Grenier tient les vivres,
  l'Entrepôt les matériaux et les objets ; le surplus ne rentre pas, ce qui est
  rangé y reste. **Le deben n'a aucun plafond** — sinon le plafond bloquerait la
  vente, seule issue qu'il pousse à prendre.
- **Fabriquer prend du temps et plusieurs matières.** Les matières sont débitées
  à l'engagement, les pièces n'entrent qu'à l'achèvement, et **un seul ordre à
  la fois par bâtiment** : c'est ce qui donne son coût d'opportunité au craft.
- **L'Atelier et la Forge partagent tout** — un seul service, c'est la recette
  qui dit où elle se travaille.
- **Une route s'ouvre en y envoyant une caravane** : on paie, le convoi part, la
  route n'existe qu'à son arrivée. Le type de route décide du bâtiment —
  Entrepôt pour les pistes, Port pour tout ce qui flotte.
- **Le commerce est un étal, pas un bouton d'échange.** Un ordre ne débite rien,
  c'est une annonce ; les convois l'exécutent. **Le prix décide de l'empressement
  du partenaire**, donc du volume qui bouge — c'est ce qui en fait un levier
  plutôt qu'un curseur à pousser au maximum.
- **Un convoi parti est un engagement pris** : on débite au départ ce qu'on
  engage, on reçoit au retour, et le convoi porte **sa propre copie** de
  l'échange — retirer une annonce n'annule pas ce qui roule.
- **Les fourchettes se déduisent** du cours (200 % à la vente, 150 % à l'achat),
  jamais d'une table par partenaire ; et **un partenaire ne vend jamais ce qu'il
  achète**, sans quoi une route serait une machine à arbitrer.
- **Le luxe se débloque par l'Entrepôt, pas par l'Atelier** : le prestige n'est
  atteignable qu'une fois le commerce établi.
- **Une spécialité d'atelier ne vaut que sur son propre ouvrage**, et passe par
  la qualité de direction. Le Négociateur et le Logisticien font exception —
  leur effet n'est pas une production.

**Pièges payés pendant la phase**

- **Doctrine insère avant de supprimer.** Remplacer une caravane rentrée par une
  neuve dans la même quinzaine faisait sauter la contrainte d'unicité : une
  caravane **repart** plutôt qu'elle n'est recréée. Le piège des gisements,
  repayé.
- **Des plafonds de stock trop bas** (150) faisaient démarrer la ville à 95 % de
  saturation, la dotation valant déjà 143. Portés à 250.
- **Un plafond de vente à 140 %** ne laissait au lin que deux prix entiers
  possibles — un levier sans amplitude n'est pas un levier. Porté à 200 %.
- **Mesurer une vente de blé mesure aussi le dîner de la ville**, et une
  caravane rentrée repart aussitôt : deux tests faux avant d'être justes.
- **Compter les quinzaines ne mesure pas la qualité d'un chef** : elles sont
  entières, et n'y distinguent pas 134 % de 114 %.

**Calibrages vérifiés plutôt que postulés**

- **La marge de transformation**, sur les douze recettes : chacune reste au
  voisinage de 165 %, et le test tombe si une recette ajoutée s'en écarte.
- **La courbe d'empressement** d'un partenaire : le prix annoncé change bien le
  volume qui bouge, et l'écran le montre **avant** l'engagement.
- **Le trajet d'un convoi** vaut exactement deux fois la distance, mesuré et non
  supposé.

**Ce que la phase laisse ouvert**

- **Les marchands rivaux** (doc 08) : reportés en bloc après les enquêtes
  (Phase 7), décision de la joueuse — l'une de leurs trois issues est une
  enquête.
- **La péremption du surplus** (doc 01) : écartée, on plafonne sans dégrader.
- **L'héritage commercial inter-missions** (doc 12) : suppose une campagne qui
  enchaîne ses missions, donc la Phase 8.
- **L'usage des armes et des outils** : Phase 10 pour les unes, indéfini pour
  les autres. Ils se vendent, c'est tout, et l'interface le dit.
- **Le kite**, dixième du deben : sans objet tant que les prix restent entiers.
- **La vérification d'équilibrage en conditions réelles** — mener une partie sur
  une année complète au navigateur — reste due, comme à la fin de la Phase 4.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Le craft est-il instantané ? | **Non** : un ordre produit plusieurs pièces sur plusieurs quinzaines, à partir de plusieurs ressources |
| Comment commerce-t-on à distance ? | On **ouvre une route en envoyant une première caravane**, on annonce ses prix, puis les convois vont et viennent au rythme de la distance |
| Les rivaux commerciaux ? | **Reportés** après les enquêtes |
| Le stockage est-il limité ? | **Oui, plafonné** par le Grenier et l'Entrepôt — mais **rien ne se périme** |

---

### 5.7 Phase 6 — Faveur divine et événements  ✅

**Sources** : doc `07` (panthéon, paliers, offrandes, épidémies), doc `01`
(Temple), doc `03` (trait « Pieux », spécialité « Dévot »).

**Intention.** Le Temple existait, se construisait, montait en niveau — et ne
servait à rien. Un trait de candidat et une spécialité de chef étaient tirés,
affichés, et annonçaient eux-mêmes leur inertie. Cette phase leur donne leur
système d'accueil.

Elle apporte au jeu ce qui lui manquait après cinq phases d'économie : **une
variable que le joueur choisit d'alimenter sans contrepartie immédiate**. Tout
le reste se calcule — un Grenier rapporte tant, un convoi rapporte tant. Une
offrande est un pari.

| Lot | Contenu | |
|---|---|---|
| 6.0 | Le panthéon : huit divinités, leurs domaines, l'échelle de faveur | ✅ |
| 6.1 | Le Temple : offrir, et ce que le niveau autorise | ✅ |
| 6.2 | La négligence : décroissance vers le neutre, jamais en dessous | ✅ |
| 6.3 | Ce que la faveur change réellement, branché sur l'existant | ✅ |
| 6.4 | Les fêtes calendaires attestées | ✅ |
| 6.5 | Bénédictions et malédictions ponctuelles | ✅ |
| 6.6 | Les épidémies | ✅ |
| 6.7 | Le trait « Pieux » et la spécialité « Dévot » | ✅ |

**Les règles qui en sortent**

- **Le panthéon est du contenu, la faveur est de l'état.** Seule la clé d'un
  dieu et la valeur de sa faveur sont persistées, et **une ligne naît au premier
  geste, jamais au lancement**.
- **Le Temple est la seule limite de la dévotion**, et il en pose deux qui ne
  disent pas la même chose : combien de dieux on porte au-dessus du neutre (un
  par niveau), et jusqu'où leur faveur monte (`50 + 5 × niveau`). La première
  oblige un Temple modeste à choisir, la seconde fait du palier Dévoué une
  conquête — il demande un niveau 6.
- **On offre en deben ou en marchandise**, converties au cours du Marché et par
  aucun autre barème. C'est aussi le premier débouché du surplus que le plafond
  de stock refuse.
- **La négligence s'arrête au neutre.** Un dieu délaissé cesse de favoriser, il
  ne punit pas : une partie menée sans mettre les pieds au Temple finit comme
  elle a commencé.
- **La faveur n'ajoute jamais un multiplicateur à une chaîne qui en a déjà un.**
  Là où un facteur existe, elle déplace ce qui l'alimente ; là où il n'en existe
  aucun, elle agit directement.
- **Un dieu favorable ne pénalise jamais une production.** L'hostilité se paie
  par une crue moins généreuse ou par la fièvre, jamais par un malus de
  rendement.
- **Les fêtes sont datées par les sources**, jamais étalées pour l'équilibre, et
  leur supplément est **forfaitaire** : c'est le moment qui compte, pas la
  générosité.
- **Une malédiction retarde et coûte, elle n'efface pas** — et **jamais
  d'échec** : la famine reste la seule cause de défaite.
- **Une épidémie couche des bras, elle ne tue personne**, et passe par le canal
  existant du rendement d'effectif.
- **Un dieu sans emploi le dit.** Deux à la clôture de cette phase — Isis et
  Thot ; le lot 7.7 a depuis réveillé le second.

**Le partage des canaux, dieu par dieu**

| Dieu | Ce qu'il change | Par où |
|---|---|---|
| Hâpi | La crue de l'année | Infléchit le **tirage**, d'un cran — la récolte garde son unique modificateur |
| Ptah | Les chantiers | **S'ajoute** au facteur de saison, même unité |
| Osiris | Les champs terrestres | Raccourcit la **jachère** : la récolte revient plus tôt, elle n'est pas plus grosse |
| Amon-Rê | L'attractivité | Allège l'appel d'habitants, ajoute à ce que la renommée a ouvert |
| Sobek | Les trajets **par eau** | Jamais sous une quinzaine |
| Sekhmet | La fièvre | L'écarte, l'abrège, et se laisse fléchir pendant qu'elle dure |

**Pièges payés pendant la phase**

- **Une contradiction du doc 07**, tranchée : il annonce un départ « neutre à
  50 » tout en plaçant le palier Favorable à 50. À la lettre, huit bonus actifs
  pour qui n'a jamais mis les pieds au Temple. Départ à **40**.
- **Sekhmet promettait d'écarter une fièvre qui n'existait pas encore**, depuis
  le lot 6.0. Corrigée au 6.3, réveillée au 6.6.
- **Sobek promettait la pêche** : elle passe déjà par la qualité de direction du
  Port, c'eût été le multiplicateur de trop. Effet réduit à la navigation.
- **Une invention corrigée par la mesure** : j'avais écrit que les trois fêtes
  tombaient hors de la moisson. La Belle Fête de la Vallée est en pleine Chémou,
  là où les sources la placent. C'est l'affirmation qui a sauté, pas la date.
- **La branche « malédiction » n'aurait jamais tourné** : rien ne rendait un dieu
  hostile. Le piège d'`ajusterRenommee()` — une règle indexée sur une valeur que
  rien ne fait bouger — évité de justesse en lui donnant sa source, la famine.
- **Un arrondi vidait les épidémies de leur substance** : 20 % de quatre actifs
  fait zéro. La fièvre couche désormais au moins une paire de bras.
- **Mesurer Ptah sur un Grenier de deux cycles** ne montrait rien : les
  quinzaines sont entières. La leçon du lot 5.9, repayée une demi-fois.

**Ce que la phase laisse ouvert**

- **Les quêtes de temple** (doc 07 : +15 en réussite, −10 en échec) : elles
  supposent le système de quêtes de la campagne, Phase 8. Elles apporteront à
  l'hostilité une seconde source, aujourd'hui unique.
- **Les choix moraux** alignés ou contraires à un domaine : ils supposent la
  narration du fil rouge, Phase 7.
- **Isis et Thot** restaient offrables et inertes, et le disaient. Thot a
  trouvé son emploi au lot 7.7 ; il ne reste qu'Isis, pour la Phase 10.
- **Les divinités au-delà des huit** : le doc les laisse ouvertes, on s'en tient
  aux huit attestées.
- **Le barème d'offrande reste provisoire** — 30 deben pour amener un dieu au
  plafond d'un Temple de niveau 1, 80 pour atteindre Dévoué sous un niveau 6,
  contre ~39 deben la quinzaine de salaires. À reprendre au playtest.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Peut-on offrir **en ressources** autant qu'en deben ? | **Oui**, converties au cours du Marché |
| Le barème de 10 deben pour 5 points tient-il ? | **Oui pour le moment**, à corriger au playtest |
| Une malédiction peut-elle faire échouer une partie ? | **Non.** Elle retarde et elle coûte, elle ne termine jamais |

---

### 5.8 Phase 7 — Énigmes, enquêtes et fil rouge  ✅

**Sources** : doc `10` (énigmes, enquêtes, trois actes), `09` (lore et fil
rouge), `01` (Maison des scribes), `04` (rôles d'exploration), `08` (rivaux).

**Intention.** Le jeu savait faire prospérer une ville ; il ne savait rien
**raconter**. La Maison des scribes se construisait sans rien porter, les cases
d'événement de la carte étaient posées depuis le lot 3.2 et ne menaient nulle
part, et la commande du pharaon affichée au lancement n'avait pas de suite.
Cette phase branche les trois.

Elle porte aussi le seul objectif **pédagogique** explicite du projet : faire
découvrir l'écriture, l'iconographie et l'astronomie égyptiennes **en jouant**,
jamais par un encart documentaire.

| Lot | Contenu | |
|---|---|---|
| 7.0 | La clé de lecture : vingt signes, et la Maison des scribes qui les ouvre | ✅ |
| 7.1 | Le déchiffrage : remettre les sens en face des signes | ✅ |
| 7.2 | Devinettes, oracles, associations et astronomie | ✅ |
| 7.3 | Le dossier d'enquête : collecter des indices | ✅ |
| 7.4 | La déduction, et le droit de se tromper | ✅ |
| 7.5 | D'où viennent les indices : l'Éclaireur et l'Émissaire | ✅ |
| 7.6 | Le fil rouge en trois actes, sur la mission 1 | ✅ |
| 7.7 | Les deux Scribes, et Thot qui cesse d'attendre | ✅ |
| 7.8 | Les marchands rivaux, reportés de la Phase 5 | ✅ |

**Les règles qui en sortent**

- **Les signes sont vrais, les combinaisons sont du jeu.** Vingt hiéroglyphes de
  la liste de Gardiner, avec leur vrai code, leur vrai glyphe et une glose
  fidèle ; les inscriptions, elles, sont des **indices en rébus**, jamais des
  phrases d'égyptien. Le dire évite de faire croire au joueur qu'il apprend à
  lire.
- **Quatre signes sont connus d'emblée**, pour que la première énigme soit
  tentable avant d'avoir rien bâti — sans quoi le tutoriel de l'acte I
  n'ouvrirait sur rien.
- **La clé s'enrichit par deux voies, une seule est persistée** : ce que le
  niveau ouvre se calcule, ce qu'une énigme apprend se stocke.
- **Une énigme ne punit jamais** : se tromper sur une inscription ne coûte ni
  ressource ni cycle. Une énigme qui punit est une énigme qu'on cesse de
  tenter.
- **Une question à choix multiple ne se retente pas**, en revanche : avec
  quatre propositions et un droit de reprise, on essaie tout et il ne reste
  qu'un formulaire.
- **L'explication tombe qu'on ait raison ou tort.** Le vrai gain d'une énigme
  est ce qu'elle apprend ; la récompense passe.
- **Une enquête se démêle, elle ne se compte pas** : seuls les indices
  concordants rapprochent de la conclusion, et **le joueur ne sait jamais**
  lequel est une fausse piste.
- **Une principale se rejoue, une secondaire se perd** (décision de la
  joueuse) : le temps est la seule monnaie d'une erreur.
- **Un éclaireur va vers l'inconnu, un émissaire va vers les gens.**
- **L'acte d'un fil rouge se déduit, il ne se stocke pas.**
- **Un rival rogne, il ne ferme rien**, et c'est la renommée qui l'attire.

**Ce qui a trouvé son emploi**

| Posé depuis | Ce qui dormait | Réveillé par |
|---|---|---|
| Lot 3.2 | Le contenu `Evenement` d'une case | La fouille (7.3) |
| Lot 3.4 | Le rôle d'Émissaire, qui doublait l'éclaireur en trois fois plus cher | Les témoignages (7.5) |
| Lot 4.2 | Les spécialités Déchiffreur et Oraculaire | 7.7 |
| Phase 2 | La Maison des scribes, l'Auberge | La clé (7.0), les devinettes (7.2) |
| Phase 6 | Thot, dieu offrable et inerte | 7.7 |

**Pièges payés pendant la phase**

- **Deux contradictions de documents**, tranchées comme celle du doc 07 au lot
  6.0 : le doc 10 annonce « 4 signes aux niveaux 1-2, puis 2 par niveau,
  jusqu'à 20 au niveau 8 » — les trois nombres ne s'accordent pas.
- **Fouiller sans Maison des scribes** rendait un indice qu'aucun dossier ne
  recueillait. C'est le bâtiment qui conduit les enquêtes (doc 01) : sans lui,
  on ne fouille pas.
- **La réponse se lisait dans la source de la page** : jetons du déchiffrage,
  propositions d'énigme et conclusions d'enquête sont mélangés **au rendu**.
- **Une interaction bâtie sur le seul `dragstart`** est inutilisable au clavier,
  et aucun test fonctionnel ne le signalerait. Le clavier d'abord, la souris
  par-dessus.

**Ce que la phase laisse ouvert**

- **Les dix fils rouges** : Phase 8. Un seul est écrit, celui de la mission 1 —
  écrire les dix avant d'avoir joué le premier reviendrait à écrire dix fois la
  même erreur.
- **La répartition des énigmes secondaires par mission** (5 à 8, doc 10) :
  Phase 8. Le catalogue est commun et se filtre par les bâtiments dressés.
- **Le Chef d'expédition**, dernier rôle d'exploration sans emploi : Phase 10.
- **Le débauchage d'un contact commercial** par un rival (doc 08) : il suppose
  le carnet d'adresses de la Phase 9.
- **Isis** et le trait « Bagarreur », tous deux pour la Phase 10.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Le déchiffrage se fait-il par glisser-déposer ? | **Oui** — la manipulation des signes fait partie de ce qu'on apprend |
| Les énigmes secondaires sont-elles obligatoires ? | **Toutes les missions en portent, aucune n'est requise** pour finir la mission |
| Une enquête peut-elle échouer définitivement ? | **Une secondaire, oui. Une principale, non** — elle se rejoue jusqu'à être résolue |

---

### 5.9 Phase 8 — La campagne : dix missions, un pharaon chacune  *(à faire)*

**Sources** : doc `09` (lore, objectifs, quêtes de chantier, réussite
partielle), `11` (les dix régions et leur difficulté), `10` (les trois actes).

**Intention.** Dix missions existent déjà — leur région, leur ville, leur
pharaon, leur difficulté et leur carte. Ce qui manque est **ce qui en fait une
campagne** : on ne peut ni gagner une mission, ni passer à la suivante. Une
partie s'ouvre sur la commande d'un roi et n'a aucune façon de s'achever
autrement que par la famine.

À la fin de la phase, on doit pouvoir raconter : *« Avaris est repeuplée, la
route rouverte, et la stèle dressée. Ahmôsis me reconnaît une réussite aux
trois quarts — je n'ai pas atteint le volume d'échanges qu'il attendait. Il me
lègue quelque chose pour la suite, et Thoutmôsis Ier m'envoie à Saï. »*

| Lot | Contenu | |
|---|---|---|
| 8.0 | Les objectifs d'une mission, visibles dès le premier jour | ✅ |
| 8.1 | Mesurer l'avancement : ce que le jeu sait déjà compter, et ce qu'il ignore | ✅ |
| 8.2 | Achever une mission : réussite totale, réussite partielle | |
| 8.3 | Les quêtes de chantier du pharaon | |
| 8.4 | Les dix fils rouges | |
| 8.5 | Enchaîner : de la mission N à la mission N+1 | |
| 8.6 | Le legs du pharaon | |

#### 8.0 — Les objectifs, visibles dès le premier jour  ✅

Le doc 09 tranche deux choses, et elles vont ensemble : chaque mission combine
**un objectif narratif obligatoire** — le fil rouge résolu — et **deux à trois
objectifs quantitatifs** tirés d'un pool de six (richesse, population,
commerce, infrastructure, renommée, ressource précise).

**Ils sont affichés dès le début**, façon liste de quêtes : « la transparence
évite au joueur de découvrir tardivement des conditions qu'il n'a pas pu
anticiper ». Ce n'est pas un jeu de mystère sur ses propres objectifs.

Les seuils croissent avec la difficulté de la région (doc 09) : richesse
`200 + 50 × difficulté`, population `20 + 10 × difficulté`, commerce
`500 + 100 × difficulté`, ressource `100 + 20 × difficulté`, infrastructure
niveau `4 + difficulté / 3`, renommée palier `2 + difficulté / 4`.

**Les seuils ont été recalibrés sur l'économie du jeu** (décision de la
joueuse), pas recopiés : le document les a chiffrés avant les Phases 4 et 5, et
comptait encore en or comme si c'était la monnaie. Les documents du Drive
seront repris ensuite.

| Type | Doc 09 | Retenu | Étalon |
|---|---|---|---|
| Richesse | `200 + 50 × d` **en or** | `250 + 75 × d` **en deben** | La ville d'exemple du lot 4.6 dégage une trentaine de deben nets par quinzaine |
| Population | `20 + 10 × d` travailleurs | `12 + 4 × d` habitants | Deux cents parties de vingt ans : une ville à Quartier 1 monte à treize |
| Commerce | `500 + 100 × d` | `400 + 120 × d` | Un gros contrat vaut 40 deben |
| Ressource | `100 + 20 × d` | `60 + 15 × d` | Une extraction rend une vingtaine d'unités par quinzaine, au plein rendement |
| Infrastructure | `4 + d / 3` | inchangé | Seul seuil du document qui tienne : la borne régionale (`5 + d`) est plus généreuse à toutes les missions |
| Renommée | palier `2 + d / 4` | inchangé | Le Marché donne un point par gros contrat |

**Une contradiction entre documents, tranchée par l'histoire** : le doc 09
demande de l'or à Éléphantine, le doc 08 place les mines d'or ailleurs. Les
deux ont raison — Éléphantine était un **poste douanier**, l'or de Nubie y
transitait sans qu'on l'y extraie. L'objectif tient donc, mais par le commerce.
L'invariant testé n'est pas « la ressource est sur le terrain » mais **« un
chemin y mène »** : le terrain, ou une route que la région ouvre.

**Deux mesures n'existent pas encore** — la valeur échangée et la ressource
rapportée. L'écran l'écrit (« mesure à venir ») plutôt que d'afficher un zéro
qui ne bougerait jamais, et un objectif non mesuré n'est **jamais** compté
comme atteint. Un test verrouille la liste : elle doit rétrécir au lot 8.1,
jamais s'allonger.

**Un défaut d'ergonomie attrapé au passage** : le contrôleur d'onglets apparie
onglets et panneaux **par rang**. Ajouter le panneau « Mission » ailleurs que
dans l'ordre de son onglet décalait tout ce qui suit — on cliquait sur Mission
et l'on ouvrait les Bâtiments. C'est pour cela que le test compare les deux
listes **dans l'ordre**.

#### 8.1 — Mesurer l'avancement  ✅

Quatre des six mesures existent déjà : la trésorerie, la population, la
renommée et le niveau d'un bâtiment se lisent sans rien ajouter. **Deux
manquent** :

- **La valeur totale échangée** (Marché + convois) : rien ne la cumule
  aujourd'hui. C'est un compteur à ajouter sur la partie, alimenté aux deux
  endroits où des deben changent de main.
- **La quantité d'une ressource « récoltée/rapportée »** : à distinguer du
  stock courant, qui monte et descend. Un objectif atteint puis dépensé doit
  rester atteint — sans quoi on serait puni d'avoir joué.

**Les deux comptes se tiennent au seul passage obligé.** Les ressources
rapportées se cumulent dans `City::crediterRessources()` — même raison que le
plafond de réserve : le poser ailleurs obligerait chaque nouvelle source à s'en
souvenir, et l'une d'elles finirait par l'oublier. La valeur échangée se compte
au Marché et **au retour d'un convoi**, jamais à son départ : au départ, la
marchandise est engagée, elle n'est pas encore échangée.

**Le piège du lot 4.8, évité autrement qu'annoncé.** Le lot 8.0 portait un
drapeau `seMesureDeja()` disant quels types n'étaient pas encore mesurés ; une
fois les six mesures en place, ce drapeau devenait un mensonge que PHPStan a
signalé (une branche `null` devenue inatteignable). Il a été **remplacé par un
test qui fait bouger chaque mesure, une par une** — un drapeau déclaratif
n'aurait de toute façon pas empêché un objectif indexé sur une valeur inerte ;
ce test-là, si, et tout type ajouté au pool y tombera tant que rien ne le fait
avancer.

#### 8.2 — Achever une mission

**Pas de « game over » brutal** (doc 09), et la réussite partielle est tranchée
par le document : la mission **se termine quand même** si le fil rouge est
résolu, même objectifs quantitatifs non atteints. Le score vaut
`objectifs atteints / objectifs totaux`, affiché comme un résultat partiel et
non comme un échec.

Conséquence sur `StatutDePartie`, qui ne connaît aujourd'hui que `EnCours` et
`Echouee` : il lui faut un état **achevé**, avec son score. Une partie achevée
reste consultable, comme une partie échouée — c'est le principe du doc 00.

**Une couture à traiter** : la famine reste la seule cause d'échec. Une mission
achevée ne doit pas pouvoir basculer en échec ensuite, ni l'inverse.

#### 8.3 — Les quêtes de chantier

Le pharaon commanditaire réclame ponctuellement des ressources pour un chantier
qu'il a **réellement fait bâtir** : la pyramide d'Ahmôsis à Abydos, les
obélisques de Thoutmôsis Ier, Deir el-Bahari pour Hatchepsout, l'Akh-menou de
Thoutmôsis III, les talatat d'Akhenaton, Médinet Habou pour Ramsès III.

Tous les quatre cycles, 20 à 50 unités, `+5` de renommée et `+10` de faveur
envers la divinité du monument. **Refuser coûte 2 points de renommée et rien
d'autre** — le joueur reste libre de sa stratégie.

Chaque quête porte **deux ou trois phrases pédagogiques** sur le monument
réel : c'est la même exigence qu'aux lots 7.0 et 7.2, et le même garde-fou —
ce qu'on apprend doit être vrai.

#### 8.4 — Les dix fils rouges

La mission 1 en a un (lot 7.6). Les neuf autres suivent la même structure en
trois actes, chacun avec son obstacle local cohérent avec son pharaon et son
type de mission : **fonder** (Akhetaton, Malkata, Saï), **restaurer ou
développer** (Avaris, Shedet, Serabit, Mersa Gaouasis, Éléphantine),
**sécuriser** (Megiddo).

**C'est le plus gros morceau d'écriture de la phase**, et il vient après avoir
joué le premier : chaque fil demande une inscription d'ouverture lisible avec
la clé du moment, une enquête avec ses indices et ses fausses pistes, et une
énigme finale.

#### 8.5 — Enchaîner les missions

L'ordre est imposé, de 1 à 10 (décision déjà actée). Ce qui manque est la
**transition** : une mission achevée ouvre la suivante, et le joueur la lance
depuis son compte. Rien ne se transporte d'une ville à l'autre — sauf le legs.

**La mission 9 est un cas à part** (doc 09) : un camp minier temporaire, pas une
ville. Le document laisse ouvert s'il suit les mêmes règles ou un système
allégé. À trancher.

#### 8.6 — Le legs du pharaon

À la réussite, totale ou partielle : un bonus de renommée proportionnel au
score, et un **objet légué** — un petit avantage de départ pour la mission
suivante. C'est le premier fil entre deux parties, et la porte d'entrée de la
Phase 9 (héritage familial, doc 13).

**Le tenir modeste** : un legs qui change l'équilibre d'une mission rendrait la
suivante dépendante de la précédente, ce qui punirait une réussite partielle
deux fois.

#### Hors périmètre, explicitement

- **L'héritage familial complet** et le carnet de contacts : Phase 9.
- **L'héritage commercial inter-missions** (doc 12), reporté de la Phase 5 :
  il suppose l'enchaînement, donc il devient possible — mais il relève de la
  Phase 9, avec le reste de ce qui se transmet.
- **Le mode Aventure** : Phase 11.

#### À trancher avec la joueuse

| Question | Enjeu |
|---|---|
| La mission 9 (camp minier) suit-elle les mêmes règles ? | Le doc laisse le choix entre règles communes et système allégé. Une ville sans habitants ni logement changerait beaucoup de mécaniques |
| Le legs doit-il être **cosmétique** ou donner un vrai avantage ? | Le doc dit « bonus cosmétique ou petit avantage ». Un avantage rend les missions dépendantes ; du cosmétique risque de ne rien peser |
| Les objectifs quantitatifs se **recalibrent-ils** sur l'économie mesurée ? | Les seuils du doc 09 datent d'avant les Phases 4 et 5, et l'un d'eux parle encore d'or comme monnaie |

#### Définition de « fini »

Parcours de bout en bout : lancer la mission 1 → voir ses objectifs dès le
premier écran → les voir avancer → résoudre le fil rouge → recevoir la
reconnaissance du pharaon, totale ou partielle → recevoir un legs → lancer la
mission 2 depuis son compte.

Tests sur les invariants : un objectif atteint le reste, une mission achevée ne
bascule jamais en échec, refuser une quête de chantier ne bloque rien, et
**aucun seuil n'est inatteignable** — un test qui vérifie, région par région,
que les objectifs tiennent dans ce que la carte et l'économie permettent. C'est
l'exercice d'`OuvertureDePartieTest`, appliqué à la fin d'une mission plutôt
qu'à son début.

---

### 5.10 Phases 9 à 12 — à cadrer

Chacune traduit un document déjà spécifié ; le cadrage technique se fera à son
tour, comme pour les précédentes.

| Phase | Sujet | Ce qu'elle apporte |
|---|---|---|
| **9** — Renommée et héritage (`13`) | Succession familiale, carnet de contacts | La renommée existe déjà et bouge ; c'est l'héritage entre parties qui manque |
| **10** — Medjaÿ et combat (`03`) | Recrutement militaire, équipement, zones à bandits | Réveille le trait « Bagarreur », l'usage des armes de la Forge, et le Chef d'expédition, dernier rôle d'exploration sans emploi |
| **11** — Mode Aventure (`14`) | Memphis, succession des règnes, partie sans fin | Le mode existe déjà comme choix au lancement, sans contenu propre |
| **12** — Sprites (`15`) | Découpage et intégration des 18 planches | Hors planche « tuiles », déjà intégrée en Phase 3 |

---

## 6. Valeurs inventées, à calibrer en playtest

Aucun document ne les chiffre ; toutes sont signalées comme telles dans le
code. Le tableau porte les valeurs **courantes** — plusieurs ont été revues par
le calibrage du lot 4.6.

| Valeur | Retenue | Où |
|---|---|---|
| Récolte d'un champ, à la récolte | 25 | `RendementDesChamps::RECOLTE_DE_REFERENCE` |
| Extraction d'un gisement | 20, avant rareté régionale | `Recoltes::EXTRACTION_DE_REFERENCE` |
| Pêche d'une pêcherie | 10, sans rareté régionale | `Recoltes::PECHE_DE_REFERENCE` |
| Cycle agricole terrestre | semis 1 / pousse 3 / récolte 2 / repos 1 | `CycleAgricoleTerrestre` |
| Provisions d'un éclaireur, hors rayon gratuit | 5 vivres | `RoleDExploration::provisions()` |
| Rayon gratuit de l'éclaireur | < 3 cases, deben et vivres | `RoleDExploration` |
| Salaire d'un chef | `2 + compétence × 0,12` (4 à 14 deben) | `GenerateurDeCandidat` |
| Salaire d'un travailleur | 1 deben | `Salaires::SALAIRE_DUN_TRAVAILLEUR` |
| Bonus de niveau du bâtiment gouvernant | +10 % par niveau | `Effectifs::BONUS_PAR_NIVEAU_GOUVERNANT` |
| Naissances | 1 chance sur 10 par actif et par an | `Population::CHANCE_NAISSANCE_PAR_ACTIF` |
| Coût d'un appel d'habitants | 30 à 5 deben selon le palier | `PalierDeRenommee::coutDAppel()` |
| Marge de dotation par difficulté | +10 deben par niveau | `DotationRoyale` |
| Part de terre broussailleuse | 15 % des cases centrales | `GenerateurDeCarte::PART_DE_TERRE_CLASSIQUE` |
| Poids du bois local | ×3 sur la broussaille, 15 % sur la terre fertile, nul ailleurs | `GenerateurDeCarte::poidsDuBoisLocal()` |
| Seuil d'un « gros contrat » (+1 renommée) | 40 deben | `Marche::RECETTE_DUN_GROS_CONTRAT` |
| Famine — mécontentement, puis échec | 4 puis 12 quinzaines | `Subsistance` |
| Mécontentement — seuil, malus, plafond | 2 quinzaines, −30 %, 12 | `Mecontentement` |
| Départ d'un chef | `100 / ancienneté` % par quinzaine, doublé si mécontentement | `DepartsNaturels` |
| Facteur de compétence d'un chef | `90 + compétence × 0,4` (98 % à 130 %) | `EffetDeChef` |
| Bonus du Gestionnaire du Grenier | +15 % | `EffetDeChef::BONUS_GESTIONNAIRE` |

**Une leçon de méthode, payée en Phase 3** : quatre valeurs de population
avaient été inventées alors que les docs 01 et 02 les chiffraient (consommation,
capacité du Quartier, vivier régional). Avant d'inventer, vérifier que le
document ne dit rien.

---

## 7. Pipeline des assets graphiques

Les **18 planches** décrites dans le document 15 sont **déjà générées** et
présentes dans le sous-dossier `Sprites/` du Drive (bâtiments ×12, carte,
ressources brutes, ressources importées, objets fabriqués, icônes d'interface,
divinités). Aucune génération d'image à refaire — il reste un travail de
découpage et d'intégration.

1. Récupérer les 18 JPEG depuis `Sprites/` et les découper en sprites individuels
   (grille 2×2 pour les bâtiments, 4×2/5×3/4×4 selon la planche)
2. Exporter chaque sprite en PNG avec fond transparent, nommage cohérent
   (`batiment_grenier_palier1.png`…)
3. Ranger dans `app/assets/images/sprites/`, servis via AssetMapper
4. Vérifier la lisibilité des planches denses (ressources brutes 15 items, icônes
   interface 16 items) — le doc 15 anticipe déjà un re-découpage en 2 si besoin

La planche « tuiles » est **découpée dès la Phase 3** (lot 3.3) : la carte se
dessine avec elle. Le reste peut attendre.

**Attention au format.** La planche « tuiles » livrée contient des losanges
**isométriques** sur fond sombre opaque, alors que son prompt du doc 15 demandait
des tuiles carrées vues de dessus et à fond transparent. C'est la direction
artistique générale qui l'a emporté sur le prompt — cohérent, mais le prompt de
la planche 13 mériterait d'être corrigé dans le document.

---

## 8. Qualité et outillage

Skills à mobiliser au fil du développement, pas en revue finale :

| Skill | Quand |
|---|---|
| `symfony-coding-standards` | À chaque fichier `.php`/`.twig` écrit ou modifié |
| `phpstan-analysis` | En continu, typage strict dès la Phase 0 |
| `phpunit-testing-standards` | Tests unitaires/intégration pour chaque service |
| `functional-e2e-testing` | Si Behat est introduit pour les scénarios de jeu (voir §2) |
| `web-security-checklist` | Revue dédiée avant merge de l'inscription/connexion (OWASP) |
| `web-accessibility-a11y` | Formulaires et navigation dès la Phase 1 (WCAG 2.2 AA) |
| `git-commit-conventions` | Tout au long, dès le premier commit |
| `ci-pipeline-standards` | Mise en place du pipeline en Phase 0 |
| `code-review` / `code-audit-orchestrator` | Revue de chaque lot livré |

L'infrastructure Docker suit `.claude/rules/stack-conventions.md`, qui fait
autorité sur toute question d'arborescence, nommage, ports et `.env`.

---

## 9. Décisions actées

| Question | Décision |
|---|---|
| Nom de famille | Choisi **au lancement d'une partie**, pas à l'inscription |
| Vérification d'email | **Oui**, mais non bloquante. 7 jours de grâce, puis suppression définitive du compte non vérifié |
| Mot de passe oublié | **Inclus dès la Phase 1** |
| Base de données | **PostgreSQL** |
| Hébergement | Pas de choix figé — **VPS envisagé** pour la production ultérieure. Plan indépendant de l'hébergeur |
| Parties simultanées | **Oui**, jusqu'à **5** `GameSave` actifs par compte |
| CSS | **Tailwind CSS 4.3** via `symfonycasts/tailwind-bundle`, pas de Node.js |
| Serveur staging/prod | **Dédié** (`--dedicated-server`), pas de Traefik partagé |
| Stock des ressources | **Générique** (table ressource → quantité), migré dès le lot 3.1 |
| Génération de la carte | **À la création de la partie** |
| Écran de carte | **Grille isométrique**, avec les tuiles du Drive |
| Abandon d'une partie | **Suppression définitive**, derrière confirmation |
| Ordre des missions | **Imposé**, de la mission 1 à la 10 |
| Reprise de partie | **Récapitulatif d'état** avant de rendre la main |
| Coûts de construction | **Ressources nommées**, jamais de générique « bois »/« pierre » ; un coût se paie exactement avec ce qu'il nomme. La **fondation** est en matériaux seuls, le **deben** n'intervient qu'à partir du niveau 2 — sauf Temple et Port |
| Le bois | **Deux ressources distinctes** : le bois local (acacia, sycomore) qu'on ramasse au bord du Nil et dont tout bâtiment est charpenté, et le cèdre du Levant, importé et réservé au prestige |
| Terre broussailleuse | La « terre classique » du doc 02 est un **terrain**, pas un contenu : jamais cultivable, elle porte le bois local et ne se sème que dans les régions à Nil |
| Gisements par case | **Jusqu'à deux**, jamais deux fois le même matériau |
| Offrandes | **En deben ou en ressources**, au choix, converties au cours du Marché |
| Échec d'une partie | **La famine seule** y mène. Aucun événement, aucune malédiction ne termine une partie directement |
| Placement de la ville | **Le Nil en priorité** s'il existe, sinon tout point d'eau, sinon terre fertile — jamais en plein désert |
| Gisements non alimentaires près de la ville | **Un seul exemplaire** dans l'anneau des 8 cases, plafonné même par le tirage aléatoire |
| Cycle agricole | **Quatre étapes** (semis/pousse/récolte/repos) ; le Nil suit la saison, la terre suit son propre compteur ; aucune nourriture hors récolte |
| Rayon gratuit de l'éclaireur | **< 3 cases** : entièrement gratuit, or et vivres compris ; au-delà, les deux sont dus |
| Échec de partie | **Famine prolongée** → partie « échouée », conservée et consultable, jamais supprimée |
| Port | Constructible **dès qu'un point d'eau jouxte la ville**, sans autre condition ; il débloque la pêche, son niveau ne change rien encore |
| Poisson | **Renouvelable** — la seule ressource du jeu qui ne s'épuise jamais, sans quoi un Port coûteux deviendrait un piège |
| Monnaie | Le **deben**, unité de compte pondérale du Nouvel Empire — l'Égypte pharaonique n'a pas de monnaie frappée. L'**or** redevient un métal qu'on extrait et qu'on vend |
| Granularité de la population | **Trois nombres, jamais des individus** : habitants, actifs, inactifs (enfants et anciens). Aucun âge, aucun foyer n'est suivi |
| Logement | Le Quartier d'habitation **plafonne** la population (`20 × niveau` maisonnées), il ne la produit jamais |
| Bilan démographique | **Une fois l'an**, pas à chaque quinzaine : des enfants entrent dans la vie active, des actifs passent la main, la mort prend sa part |
| Naissances | **Oui, mais seulement s'il y a de la place** : nulles quand les maisons sont pleines. La ville se maintient seule, elle ne grandit qu'en faisant venir du monde |
| Mécontentement | **Deux causes, un seul mécanisme** : la faim et les salaires impayés. Il monte et se résorbe d'un cran par quinzaine |
| Qui travaille | **Tous les actifs, sans distinction de sexe** : les Égyptiennes filaient, tissaient, brassaient, moissonnaient, et exerçaient des métiers attestés |
| Ce qu'on recrute | **Des chefs seulement**, qui s'installent avec leur maisonnée ; les ouvriers se puisent parmi les actifs déjà résidents |
| Arrivée d'habitants | Les **volontaires du pharaon** à l'ouverture, puis une **action du joueur** adossée à la renommée — et impossible sans logement disponible |
| Ration alimentaire | **1 vivre par actif, une demi-ration par inactif**, par quinzaine |
| Salariés du territoire | **1 par champ, 2 par gisement, 1 par pêcherie** : rien ne s'exploite tout seul. Le niveau du Grenier, de l'Entrepôt et du Port augmente équipage **et** rendement de l'exploitation qu'il gouverne |
| Poste vacant | **Tout tourne au moins à moitié**, bâtiments comme exploitations — aucune impasse possible, et l'emploi devient un investissement plutôt qu'une taxe |
| Salaires impayés | Le poste **s'arrête**, puis mécontentement et départs — même mécanisme que la famine |
| Salaire des travailleurs | **Dû**, en forfait par tête, bien inférieur à celui d'un chef |
| Dotation royale | De quoi dresser **les quatre bâtiments d'ouverture** (Quartier, Grenier, Marché, Entrepôt), plus un an de vivres et un an de salaires. Aucune marge en matériaux : le pharaon finance le démarrage, pas la suite |
| Ouverture de partie | La ville doit **rouler dès la première quinzaine** : ses quatre bâtiments engageables, un champ et une carrière de chaque matériau ouvrables — rien qui bloque |
| Famine | **Deux paliers** : mécontentement à 4 quinzaines (production ralentie, départs anticipés, renommée en baisse — doc 02), échec seulement à 12 |
| Départ d'un chef | **Tiré à chaque quinzaine** sur son ancienneté annoncée ; il repart avec sa maisonnée, comme au renvoi |
| Effet d'un chef | Il module la **qualité de direction** du bâtiment, aux côtés de l'effectif — jamais un multiplicateur de plus sur la base. **Un mauvais chef reste meilleur que pas de chef** |
| Spécialités sans système d'accueil | **Tirées et affichées, mais inertes**, et l'interface le dit — promettre un bonus qui ne s'applique nulle part tromperait le joueur au moment du choix |
| Affichage des PNJ | **Chiffré en interne, qualitatif à l'écran** (doc 03) : étoiles et libellés, jamais de compétence brute. Le salaire fait exception, déjà qualitatif par nature |
| Noms des PNJ | **Aucun pour l'instant** : un employé se désigne par son poste, comme dans les documents |
| Chiffres de conception | **Provisoires par nature**, dans les documents comme dans le code. Ils se rectifient au fil de la conception ; le critère est l'équilibre et le fait de **pousser le joueur à se servir des mécaniques**, pas la fidélité au document |
