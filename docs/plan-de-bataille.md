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
- [ ] **Phase 4** — Population : recrutement, chefs et travailleurs · §6.3 ·
      `01`, `03`, `02`, `05` — amorcée au lot 3.7 (habitants, consommation,
      famine), dont le lot 4.0 corrige les écarts aux documents
- [ ] **Phase 5** — Artisanat et commerce · `08`, `12`
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
  surplus, prix inventés) : sans lui, l'or n'avait aucune source renouvelable
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
- **Reconnaître les abords de la ville ne coûte pas d'or** (rayon étendu et
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
quinzaine à l'autre, son compteur ne descend jamais. Un Port coûte 50 or,
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
  entièrement gratuit** — ni or ni vivres à moins de trois cases de la ville
  (`RoleDExploration::coutPourUneDistance()` / `provisionsPourUneDistance()`).
  Au-delà, les deux sont dus.

**Amorce de la Phase 4 — population et subsistance.** Une ville affiche ses
habitants (`Population`) : une famille fondatrice fixe, plus un supplément par
niveau de Quartier d'habitation. Chaque quinzaine, la ville consomme de la
nourriture selon sa population (`Subsistance`, après la récolte) ; à défaut de
vivres suffisants, la famine s'accumule et, passé un seuil, la partie bascule
en échec (`StatutDePartie::Echouee`) — conservée et consultable, jamais
supprimée (doc 00 : « chaque partie est une run complète »). Un nouvel
attribut de vote, `PartieVoter::JOUER`, ferme les actions qui modifient l'état
sur une partie échouée ; la lecture (`VOIR`) reste ouverte. La dotation royale
couvre désormais un an complet de vivres pour la famille fondatrice plutôt
qu'une réserve fixe.

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

### Valeurs inventées de la Phase 3, à calibrer en playtest

Aucun document ne les chiffre. Toutes signalées comme telles dans le code.

| Valeur | Retenue | Où |
|---|---|---|
| Récolte d'un champ par quinzaine, à la récolte | 10 | `RendementDesChamps::RECOLTE_DE_REFERENCE` |
| Extraction d'un gisement par quinzaine | 5, avant rareté régionale | `Recoltes::EXTRACTION_DE_REFERENCE` |
| Durées du cycle agricole terrestre | semis 1 / pousse 3 / récolte 1 / repos 2 quinzaines | `CycleAgricoleTerrestre` |
| Provisions d'un éclaireur (au-delà du rayon gratuit) | 5 vivres | `RoleDExploration::provisions()` |
| Rayon gratuit de l'éclaireur | < 3 cases, or et vivres | `RoleDExploration` |
| Habitants de base / par niveau de Quartier | 5 / +10 | `Population` |
| Ration par habitant | 1 vivre/quinzaine | `Population::RATION_PAR_HABITANT` |
| Seuil de famine avant échec de partie | 4 quinzaines consécutives | `Subsistance::SEUIL_DE_FAMINE` |
| Dotation royale en vivres | population de base × ration × 25 quinzaines (≈125 blé) | `DotationRoyale` |

**Quatre de ces valeurs n'avaient pas à être inventées.** Les trois dernières
lignes, plus celle des habitants, touchent à la population — que les docs 01 et
02 chiffrent en réalité (`consoParCycle = 2`, capacité du Quartier
`20 × niveau`, `nbFamillesDisponibles = 20 - 1,5 × difficulté`). Elles ont été
posées sans consulter ces documents ; le lot 4.0 les réaligne. Les autres
lignes restent bien des inventions assumées, les docs ne les chiffrant nulle
part.

---

### 6.3 Phase 4 — Population : recrutement, chefs et travailleurs

**Sources** : docs `01` (chefs/travailleurs, effets de niveau), `03`
(recrutement, stats, traits, spécialités), `02` (familles disponibles,
consommation, mécontentement), `05` (délai de recrutement, départ naturel).

**Intention.** Faire cesser la fiction d'une ville que le joueur actionne
seul. Jusqu'ici les bâtiments fonctionnent parce qu'ils existent, les carrières
s'exploitent toutes seules et les champs se moissonnent sans personne ; à la
fin de cette phase, tout cela réclame des bras — et ces bras ont une famille à
nourrir et un salaire à toucher.

À la fin de la phase, on doit pouvoir raconter : *« je poste une offre pour
diriger mon Grenier. Trois candidats se présentent : je prends le moins
compétent des trois, parce qu'il arrive avec deux grands enfants qui seront en
âge de travailler dans l'année. Il s'installe avec les siens à la quinzaine
suivante et va chercher ses ouvriers parmi les adultes de la ville. Ma ville
compte désormais quarante habitants, vingt-huit bouches à nourrir et une masse
salariale en deben à verser chaque quinzaine. »*

**Deux principes structurent la phase.** Le premier vient du doc 03 :
**chiffré en interne, qualitatif à l'affichage** — le moteur manipule des
compétences de 20 à 100, le joueur ne voit que des étoiles et des libellés.
Seul le salaire s'affiche en clair, étant déjà qualitatif par nature.

Le second est posé par la joueuse et vaut pour tout le projet : **les chiffres
des documents de conception sont provisoires** et se rectifient au fil de la
conception. Le critère n'est pas la fidélité au document mais l'équilibre, et
le fait de **pousser le joueur à se servir des mécaniques du jeu**. Un système
qu'on n'utilise jamais est un échec au même titre qu'un système qui bloque.

#### 4.0 — Le deben : introduire la monnaie  ✅  *(prérequis)*

**L'Égypte pharaonique n'a pas de monnaie frappée** — elle n'apparaît que sous
domination perse, puis chez les Ptolémées. Les échanges du Nouvel Empire se
font par troc, mais avec une **unité de compte pondérale** : le **deben**
(≈ 91 g) et son dixième, le **kite**. Les ostraca de Deir el-Médineh chiffrent
les prix en deben de cuivre pour le quotidien, en deben d'argent ou d'or pour
les grosses sommes.

Le jeu confond aujourd'hui deux choses sous le même nom : `Ressource::Or` sert
à la fois de monnaie et de matériau — au point que la mission 2 (Haute-Nubie)
liste l'or parmi ses ressources de zone, ce qui rendrait une carrière de
monnaie. La confusion se défait :

- **Le deben devient la monnaie** — dotation, salaires, coûts de construction,
  prix du Marché, solde des expéditions. Techniquement, une ligne de stock de
  plus (`Ressource::Deben`), le stock étant générique depuis le lot 3.1 : rien
  à migrer que les valeurs
- **L'or redevient un métal qu'on extrait**, avec un prix au Marché — le plus
  élevé du jeu, l'or valant historiquement un multiple de son poids en cuivre.
  Les mines de Nubie de la mission 2 retrouvent ainsi leur sens : on y extrait
  de l'or, qu'on convertit en deben en le vendant
- Une douzaine d'appels à `Ressource::Or` sont concernés, plus trois gabarits

**À corriger au passage** : `CLAUDE.md` met encore en garde contre la colonne
`stock_or` et le mot réservé `or` du SQL. Cette colonne n'existe plus depuis
que le lot 3.1 a généralisé le stock en table `ressource → quantité` — la
leçon sur les mots réservés reste bonne, son exemple est périmé.

##### Livré

Le prix de l'or est fixé à **30 deben l'unité**, le plus élevé du jeu — le
rapport réel de l'or au cuivre sous le Nouvel Empire était bien plus écrasant,
la valeur est comprimée pour rester jouable comme le reste de `PrixDuMarche`.

La migration `Version20260828140000` ne convertit **que le stock**, jamais les
gisements : une ligne de stock `or` était de la monnaie, un gisement `or` est
une mine. Aucun gisement d'or n'existait en base — la mission 2 n'étant pas
atteignable — mais la distinction devait être écrite pour les migrations
futures.

Un test de rendu vérifie que la barre de jeu affiche bien deux compteurs
distincts, la dotation en deben d'un côté et l'or extrait de l'autre
(`VilleTest::testLaBarreDeJeuCompteEnDebenEtRangeLOrParmiLesMateriaux()`), et
un autre que l'or se vend désormais au Marché comme n'importe quel métal.

#### 4.1 — Habitants, actifs, inactifs  ✅

Le lot 3.7 avait posé la population sans consulter les documents ; une première
version de ce lot a suivi chaque maisonnée et l'âge de chacun de ses enfants.
La joueuse a tranché pour l'inverse : **aucune granularité fine**. Le modèle
retenu tient en trois nombres.

- **La ville compte des habitants, des actifs et des inactifs** — ces derniers
  étant les enfants et les anciens réunis. Aucun individu n'est suivi : ce qui
  importe est de savoir combien de bras la ville a et combien de bouches.
- **Le bilan se fait une fois l'an**, pas à chaque quinzaine : des enfants
  entrent dans la vie active, des actifs passent la main, et la mort prend sa
  part. Une année compte 25 quinzaines, ce qui laisse au joueur le temps de
  voir venir.
- **Un actif mange une ration, un inactif une demi-ration.**
- **Le Quartier d'habitation plafonne, il ne peuple pas** (`20 × niveau`
  maisonnées, doc 01), et l'écran doit dire au joueur quand il manque des
  logements pour faire venir du monde.
- **Le pharaon envoie des volontaires** s'installer avec la famille du joueur :
  c'est ainsi que la ville s'ouvre. Ensuite, faire venir des habitants devient
  une action du joueur, adossée à la renommée — et impossible sans logement.

##### Livré

Quatre actifs, cinq enfants et un ancien à l'ouverture, soit dix habitants pour
deux maisonnées — que la seule Résidence familiale ne loge pas : le joueur
manque de logements dès la première quinzaine, ce qui rend le Quartier
d'habitation immédiatement lisible comme un besoin.

Trois choix d'implémentation qui découlent de règles du projet :

- **Chaque personne est tirée séparément** plutôt qu'un pourcentage appliqué à
  un total. C'est ce qui permet de rester en entiers sans traîner de reliquat
  d'une année sur l'autre — un taux de 3 % sur douze actifs ne donnerait sinon
  jamais rien — et la variance qui en résulte est juste : certaines années sont
  plus dures que d'autres.
- **C'est `PassageDeCycle` qui décide du moment du bilan**, pas `Demographie`.
  Lui faire vérifier la date lui-même le faisait tomber dès le premier cycle
  d'une partie, où la ville vient tout juste d'arriver — `ouvreUneAnnee()` est
  vrai au cycle 1.
- **La consommation se compte en demi-rations**, converties une seule fois à
  l'échelle de la ville et arrondies au supérieur. Arrondir groupe par groupe
  ferait manger un inactif isolé gratuitement.

**Vérifié sur vingt ans de simulation** plutôt que postulé : la population
passe de 10 à 7 habitants pendant que les actifs montent de 4 à 6, les enfants
mûrissant plus vite que les anciens ne s'éteignent. C'est une pression lente,
pas un effondrement — mais **personne ne naît**, et une ville laissée à
elle-même finit donc par s'éteindre. C'est le prix assumé du choix de faire de
l'immigration une action du joueur ; à resurveiller si le mode Aventure allonge
franchement les parties.

La migration donne aux parties en cours la même troupe de volontaires qu'aux
nouvelles. C'est une **réparation, pas une invention** : ce contingent fait
partie des conditions de départ, et une ville créée avant la règle ne l'a
jamais reçu. Recopier à la place les deux adultes de l'ancienne table lui
aurait laissé une population incapable de croître.

##### Ce que ce modèle abandonne

Le suivi des âges permettait à un candidat d'annoncer « deux enfants bientôt en
âge de travailler », et faisait de l'âge un critère d'embauche à côté de la
compétence. Ce signal disparaît : un candidat annonce désormais des actifs et
des inactifs, sans dire quand ces derniers deviendront des bras. C'est le prix
de la simplicité demandée, et il se paie sur la richesse du choix à l'embauche.

##### À reprendre plus tard

##### Repris ensuite — naissances et appel d'habitants  ✅

Les deux manques ci-dessus sont comblés, dans le même lot parce qu'ils se
tiennent : sans naissances l'appel d'habitants est la seule source de
population, et sans logement ni l'un ni l'autre ne produit quoi que ce soit.

- **On naît, mais seulement s'il y a de la place.** Un actif a une chance sur
  dix de donner un enfant dans l'année (valeur inventée), et aucune quand les
  maisons sont pleines. La ville ne déborde jamais de son logement —
  simplification assumée, qui rend le plafond du Quartier lisible plutôt que
  théorique.
- **Faire venir une maisonnée** coûte des deben, d'autant moins que la famille
  est connue (`PalierDeRenommee::coutDAppel()`, 30 à 5), et se refuse tant
  qu'il manque des logements.
- **La migration spontanée** du doc 13 s'ajoute à partir de « Respectée » :
  une maisonnée s'installe d'elle-même, sans qu'on l'appelle ni qu'on la paie.

**Un défaut de fond trouvé en chemin** : `ajusterRenommee()` n'était appelé de
nulle part. La renommée valait donc zéro pour toujours, et tout ce qui aurait
été indexé dessus — prix d'un appel, migration spontanée — serait resté inerte
sans qu'aucun test ne le signale. Le Marché l'alimente désormais : le doc 13
accorde +1 pour un « gros contrat commercial conclu », dont le seuil (40 deben)
est inventé et à recalibrer. C'est aujourd'hui la seule source de renommée
branchée.

**Calibrage vérifié**, pas postulé — 200 parties de vingt ans par cas :

| Logement | Population après 20 ans | Actifs | Villes éteintes |
|---|---|---|---|
| Aucun Quartier (1 foyer) | 10 → 5 | 3 | 0 % |
| Quartier niveau 1 (21 foyers) | 10 → 13 | 7 | 0 % |

Ne pas bâtir coûte des habitants, bâtir en fait gagner lentement, et aucune
ville ne s'éteint. C'est la pression voulue : le Quartier d'habitation cesse
d'être décoratif sans que la démographie devienne une course.

#### 4.2 — Le candidat : compétence, traits, spécialité  ✅

Règles pures, dans `src/Game/`, sans rien de persisté — c'est du contenu, pas
de l'état. Les valeurs viennent du doc 03.

- Compétence tirée uniformément entre **20 et 100** ; barème d'affichage en
  étoiles : 20-36 ★, 37-52 ★★, 53-68 ★★★, 69-84 ★★★★, 85-100 ★★★★★
- Salaire demandé — voir le calibrage du lot 4.6, qui s'écarte du doc 03
- Ancienneté probable de base = **20 quinzaines**, affichée en libellé
  (« devrait rester longtemps » / « risque de partir bientôt »)
- **La maisonnée** qu'il amène : deux actifs et de zéro à six inactifs — une
  information que le joueur doit voir avant de choisir, puisqu'elle décide de
  ce que le candidat coûtera à nourrir et des bras qu'il apporte
- **Huit traits** : Travailleur acharné, Économe, Fidèle, Ambitieux, Croyant,
  Bagarreur, Expérimenté, Novice, avec leurs effets chiffrés du doc 03
- Tirage des traits : **45 % aucun, 40 % un seul, 15 % deux**, chacun des huit
  à parts égales. **Incompatibilités** : Ambitieux/Fidèle et Travailleur
  acharné/Économe ne peuvent jamais coexister
- **Spécialité de chef**, tirée au hasard selon le bâtiment dirigé (doc 03) :
  Pêcheur ou Commerçant naval au Port, Acheteur ou Vendeur au Marché,
  Gestionnaire rigoureux au Grenier, etc.
- **Pas de nom** (décision de la joueuse) : un PNJ se désigne par son poste et
  ses statistiques. La question se reposera quand les écrans montreront
  plusieurs employés d'un même bâtiment

##### Livré

`Candidat` n'est jamais persisté : une candidature ne dure que le temps du
choix, seul le foyer embauché survit (lot 4.3). Sa compétence reste accessible
au moteur — les calculs de production en auront besoin au lot 4.8 — mais le
docblock interdit explicitement à tout gabarit de l'imprimer : c'est là que se
joue le « chiffré en interne, qualitatif à l'affichage ».

Trois choses que le document laissait implicites et qu'il a fallu trancher :

- **La compétence est bornée après application des traits.** Un
  « Expérimenté » à 95 passerait sinon à 118, hors du barème d'étoiles.
- **Les traits étendent la fourchette de salaire** au-delà de la formule de
  base : mesuré sur 400 tirages, 3 à 16 deben pour une moyenne de 8,4, quand
  la formule seule donne 4 à 14. C'est l'effet cumulé d'« Expérimenté » et de
  « Novice », et il reste dans l'ordre de grandeur voulu.
- **Trois bâtiments n'ont aucune spécialité** — Résidence familiale, Quartier
  d'habitation, Auberge —, le doc 03 n'en listant pas pour eux.

**Révisé avec le lot 4.1** : le candidat annonçait d'abord l'âge de chacun de
ses enfants, ce qui faisait de l'âge un critère d'embauche. Le passage de la
population aux compteurs agrégés a supprimé ce signal — il annonce désormais
des actifs et des inactifs, sans dire quand ceux-ci deviendront des bras.

La distribution des traits est vérifiée sur 400 tirages plutôt que postulée :
46,8 % sans trait, 38,2 % avec un, 15,0 % avec deux, pour des taux visés de
45 / 40 / 15.

**Un piège de méthode évité** : semer un `Mt19937` par candidat avec des
entiers consécutifs produit des premiers tirages corrélés, ce qui aurait
faussé toute mesure de distribution. Les tests emploient donc un générateur
unique qui tire longuement, et deux graines distinctes pour le foyer et le
profil — les partager aurait lié la taille de la famille à la compétence.

#### 4.3 — L'offre d'emploi : poster, choisir, renvoyer

**Seuls les chefs se recrutent par offre** (décision de la joueuse, conforme
au doc 05 : « les chefs recrutent des travailleurs disponibles »). Les
ouvriers, eux, se puisent dans le vivier d'adultes déjà installés — le joueur
n'embauche pas ses manœuvres un par un.

- Poster une offre selon le poste recherché — **action libre**, elle ne
  consomme pas de quinzaine (doc 05)
- **2 à 3 candidats** générés, comparés côte à côte
- Le choix fait, le poste est **pourvu à la quinzaine suivante** (doc 05 :
  « durée d'un recrutement une fois le candidat choisi : 1 cycle »), et **le
  chef s'installe avec sa famille** — dont les autres adultes rejoignent le
  vivier de main-d'œuvre
- Renvoyer ou remplacer à tout moment
- Traits « Pieux » et « Bagarreur » posés dès maintenant mais **sans effet**
  tant que la faveur divine (Phase 6) et le combat (Phase 10) n'existent pas —
  l'affichage doit le dire plutôt que de laisser croire à un bonus actif

#### 4.4 — Chefs et travailleurs des bâtiments

Les deux formules du doc 01, appliquées à tous les bâtiments :

- **`nbChefs(niveau) = arrondiSupérieur(niveau / 3)`** — 1 chef aux niveaux 1-3,
  2 aux niveaux 4-6, 3 aux niveaux 7-9
- **`travailleursParChef(niveau) = travailleursBase + arrondiInférieur((niveau - 1) / 3)`**,
  où `travailleursBase` est propre au bâtiment — déjà en place dans le code
  (`TypeDeBatiment::travailleursDeBase()`), posé en Phase 2 et inutilisé depuis
- Les chefs **puisent leurs travailleurs dans le vivier d'adultes résidents**
  au passage d'une quinzaine (doc 05) — le joueur voit une jauge
  « X / Y travailleurs » par chef, il ne recrute pas les ouvriers un par un
- **Le Port descend à 1 travailleur de base** (décision de la joueuse), contre
  3 au doc 01 : un chef et un homme suffisent à tenir un quai. C'est la
  correction du point faible signalé au plan précédent, où l'équipage du Port
  mangeait tout ce que la pêche rapportait

##### La règle du demi-rendement, valable partout

Le doc 01 ne parlait que du sous-effectif (« capacité réduite »), jamais de
l'absence totale de personnel. Tranché avec la joueuse : **rien ne s'éteint
faute d'employés, tout tourne au moins à moitié** — « il ne stocke que la
moitié de ce qui est produit ». Une partie sans deben pour embaucher continue
donc de fonctionner, au ralenti.

Une seule formule, appliquée aux bâtiments comme aux exploitations du
lot 4.5 :

```
rendement = 0,5 + 0,5 × (effectif réel / effectif requis)
```

Sans personne, la moitié ; au complet, le plein ; entre les deux, la réduction
proportionnelle que le doc 01 appelle « capacité réduite ». La compétence des
chefs se module ensuite par-dessus (lot 4.8).

**Conséquence structurante** : les employés cessent d'être une taxe
obligatoire pour devenir un **investissement**. C'est ce qui rend la phase
jouable.

#### 4.5 — Le territoire aussi a des salariés (décision de la joueuse)

Correction d'une invraisemblance que la Phase 3 avait laissée passer : une
carrière s'exploitait et un champ se moissonnait sans que personne n'y
travaille. Désormais :

| Exploitation | Travailleurs de base | Bâtiment qui la gouverne |
|---|---|---|
| Un champ semé | **1** | Grenier |
| Un gisement en extraction | **2** | Entrepôt |
| Une pêcherie | **1** — un homme, un bateau | Port |

La règle du demi-rendement du lot 4.4 s'y applique telle quelle : un gisement
sans personne rend la moitié — la famille s'en occupe elle-même — et le plein
une fois ses deux ouvriers en place.

C'est le lot qui **répare le déséquilibre le plus profond** de la phase.
Jusqu'ici l'extraction ne dépendait d'aucun bâtiment et rapportait autant à
une ville déserte qu'à une ville pourvue : la moitié de l'économie échappait
au système d'emploi. Elle y entre.

##### Une règle uniforme : le bâtiment gouverne son exploitation

Piste de la joueuse, généralisée aux trois cas : **le niveau du bâtiment
augmente à la fois l'équipage affectable et le rendement** de l'exploitation
qu'il gouverne — le Grenier pour les champs, l'Entrepôt pour les gisements, le
Port pour les pêcheries.

Trois bénéfices d'un coup, pour une seule règle :

- elle donne enfin un **effet concret aux niveaux** de trois bâtiments qui n'en
  ont aucun aujourd'hui ;
- elle referme la boucle du jeu — bâtir plus haut → employer plus → produire
  plus → pouvoir employer davantage ;
- elle règle le cas du Port sans traitement particulier : monter le Port fait
  pêcher davantage et arme plus de bateaux.

À écrire dans le même lot que les équipages de base, dont elle n'est que la
progression.

#### 4.6 — Le coût d'employer : salaires et vivres

La première charge récurrente en deben du jeu, à côté de la nourriture.

- Salaires prélevés **à chaque quinzaine**, avec les vivres, dans
  `PassageDeCycle` — même place que `Subsistance`
- La ville mange **1 vivre par personne** (lot 4.1)
- **Les travailleurs coûtent aussi** (décision de la joueuse), bien moins
  qu'un chef. Le doc 03 ne chiffre que les candidats recrutés par offre ; un
  travailleur n'ayant ni compétence tirée ni candidature, son salaire est une
  valeur à inventer
- **La dotation royale couvre un an de salaires** (décision de la joueuse), en
  plus de l'année de vivres déjà acquise au lot 3.7 — le pharaon finance le
  démarrage, pas la suite

##### Salaires impayés : le bâtiment s'arrête

Aucun document ne le disait. Tranché : **un bâtiment ou une exploitation dont
les salaires ne sont pas payés cesse de fonctionner**, et le mécontentement
suit le même chemin que la famine — il s'accumule, puis les employés partent.

**Une conséquence à assumer, ou à corriger en playtest** : un poste impayé
rend *moins* qu'un poste vacant, qui tourne encore à moitié. Le joueur a donc
intérêt à renvoyer un employé qu'il ne peut plus payer plutôt qu'à le laisser
en poste. C'est défendable — des gens non payés cessent le travail, alors
qu'une carrière sans ouvriers reste grattée par la famille — et ça donne au
joueur une action claire à prendre plutôt qu'une spirale subie. Si l'effet
paraît pervers à l'usage, le levier est de ramener l'impayé à moitié lui aussi.

##### La masse salariale

Notion que le jeu doit porter explicitement (demande de la joueuse) : ce que
la ville doit verser à chaque quinzaine, **calculé à partir de la composition
réelle des foyers** et non d'un forfait. Elle bouge toute seule, sans que le
joueur ait rien fait — un enfant qui atteint douze ans grossit le vivier, donc
potentiellement l'emploi ; un départ naturel la fait chuter.

Elle se lit à côté de son pendant alimentaire — population totale d'un côté,
bouches nourries de l'autre — et ces deux chiffres sont les indicateurs de
santé de la ville.

##### Calibrage de travail

Première proposition cohérente, vérifiée à la main, à retoucher en playtest —
au même titre que les valeurs des documents. Elle touche autant aux inventions
du lot 3.5 qu'aux chiffres des docs 01 et 03.

| Valeur | Avant | Proposé | Pourquoi |
|---|---|---|---|
| Extraction d'un gisement | 5 | **20** (2 ouvriers) | Invention du lot 3.5. Dix unités par ouvrier, comme la pêche |
| Pêche | 5 | **10** (1 ouvrier) | Même productivité par tête que la mine ; c'est l'équipage qui diffère, pas l'homme |
| Récolte d'un champ | 10 | **25** | Un champ du Nil ne donne que pendant Chémou : ~8 par quinzaine ramené à l'année, pour un seul ouvrier |
| Durée du cycle terrestre | semis 1 / pousse 3 / **récolte 1** / repos 2 | semis 1 / pousse 3 / **récolte 2** / repos 1 | Sans ça, un champ hors Nil rend ~3,6 par quinzaine — moins que ce que mange la famille qui le tient, donc jamais rentable |
| Travailleurs de base du Port | 3 (doc 01) | **1** | Décision de la joueuse |
| Salaire d'un chef | `5 + comp × 0,3` (11-35) | **`2 + comp × 0,12`** (4-14 deben) | Écart assumé au doc 03. L'écart entre un mauvais et un excellent chef reste d'environ ×3 : seule l'échelle change, l'arbitrage demeure |
| Salaire d'un travailleur | — | **1 deben** | Valeur à inventer |
| Ration | 1 par foyer | **1 par adulte, ½ par enfant** | Décision de la joueuse |

##### Ce que ce calibrage donne, vérifié à la main

Ville d'exemple, Delta, difficulté 0 : Grenier, Marché et Port de niveau 1
pourvus ; trois champs du Nil semés, deux gisements et une pêcherie en
activité.

| Poste | Emplois |
|---|---|
| Chefs (Grenier, Marché, Port) | 3 |
| Ouvriers des bâtiments (1 + 2 + 1) | 4 |
| Ouvriers du territoire (3 champs, 2 gisements × 2, 1 pêcherie) | 8 |
| **Total** | **15 emplois** |

Quinze emplois réclament quinze adultes, soit **huit familles** résidentes à
deux adultes en moyenne — dont celles des trois chefs. Ces huit familles
comptent ~40 personnes : 16 adultes et 24 enfants.

- **Bouches** : 16 adultes + 24 demi-rations = **28 vivres par quinzaine**
- **Vivres produits** : 3 champs du Nil (~24 en moyenne annuelle) + pêcherie
  (10) = **34**. Surplus de 6, vendable ou mis en réserve pour la croissance —
  la marge est mince sans être étouffante
- **Deben** : 2 gisements × 20 unités × ~1,7 = **~68 par quinzaine**, plus le
  surplus alimentaire vendu
- **Masse salariale** : 3 chefs (~9 en moyenne) + 12 ouvriers = **~39 deben**.
  Reste ~30 pour construire
- **Vivier** : 8 familles sur les 20 disponibles au Delta — la marge existe,
  mais chaque nouvel emploi appelle une famille de plus, et le Quartier
  finira par contraindre

Et l'embauche d'un chef reste un choix qui se défend :

| Chef | Ce qu'il rapporte | Ce qu'il coûte |
|---|---|---|
| **Marché** | Double les prix de vente : **+34 deben** | ~9 deben, ~3,5 bouches |
| **Grenier** | Double la moisson conservée : **+12 vivres** | ~9 deben, ~3,5 bouches |
| **Port** | Double la pêche : **+5 vivres** | ~9 deben, ~3,5 bouches |

Le Port reste le plus modeste des trois, mais il n'est plus déficitaire : son
équipage ramené à un homme et sa pêcherie à un bateau lui rendent une marge,
et son niveau ouvrira d'autres bateaux (lot 4.5).

**Un déplacement d'équilibre à assumer** : avec ce calibrage, la masse
salariale dépasse largement le coût des bâtiments (un Grenier coûte 15 deben,
une quinzaine de salaires en coûte 39). Le poste de dépense principal du jeu
cesse d'être la construction pour devenir l'emploi — cohérent avec la phase,
et historiquement défendable, mais mérite d'être vu plutôt que subi.

#### 4.7 — Départ naturel, mécontentement et famine à deux paliers

- **Départ naturel** : tirage à chaque quinzaine selon l'ancienneté probable
  du PNJ (doc 05). Le poste se libère, le foyer s'en va, l'offre se renouvelle
- **Mécontentement de famine** (doc 02), nouveau palier avant l'échec :
  production ralentie, **départs anticipés**, baisse de renommée
  (`Family::ajusterRenommee()`, déjà en place)
- **Mécontentement d'impayé**, sur le même modèle (lot 4.6). Deux causes, un
  seul mécanisme — c'est ce qui évite d'écrire deux fois la même spirale
- **L'échec par famine du lot 3.7 recule d'un cran** : les quatre quinzaines
  actuelles deviennent le seuil du mécontentement, l'échec ne tombant qu'après
  une famine nettement plus longue. C'est le compromis tranché entre le « pas
  de game over brutal » du doc 02 et l'échec demandé au lot 3.7

#### 4.8 — Ce que la compétence d'un chef change réellement

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

#### Points tranchés avant d'ouvrir la phase

| Question | Décision | Lot |
|---|---|---|
| Quelle monnaie ? | Le **deben**, unité de compte pondérale du Nouvel Empire ; l'or redevient un métal qu'on extrait et qu'on vend | 4.0 |
| Que recrute-t-on ? | **Des chefs seulement**, par offre ; chacun s'installe avec sa famille. Les ouvriers se puisent dans le vivier d'adultes résidents | 4.1 / 4.3 |
| De quoi une famille est-elle faite ? | **2 adultes et 0 à 6 enfants** (2 à 8 personnes, moyenne 5). Tous les adultes travaillent, **sans distinction de sexe** — les Égyptiennes travaillaient | 4.1 |
| Les enfants grandissent-ils ? | **Oui, au rythme réel** — adulte vers douze ans. Il faut donc stocker un **âge**, pas une catégorie : un enfant de onze ans donne un bras dans l'année, un nourrisson n'en donnera qu'en mode Aventure | 4.1 |
| Que mange une personne ? | **1 vivre** pour un adulte, **une demi-ration** pour un enfant | 4.1 |
| Les PNJ ont-ils un nom ? | **Non pour l'instant** | 4.2 |
| Un poste vacant fait-il tout cesser ? | **Non, tout tourne à moitié** — aucune impasse possible | 4.4 |
| Champs, gisements et pêcheries ont-ils des salariés ? | **Oui** : 1 par champ, 2 par gisement, 1 par pêcherie (un homme, un bateau). Le niveau du bâtiment qui les gouverne — Grenier, Entrepôt, Port — augmente équipage **et** rendement | 4.5 |
| Les travailleurs coûtent-ils ? | **Oui**, bien moins qu'un chef | 4.6 |
| Salaires impayés ? | Le poste **s'arrête**, puis mécontentement et départs | 4.6 |
| Que donne le pharaon ? | Un an de vivres **et** un an de salaires | 4.6 |

#### Définition de « fini »

Parcours de bout en bout : poster une offre → comparer des candidats aux
compétences, traits et foyers visibles → en choisir un → le voir prendre son
poste à la quinzaine suivante → le voir embaucher ses ouvriers → affecter des
ouvriers à un gisement et à un champ → payer salaires et vivres à chaque
quinzaine → voir un PNJ partir naturellement et l'offre se rouvrir.

Tests unitaires sur les formules du doc, qui sont le cœur calculatoire :
`nbChefs`, `travailleursParChef`, barème d'étoiles, salaire, règle du
demi-rendement, consommation d'une population. Les tirages étant aléatoires
(compétence, traits, taille du foyer), leurs tests portent sur des
**invariants et des distributions** — deux traits incompatibles ne sortent
jamais ensemble, la compétence reste dans 20-100, un foyer compte entre 2 et 8
— plutôt que sur un candidat attendu.

Une **vérification d'équilibrage** en conditions réelles, en plus des tests :
mener une partie sur une année complète et constater qu'une ville correctement
gérée ne meurt ni de faim ni de faillite, et qu'embaucher reste avantageux.
C'est le seul moyen de valider le calibrage du lot 4.6, qu'aucun test unitaire
ne peut juger.

Les quatre portes qualité au vert, et une revue de sécurité : recruter,
renvoyer, poster une offre et affecter des ouvriers modifient l'état d'une
partie et doivent passer par `PartieVoter::JOUER`.

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
| Coûts de construction | **Ressources nommées**, jamais de générique « bois »/« pierre » ; un coût se paie exactement avec ce qu'il nomme |
| Gisements par case | **Jusqu'à deux**, jamais deux fois le même matériau |
| Placement de la ville | **Le Nil en priorité** s'il existe, sinon tout point d'eau, sinon terre fertile — jamais en plein désert |
| Gisements non alimentaires près de la ville | **Un seul exemplaire** dans l'anneau des 8 cases, plafonné même par le tirage aléatoire |
| Cycle agricole | **Quatre étapes** (semis/pousse/récolte/repos) ; le Nil suit la saison, la terre suit son propre compteur ; aucune nourriture hors récolte |
| Rayon gratuit de l'éclaireur | **< 3 cases** : entièrement gratuit, or et vivres compris ; au-delà, les deux sont dus |
| Échec de partie | **Famine prolongée** (4 quinzaines) → partie « échouée », conservée et consultable, jamais supprimée |
| Port | Constructible **dès qu'un point d'eau jouxte la ville**, sans autre condition ; il débloque la pêche, son niveau ne change rien encore |
| Poisson | **Renouvelable** — la seule ressource du jeu qui ne s'épuise jamais, sans quoi un Port coûteux deviendrait un piège |
| Monnaie | Le **deben**, unité de compte pondérale du Nouvel Empire — l'Égypte pharaonique n'a pas de monnaie frappée. L'**or** redevient un métal qu'on extrait et qu'on vend |
| Population | **Un nombre de personnes**, somme des foyers résidents ; le Quartier d'habitation en est le **plafond**, exprimé en familles (`20 × niveau`), jamais la source |
| Granularité de la population | **Trois nombres, jamais des individus** : habitants, actifs, inactifs (enfants et anciens). Aucun âge n'est suivi |
| Bilan démographique | **Une fois l'an**, pas à chaque quinzaine : des enfants entrent dans la vie active, des actifs passent la main, la mort prend sa part. **Personne ne naît** — repeupler est une action du joueur |
| Qui travaille | **Tous les actifs, sans distinction de sexe** : les Égyptiennes filaient, tissaient, brassaient, moissonnaient, et exerçaient des métiers attestés |
| Ce qu'on recrute | **Des chefs seulement**, qui s'installent avec leur maisonnée ; les ouvriers se puisent parmi les actifs déjà résidents |
| Arrivée d'habitants | Les **volontaires du pharaon** à l'ouverture, puis une **action du joueur** adossée à la renommée — et impossible sans logement disponible |
| Ration alimentaire | **1 vivre par actif, une demi-ration par inactif**, par quinzaine |
| Salariés du territoire | **1 par champ, 2 par gisement, 1 par pêcherie** : rien ne s'exploite tout seul. Le niveau du Grenier, de l'Entrepôt et du Port augmente équipage **et** rendement de l'exploitation qu'il gouverne |
| Poste vacant | **Tout tourne au moins à moitié**, bâtiments comme exploitations — aucune impasse possible, et l'emploi devient un investissement plutôt qu'une taxe |
| Salaires impayés | Le poste **s'arrête**, puis mécontentement et départs — même mécanisme que la famine |
| Salaire des travailleurs | **Dû**, en forfait par tête, bien inférieur à celui d'un chef |
| Dotation royale | Un an de vivres **et** un an de salaires : le pharaon finance le démarrage, pas la suite |
| Famine | **Deux paliers** : mécontentement d'abord (production ralentie, départs anticipés, renommée en baisse — doc 02), échec seulement si elle se prolonge bien au-delà |
| Affichage des PNJ | **Chiffré en interne, qualitatif à l'écran** (doc 03) : étoiles et libellés, jamais de compétence brute. Le salaire fait exception, déjà qualitatif par nature |
| Noms des PNJ | **Aucun pour l'instant** : un employé se désigne par son poste, comme dans les documents |
| Chiffres de conception | **Provisoires par nature**, dans les documents comme dans le code. Ils se rectifient au fil de la conception ; le critère est l'équilibre et le fait de **pousser le joueur à se servir des mécaniques**, pas la fidélité au document |
