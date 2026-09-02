# Niout — Plan de bataille

Document de cadrage technique. Traduit les 16 documents de conception du jeu
(dossier Google Drive `Niout`, fichiers `00` à `15`) en un plan de développement
Symfony.

- **Stack** : Symfony 8.1 · Twig · Tailwind CSS 4.3 · PostgreSQL · FrankenPHP/Docker
- **Contrainte** : rendu serveur, zéro React, zéro headless
- **Source de conception** : Google Drive `Niout/`, docs 00–15 + sous-dossier `Sprites/` (18 planches)

Ce document est la **feuille de route** : ce qui est livré, ce qui vient, et
les décisions actées. Le reste vit à côté, pour éviter de le répéter à deux
endroits :

- le **journal des phases livrées** — intention, lots, pièges payés — dans
  [`phases-livrees.md`](phases-livrees.md) ;
- les **règles vives du jeu** dans [`regles-du-jeu.md`](regles-du-jeu.md) et
  les **écrans** dans [`interface.md`](interface.md) ; ce sont elles qui font
  foi pour écrire du code ;
- la **stack, les commandes et l'architecture** dans
  [`CLAUDE.md`](../CLAUDE.md).

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
| `Family` | ✅ | Nom choisi au lancement (1 par `GameSave`) et jauge de renommée **de la mission** — l'acquis vit sur `Lignee` | 13 |
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
| `Lignee` | ✅ | L'acquis d'un joueur, qui survit à ses parties : renommée persistante | 13 |
| … (Phase 10+) | — | Medjaÿ, unités et zones à bandits | 03, 02 |

`Family`, `City` et tout ce qui s'y rattache (`Zone`, `Building`, `Chantier`,
`Expedition`, `Employee`, `JobOffer`, `OrdreDeFabrication`, `RouteCommerciale`,
`FaveurDivine`, `DossierDEnquete`) sont détenus par leur `GameSave` : l'abandon d'une partie, comme
la purge d'un compte, les emporte en cascade.

**`Lignee` est la seule exception, et c'est sa raison d'être** : elle appartient
au **joueur**, pas à une partie, puisqu'elle porte ce qui doit survivre à
celle-ci. Aucune cascade ne l'emporte donc — `app:users:purge-unverified` la
supprime explicitement, avant le compte qu'elle référence. Toute future entité
qui suivrait le joueur plutôt que la partie devra faire de même.

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
| **8** | Campagne : les 10 missions et leurs objectifs | `09`, `11` | ✅ |
| **8 bis** | Finition : cohérence et lisibilité | — | ✅ |
| **8 ter** | Écriture : l'alphabet des scribes et les stèles | `10`, `09` | ✅ |
| **9** | Renommée, héritage et succession familiale | `13` | ✅ (9.6 → Phase 11) |
| **10** | Medjaÿ et combat automatique | `03` | cadrée |
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

Le **détail des phases livrées** — intention, lots, règles qui en sortent,
pièges payés, ce que chacune laisse ouvert — vit dans
[`phases-livrees.md`](phases-livrees.md), pour que cette feuille de route reste
lisible d'un coup d'œil.

---

## 4 ter. Phase 9 — Renommée, héritage et succession familiale  *(cadrée)*

Le doc 13 est le seul document de conception dont le jeu applique **la moitié
sans le savoir** : les cinq paliers de renommée existent, aux plages exactes du
document, et six mécanismes la font bouger. Ce qui manque n'est pas la jauge,
c'est **ce qu'elle traverse** — une mission, une génération, une campagne.

### Ce qui existe déjà, et qu'il ne faut pas refaire

| Le document demande | Le jeu fait |
|---|---|
| Cinq paliers, 0-19 / 20-39 / 40-59 / 60-79 / 80-100 | `PalierDeRenommee`, aux mêmes plages |
| L'attractivité varie par palier | Le prix d'un appel d'habitants et la migration spontanée en dépendent |
| Gros contrat commercial : +1 | `Marche::RECETTE_DUN_GROS_CONTRAT` |
| Quête de chantier réussie : +5, refusée : −2 | `QuetesDeChantier` |
| Bonus proportionnel au score de mission | `Legs::renommeePour()` |

Deux sources que le jeu ajoute et que le document ne prévoit pas : la
**providence** (±1 selon qu'un dieu bénit ou maudit) et le **mécontentement**
(−1 par quinzaine de colère). Elles sont cohérentes avec le reste et se
gardent ; le document se corrigera.

### Le défaut de fond : la renommée ne traverse rien

**`Family` naît avec chaque partie.** La renommée repart donc de zéro à chaque
mission, augmentée du seul legs de la mission **immédiatement précédente**,
plafonné à quatre points. Le document veut l'inverse, et le dit en toutes
lettres : « une seule jauge de renommée par famille, **persistante d'une
mission à l'autre** […] elle ne fait que croître au fil de la campagne ».

C'est le cœur de la phase, et ce n'est pas qu'une colonne à déplacer : cela
touche à un invariant déjà posé — **le legs s'ajoute à la dotation, il ne la
remplace pas**, pour que chaque mission reste jouable seule. Une renommée
cumulée qui infléchit les prix rend mécaniquement les dernières missions plus
faciles. C'est **voulu** par le document ; il faut le décider en connaissance
de cause plutôt que le découvrir au playtest.

### Les lots

| Lot | Contenu | |
|---|---|---|
| 9.0 | Les arbitrages tranchés, et la forme qu'ils donnent à la phase | ✅ |
| 9.1 | La renommée devient une jauge de famille, qui traverse la campagne | ✅ |
| 9.2 | Les deux sources manquantes : énigme résolue, enquête résolue | ✅ |
| 9.3 | La renommée infléchit les prix, à l'achat comme à la vente | ✅ |
| 9.4 | Le carnet de contacts : ce qu'une région visitée laisse | ✅ |
| 9.5 | Le bonus de départ par missions accomplies | ✅ |
| 9.6 | La succession : générations, héritiers et leur trait | ↦ Phase 11 |

#### 9.0 — Les arbitrages, tranchés

Le bloc « À trancher avec la joueuse » ci-dessous a été posé avant d'écrire une
ligne de code : chacune de ces questions change la forme des lots suivants, et
les découvrir en cours de route aurait coûté une reprise. Les réponses sont
donc actées ici, avec leur raison.

| Question | Tranché | Ce que cela impose |
|---|---|---|
| La renommée cumulée facilite les dernières missions — on assume ? | **Oui**, comme le doc 13 le demande | La renommée traverse la campagne sans réserve. L'invariant « chaque mission jouable seule » se relit : aucune mission ne devient *injouable* sans héritage, mais la dixième se joue avantagée — c'est la récompense de la campagne, pas un défaut d'équilibrage |
| Un plafond global au cumul renommée + contacts + missions accomplies ? | **Oui**, un plafond unique sur la remise totale | Les trois sources ne s'additionnent pas sans limite. Le plafond porte sur le **résultat**, pas sur chaque source — sans quoi trois plafonds séparés se cumulent et n'en plafonnent aucun |
| Un contact débloque-t-il une ressource ? | **Non**, remise seule | Le carnet reste une commodité économique. Il ne touche ni aux objectifs de mission ni à ce qui est atteignable : un raccourci de progression aurait demandé de recalibrer les missions tardives |
| L'enquête résolue vaut +3 ou +1 ? | **+2**, avec plafond de contribution | +3 mettrait quatre-vingt-dix points sur cent à la portée d'un seul système ; +1 ne paie pas un système qui demande plusieurs quinzaines de collecte. **Valeur inventée**, à calibrer comme les seuils du doc 09 |
| La succession maintenant, ou en Phase 11 ? | **Phase 11** | Une génération dure 60 cycles ± 20, une mission de campagne les dépasse rarement : le lot ne se déclencherait presque jamais. Il rejoint le mode Aventure, où il a un sens. **La Phase 9 se livre donc en 9.1 à 9.5**, et le lot 9.6 est reporté |

**Une conséquence de forme, qui décide du 9.1.** Deux exigences du cadrage
paraissent s'opposer : « une seule jauge par famille, persistante d'une mission
à l'autre », et « deux parties menées de front ne se volent pas leur renommée ».
Elles se concilient en séparant deux choses que le mot « renommée » confond :

- **l'acquis**, porté par la lignée — le plancher, qui ne descend jamais et que
  chaque nouvelle partie reçoit au lancement ;
- **la jauge de la mission**, portée par `Family` — celle qui bouge, que le
  mécontentement fait baisser, et qui reste propre à sa partie.

Une mission accomplie relève l'acquis ; elle ne l'abaisse jamais. C'est la même
discipline que le plancher du neutre de la négligence divine, et c'est ce qui
permet à deux parties de coexister sans se contaminer.

#### 9.1 — Une jauge de famille, pas de partie  *(livré)*

**Retenu : l'entité `Lignee`**, une par joueur, créée paresseusement au premier
lancement qui en a besoin — elle accueillera le carnet de contacts du 9.4. Elle
porte l'**acquis** ; `Family` garde la **jauge de la mission** (cf. 9.0).
`Lignees` est le seul accès : lecture au lancement, relèvement à la clôture
d'une mission de campagne par `AchevementDeMission`. La migration
rétro-alimente les joueurs déjà avancés depuis leurs parties achevées.

`Legs` perd son volet renommée : quatre points depuis zéro et l'acquis entier
auraient compté deux fois la même réussite. Il reste le legs en deben.

La renommée quitte `GameSave` pour suivre le **joueur**. Trois façons de le
faire, à trancher :

- **une entité `Lignee` par joueur**, que chaque partie référence — le plus
  propre, le plus coûteux en migration ;
- **un calcul** à partir des parties achevées, comme `Legs` le fait déjà —
  aucune colonne, mais la renommée gagnée *en cours de mission* ne survit pas ;
- **la valeur recopiée au lancement** depuis la plus haute atteinte — simple,
  mais deux parties menées de front divergent.

La première est la seule qui tienne la phrase du document. Les deux autres
sont des raccourcis qu'il faudra défaire.

**Le plancher est la règle qui compte** : « la renommée ne redescend jamais en
dessous de son niveau de fin de mission précédente ». Les pertes ponctuelles —
refus d'une requête, mécontentement — jouent **dans** la mission, jamais en
travers de la campagne. C'est la même discipline que le plancher du neutre de
la négligence divine.

#### 9.2 — Deux sources qui manquent  *(livré)*

**Retenu : +1 par énigme, +2 par enquête, sous un plafond de huit points par
mission** (`Family::RENOMMEE_MAX_DES_AFFAIRES`). Le plafond est la moitié du
lot : la renommée traversant désormais la campagne, dix missions où l'on résout
tout verseraient bien au-delà des cent points de l'échelle, et la jauge
cesserait de mesurer une réputation pour ne compter que l'assiduité à deux
mini-jeux. Il se compte **par mission**, pas par campagne — sinon deux systèmes
qui la traversent de bout en bout n'y vaudraient que huit points en tout.

Il ne borne que les affaires, jamais la jauge : elle bouge pour six raisons, et
un plafond qui la lirait plafonnerait les cinq autres. Le gain réellement versé
est rendu à l'appelant, pour que l'écran se taise au lieu d'annoncer un gain nul.

Le document accorde **+1 par énigme secondaire résolue** et **+3 par enquête
complète** ; le jeu ne donne rien pour la première et +1 pour la seconde.

L'écart sur l'enquête est à trancher : +3 récompense un système qui demande
plusieurs quinzaines de collecte, ce qui se défend ; mais la renommée se
compte sur cent points, et une campagne porte une trentaine d'enquêtes.
**À calibrer sur l'économie mesurée**, comme les seuils du doc 09 l'ont été.

#### 9.3 — La renommée dans les prix  *(livré)*

**La pièce qui décidait du lot existait déjà** : `$avantage`, le facteur du
Négociateur, élargit la fourchette d'un partenaire des deux côtés, en points de
pourcentage entiers. La renommée y entre (`AvantageDeNegoce`), elle n'ajoute
aucun troisième multiplicateur. Au Marché, elle s'**ajoute au coefficient** de
qualité de direction, qui reste appliqué en une seule multiplication et une
seule division.

**Le plafond global du 9.0 vaut 40 points**, et la raison est arithmétique : le
plancher d'un partenaire vaut 150 % du cours local moins l'avantage, donc à
cinquante il rejoint le cours et importer ne coûterait plus rien de plus que
produire sur place. Conséquence assumée : un Négociateur (25) chez une famille
illustre (20) est rogné — le plafond porte sur la somme, c'est ce qui a été
tranché.

Une limite mesurée en écrivant les tests : sous une dizaine de deben, la
division entière avale tout l'avantage. Cela ne dessert personne — le commerce
lointain ne porte pas de l'argile.

`reductionAchat = −0,2 % par point, plafonné à −20 %`, et la majoration
symétrique à la vente. Deux pièges connus du projet s'y appliquent :

- **jamais en flottants** — cela se compte en centièmes, comme les rendements
  et la qualité de direction ;
- **un seul multiplicateur par chaîne** : la vente au Marché porte déjà la
  qualité de direction du bâtiment, et l'ordre commercial porte déjà
  l'empressement du partenaire. La renommée doit **entrer dans un facteur
  existant**, pas en ajouter un troisième — c'est la discipline du lot 6.3.

#### 9.4 — Le carnet de contacts  *(livré)*

**Rien ne se persiste** : le carnet se déduit des missions accomplies
(`CarnetDeContacts`), comme les partenaires se déduisent du catalogue. Une
colonne de plus ne dirait rien que `MissionCatalogue` ne sache déjà.

Le contact vaut +2 sur ce que **sa région porte en gisement**, et sur rien
d'autre — c'est ce qui l'empêche d'être une remise générale de plus. Il entre
dans le même facteur que la renommée, donc sous le même plafond : l'avantage se
compte désormais **marchandise par marchandise**, ce que la signature
`avantageDeNegoce($partie, ?$ressource)` porte. La ville de la mission en cours
n'est jamais un contact — on ne se fait pas de prix à soi-même.

**L'héritage du doc 12 est fait avec**, comme le cadrage le recommandait :
`Commerce::RABAIS_DUNE_ROUTE_HERITEE`, −20 % sur une route déjà armée dans une
autre partie. Il se déduit lui aussi, en interrogeant les parties du joueur.
Une partie ne s'hérite pas elle-même, et une partie abandonnée ne lègue rien —
cohérent avec le fait qu'elle ne lègue ni deben ni renommée.

Chaque mission accomplie laisse un **contact** — la ville elle-même — qui
donne +2 % sur les ressources caractéristiques de sa région, cumulables avec le
bonus de renommée. Comme les partenaires commerciaux, **seule la clé se
persiste** ; le nom, la région et les ressources sont du contenu.

Le document laisse ouvert un point qui change la nature du système : un contact
donne-t-il seulement un rabais, ou **débloque-t-il une ressource** qu'on
devrait autrement importer ? La seconde lecture en ferait un raccourci de
progression, et non plus une remise.

**Il croise l'héritage du doc 12**, qui n'est pas non plus implémenté : −20 %
sur l'ouverture d'une route déjà exploitée dans une partie précédente. Les deux
se complètent — l'un porte sur les routes, l'autre sur les prix courants — et
gagnent à être faits ensemble.

#### 9.5 — Le bonus de départ  *(livré)*

`BonusDeDepart` compte **toutes** les missions accomplies, hors celle qu'on
lance — rejouer une mission ne la compte pas deux fois, même règle que le
carnet. Il s'ajoute au legs, qui reste distinct : l'un vient du roi et suit le
score de la seule mission d'avant, l'autre vient de la maisonnée et suit la
campagne entière.

**Le plafond du 9.0 se lit sur la dotation elle-même**, ressource par
ressource : neuf missions vaudraient cent quatre-vingts deben, davantage que ce
que le pharaon envoie, et le don du roi cesserait d'être le socle de la partie
pour n'en être plus que l'appoint. Rien à calibrer — le plafond suit tout
changement de coût des bâtiments d'ouverture.

Les **vivres en sont exclus** : la dotation les taille sur la consommation
réelle de la maisonnée expédiée, et un forfait par-dessus casserait ce calcul.

`20 deben` et `5 unités` par mission accomplie, **superposés à la dotation
royale**, jamais à sa place. `Progression::plusHauteAchevee()` sait déjà
compter ; `Legs`, lui, ne regarde que la mission précédente et devra compter
toutes les accomplies.

La dotation, elle, ne change pas : elle reste calculée sur les **coûts réels**
des quatre bâtiments d'ouverture, et non sur le `50 + 10 × difficulté` du
document, qui compte encore en or.

#### 9.6 — La succession  *(reporté en Phase 11, cf. 9.0)*

Une génération dure **60 cycles ± 20**. À son terme, le joueur choisit parmi
deux ou trois héritiers, chacun tiré avec un ou deux des huit traits déjà
définis — `TraitDeCandidat` existe, `GenerateurDeCandidat` aussi, et le
mécanisme de l'offre d'emploi se transpose presque tel quel.

**Ce qui persiste** : la renommée, les contacts, la faveur divine, la ville
entière. **Ce qui se renouvelle** : le trait actif et le nom du chef de
famille. Rien ne se perd — c'est la ville qui compte, pas la personne.

Deux réserves :

- le document dit lui-même que la succession est **surtout pertinente en mode
  Aventure**, une mission de campagne dépassant rarement soixante cycles. Ce
  lot pourrait donc attendre la Phase 11 — sauf à vouloir l'éprouver plus tôt ;
- les **noms d'héritiers** demandent une liste de prénoms égyptiens attestés,
  du même travail de sourcing que les cartouches. `Nakht` est déjà justifié
  ainsi dans le doc 09.

#### Les questions telles qu'elles se posaient

Conservées pour l'enjeu qu'elles portent ; les réponses sont au 9.0.

| Question | Enjeu |
|---|---|
| La renommée cumulée rend-elle les dernières missions plus faciles, et l'assume-t-on ? | Le document le veut. Cela heurte l'invariant « chaque mission jouable seule », qui a justifié que le legs s'ajoute sans remplacer |
| Faut-il un **plafond global** au cumul renommée + contacts + missions accomplies ? | Le document pose la question sans y répondre. Sans plafond, la mission 10 se joue avec −20 % à l'achat, +20 % à la vente, neuf contacts et 180 deben de bonus |
| Un contact **débloque-t-il** une ressource, ou se contente-t-il d'une remise ? | La première lecture en fait un raccourci de progression, la seconde une commodité |
| La succession maintenant, ou avec le mode Aventure (Phase 11) ? | Elle ne se déclenche presque jamais en campagne : soixante cycles dépassent la durée d'une mission |
| L'enquête résolue vaut-elle +3 comme le document le dit, ou +1 comme aujourd'hui ? | Trente enquêtes à +3 font quatre-vingt-dix points sur une échelle de cent |

#### Définition de « fini »

Parcours de bout en bout : accomplir la mission 1 → voir la renommée gagnée
**rester** au lancement de la mission 2 → constater un prix d'achat plus bas et
un contact « Delta » au carnet → recevoir le bonus de départ par-dessus la
dotation royale.

Tests sur les invariants : la renommée ne redescend jamais sous son niveau de
fin de mission, aucune chaîne de production ne gagne un multiplicateur de plus,
le bonus de départ **s'ajoute** à la dotation sans la remplacer, et deux
parties menées de front ne se volent pas leur renommée.

---

## 4 quater. Phase 10 — Medjaÿ et combat automatique  *(cadrée)*

Le doc 03 est le document le plus **entièrement chiffré** du projet : il donne
la formule de résolution, les forces d'unité, les coûts, les taux de blessure et
de mort, les paliers de faveur qui les infléchissent. Il n'y a presque rien à
inventer — et c'est ce qui distingue cette phase de la précédente, où le
document nommait des faits sans les compter.

Le jeu, lui, en applique déjà **la moitié civile** : le recrutement par offres
d'emploi, les stats chiffrées affichées en qualitatif, les huit traits, les
spécialités par bâtiment, le barème d'étoiles. C'est la moitié militaire qui
manque, et elle manque entièrement.

### Ce qui existe déjà, et qu'il ne faut pas refaire

| Le document demande | Le jeu fait |
|---|---|
| Recrutement par offre, 2-3 candidats, renvoi possible | `JobOffer`, `GenerateurDeCandidat`, `Recrutements` |
| Huit traits, 45/40/15 % et incompatibilités | `TraitDeCandidat`, `PoidsDeTirage` |
| Spécialité tirée, propre au type de bâtiment | `SpecialiteDeChef`, y compris `CaserneInstructeurArcher` et `CaserneInstructeurBouclier` |
| Chiffré en interne, qualitatif à l'écran | Le barème d'étoiles et les libellés d'ancienneté |
| Caserne, ses coûts et ses neuf niveaux | `TypeDeBatiment::Caserne` |
| Armes de cuivre à la Forge | `Recette::Armes`, `Ressource::Armes` |
| Isis protège au combat, Sekhmet décide du sort | `Divinite`, qui les distingue déjà en toutes lettres |

### Six choses inertes, qui n'attendent que cette phase

C'est la particularité de la Phase 10 : elle ne construit pas seulement du
neuf, elle **réveille** ce qui a été posé sans emploi et qui le dit lui-même.

- `TraitDeCandidat::Bagarreur` — « sans effet tant qu'aucun Medjaÿ n'est
  recruté », écrit dans le code ;
- `SpecialiteDeChef::CaserneInstructeurArcher` et `CaserneInstructeurBouclier` —
  déclarées, **lues nulle part** ;
- `Divinite::Isis` — seule divinité déclarée inactive, avec le message « aucune
  bataille ne se livre encore » ;
- `RoleDExploration::ChefDExpedition` — dernier rôle d'exploration sans emploi ;
- `Ressource::Armes` — fabriquée, et bonne à vendre seulement ;
- le gabarit `_caserne.html.twig` — un écran qui dit « vos Medjaÿ ne sont pas
  encore levés ».

Aucun de ces six n'est un chantier : ce sont des branchements. Le vrai travail
est ailleurs.

### Le défaut de fond : la carte ne connaît pas le danger

Le document calcule la défense d'une zone ainsi :

```
scoreDefense = valeurBase_zone × (1 + 0,15 × nbZonesBandits_region)
```

Or `ContenuDeZone` ne porte que `Rien`, `Ressource`, `ChampEligible` et
`Evenement`. **Aucune case n'est dangereuse, et aucune région ne compte ses
zones à bandits.** La formule référence un état que la carte n'a pas.

C'est le cœur de la phase, et ce n'est pas un système de combat : c'est une
addition à la **génération de carte** (doc 02), qui touche `GenerateurDeCarte`,
`ContenuDeZone` et la géographie des dix régions. Le combat vient après, et il
sera plus simple.

Conséquence à ne pas manquer : le facteur `0,15 × nbZonesBandits_region` fait
qu'**une région dangereuse est plus dure partout**, pas seulement sur ses cases
à bandits. Le nombre de zones dangereuses par région devient donc un curseur de
difficulté régionale, à poser avec les seuils du doc 11.

### Les lots

| Lot | Contenu | |
|---|---|---|
| 10.0 | Les arbitrages, avant d'écrire | ✅ |
| 10.1 | Le danger sur la carte : zones à bandits et défense de région | ✅ |
| 10.2 | Les Medjaÿ : fantassin, archer, recrutement et entretien | |
| 10.3 | L'équipement : les armes de la Forge cessent d'être une marchandise | |
| 10.4 | La résolution automatique, et ce qu'elle coûte en hommes | |
| 10.5 | L'escorte : expéditions lourdes et caravanes | |
| 10.6 | Le Charrier : une réquisition, jamais un recrutement | |
| 10.7 | Les six branchements dormants | |

#### 10.0 — Les arbitrages, tranchés

Même méthode qu'au 9.0, pour la même raison : ces réponses décident de la forme
des sept lots, et les découvrir en codant coûterait une reprise.

| Question | Tranché | Ce que cela impose |
|---|---|---|
| Le danger est-il un contenu de case ou un attribut ? | **Un attribut superposé** | Une case garde son contenu **et** porte un danger : le filon gardé par des bandits devient possible, et c'est le cas qui donne envie de lever des Medjaÿ. Une colonne sur `Zone`, et un tirage indépendant de `ContenuDeZone` dans le générateur |
| Une zone nettoyée le reste-t-elle ? | **Oui, définitivement** | Le combat est une conquête, pas un péage. On investit des hommes et de l'équipement une fois, on récolte ensuite. Rien d'autre dans le jeu ne se dégrade tout seul, et il n'y a pas de raison de commencer ici |
| Les armes se consomment-elles ? | **Non, équipement durable** | La Forge est un palier à franchir, pas un robinet à tenir ouvert. Une unité sans arme part quand même, à qualité minimale : **rien ne bloque une expédition**, ce qui évite qu'une chaîne de production décide du rythme militaire |
| La mort permanente ? | **Oui, aux taux du doc 03** | Ce sera le seul endroit du jeu où l'on perd sans recours. Ce n'est pas un « game over » au sens du doc 00 — la partie continue — et c'est ce qui donne son poids à l'expérience accumulée. Isis y trouve enfin son effet propre |

**Deux écarts au document, actés d'avance.** Ils reprennent des décisions déjà
prises deux fois sur d'autres documents, et n'ont pas à être rediscutés :

- les **flottants du document se comptent en centièmes entiers** (`0,85` →
  `85`, `1,15` → `115`). C'est la discipline du projet depuis les rendements,
  et une probabilité en virgule flottante serait le premier endroit du jeu où
  deux parties identiques divergeraient ;
- les **« 100 or » du Charrier deviennent 100 deben.** L'Égypte pharaonique n'a
  pas de monnaie d'or ; les docs 09 et 13 ont été relus ainsi avant celui-ci.

**Deux questions tranchées sans arbitrage**, parce qu'elles ne changent la forme
d'aucun lot :

- **les Medjaÿ ne répondent pas aux rivaux commerciaux.** Ni le doc 03 ni le
  doc 08 ne le prévoient : ce serait une addition, et l'enquête reste la
  réponse au rival. À reprendre si le playtest montre que `Rivaux` manque d'une
  seconde issue ;
- **le combat existe en mode Aventure.** Le danger vient du générateur de carte
  et de la géographie de la région, pas d'un scénario de mission : Memphis en
  porte donc comme les autres. Rien à faire de particulier, à condition que le
  compte de zones dangereuses se lise sur la **carte** et non sur un numéro de
  mission.

#### 10.1 — Le danger sur la carte  *(livré)*

`Zone::$defenseDesBandits` — zéro valant « aucune bande » —, `Bandits` pour les
règles, et une pose à la génération **après** le contenu, pour que le danger s'y
superpose. Une case gardée ne s'exploite ni ne se sème : c'est ce qui donne au
filon gardé sa raison d'être, et aux Medjaÿ la leur.

**Une contradiction du doc 02, tranchée.** Le document donne deux comptes qui ne
s'accordent pas : un tableau de poids de tirage (0 / 8 / 15 % par palier de
difficulté) et une formule de paramètre
`nbZonesBandits = partieEntiere(difficulté × 0,5)`. Sur la grille 12×12 de la
dixième mission, le premier donnerait une vingtaine de zones là où le second en
donne quatre. C'est **la formule qui l'emporte** : le tableau décrit un tirage
de *contenu*, et le danger n'en est pas un depuis l'arbitrage 10.0.

**L'anneau de la ville est exclu, et c'est un invariant testé** sur les dix
régions. Le générateur y garantit un gisement de chaque matériau vital ; une
bande posée dessus rendrait la première carrière imprenable sans Caserne, donc
la partie injouable au premier cycle.

`valeurBase_zone` est une **valeur inventée** — le doc 03 renvoie au doc 02, qui
ne la chiffre nulle part. Vingt : deux fantassins valent vingt, donc une chance
sur deux à mains nues.

Une case dangereuse, un compte par région, et la défense qui en découle. Trois
questions se posent, dont deux touchent au doc 02 plus qu'au doc 03 :

- une zone à bandits est-elle un **contenu** de case (comme `Ressource`) ou un
  **attribut** qui s'y superpose ? La seconde lecture permet un gisement gardé
  par des bandits — ce qui est exactement le cas intéressant ;
- le danger **bloque-t-il** l'exploitation, ou la rend-il seulement risquée ?
- une zone nettoyée le reste-t-elle ? Sans quoi le combat n'est qu'un péage
  répété, et non une conquête.

`GenerateurDeCarte` garantit déjà les matériaux vitaux dans l'anneau des huit
cases autour de la ville. **Le danger doit respecter cette garantie** : une
partie qui ne pourrait pas ouvrir sa première carrière sans Caserne serait
injouable au premier cycle.

#### 10.2 — Les Medjaÿ

Deux unités seulement, et le document explique pourquoi : les Medjaÿ étaient un
corps de sécurité intérieure, armé d'arc et de bouclier, **jamais de chars** —
ceux-là appartenaient à la *mesha*, l'armée d'État. La distinction est
historique et le jeu la tient.

| Unité | Caserne | Force | Particularité | Recrutement | Entretien |
|---|---|---|---|---|---|
| Fantassin (bouclier) | 1-3 | 10 | Réduit les pertes de 30 % | 15 deben | 1 deben/cycle |
| Archer | 4-6 | 15 | +10 % en terrain désertique | 25 deben | 2 deben/cycle |

Une unité gagne **+5 % par combat gagné, plafonné à +50 %**. C'est ce qui donne
son poids à la mort permanente : ce n'est pas le coût de recrutement qui fait
mal, c'est l'expérience perdue.

**Un Medjaÿ n'est pas un `Employee`.** Le chef de bâtiment a une compétence, un
salaire négocié, une spécialité et une maisonnée ; le Medjaÿ a une force, une
spécialisation et une expérience. Les confondre ferait porter à `Employee` deux
modèles qui n'ont en commun qu'un salaire — nouvelle entité.

#### 10.3 — L'équipement

`qualite_equipement` entre directement dans la formule d'attaque, et vient de la
Forge (doc 01). C'est ce qui donne enfin aux **armes** une raison d'exister
autre que la vente, et à la spécialité `ForgeArmurier` son effet.

Deux points à trancher : les armes se **consomment**-elles, ou équipent-elles
durablement ? Et une unité sans arme se bat-elle quand même, à qualité minimale,
ou refuse-t-elle de partir ? La première lecture fait des armes un flux, la
seconde un stock — cela change toute la charge sur la Forge.

#### 10.4 — La résolution

```
scoreAttaque      = Σ(force × qualité d'équipement) × terrain × faveur
probabilitéVictoire = scoreAttaque / (scoreAttaque + scoreDéfense)
```

Terrain : neutre 1,00 · désert 0,85 (le défenseur est avantagé) · fluvial en
Akhèt 1,15. Faveur : 1,1 à Favorable ou mieux avec une divinité de combat, 0,9 à
Hostile.

**Tout cela se compte en centièmes entiers**, jamais en flottants — c'est la
discipline du projet depuis les rendements, et une probabilité en virgule
flottante serait le premier endroit du jeu où deux parties identiques
divergeraient.

| | Butin | Blessés (indispo. 2 cycles) | Mort permanente |
|---|---|---|---|
| Victoire | proportionnel au score | 0-10 % | 2-5 % par unité |
| Défaite | aucun, retrait | 20-30 % | 10-15 % par unité |

**Isis réduit la mort permanente** de 25 % à Favorable, 50 % à Dévoué. C'est le
seul effet de la phase qui touche un système déjà livré, et il est exactement à
sa place : le doc 07 la distingue de Sekhmet parce qu'elle protège l'homme
quand l'autre décide du sort de tous.

#### 10.5 — L'escorte

Le document nomme trois emplois : les **expéditions lourdes** (le Chef
d'expédition trouve enfin son rôle), la **protection des caravanes** — que le
docblock de la Caserne promet déjà — et la **garde de la ville**.

Le troisième croise `Rivaux`, qui rogne aujourd'hui le volume d'une route sans
qu'on puisse rien y faire hors de l'enquête. Les Medjaÿ ouvriraient une seconde
réponse — mais c'est une addition au doc 08, pas une lecture du doc 03 : à
trancher plutôt qu'à supposer.

#### 10.6 — Le Charrier

Caserne 7, Forge 4, force 25, **100 deben par expédition**, aucun entretien, il
disparaît à la fin et ne progresse jamais. Le document le veut ainsi : une
famille de marchands, même prospère, n'entretient pas de force de chars.

Le document écrit « 100 or ». Le projet a déjà tranché ce point deux fois
(doc 09, doc 13) : **l'Égypte pharaonique n'a pas de monnaie d'or**, le jeu
compte en deben, et la valeur se relit en conséquence.

#### 10.7 — Les branchements dormants

Les six ci-dessus, plus un écart réel : le document donne à **Bagarreur** un
« bonus combat si affecté aux Medjaÿ, **malus si poste civil** ». Le jeu n'a ni
l'un ni l'autre — il le dit franchement dans le libellé du trait. Le malus est
la moitié oubliée, et c'est elle qui rend le trait intéressant : un candidat
qu'on ne veut pas au Grenier devient bon à la Caserne.

#### Les questions telles qu'elles se posaient

Conservées pour l'enjeu qu'elles portent ; les réponses sont au 10.0.

| Question | Enjeu |
|---|---|
| Le danger est-il un contenu de case ou un attribut qui s'y superpose ? | La seconde lecture permet un gisement gardé — le cas intéressant. La première est plus simple et suit `ContenuDeZone` tel qu'il est |
| Une zone nettoyée le reste-t-elle ? | Sinon le combat est un péage répété, pas une conquête |
| Les armes se consomment-elles à chaque combat, ou équipent-elles durablement ? | Un flux met la Forge sous tension permanente ; un stock en fait un palier à franchir une fois |
| La mort permanente reste-t-elle à 2-15 %, dans un jeu qui n'a **aucun échec définitif** ailleurs ? | C'est le seul endroit du jeu où le joueur perd quelque chose sans recours. Le doc 00 tient à l'absence de « game over » ; une unité perdue n'en est pas un, mais c'est un changement de ton |
| Les Medjaÿ répondent-ils aux rivaux commerciaux ? | Le doc 03 ne le dit pas, le doc 08 non plus. Ce serait une addition, pas une lecture |
| Le combat existe-t-il en mode Aventure ? | Memphis n'a pas de mission, donc pas de zones à bandits placées par un scénario |

#### Définition de « fini »

Parcours de bout en bout : bâtir une Caserne → lever deux fantassins → les
équiper à la Forge → envoyer une expédition sur une case gardée → voir le
combat se résoudre sans écran de bataille → constater le butin, un blessé
indisponible deux cycles, et l'expérience gagnée par les survivants → revenir
sur la même case et la trouver libre.

Tests sur les invariants : aucune probabilité en flottant, une unité morte
repart à zéro d'expérience quand une unité blessée garde la sienne, le Charrier
ne rejoint jamais l'effectif permanent, l'anneau des huit cases autour de la
ville reste exploitable sans Caserne, et Isis réduit bien la mort permanente
sans toucher à l'issue du combat.

---

## 5. Ce qui vient

### Deux formats plus pauvres que ce que le doc 10 annonce

La sous-phase née de la relecture des documents 09 et 10 est **close** ; son
récit est au journal ([`phases-livrees.md`](phases-livrees.md), § 5.11).

Il en reste deux points de forme, sans urgence : la **reconnaissance
astronomique** (associer un décan à un mois) et l'**association symbolique**
(relier un animal à son dieu) sont des questionnaires à choix multiple là où le
document annonce un mini-jeu d'association. Le fond est juste — l'astronomie et
l'iconographie sont réelles —, la forme est plus pauvre que ce qui est écrit.

### Deux calibrages qui divergent, et qu'on garde

Ce ne sont pas des oublis mais des décisions prises **contre** le document,
rappelées ici pour que la prochaine relecture ne les redécouvre pas comme des
défauts :

| Point | Document 09 | Code | Pourquoi |
|---|---|---|---|
| Richesse | `200 + 50 × d` **en or** | `250 + 75 × d` **en deben** | Le document compte encore en or comme si c'était la monnaie ; l'Égypte pharaonique n'en a pas |
| Population | `20 + 10 × d` travailleurs | `12 + 4 × d` habitants | Seuil mesuré sur deux cents parties : une ville à Quartier 1 monte à treize |
| Commerce, ressource | `500 + 100 × d`, `100 + 20 × d` | `400 + 120 × d`, `60 + 15 × d` | Recalibrés sur l'économie réelle des Phases 4 et 5 |

Un seul vrai écart de contenu : le document veut pour la mission 9 « grauwacke
**et or** », le code demande grauwacke et une trésorerie. Le Ouadi Hammamat
portant bien de l'or, l'aligner est trivial — reste à savoir si deux objectifs
de ressource pure sur la même mission ne la rendent pas monotone.

### Les phases à cadrer

La **Phase 9 est livrée** ; son récit est au journal
([`phases-livrees.md`](phases-livrees.md), § 5.12). La **Phase 10 est cadrée**
et garde son format détaillé, lot par lot, jusqu'à sa livraison. Les deux
suivantes traduisent chacune un document déjà spécifié ; leur cadrage se fera à
leur tour.

| Phase | Sujet | Ce qu'elle apporte |
|---|---|---|
| **11** — Mode Aventure (`14`) | Memphis, succession des règnes, partie sans fin | Le mode existe déjà comme choix au lancement, sans contenu propre. **Reçoit le lot 9.6** (générations et héritiers), qui ne se déclenche presque jamais en campagne |
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
| Renommée d'une enquête résolue | +2, entre le +3 du doc 13 et le +1 d'avant | `Enquete::RENOMMEE_POUR_UNE_RESOLUE` |
| Plafond de renommée des affaires | 8 points par mission | `Family::RENOMMEE_MAX_DES_AFFAIRES` |
| Plafond de l'avantage de négoce | 40 points de pourcentage, toutes sources confondues | `AvantageDeNegoce::PLAFOND_TOTAL` |
| Défense d'une bande de brigands | 20, avant le facteur de région | `Bandits::DEFENSE_DE_BASE` |

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
| Deux pistes d'écriture | **La clé de lecture** (logogrammes, lire les inscriptions) et **l'alphabet des scribes** (unilitères, écrire) ne se mélangent jamais. Trois dessins leur sont communs, et l'écran les **relie** — c'est la leçon, pas une redite |
| Rendu des hiéroglyphes | **Texte Unicode et police embarquée**, jamais de planche de sprites : le signe reste sélectionnable et lisible par un lecteur d'écran, et le rendu cesse de dépendre de la machine du joueur |
| Cartouches royaux | **Seulement à l'introduction d'une mission**, pour le pharaon qui la commandite. Tant qu'une lecture n'est pas établie, on n'affiche rien — l'absence ne trompe personne, une approximation si |
| Transcription d'un nom | **La convention des musées**, dite comme telle : voyelles rendues par les semi-voyelles, aucun signe inventé pour boucher un trou |
| Stèles historiques | **Une par mission**, nommée et située, en **résumé jamais en citation**. Elle n'est pas l'inscription qu'on déchiffre : les dalles restent des rébus, la pierre est ce à quoi elles font écho |
| Types de mission | **Quatre**, « Exploiter » compris — le doc 09 se contredit, son tableau l'emporte sur sa section. Le type nomme, il ne change aucune règle |
| Énigmes secondaires | **Un corpus commun**, pas un corpus par mission : c'est le lieu où on les entend qui les situe, pas la région |
| Filon épuisé | La carrière **se ferme d'elle-même** et rend ses bras. Un **Prospecteur** retrouve une veine tarie à coup sûr ; chercher du neuf reste un pari. L'épuisement coûte du temps et de l'argent, il ne ferme jamais une région |
| Marché contre Entrepôt | Le Marché vend **aux gens de la ville et aux passants** : cours de base, immédiat, mais plafonné par quinzaine. Les routes gardent les volumes et les prix, contre le délai d'un convoi |
| Écran de ville | **Un onglet par bâtiment dressé**, chacun avec ce qui relève de sa fonction. La **Résidence familiale** recueille tout ce qui n'appartient à aucun bâtiment — c'est le point de chute par défaut de toute fonctionnalité qu'on ne sait pas rattacher |
| Alertes et bonnes nouvelles | **Un seul service**, lu par la ville et par la carte. Le bon compte autant que le mauvais, et chaque signal nomme la cause **et** le geste |
| Régions sans Nil | **Ni crue, ni saison d'inondation, ni Hâpi** — l'offrande y est refusée plutôt qu'encaissée pour rien. Sobek suit la même règle là où rien ne flotte. Le bilan démographique, lui, tombe partout |
| Cohérence d'une région | **Aucune n'est murée** : un test vérifie, région par région, qu'au moins une route est ouvrable et qu'une route d'eau suppose de l'eau |
| Chiffres de conception | **Provisoires par nature**, dans les documents comme dans le code. Ils se rectifient au fil de la conception ; le critère est l'équilibre et le fait de **pousser le joueur à se servir des mécaniques**, pas la fidélité au document |
