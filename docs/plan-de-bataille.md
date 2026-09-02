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
| **8** | Campagne : les 10 missions et leurs objectifs | `09`, `11` | ✅ |
| **8 bis** | Finition : cohérence et lisibilité | — | ✅ |
| **8 ter** | Écriture : l'alphabet des scribes et les stèles | `10`, `09` | ✅ |
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

Le **détail des phases livrées** — intention, lots, règles qui en sortent,
pièges payés, ce que chacune laisse ouvert — vit dans
[`phases-livrees.md`](phases-livrees.md), pour que cette feuille de route reste
lisible d'un coup d'œil.

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
