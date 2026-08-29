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
| Foyers, employés et offres (Phase 4) | — | Un foyer installé et sa taille, le poste qu'il occupe, les candidatures en cours | 01, 02, 03 |
| … (Phase 5+) | — | Medjaÿ, faveur divine, énigmes | 03, 07, 10 |

`Family`, `City` et tout ce qui s'y rattache (`Zone`, `Building`, `Chantier`,
`Expedition`) sont détenus par leur `GameSave` : l'abandon d'une partie, comme
la purge d'un compte, les emporte en cascade.

**Couche de domaine.** Ce qui relève des règles du jeu plutôt que de la
persistance vit dans `src/Game/` : catalogue des missions, dotation royale,
génération de carte, cycle agricole, population… Ces classes ne sont jamais
persistées — elles décrivent le contenu et les règles, pas l'état d'une partie.

---

## 4. Phase 0 — Fondations techniques  ✅

Dépôt git, stack Docker + squelette Symfony (généré par
`.claude/scripts/setup-symfony.sh --dedicated-server --run`), authentification
de base, thème Tailwind (doc 15 : ocre/sable/terre cuite, accents
lapis-lazuli/or), pipeline CI GitHub Actions, outillage qualité. Terminée et
stable depuis — rien à signaler.

| Outil | Configuration | Commande |
|---|---|---|
| php-cs-fixer | `@Symfony` + `declare_strict_types` | `vendor/bin/php-cs-fixer fix` |
| PHPStan | **niveau 8** (cible Symfony), extensions Symfony + Doctrine | `vendor/bin/phpstan analyse` |
| PHPUnit | Tests unitaires, d'intégration et fonctionnels | `php bin/phpunit` |
| composer audit | Aucun avis de sécurité | `composer audit` |

**Polices** : Marcellus (titrage) et Alegreya Sans (texte), **self-hébergées**
dans `app/assets/fonts/` (latin + latin-ext) — pas d'appel runtime à Google
Fonts, pour ne pas transmettre l'IP du visiteur à un tiers.

**Versions installées** (vérifiées le 2026-08-27) : Symfony 8.1.5 · PHP 8.4 ·
FrankenPHP 1.12.7 · Tailwind CSS 4.3.3 (`symfonycasts/tailwind-bundle` v1.0.0)
· PostgreSQL 18 · Ember 1.6.0.

Le stack répond sur `https://localhost` (certificat auto-signé Caddy).

---

## 5. Phase 1 — Comptes et page d'accueil  ✅

Trois pages, un compte fonctionnel : présentation publique, inscription
(vérification d'email non bloquante, purge à 7 jours), connexion avec mot de
passe oublié. Livrée, couverte par 21 tests (5 unitaires, 16 fonctionnels).

Écarts assumés par rapport au plan initial :

- **Tests fonctionnels en `WebTestCase` plutôt qu'en Behat** — l'outil natif
  Symfony couvre les mêmes parcours sans machinerie supplémentaire. Behat
  garde son intérêt pour les scénarios de jeu, où la lisibilité Gherkin
  profite à la relecture fonctionnelle.
- **URL en français** (`/inscription`, `/connexion`, `/mot-de-passe-oublie`) ;
  les *noms* de routes restent en anglais, référencés par `security.yaml`.
- **Emails envoyés en synchrone** — la recette Symfony les route vers
  Messenger, mais aucun worker ne tourne sur ce stack. À rebasculer le jour où
  un service worker est ajouté.
- **Mot de passe durci à l'inscription**, aligné sur les 12 caractères et les
  contraintes déjà exigées à la réinitialisation.

Point de sécurité laissé ouvert : le formulaire d'inscription révèle qu'une
adresse possède déjà un compte (énumération) — comportement par défaut de
Symfony, le masquer dégraderait l'inscription. Le flux de réinitialisation, lui,
ne fuit rien.

### Définition de « fini »

Un visiteur découvre le jeu sur l'accueil, crée un compte (utilisable
immédiatement), reçoit un email de vérification, se connecte, réinitialise son
mot de passe si besoin, voit sa page compte. Parcours couverts de bout en bout
par des tests fonctionnels, formulaires accessibles (labels, focus visible),
revue sécurité (CSRF, hashing, liens signés à expiration) passée avant merge.

---

## 6. Feuille de route

Chaque phase correspond à un ou plusieurs documents déjà entièrement spécifiés —
le travail y est surtout de la traduction en entités Doctrine, contrôleurs et
vues, pas de la conception.

- [x] **Phase 0** — Fondations techniques · §4
- [x] **Phase 1** — Comptes et page d'accueil · §5
- [x] **Phase 2** — Lancer une partie et bâtir · §6.1 · `01`, `05`, `13`
- [x] **Phase 3** — Carte, exploration et ressources · §6.2 · `02`, `04`, `06`, `08`
- [x] **Phase 4** — Population : recrutement, chefs et travailleurs · §6.3 ·
      `01`, `03`, `02`, `05`, `13`
- [ ] **Phase 5** — Artisanat et commerce · §6.4 · `08`, `12`, `01`
- [ ] **Phase 6** — Faveur divine et événements · `07`
- [ ] **Phase 7** — Énigmes, enquêtes et fil rouge · `10`
- [ ] **Phase 8** — Campagne : les 10 missions et leurs objectifs · `09`, `11`
- [ ] **Phase 9** — Renommée, héritage et succession familiale · `13`
- [ ] **Phase 10** — Medjaÿ et combat automatique · `03`
- [ ] **Phase 11** — Mode Aventure : Memphis et succession des règnes · `14`
- [ ] **Phase 12** — Découpage et intégration des sprites · `15` · §7 — **hors
      planche « tuiles »**, découpée dès la Phase 3 pour l'écran de carte

Le document 15 (interface & direction artistique) est **transverse** : chaque
phase l'utilise au fur et à mesure, la phase 12 ne concerne que l'intégration
des images elles-mêmes.

**Réordonnancements par rapport à la première version.** Les cycles (doc 05)
sont montés de la Phase 5 initiale à la Phase 2 : ils ne sont pas un système
parmi d'autres mais le battement du jeu, et un chantier qui ne progresse pas
n'est pas démontrable. Le combat (doc 03), inversement, descend en Phase 10 :
optionnel dans les boucles de jeu, il n'a de sens qu'une fois les zones
dangereuses posées. Même logique pour la population (doc 01, 03) : sa brique
minimale — compter les habitants, les nourrir, échouer sans nourriture — est
montée au lot 3.7, le recrutement et les chefs restant en Phase 4.

---

### 6.1 Phase 2 — Lancer une partie et bâtir  ✅

**Intention.** Livrer la plus petite tranche réellement *jouable* : créer une
partie, voir sa ville, lancer un chantier, déclencher un cycle, voir le
chantier avancer puis s'achever. C'est ce qui valide l'architecture d'état par
partie et la résolution de cycle — la fondation de tout le reste.

Livré : modèle de partie (`GameSave`, `Family`, `City`, plafond de 5 parties
actives) ; parcours de création (mode, nom de famille, dotation royale) ; liste
et gestion des parties (reprise avec récapitulatif d'état, abandon définitif) ;
vue de ville en liste/vignettes avec les 12 bâtiments et leurs conditions de
disponibilité ; cycles et chantiers (calendrier pharaonique, étapes de chantier
nommées, accélération ×1,5 en Akhèt) ; stock minimal (or, bois, pierre) affiché
en permanence dans la barre de jeu.

Hors périmètre à l'époque, couvert depuis en Phase 3 : carte, ressources
produites, effets fonctionnels des bâtiments. Le Port et le Temple restaient
hors d'atteinte (point d'eau, lin) jusqu'à la carte.

**Pièges rencontrés** — voir la liste consolidée dans
[`CLAUDE.md`](../CLAUDE.md#où-mettre-le-code-du-jeu) : `or` mot réservé du SQL,
signature des Voters en Symfony 8, avancement des chantiers en dixièmes plutôt
qu'en flottants.

**Définition de « fini » (atteinte)** : parcours complet couvert de bout en
bout par des tests fonctionnels — créer une partie → dotation créditée →
lancer un chantier → déclencher les cycles → le bâtiment est opérationnel —
plus des tests unitaires sur les formules (coût, durée, plafonds, bonus
d'Akhèt). Les quatre portes qualité au vert, revue de sécurité sur les
nouvelles routes (`PartieVoter`).

Deux notes de conception qui ont guidé les choix suivants : l'ordre imposé des
missions simplifie le modèle (`GameSave` de campagne ne porte qu'un numéro de
mission incrémenté, aucune notion de région débloquée) ; le récapitulatif de
reprise n'est **pas un journal d'événements** — le jeu n'ayant aucun temps
réel, rien ne se produit pendant l'absence du joueur, donc le récapitulatif
porte sur l'état où la partie a été laissée, pas sur ce qui s'est passé depuis.

---

### 6.2 Phase 3 — Carte, exploration et ressources  ✅

**Intention.** Faire basculer la ville de la dépense à la production. Elle
tire désormais ses matériaux de son territoire et sa nourriture de ses champs,
plutôt que de consommer une dotation qui ne se renouvelle pas.

À la fin de la phase, on peut raconter : *« j'envoie un éclaireur sur une case
voisine, il y trouve de l'argile, je l'exploite, et cette argile alimente mes
chantiers ; j'établis un champ, je bâtis le grenier, et la moisson tombe en
Chémou. Je dresse le Port sur ma berge et j'y jette les filets. Ma ville
compte ses habitants et les nourrit à chaque quinzaine — sans vivres, elle
s'affame et la partie peut y rester. »*

Les douze bâtiments du doc 01 sont désormais tous atteignables : le Temple par
le lin (lot 3.5), le Port par la berge (lot 3.6).

#### 3.1 — Généralisation du stock  ✅  *(prérequis)*

Les trois colonnes `stock_or`/`bois`/`pierre` sont devenues une table
`ressource → quantité` (énumération du doc 08 : minérales, agricoles,
importées), migrée sans perte. `crediterRessources()`/`debiterRessources()`
prennent une carte ressource → quantité ; un débit hors de moyens ne retire
**rien**, pas même ce qui suffisait.

#### 3.2 — Génération de la carte  ✅

`Zone` (position, terrain, contenu, découverte) peuplée par `GenerateurDeCarte`
selon une géographie cohérente avec l'Égypte réelle (doc 02) : Méditerranée au
nord, mer Rouge à l'est, Nil en colonne, désert sur un bord ou dispersé, oasis
dans le désert. Tirage pondéré du contenu par difficulté. Génération **à la
création de la partie** (décision) : une partie sans territoire n'aurait pas
de sens. Première carte instanciée, le Delta du Nord en 3×3 (doc 06) ; les dix
régions renseignées d'après les docs 02, 08, 11.

Deux écarts tranchés : un attribut `desertDominant` ensable les régions
décrites « désert dominant » par le doc 11 (le doc 02 ne posait le désert que
sur un bord, ce qui les rendait majoritairement fertiles) ; et une case de
sable bordant le Nil est désormais exclue du placement de la ville, les deux
règles du doc 02 (toucher l'eau, jamais en plein désert) s'y contredisant.

#### 3.3 — Découpage des tuiles et écran de carte  ✅

Planche « tuiles » du Drive découpée en 8 tuiles isométriques (176 px de
losange, cellule 189×206, posé tous les 87×70 px), fond détouré, servies via
AssetMapper. Grille isométrique avec brouillard sur les cases non reconnues.
Deux couches : les images (inertes) et les liens cliquables en `clip-path`,
sans quoi les tuiles du premier plan capturaient les clics des cases
derrière. Détail au clic rendu **côté serveur**, le jeu se jouant sans JS.

La carte est devenue **l'écran principal** d'une partie (décision prise en
cours de lot) : on y arrive en reprenant une partie, et c'est en cliquant la
tuile de la ville qu'on ouvre la liste de ses bâtiments — non l'inverse.

**Le prompt de la planche 13 demandait des tuiles carrées vues de dessus, à
fond transparent** ; la planche livrée est isométrique sur fond opaque,
conforme à la direction artistique générale mais pas au prompt. Le doc 15
mériterait d'être corrigé sur ce point.

#### 3.4 — Reconnaissance  ✅

Éclaireur : reconnaissance de toute case inconnue, plusieurs expéditions
simultanées (une par case — la contrainte vient du coût, pas d'une limite
arbitraire), progression au fil des cycles sans bloquer le joueur, bonus
d'Akhèt sur les trajets empruntant le Nil (interprété comme : la destination
est une case du fleuve). Le passage de quinzaine, jusque-là piloté par
`Chantiers` seul, est remonté dans `PassageDeCycle` : chaque service avance ce
qui le concerne sans rien persister, une seule écriture par quinzaine.

#### 3.5 — Ressources de zone, champs et cycle agricole  ✅

Ressources brutes du doc 08 posées selon la géologie réelle par région.
Exploitation d'une case reconnue : la ressource alimente le stock. Champ
établi sur zone fertile ou berge du Nil inondable ; **sans Grenier, un champ
ne produit rien d'exploitable** (doc 01). Rendement saisonnier (doc 05), crue
tirée en début d'année (faible ×0,7 / normale / forte ×1,3). Blé, orge et lin
— le lin débloque le Temple.

Décisions structurantes actées pendant ce lot (certaines révisées depuis, voir
lot 3.7) :

- **Chaque coût de construction nomme sa ressource réelle**, jamais un
  générique « bois »/« pierre » — le doc 01 les chiffre en matériaux
  génériques, mais un compteur agrégé cachait au joueur ce qu'il possédait
  vraiment. Voir le détail dans `CLAUDE.md`.
- **Le Marché est avancé de la Phase 5** dans sa forme minimale (vente du
  surplus, prix inventés) : sans lui, la monnaie n'avait aucune source renouvelable
  et toute partie finissait figée après la dotation initiale.
- **Une case porte jusqu'à deux gisements**, jamais deux fois le même ; un
  minimum de champs et de cases poissonneuses est garanti par région. Détail
  et pièges dans `CLAUDE.md`.
- **Seul le Delta est autosuffisant** en bois et pierre parmi les dix régions
  (cinq n'ont que la pierre, le Levant que le bois, trois régions n'ont ni
  l'un ni l'autre) : à partir de la région 2, le commerce (Phase 5) devient
  une condition de jouabilité, pas un confort — à trancher avant la mission 2.
- **Les quatre étapes d'un chantier sont désormais toutes rendues en
  permanence** (terminée/en cours/à venir), pas seulement déduites du
  pourcentage : un Grenier de deux quinzaines pour quatre étapes n'en montrait
  qu'une à la fois, escamotant le séchage des briques.
- **Reconnaître les abords de la ville ne coûte rien** (rayon étendu et
  entièrement gratuit depuis, voir lot 3.7).

#### 3.6 — Points d'eau, Port et pêche  ✅

- [x] Le Port devient constructible dès qu'un point d'eau jouxte la ville —
      seule la géographie peut encore l'empêcher. La contrainte est appliquée
      côté serveur : `Chantiers::lancer()` passe par le catalogue, un POST
      direct ne la contourne pas.
- [x] Pêche sur les cases d'eau reconnues, une fois le Port dressé
      (`Exploitations::exploiter()`). Le poisson nourrit la population et se
      vend au Marché : le Port devient une alternative aux champs, pas un
      ornement.
- [x] Les cases d'eau portent du contenu comme les autres (doc 02) — c'était
      **déjà le cas** depuis le lot 3.2, le tirage ne les ayant jamais
      exclues. Vérifié plutôt que réimplémenté, par un test qui exige qu'une
      case d'eau puisse porter un événement : aucune garantie n'en pose, sa
      présence prouve donc que le tirage atteint bien l'eau.

**Le poisson est la seule ressource renouvelable du jeu** (décision de la
joueuse, `Ressource::estRenouvelable()`) : un banc se reconstitue d'une
quinzaine à l'autre, son compteur ne descend jamais. Un Port coûte 50 deben,
40 roseaux et 20 calcaire ; le laisser s'épuiser au bout d'une quarantaine de
quinzaines en aurait fait un piège plutôt qu'un choix, d'autant qu'une petite
carte peut n'avoir qu'une seule case poissonneuse. L'interface affiche
« inépuisable » là où les autres gisements affichent leurs unités restantes.

**Le niveau du Port ne change rien encore** : comme le Grenier, il est binaire.
Les effets de niveau restent hors périmètre jusqu'à ce que le commerce naval
(Phase 5) donne une raison de le monter.

**Un défaut d'affichage corrigé au passage.** Une berge du Nil peut être à la
fois poissonneuse et cultivable ; le gabarit enchaînait gisements et champ en
`elseif`, si bien que la seconde action disparaissait dès que la première
s'affichait. Les deux coexistent désormais.

#### 3.7 — Ville et territoire, ajustements de la joueuse  ✅

Six demandes formulées après coup sur la génération de carte, les champs et
l'exploration, plus une amorce de la Phase 4 (population) — remontée pour la
même raison que les cycles en Phase 2 : compter les habitants et les nourrir
n'a pas besoin du recrutement pour exister.

- **Le Nil prime sur la Méditerranée et la mer Rouge** pour le placement de la
  ville.
- **Un seul gisement non alimentaire de chaque matériau dans l'anneau des 8
  cases autour de la ville**, plafonné même par le tirage aléatoire — « pour
  éviter d'avoir directement tout ». Piège et ordre de garantie détaillés dans
  `CLAUDE.md`.
- **Terre non cultivable** (`ContenuDeZone::TerreNonCultivable`) : une case qui
  aurait pu porter un champ mais que le tirage n'a pas retenue reste
  identifiable comme telle — couvre aussi le fait que toutes les berges du Nil
  ne sont pas exploitables.
- **Les champs se resserrent autour de la ville** plutôt que de se disperser.
- **Un champ traverse quatre étapes nommées** — semis, pousse, récolte, repos
  (`EtapeDeChamp`), sur le modèle des étapes de chantier. Avoir un champ ne
  nourrit plus personne : seule l'étape « récolte » verse quelque chose au
  stock, Nil comme terrestre. Un champ du Nil reste piloté par la saison
  (Perèt ne rend plus rien, seul Chémou moissonne) ; un champ terrestre
  (Fertile, Oasis) suit son propre cycle indépendant
  (`CycleAgricoleTerrestre`, `Zone::quinzainesDepuisSemis`).
- **Le rayon gratuit de l'éclaireur passe d'une case à deux, et devient
  entièrement gratuit** — ni deben ni vivres à moins de trois cases de la ville
  (`RoleDExploration::coutPourUneDistance()` / `provisionsPourUneDistance()`).
  Au-delà, les deux sont dus.

**Amorce de la Phase 4 — population et subsistance.** Une ville compte ses
habitants et les nourrit à chaque quinzaine (`Subsistance`, après la récolte) ;
à défaut de vivres, la famine s'accumule et la partie peut basculer en échec
(`StatutDePartie::Echouee`) — conservée et consultable, jamais supprimée
(doc 00 : « chaque partie est une run complète »). Un nouvel attribut de vote,
`PartieVoter::JOUER`, ferme les actions qui modifient l'état sur une partie
échouée ; la lecture (`VOIR`) reste ouverte. La dotation royale couvre un an
complet de vivres, calculé sur la consommation réelle du foyer envoyé.

**Le modèle de population de ce lot a été entièrement refait en Phase 4** : il
déduisait les habitants d'une formule de bâtiments, sans consulter les docs 01
et 02 qui les chiffrent. Voir §6.3 — c'est de là que vient la leçon « avant
d'inventer, vérifier que le document ne dit rien ».

#### Hors périmètre, explicitement

**Les rôles d'exploration autres que l'éclaireur.** L'émissaire suppose des
PNJ, le chef d'expédition des zones lourdes, l'escorte des Medjaÿ — qui
n'arrivent qu'en Phase 10. La première mission se joue en difficulté 0, sans
aucune zone à bandits : l'éclaireur seul y suffit.

Également hors périmètre : la re-exploration (régions de difficulté 4+) ; le
commerce naval et le craft (Phase 5) ; les événements de zone et les énigmes
(Phase 7) ; le recrutement, les chefs et les travailleurs (Phase 4).

**Une incohérence connue, laissée en l'état.** Le doc 02 réserve l'épuisement
des gisements aux régions de difficulté 4 et plus, et
`PoidsDeTirage::gisementsEpuisables()` existe pour le dire — mais elle n'est
appelée nulle part : dans les faits, tout gisement non renouvelable se vide,
quelle que soit la région. Sans conséquence à ce stade (200 unités au Delta,
soit une quarantaine de quinzaines), à trancher quand les régions difficiles
arriveront : soit brancher la méthode, soit la supprimer et acter que tout
s'épuise.

#### Définition de « fini »

Parcours couvert de bout en bout : carte générée à la création de la partie →
éclaireur envoyé → cycles déclenchés → case révélée → ressource exploitée →
chantier financé par cette ressource. Plus un champ établi, un grenier bâti,
une moisson qui tombe en Chémou et pas en Akhèt, un Port dressé sur la berge
qui ouvre la pêche, et une ville qui nourrit ses habitants ou tombe en famine.

Tests unitaires sur les points où une régression passerait inaperçue : les
règles de placement géographique, le tirage pondéré, le rendement saisonnier,
le cycle de subsistance. La génération étant semi-aléatoire, ses tests portent
sur des **invariants** (la ville touche toujours l'eau si l'eau existe, la
grille fait toujours la bonne taille) plutôt que sur une carte attendue.

Les quatre portes qualité au vert, et une revue de sécurité sur les nouvelles
routes — exploiter une case, semer, ou envoyer un éclaireur modifie l'état
d'une partie et doit passer par le `PartieVoter`.

### Valeurs inventées, à calibrer en playtest

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

### 6.3 Phase 4 — Population : recrutement, chefs et travailleurs  ✅

**Sources** : docs `01` (chefs/travailleurs, effets de niveau), `03`
(recrutement, stats, traits, spécialités), `02` (familles disponibles,
consommation, mécontentement), `05` (délai de recrutement, départ naturel),
`13` (renommée).

**Intention.** Faire cesser la fiction d'une ville que le joueur actionne
seul. Avant cette phase, les bâtiments fonctionnaient parce qu'ils existaient,
les carrières s'exploitaient toutes seules et les champs se moissonnaient sans
personne. Désormais tout réclame des bras — et ces bras ont une famille à
nourrir et un salaire à toucher.

**Deux principes ont structuré la phase.** Le premier vient du doc 03 :
**chiffré en interne, qualitatif à l'affichage** — le moteur manipule des
compétences de 20 à 100, le joueur ne voit que des étoiles et des libellés.
Seul le salaire s'affiche en clair, étant déjà qualitatif par nature.

Le second est posé par la joueuse et vaut pour tout le projet : **les chiffres
des documents de conception sont provisoires**. Le critère n'est pas la
fidélité au document mais l'équilibre, et le fait de **pousser le joueur à se
servir des mécaniques du jeu**. Un système qu'on n'utilise jamais est un échec
au même titre qu'un système qui bloque.

#### Ce que la phase a livré

| Lot | Contenu | |
|---|---|---|
| 4.0 | Le deben devient la monnaie, l'or redevient un métal qu'on extrait et qu'on vend | ✅ |
| 4.1 | Habitants, actifs, inactifs ; bilan annuel ; naissances et appel d'habitants | ✅ |
| 4.2 | Le candidat : compétence, traits, spécialité, maisonnée | ✅ |
| 4.3 | L'offre d'emploi : poster, choisir, renvoyer | ✅ |
| 4.4 | Chefs et travailleurs des bâtiments, règle du demi-rendement | ✅ |
| 4.5 | Équipages du territoire et bâtiment gouvernant | ✅ |
| 4.6 | Salaires, masse salariale et calibrage de la phase | ✅ |
| 4.7 | Départs naturels, mécontentement, famine à deux paliers | ✅ |
| 4.8 | Ce que la compétence d'un chef change | ✅ |

#### Les règles qui en sortent

**La monnaie est le deben** (≈ 91 g), unité de compte pondérale du Nouvel
Empire — l'Égypte pharaonique n'a pas de monnaie frappée. L'or est un métal
qu'on extrait et qu'on vend au prix le plus élevé du jeu (30 deben). Sans cette
séparation, la mission 2 aurait fait exploiter une carrière de monnaie.

**La population se compte en trois nombres** — actifs, enfants, anciens
(décision de la joueuse). Le bilan tombe une fois l'an ; chaque personne est
tirée séparément, ce qui évite tout reliquat fractionnaire. On naît, mais
seulement s'il y a de la place : le Quartier d'habitation **plafonne**, il ne
peuple pas.

**Peupler passe par le logement, toujours.** Les deux voies — appel
d'habitants et embauche d'un chef, qui arrive avec sa maisonnée — butent sur le
même verrou. Et le chef repart avec les siens s'il est renvoyé ou s'il s'en va,
sans quoi le va-et-vient serait un peuplement gratuit.

**Ce sont les chefs qui recrutent** (doc 05). Un bâtiment sans chef ne réclame
aucun travailleur, donc tourne au plancher : « sans chef, la moitié » n'est pas
une règle à part mais un cas de la formule générale

```
rendement = 0,5 + 0,5 × (effectif réel / effectif requis)
```

**Rien ne s'éteint faute d'employés** (décision de la joueuse) : le doc 01 ne
parlait que de « capacité réduite ». Embaucher est un investissement, pas une
taxe — c'est ce qui rend la phase jouable.

**Le territoire aussi a des salariés** : un homme par champ, deux par carrière,
un par pêcherie. Chaque exploitation a un **bâtiment gouvernant** — Grenier,
Entrepôt, Port — dont le niveau élargit l'équipage réclamé *et* le rendement.
C'est ce qui referme la boucle du jeu, et rend un niveau coûteux avant d'être
payant.

**Les salaires tombent à chaque quinzaine, avant la production.** L'unité de
paiement est le bâtiment ou l'exploitation entière, jamais l'homme. Une unité
impayée **s'arrête** — elle rend donc moins qu'une unité vacante, ce qui donne
au joueur une action claire (renvoyer) plutôt qu'une spirale subie.

**Le mécontentement a deux causes et un seul mécanisme** : la faim et l'impayé
mènent à la même colère. Elle ralentit la production, précipite les départs et
fait perdre de la renommée. La famine se lit à deux paliers : mécontentement à
quatre quinzaines, échec à douze — compromis entre le « pas de game over
brutal » du doc 02 et l'échec demandé au lot 3.7.

#### Écarts assumés aux documents

| Point | Document | Retenu | Pourquoi |
|---|---|---|---|
| Salaire d'un chef | `5 + comp × 0,3` (11-35) | `2 + comp × 0,12` (4-14) | Un seul chef dépassait tout ce qu'une ville du Delta peut gagner. L'écart mauvais/excellent reste de ×3 |
| Travailleurs du Port | 3 | **1** | Un chef et un homme tiennent un quai ; à trois, l'équipage mangeait toute la pêche |
| Bâtiments sans spécialité | — | Ne se dirigent pas | Résidence, Quartier, Auberge : la famille les tient elle-même. Déduction, pas une ligne de document |

#### Pièges payés pendant la phase

- **`ajusterRenommee()` n'était appelé de nulle part.** La renommée valait zéro
  pour toujours ; toute règle indexée dessus serait restée inerte sans qu'aucun
  test ne le signale. C'est le Marché qui l'alimente désormais. **Avant
  d'indexer une règle sur une valeur, vérifier qu'une source la fait bouger.**
- **Un double comptage de rendement**, introduit au lot 4.4 et retiré au 4.5 :
  deux planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout
  tourne au moins à moitié » que la règle promet. Un test garde désormais
  l'invariant. **Avant d'ajouter un multiplicateur à une production, vérifier
  qu'aucun autre ne s'y applique déjà.**
- **Le rendement propre d'un bâtiment était devenu décoratif** une fois ce
  double comptage retiré — défaut réel, rattrapé au 4.6 : il module désormais
  le **bonus** que le bâtiment accorde à ses exploitations, jamais leur base.
- **Une offre d'emploi doit figer son tirage.** `JobOffer` est persistée, ce qui
  contredit en apparence le lot 4.2 : sans cela, recharger la page relancerait
  les dés jusqu'au cinq étoiles.
- **Semer un `Mt19937` par candidat avec des entiers consécutifs** produit des
  premiers tirages corrélés, ce qui fausse toute mesure de distribution.

#### Ce que la phase abandonne, et ce qu'elle laisse ouvert

- **Le suivi des âges** permettait à un candidat d'annoncer « deux enfants
  bientôt en âge de travailler ». Le modèle agrégé supprime ce signal : c'est
  le prix de la simplicité demandée, et il se paie sur la richesse du choix à
  l'embauche.
- **L'ordre de service des bâtiments est alphabétique** quand les bras
  manquent — stable et explicable, mais arbitraire : le joueur ne peut pas dire
  lequel servir en premier. À reprendre si le playtest montre que ça coûte des
  parties.
- **La dotation avance une année de salaires des bras envoyés** (100 deben),
  pas d'un chef, qui coûte 200 par an à lui seul. Embaucher avant d'avoir un
  revenu de Marché mène à la faillite en une quinzaine de cycles : tension
  voulue, mais raide, à surveiller.

#### Calibrages vérifiés plutôt que postulés

- **Démographie**, 200 parties de vingt ans : sans Quartier la population fond
  de 10 à 5, avec un Quartier de niveau 1 elle monte à 13, aucune ville ne
  s'éteint.
- **Économie**, sur la ville d'exemple du lot 4.6 : 15 emplois, ~34 vivres
  produits pour 26 mangés, ~68 deben de revenus pour 39 de salaires. La marge
  est mince sans être étouffante.
- **Distribution des traits**, 400 tirages : 46,8 / 38,2 / 15,0 % contre des
  taux visés de 45 / 40 / 15.
- **Espérance de service d'un chef**, 300 tirages : une vingtaine de quinzaines
  pour une ancienneté annoncée de 20.
- **La spirale de mécontentement se redresse** : une ville affamée huit
  quinzaines puis ravitaillée retrouve le calme en huit, sans perdre la partie
  ni sa population. C'est là que ce genre de mécanisme casse — quand le malus
  empêche de produire de quoi lever sa propre cause.

**Un déplacement d'équilibre à assumer** : la masse salariale dépasse largement
le coût des bâtiments (un Grenier coûte 15 deben, une quinzaine de salaires en
coûte 39). Le poste de dépense principal du jeu cesse d'être la construction
pour devenir l'emploi — cohérent avec la phase et historiquement défendable,
mais à voir plutôt qu'à subir.

#### 4.8 — Ce que la compétence d'un chef change réellement  ✅

Le doc 01 pose que « la compétence d'un chef influence la production du
bâtiment ». Encore faut-il que le bâtiment produise quelque chose : la plupart
n'ont d'effet qu'à partir de la Phase 5 (Atelier, Forge) ou plus tard.

**Principe de périmètre** : n'implémenter les effets de chef que là où une
production existe déjà — trois bâtiments, et le territoire depuis le lot 4.5.

| Bâtiment | Effet du chef | Spécialité (doc 03) |
|---|---|---|
| Grenier | Réduit les pertes sur la récolte | Gestionnaire rigoureux |
| Port | Module le rendement de pêche | Pêcheur (+20 %) |
| Marché | Module les prix | Acheteur / Vendeur (±10 %) |

Les spécialités des autres bâtiments sont **générées** dès maintenant (un
candidat est un profil complet, doc 03) mais restent **inertes** jusqu'à leur
phase. L'interface doit l'annoncer clairement.

##### Livré

**La compétence n'a créé aucun multiplicateur nouveau** — c'était le risque
annoncé du lot. Elle passe par la *qualité de direction* d'un bâtiment, aux
côtés de son effectif : `qualité = rendement d'effectif × facteur du chef`,
et c'est cette qualité qui module le bonus accordé aux exploitations. Un
facteur de plus sur la base aurait refait tomber la chaîne sous le « tout
tourne au moins à moitié ».

Le facteur vaut `90 + compétence × 0,4`, soit **98 % à 20 de compétence et
130 % à 100** (valeurs inventées). L'invariant qui en découle et qu'il ne faut
pas défaire : **un mauvais chef reste meilleur que pas de chef**. Un bâtiment
désert tourne au plancher de 50 % ; le pire des chefs, son équipe au complet,
rend 98 %. Sans cela, embaucher au hasard serait un risque de faire pire que
rien, et le joueur n'oserait plus.

**Le Marché trouve enfin son effet de personnel** : ses prix suivent sa
qualité de direction. Un Marché désert écoule à moitié prix, ce qui fait du
chef du Marché exactement ce que le calibrage du lot 4.6 annonçait — il
**double les prix de vente**.

Mesuré sur une vente de dix calcaire, contre 4 à 14 deben de salaire :

| Direction du Marché | Recette | Gain |
|---|---|---|
| Aucun chef | 15 deben | — |
| Chef ★ (compétence 20) | 32 deben | +17 |
| Chef ★★★ (compétence 60) | 37 deben | +22 |
| Chef ★★★★★ (compétence 100) | 42 deben | +27 |

L'embauche se défend donc toujours au Marché, même mal servie par le tirage —
peut-être un peu trop : c'est le logement, la nourriture et la masse salariale
qui la bornent, pas la rentabilité. À surveiller en playtest.

**Une correction au passage** : l'Acheteur du Marché était annoncé comme
agissant alors que **rien ne s'achète encore** (l'achat vient en Phase 5). Il
rejoint les spécialités endormies, que l'interface signale désormais
explicitement — pour les chefs en poste comme pour les candidats qu'on compare.

**Une limite mesurée, à connaître** : aux ordres de grandeur actuels, l'arrondi
entier peut avaler une spécialité. Sur une pêche de référence de 10 unités,
l'écart de +20 % du Pêcheur disparaît si le Port est en sous-effectif. Le
signal existe, il n'est pas toujours visible — c'est un argument pour relever
les références plutôt que les bonus, si le playtest le confirme.

#### Hors périmètre, explicitement

- **Les Medjaÿ et le combat** (doc 03, seconde moitié) restent en Phase 10 :
  unités, équipement, formule de résolution, blessures et morts permanentes.
  La Caserne se dote de chefs comme les autres bâtiments, mais ne recrute
  aucun Medjaÿ dans cette phase
- **Le Charrier** (réquisition, Caserne niveau 7) suit les Medjaÿ en Phase 10
- **Le craft** de l'Atelier et de la Forge, et le commerce par caravanes de
  l'Entrepôt : Phase 5
- **Le bonus de main-d'œuvre d'Akhèt** sur le vivier régional (doc 05) — à
  ajouter une fois le vivier stabilisé, pas dans le même lot
- **Les capacités de stockage** du Grenier et de l'Entrepôt, et la péremption
  du surplus à l'air libre (doc 01) : un système d'inventaire à part entière,
  qui mérite son propre lot
- **Le kite**, dixième du deben : sans objet tant que les prix restent en
  nombres entiers de deben

#### Points tranchés avec la joueuse

| Question | Décision | Lot |
|---|---|---|
| Quelle monnaie ? | Le **deben** ; l'or redevient un métal qu'on extrait et qu'on vend | 4.0 |
| Que recrute-t-on ? | **Des chefs seulement**, par offre ; chacun s'installe avec sa famille. Les ouvriers se puisent dans le vivier d'actifs | 4.1 / 4.3 |
| Quelle granularité de population ? | **Aucune granularité fine** : trois nombres — actifs, enfants, anciens. Ni foyers ni âges individuels | 4.1 |
| Tous les adultes travaillent-ils ? | **Oui, sans distinction de sexe** — les Égyptiennes travaillaient | 4.1 |
| Que mange une personne ? | **Une ration** pour un actif, **une demi** pour un inactif | 4.1 |
| D'où viennent les nouveaux habitants ? | Les volontaires du pharaon à l'ouverture, puis **l'appel du joueur**, indexé sur la renommée et borné par le logement | 4.1 |
| Les PNJ ont-ils un nom ? | **Non pour l'instant** | 4.2 |
| Un poste vacant fait-il tout cesser ? | **Non, tout tourne à moitié** — aucune impasse possible | 4.4 |
| Champs, gisements et pêcheries ont-ils des salariés ? | **Oui** : 1 par champ, 2 par gisement, 1 par pêcherie. Le bâtiment qui les gouverne augmente équipage **et** rendement | 4.5 |
| Les travailleurs coûtent-ils ? | **Oui**, bien moins qu'un chef | 4.6 |
| Salaires impayés ? | Le poste **s'arrête**, puis mécontentement et départs | 4.6 |
| Que donne le pharaon ? | Un an de vivres **et** un an de salaires | 4.6 |

#### Définition de « fini »

Parcours de bout en bout : poster une offre → comparer des candidats aux
compétences, traits et foyers visibles → en choisir un → le voir prendre son
poste à la quinzaine suivante → le voir embaucher ses ouvriers → affecter des
ouvriers à un gisement et à un champ → payer salaires et vivres à chaque
quinzaine → voir un PNJ partir naturellement et l'offre se rouvrir.

Tests unitaires sur les formules du doc, qui sont le cœur calculatoire ;
tirages aléatoires testés sur des **invariants et des distributions**, jamais
sur un résultat attendu.

**Reste une vérification en conditions réelles**, qu'aucun test unitaire ne
peut juger : mener une partie sur une année complète au navigateur et
constater qu'une ville correctement gérée ne meurt ni de faim ni de faillite.
Les calibrages sont mesurés pièce par pièce ; leur composition sur une partie
entière ne l'est pas encore.

---


### 6.4 Phase 5 — Artisanat et commerce

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

#### 5.0 — Les ressources fabriquées  *(prérequis)*

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

#### 5.1 — Capacité de stockage : plafonner sans périmer *(décision de la joueuse)*

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
