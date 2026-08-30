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
| `Expedition` | ✅ | Un éclaireur en route vers une case | 04 |
| `StockDeRessource` | ✅ | Une ligne du stock de la ville (ressource → quantité), deben compris | 08 |
| `Employee` | ✅ | Un chef en poste : compétence, salaire, spécialité, la maisonnée qu'il a amenée | 03, 05 |
| `JobOffer` | ✅ | Une annonce affichée et son tirage de candidats, figé | 03 |
| Recettes, ordres et convois (Phase 5) | — | Ce qu'on fabrique, ce qu'on vend, ce qui voyage | 08, 12 |
| … (Phase 6+) | — | Medjaÿ, faveur divine, énigmes | 03, 07, 10 |

`Family`, `City` et tout ce qui s'y rattache (`Zone`, `Building`, `Chantier`,
`Expedition`, `Employee`, `JobOffer`) sont détenus par leur `GameSave` : l'abandon d'une partie, comme
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
| **5** | Artisanat et commerce | `08`, `12`, `01` | à faire |
| **6** | Faveur divine et événements | `07` | à cadrer |
| **7** | Énigmes, enquêtes et fil rouge | `10` | à cadrer |
| **8** | Campagne : les 10 missions et leurs objectifs | `09`, `11` | à cadrer |
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

Les cinq premières sont livrées et résumées ici. Le détail des pièges déjà
payés et des conventions qui en découlent vit dans [`CLAUDE.md`](../CLAUDE.md) ;
l'historique complet est dans les messages de commit.

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

### 5.6 Phase 5 — Artisanat et commerce  *(à faire)*

**Sources** : docs `08` (ressources, recettes, prix, rivaux), `12` (routes
commerciales par mission), `01` (Atelier, Forge, Entrepôt, Port, capacités de
stockage).

**Intention.** La ville sait produire des matières premières et les vendre au
Marché local. Elle ne sait ni les **transformer**, ni aller chercher ce que sa
région ne porte pas. C'est le blocage identifié dès le lot 3.5 : **seul le
Delta est autosuffisant** en matériaux parmi les dix régions ; à partir de la
mission 2, le commerce cesse d'être un confort pour devenir une condition de
jouabilité.

À la fin de la phase, on doit pouvoir raconter : *« mon argile part à
l'Atelier, où trois potiers en tirent des jarres pendant deux quinzaines.
J'ouvre une route vers Byblos en y envoyant une première caravane, j'annonce
que j'achète du cèdre à douze deben et que je vends mon lin à cinq. Trois
quinzaines plus tard, un navire entre au port avec le cèdre ; le mien est
reparti chargé de lin. »*

**Le principe de commerce universel du doc 08** structure toute la phase :
n'importe quelle ressource peut être achetée ou vendue, quelle que soit sa
catégorie. Le Marché et l'Entrepôt sont des points d'échange généralistes, pas
des catalogues fermés. La catégorie ne dit qu'une chose : **où l'obtenir sans
commercer**.

#### 5.0 — Les ressources fabriquées  ✅  *(prérequis)*

Dix ressources de plus dans `Ressource`, avec leurs prix (doc 08) : poterie,
pain, bière, vannerie, papyrus, sandales, tissus pour l'Atelier ; outils et
armes pour la Forge ; bijoux, statuettes et vases pour le craft de luxe.

Aucune n'est trouvable sur la carte : elles n'existent que par le craft ou par
l'import. `Ressource` gagne donc de quoi le dire — une ressource fabriquée ne
doit jamais pouvoir être tirée par `GenerateurDeCarte`, et l'invariant mérite
son test.

**Prix à établir** : le doc 08 chiffre les matières premières mais **pas les
objets fabriqués**. Ils s'en déduisent : un objet doit valoir nettement plus
que la somme de ses ingrédients, sans quoi personne ne fabriquerait rien. Le
rapport est à calibrer, et c'est le premier arbitrage économique de la phase.

##### Livré

Douze ressources, et non dix : sept à l'Atelier, deux à la Forge, trois au
craft de luxe.

**La marge de transformation est de 165 %** — un objet vaut environ deux tiers
de plus que la matière et le deben qu'on y met. En deçà, personne ne
fabriquerait : vendre brut irait aussi vite sans immobiliser l'Atelier. Au-delà,
vendre brut n'aurait plus jamais de sens et la moitié du commerce
disparaîtrait. Le chiffre n'est pas posé au jugé mais **mesuré sur les dix
recettes du doc 08** : de 159 % à 171 %, moyenne 165 %.

Deux décisions prises en écrivant :

- **Le pain et la bière nourrissent.** Ce sont les deux formes sous lesquelles
  l'Égypte consommait réellement son grain, et les ostraca de Deir el-Médineh
  paient les ouvriers en pains et en cruches, pas en épis. Une ville pourra
  donc manger ce qu'elle fabrique — sans quoi cuire du pain produirait un
  objet immangeable, ce qui serait absurde.
- **La Forge n'est chiffrée nulle part** par le doc 08, ni ses recettes ni ses
  prix. Outils et armes sont comptés sur quatre à cinq cuivres, l'arme
  demandant plus de travail que l'outil.

**Un test à refaire au lot 5.2** : la marge est vérifiée contre une copie des
recettes du doc 08, inline dans le test. Le lot 5.2 les réécrit à plusieurs
ingrédients (décision de la joueuse) — le coût de production changera, et le
test devra s'adosser au vrai catalogue plutôt qu'à cette copie. La marge devra
tenir malgré tout.

#### 5.1 — Capacité de stockage : plafonner sans périmer  ✅  *(décision de la joueuse)*

Le stock est illimité depuis le lot 3.1. Il cesse de l'être : le **Grenier
plafonne les denrées** et l'**Entrepôt les matériaux et objets** (doc 01), le
plafond suivant leur niveau.

**Le surplus est perdu à l'entrée, il ne se dégrade pas** (décision de la
joueuse) : une récolte qui déborde ne rentre pas, mais ce qui est en réserve y
reste indéfiniment. La péremption progressive du doc 01 est écartée — c'est un
système d'inventaire à part entière, et le plafond suffit à donner un effet aux
niveaux.

**Le piège à éviter** : un plafond qui fait silencieusement disparaître une
moisson est le genre de règle qu'un joueur subit sans comprendre. L'écran doit
annoncer la saturation **avant** qu'elle ne coûte quelque chose, et le passage
de cycle doit dire ce qui a été perdu.

**À vérifier avant d'écrire** : la dotation royale d'ouverture ne doit pas
dépasser le plafond de départ, sans quoi le pharaon ferait un cadeau dont une
partie s'évaporerait à la première quinzaine.

##### Livré

**Deux réserves, jamais une seule** : le Grenier tient les vivres, l'Entrepôt
les matériaux et les objets. Chacune a une **réserve de base** — la Résidence
familiale a ses propres jarres — que le niveau du bâtiment élève : 100 par
niveau de Grenier (doc 01), 150 par niveau d'Entrepôt (inventé, le document
décrivant sa capacité sans jamais la chiffrer).

**Le deben n'est pas stocké.** La monnaie n'occupe ni grenier ni entrepôt et
n'a donc aucun plafond : c'est ce qui fait de la vente l'issue au surplus, une
valeur qui ne déborde jamais. Sans cette exception, le plafond aurait bloqué la
seule sortie qu'il est censé pousser à prendre.

**Les ressources d'une même réserve se partagent son plafond** : ranger des
roseaux, c'est autant de moins pour l'argile. Un compteur par ressource
n'aurait donné aucune raison de monter l'Entrepôt, et personne ne l'aurait
surveillé.

**Le plafonnement est fait dans `City::crediterRessources()`**, seul point
d'entrée du stock, pour qu'aucun chemin ne puisse l'oublier. `surplusRefuse()`
dit **avant** de créditer ce qui restera dehors — c'est sur cette promesse que
le passage de cycle annonce la perte.

**Calibrage mesuré**, la vérification que le lot réclamait :

| | Plafond | Dotation | Saturation à deux carrières |
|---|---|---|---|
| Sans bâtiment | 250 / 250 | 70 % / 57 % | 7 quinzaines |
| Grenier et Entrepôt 1 | 350 / 400 | — | 10 quinzaines |
| Niveau 5 | 750 / 1000 | — | 25 quinzaines |

La dotation tient avec de la marge — **et la marge compte autant que le
plafond** : une réserve qui la contiendrait au ras ferait démarrer la ville
saturée, et le joueur perdrait sa première extraction avant d'avoir compris
qu'il a un plafond. Une première version calibrée à 150 faisait exactement
cela, à 95 % dès la première quinzaine.

**Le piège est traité des deux côtés** : la barre de jeu affiche chaque réserve
avec son plafond et vire au rouge à 85 %, et le passage de cycle dit ce qui a
débordé. Prévenir avant, constater après.

#### 5.2 — L'Atelier : des ordres de fabrication  *(décision de la joueuse)*

**Fabriquer prend du temps et consomme plusieurs ressources** — la joueuse a
tranché contre un craft instantané à un seul ingrédient : « fabriquer plusieurs
pièces sur un cycle, avec plusieurs ressources ».

- Le joueur lance un **ordre de fabrication** : une recette, une quantité. Les
  ressources sont **débitées à l'engagement**, comme un chantier — on ne
  réserve pas, on paie.
- L'ordre occupe l'Atelier pendant plusieurs quinzaines, et les pièces
  entrent au stock **à l'achèvement**, jamais avant. C'est la même règle que
  les champs : rien ne rentre hors de la récolte.
- Le **niveau de l'Atelier** débloque les recettes (doc 08 : poterie et pain
  au niveau 1, tissus au niveau 4) **et** élargit la taille d'un ordre.
- Les **ouvriers de l'Atelier** décident du rythme : la règle du demi-rendement
  s'applique, un Atelier désert produit deux fois plus lentement.

**Écart assumé au doc 08** : ses recettes ne portent qu'un seul ingrédient —
poterie = 5 argile, pain = 5 blé. La joueuse en veut de vraies, à plusieurs
matières. Elles sont donc à réécrire sur une base historique : une poterie
demande de l'argile **et** de la paille de dégraissant, du pain demande du blé
**et** du combustible. Le document sera à mettre à jour en conséquence.

**Un arbitrage à surveiller** : transformer doit rapporter davantage que
vendre la matière brute, **et** immobiliser l'Atelier doit avoir un coût
d'opportunité. Si l'un des deux manque, le craft devient soit inutile, soit
évident.

#### 5.3 — La Forge : outils et armes

Même mécanique que l'Atelier — ordres de fabrication, déblocage par niveau —
sur une matière que le Delta ne porte pas : le **cuivre**. La Forge est donc le
premier bâtiment dont la production **dépend du commerce**, ce qui en fait la
démonstration du lot suivant.

Les armes n'ont aucun usage avant la Phase 10 (Medjaÿ, combat) ; les outils
non plus tant que rien ne les consomme. **Les deux se vendent**, ce qui suffit
à les rendre utiles — mais l'interface doit dire que leur usage propre viendra
plus tard, comme elle le fait déjà pour les traits et les spécialités
endormis.

#### 5.4 — Les partenaires commerciaux

Contenu pur, non persisté (`src/Game/`), tiré du doc 12 : chaque mission a ses
routes attestées, terrestres, fluviales et maritimes.

Un partenaire porte un nom, une **distance en quinzaines**, un type de route,
ce qu'il **vend** et ce qu'il **achète**. Byblos vend du cèdre et achète du
lin ; Pount vend de l'encens et de la myrrhe et n'achète presque rien, la
région étant un point de transit.

Chaque partenaire porte aussi une **fourchette de prix acceptables** par
ressource — c'est elle qui donne un sens au prix que le joueur affiche
(lot 5.6). Ces fourchettes sont à inventer : le doc 08 pose les prix locaux et
une majoration d'import de ×1,5, jamais une marge de négociation.

**Les distances sont à inventer** aussi : le doc 12 nomme les routes sans les
chiffrer. Elles doivent rester lisibles — Byblos plus loin que Memphis, Pount
plus loin que tout.

#### 5.5 — Ouvrir une route

**Ouvrir une route, c'est envoyer une première caravane** (décision de la
joueuse) : le geste déclare à une cité qu'on est prêt à commercer avec elle.
Il coûte 100 deben par voie terrestre, 150 par voie maritime (doc 12, l'or y
devenant le deben) et prend le temps du trajet.

- Les routes **terrestres** passent par l'**Entrepôt**, les routes
  **maritimes et fluviales** par le **Port** (doc 12). Sans le bâtiment, la
  route n'est pas ouvrable.
- Le **volume échangeable par quinzaine** suit le niveau du bâtiment :
  `10 × niveau` pour une caravane, `15 × niveau` pour un navire (doc 12).
- Une route reste ouverte une fois payée.

**Hors périmètre, et c'est une dépendance à énoncer** : l'**héritage
commercial inter-missions** du doc 12 (−20 % sur l'ouverture d'une route déjà
exploitée) suppose que la campagne enchaîne réellement ses missions. Elle n'en
compte qu'une jouable aujourd'hui. Écrire l'héritage maintenant serait écrire
du code que rien n'exerce.

#### 5.6 — Annoncer ce qu'on vend et ce qu'on achète

Le cœur de la décision du joueur, et ce qui distingue ce commerce d'un robinet.

Sur une route ouverte, le joueur pose des **ordres permanents** : « je vends du
lin à 5 deben », « j'achète du cèdre jusqu'à 12 ». Chacun porte une ressource,
un sens, un prix et une quantité maximale par convoi.

**Le prix est le levier, et le seul.** Chaque partenaire a sa fourchette : trop
gourmand à la vente, personne n'achète ; trop pingre à l'achat, rien n'arrive.
Généreux, les convois se pressent. C'est ce qui rend le commerce **jouable
plutôt que subi** — le joueur ne clique pas « échanger », il tient un étal et
en fixe les prix.

Un ordre de vente ne part que si le stock suit ; un ordre d'achat ne se conclut
que si la bourse suit. Ni l'un ni l'autre ne doit **jamais** vider la ville
sans prévenir : la limite par convoi existe pour ça.

#### 5.7 — Le trafic : caravanes et navires en chemin

La résolution, à chaque quinzaine, sur le modèle des expéditions du lot 3.4 —
même forme, même absence de persistance dans le service.

- **Nos convois partent** chargés de ce que nos ordres de vente proposent et
  que le partenaire accepte à ce prix.
- **Les leurs arrivent** avec ce que nos ordres d'achat réclament, et
  repartent avec ce qu'ils nous prennent.
- Chacun met le **temps de la distance** à l'aller comme au retour. Un convoi
  en chemin est visible, et le joueur voit ce qu'il transporte et quand il
  arrive.

**Le point à ne pas rater** : un convoi parti est un engagement pris. Si le
stock a fondu entre le départ et l'arrivée, c'est trop tard — la marchandise
est partie avec lui, débitée au départ. Débiter à l'arrivée permettrait de
vendre deux fois la même chose.

#### 5.8 — Le craft de luxe

Second jeu de recettes de l'Atelier, débloqué non par l'Atelier mais par
l'**Entrepôt au niveau 8** (docs 01 et 08) : bijoux, statuettes, vases. Il
réclame des matières que la région ne porte pas, et n'est donc atteignable
qu'une fois le commerce établi — c'est la progression économique voulue par le
doc 08, artisanat courant puis artisanat de prestige.

À écrire **en dernier**, parce qu'il ne démontre rien tant que les routes ne
livrent pas.

#### 5.9 — Les chefs de l'Atelier, de la Forge et de l'Entrepôt

Sept spécialités dorment depuis le lot 4.8 faute d'un système d'accueil :
Potier, Papyrussier, Vannier, Tisserand, Brasseur, Armurier, Outilleur, plus
le Négociateur et le Logisticien de l'Entrepôt. Elles se réveillent ici.

Elles passent par le canal existant — la **qualité de direction**
(`EffetDeChef`) —, jamais par un multiplicateur de plus. Le Négociateur élargit
la fourchette de prix acceptée par les partenaires, le Logisticien raccourcit
les trajets (doc 03).

**L'invariant du lot 4.5 s'applique** : avant d'ajouter un facteur à une
production, vérifier ce qui s'y applique déjà.

#### Hors périmètre, explicitement

- **Les marchands rivaux** (doc 08) : reportés en bloc après les enquêtes
  (Phase 7, doc 10), décision de la joueuse. L'une de leurs trois issues est
  une enquête ; écrire le système sans elle reviendrait à le réécrire ensuite.
- **La péremption du surplus** (doc 01) : le lot 5.1 plafonne sans dégrader.
- **L'héritage commercial inter-missions** (doc 12) : suppose une campagne qui
  enchaîne ses missions, ce qui relève de la Phase 8.
- **L'usage des armes et des outils** : Phase 10 pour les unes, indéfini pour
  les autres. Ils se vendent, c'est tout, et l'interface le dit.
- **Le kite**, dixième du deben : sans objet tant que les prix restent entiers.

#### Points tranchés avec la joueuse

| Question | Décision |
|---|---|
| Le craft est-il instantané ? | **Non** : un ordre de fabrication produit plusieurs pièces sur plusieurs quinzaines, à partir de **plusieurs ressources** |
| Comment commerce-t-on à distance ? | On **ouvre une route en envoyant une première caravane**, on annonce **ce qu'on vend et achète et à quel prix**, puis les convois vont et viennent au rythme de la distance |
| Les rivaux commerciaux ? | **Reportés** après les enquêtes |
| Le stockage est-il limité ? | **Oui, plafonné** par le Grenier et l'Entrepôt — mais **rien ne se périme** |

#### Définition de « fini »

Parcours de bout en bout : lancer un ordre de fabrication à l'Atelier → le voir
occuper des quinzaines → récupérer les pièces → ouvrir une route vers une cité
→ annoncer un prix d'achat et un prix de vente → voir partir un convoi chargé
et en voir arriver un autre → fabriquer avec une matière que la région ne porte
pas.

Tests unitaires sur les recettes, les plafonds de stock, les fourchettes de
prix et les volumes de convoi. Les allers-retours de convois se testent comme
les expéditions : sur des invariants — un convoi arrive toujours, un ordre ne
vend jamais plus que le stock, un prix hors fourchette ne conclut rien.

**Une vérification d'équilibrage** que les tests ne peuvent pas juger : mener
une partie jusqu'à ce que le commerce soit rentable, et constater que
transformer vaut mieux que vendre brut sans que le craft écrase la vente
directe. C'est le même exercice que pour le calibrage du lot 4.6.

Les quatre portes qualité au vert, et une revue de sécurité : ouvrir une route,
poser un ordre, lancer une fabrication modifient l'état d'une partie et doivent
passer par `PartieVoter::JOUER`.

---

### 5.7 Phases 6 à 12 — à cadrer

Chacune traduit un document déjà spécifié ; le cadrage technique se fera à son
tour, comme pour les précédentes.

| Phase | Sujet | Ce qu'elle apporte |
|---|---|---|
| **6** — Faveur divine et événements (`07`) | Offrandes au Temple, divinités favorisées, bénédictions | Réveille le trait « Croyant » et la spécialité Dévot, tous deux posés mais inertes |
| **7** — Énigmes, enquêtes et fil rouge (`10`) | Maison des scribes, clés de lecture, enquêtes | Prérequis des marchands rivaux du doc 08, reportés de la Phase 5 |
| **8** — Campagne (`09`, `11`) | Les 10 missions, leurs objectifs, l'enchaînement | Prérequis de l'héritage commercial inter-missions (doc 12) |
| **9** — Renommée et héritage (`13`) | Succession familiale, carnet de contacts | La renommée existe déjà et bouge ; c'est l'héritage entre parties qui manque |
| **10** — Medjaÿ et combat (`03`) | Recrutement militaire, équipement, zones à bandits | Réveille le trait « Bagarreur », l'usage des armes de la Forge, et les rôles d'exploration autres que l'éclaireur |
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
