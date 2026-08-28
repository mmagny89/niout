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
| `StockDeRessource` | ✅ | Une ligne du stock de la ville (ressource → quantité) | 08 |
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
- [ ] **Phase 3** — Carte, exploration et ressources · §6.2 · `02`, `04`, `06`, `08`
      — 6 lots sur 7, seul le Port/pêche (3.6) reste à livrer
- [ ] **Phase 4** — Population : recrutement, chefs et travailleurs · `01`, `03`
      — amorcée au lot 3.7 (habitants affichés, consommation de nourriture,
      échec par famine) ; recrutement, chefs et travailleurs restent entiers
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

### 6.2 Phase 3 — Carte, exploration et ressources  *(6 lots sur 7 — 3.6 restant)*

**Intention.** Faire basculer la ville de la dépense à la production. Elle
tire désormais ses matériaux de son territoire et sa nourriture de ses champs,
plutôt que de consommer une dotation qui ne se renouvelle pas.

À la fin de la phase (3.6 excepté), on peut raconter : *« j'envoie un éclaireur
sur une case voisine, il y trouve de l'argile, je l'exploite, et cette argile
alimente mes chantiers ; j'établis un champ, je bâtis le grenier, et la
moisson tombe en Chémou. Ma ville compte ses habitants et les nourrit à chaque
quinzaine — sans vivres, elle s'affame et la partie peut y rester. »*

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

#### 3.6 — Points d'eau, Port et pêche

- [ ] Le Port devient constructible dès qu'un point d'eau jouxte la ville
- [ ] Pêche sur les cases d'eau reconnues, une fois le Port dressé
- [ ] Les cases d'eau cessent d'être un décor : elles portent du contenu comme
      les autres (doc 02)

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

Également hors périmètre : l'épuisement des gisements et la re-exploration
(régions de difficulté 4+) ; le commerce et le craft (Phase 5) ; les
événements de zone et les énigmes (Phase 7) ; le recrutement, les chefs et les
travailleurs (Phase 4).

#### Définition de « fini »

Parcours couvert de bout en bout : carte générée à la création de la partie →
éclaireur envoyé → cycles déclenchés → case révélée → ressource exploitée →
chantier financé par cette ressource. Plus un champ établi, un grenier bâti,
une moisson qui tombe en Chémou et pas en Akhèt, et une ville qui nourrit ses
habitants ou tombe en famine.

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
