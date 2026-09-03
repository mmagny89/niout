# Niout — Plan de bataille

Document de cadrage technique. Traduit les 16 documents de conception du jeu
(dossier Google Drive `Niout`, fichiers `00` à `15`) en un plan de développement
Symfony.

- **Stack** : Symfony 8.1 · Twig · Tailwind CSS 4.3 · PostgreSQL · FrankenPHP/Docker
- **Contrainte** : rendu serveur, zéro React, zéro headless
- **Source de conception** : Google Drive `Niout/`, docs 00–15 + sous-dossier `Sprites/` (18 planches)

**Les onze phases sont livrées.** Ce document ne raconte donc plus ce qui a été
fait — cela vit au journal — mais garde **ce qui reste** : ce qu'il faut
corriger, ce qu'il faut éprouver, ce qui attend une décision (§ 5), et les
décisions déjà prises qu'il ne faut pas redécouvrir (§ 9).

Le reste vit à côté, pour éviter de le répéter à deux endroits :

- le **journal des phases livrées** — intention, lots, cadrage détaillé, pièges
  payés — dans [`phases-livrees.md`](phases-livrees.md) ;
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
| `Expedition` | ✅ | Un éclaireur, un prospecteur ou une expédition en armes vers une case, chars compris | 04, 03 |
| `StockDeRessource` | ✅ | Une ligne du stock de la ville (ressource → quantité), deben compris | 08 |
| `Employee` | ✅ | Un chef en poste : compétence, salaire, spécialité, la maisonnée qu'il a amenée | 03, 05 |
| `JobOffer` | ✅ | Une annonce affichée et son tirage de candidats, figé | 03 |
| `OrdreDeFabrication` | ✅ | Un lot en cours à l'Atelier, à la Forge ou au Luxe | 08 |
| `RouteCommerciale` | ✅ | Une route ouverte vers un partenaire, et ce qu'on y échange | 12 |
| `OrdreCommercial` | ✅ | Une ligne de l'étal : ressource, sens, prix, volume par convoi | 08, 12 |
| `Convoi` | ✅ | Une caravane ou un navire en chemin, avec sa copie de l'échange | 12 |
| `FaveurDivine` | ✅ | Ce qu'un dieu pense de la ville, et depuis quand on l'a négligé | 07 |
| `DossierDEnquete` | ✅ | Une enquête en cours, ses indices versés, sa conclusion | 10 |
| `QueteDeChantier` | ✅ | Une requête du pharaon adressée à la ville, et ce qu'on en a fait | 09 |
| `RivalCommercial` | ✅ | Un concurrent installé sur une route, et la part qu'il prend | 08 |
| `Lignee` | ✅ | L'acquis d'un joueur, qui survit à ses parties : renommée persistante | 13 |
| `Medjay` | ✅ | Un homme levé à la Caserne : spécialisation, arme, expérience, blessure | 03 |
| … | ✅ | Générations, héritiers et traits familiaux vivent sur `Family` — aucune entité neuve | 13, 14 |

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
| **10** | Medjaÿ et combat automatique | `03` | ✅ |
| **11** | Mode Aventure : Memphis, succession des règnes, héritage familial | `14`, `13`, `01` | ✅ |

**La Phase 11 est la dernière.** L'intégration des sprites, un temps prévue en
Phase 12, **n'est plus une phase** (décision de la joueuse) : le document 15 est
transverse, chaque phase l'utilise au fur et à mesure, et le travail restant est
un **découpage d'images**, pas un système de jeu. Le rythme d'une phase — un
cadrage, des lots, un journal — ne lui apportait rien. Il se mènera au fil de
l'eau, planche par planche ; le pipeline est décrit en § 7.

**Réordonnancements par rapport à la première version.** Les cycles (doc 05)
sont montés de la Phase 5 initiale à la Phase 2 : ils ne sont pas un système
parmi d'autres mais le battement du jeu, et un chantier qui ne progresse pas
n'est pas démontrable. Le combat (doc 03), inversement, descend en Phase 10 :
optionnel dans les boucles de jeu, il n'a de sens qu'une fois les zones
dangereuses posées. Même logique pour la population (doc 01, 03) : sa brique
minimale — compter les habitants, les nourrir, échouer sans nourriture — est
montée au lot 3.7, le recrutement et les chefs restant en Phase 4.

**Le cadrage détaillé de chaque phase a rejoint le journal** — il y figure sous
« Le cadrage, lot par lot ». C'est là qu'il faut aller pour savoir *pourquoi*
une décision a été prise ; ce document ne garde que ce qui n'est pas fait.

---

## 5. Ce qui reste à faire

**Les onze phases sont livrées.** Leur récit — intention, lots, cadrage, pièges
payés — vit au journal, [`phases-livrees.md`](phases-livrees.md). Ce document ne
garde plus que **ce qui n'est pas fait** : ce qu'il faut corriger, ce qu'il faut
éprouver, et ce qui attend une décision.

### À corriger — des défauts connus, hors périmètre quand on les a trouvés

| Défaut | Où | Pourquoi il compte |
|---|---|---|
| **Le plafond de cinq parties compte les parties achevées** | `GameSaveRepository::compterPourJoueur()` | Un joueur qui accomplit cinq missions ne peut plus en lancer une sixième : **la campagne de dix missions est infinissable** sans supprimer des parties. Le docblock de `GameSave::MAX_PAR_COMPTE` et le § 3 disent tous deux « parties **en cours** » — c'est l'implémentation qui diverge. Trouvé au lot 9.5 ; à traiter **avant tout playtest** |
| **Deux mini-jeux plus pauvres que ce que le doc 10 annonce** | `Enigme` | La **reconnaissance astronomique** (associer un décan à un mois) et l'**association symbolique** (relier un animal à son dieu) sont des questionnaires à choix multiple là où le document annonce un mini-jeu d'association. Le fond est juste — l'astronomie et l'iconographie sont réelles —, la forme est plus pauvre. Sans urgence |
| **La mission 9 demande une trésorerie là où le doc 09 veut de l'or** | `ObjectifsDeMission` | Le Ouadi Hammamat en porte : l'aligner est trivial. Reste à savoir si deux objectifs de ressource pure sur la même mission ne la rendent pas monotone — c'est pourquoi ce n'est pas encore fait |

### À éprouver — ce qu'aucun test ne peut trancher

Le **calibrage** est le gros du reste, et il ne se décide qu'en jouant. Le § 6
liste les valeurs inventées une à une. Trois méritent une attention
particulière, parce qu'elles infléchissent une courbe entière :

- **le butin d'une bande décroît au fil de la campagne.** Nettoyer une case
  affaiblit toutes les autres, donc la première victoire rapporte plus que la
  dernière — courbe descendante là où on l'attendrait montante ;
- **la renommée cumulée facilite les dernières missions**, et c'est voulu
  (arbitrage 9.0). Reste à voir si l'avantage est une récompense ou une
  facilité ;
- **une longue partie Aventure nourrit la lignée** dont profiteront les
  missions de campagne. Si le bac à sable devient le chemin court vers une
  campagne facile, le remède sera un plafond, pas un cloisonnement.

**Trois défauts ont été trouvés par le playtest de l'écriture, pas par les
tests** : un signe faux enseigné pendant des semaines, une refonte d'ergonomie
invisible faute d'avoir vidé `app/public/assets`, un formulaire CSRF muet sans
son attribut Stimulus. Aucun test fonctionnel ne les aurait vus.

### À trancher — des questions ouvertes, sans réponse par défaut

| Question | Enjeu |
|---|---|
| **Smenkhkarê entre-t-il dans la succession ?** | Son existence propre, sa durée et jusqu'à son identité sont débattues. La règle du projet interdit d'afficher ce qui ne s'établit pas ; il est donc absent. Le rétablir demande de trancher la question égyptologique, pas d'écrire du code |
| **Le trait familial vient-il de l'héritier ou d'un palier de Résidence ?** | Le doc 01 le promet aux niveaux 2 et 5 de Résidence ; le jeu le fait venir **avec l'héritier** qu'on retient. Plus fidèle à l'esprit du doc 13 — un trait appartient à quelqu'un — mais c'est un écart au document, assumé et réversible |
| **Les Medjaÿ répondent-ils aux rivaux commerciaux ?** | Ni le doc 03 ni le doc 08 ne le prévoient. L'enquête reste la seule réponse au rival. À rouvrir si le playtest montre que `Rivaux` manque d'une seconde issue |
| **Les Medjaÿ partis en expédition couvrent-ils encore les routes ?** | Oui aujourd'hui : le jeu suit leur disponibilité, pas leur position. Les marquer absents demande une colonne, et rendrait une sortie plus coûteuse encore |
| **Le bonus de renommée passif du niveau 4 de Résidence** | Promis par le doc 01, jamais chiffré, jamais implanté |
| **Les paramètres de lancement du doc 14** | Point de départ dans la succession et vitesse des règnes ne sont pas offerts. La taille de grille et la difficulté le sont déjà |

### Deux calibrages qui divergent, et qu'on garde

Ce ne sont pas des oublis mais des décisions prises **contre** le document,
rappelées ici pour que la prochaine relecture ne les redécouvre pas comme des
défauts :

| Point | Document 09 | Code | Pourquoi |
|---|---|---|---|
| Richesse | `200 + 50 × d` **en or** | `250 + 75 × d` **en deben** | Le document compte encore en or comme si c'était la monnaie ; l'Égypte pharaonique n'en a pas |
| Population | `20 + 10 × d` travailleurs | `12 + 4 × d` habitants | Seuil mesuré sur deux cents parties : une ville à Quartier 1 monte à treize |
| Commerce, ressource | `500 + 100 × d`, `100 + 20 × d` | `400 + 120 × d`, `60 + 15 × d` | Recalibrés sur l'économie réelle des Phases 4 et 5 |

### Du contenu, sans code

Deux chantiers ne demandent aucune ligne de code, et c'est ce qui les rend
faciles à repousser indéfiniment :

- **les XIXᵉ et XXᵉ dynasties**, jusqu'à Ramsès XI. La succession est une
  **donnée** : allonger la liste ne touche rien. Chaque pharaon demande un
  cartouche — codes de Gardiner et glyphe Unicode — et un chantier attesté, au
  même soin que les treize déjà faits. La police se régénère après ;
- **le découpage des dix-huit planches** de sprites (§ 7), hors planche
  « tuiles » déjà intégrée.

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
| Qualité d'un homme sans arme | 70 centièmes | `Equipement::QUALITE_SANS_ARME` |
| Butin d'une bande vaincue | 50 % de ce qu'elle opposait | `Combat::BUTIN_POUR_CENT_DE_LA_DEFENSE` |
| Risque de pillage d'un convoi | 5 % par bande encore tenue | `Commerce::RISQUE_PAR_BANDE_DE_LA_REGION` |
| Bonus de combat du Bagarreur | +10 % à la Caserne | `TraitDeCandidat::BONUS_DE_COMBAT_DU_BAGARREUR` |
| Malus civil du Bagarreur | −10 % de compétence | `TraitDeCandidat::MALUS_CIVIL_DU_BAGARREUR` |
| Poids du score d'Aventure | 10 par habitant, 1 par deben, 10 par point de renommée, 1 pour 10 deben échangés | `ScoreDAventure` |
| Score converti en centièmes de réussite | 100 points pour un centième | `Successions::POINTS_PAR_CENTIEME` |
| Couverture d'un Medjaÿ sur les routes | 15 % du risque | `Commerce::PROTECTION_PAR_MEDJAY` |

**Une leçon de méthode, payée en Phase 3** : quatre valeurs de population
avaient été inventées alors que les docs 01 et 02 les chiffraient (consommation,
capacité du Quartier, vivier régional). Avant d'inventer, vérifier que le
document ne dit rien.

---

## 7. Pipeline des assets graphiques  *(hors phase)*

**Ce travail ne fait pas l'objet d'une phase** (décision de la joueuse) : c'est
un découpage d'images, pas un système de jeu, et il se mène au fil de l'eau,
planche par planche, quand un écran en a besoin. Ce qui suit est la marche à
suivre, pas une feuille de route.

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
| Renommée cumulée | **Elle traverse la campagne**, et l'avantage des dernières missions est assumé : c'est la récompense de la campagne. L'**acquis** vit sur `Lignee`, la **jauge de la mission** sur `Family` — deux choses que le mot confondait, et c'est ce qui permet à deux parties menées de front de coexister |
| Plafond des remises | **Un seul, sur le résultat**, jamais un par source : trois plafonds séparés se cumulent et n'en plafonnent aucun |
| Carnet de contacts | **Une remise, jamais un déblocage** de ressource — sinon il faudrait recalibrer les missions tardives autour de ce que le joueur a déjà fait |
| Danger sur la carte | **Un attribut de case, pas un contenu** : une case garde son gisement *et* porte des bandits — c'est le filon gardé qui donne envie de lever une troupe. L'anneau des huit cases autour de la ville en est exclu, sans quoi la partie serait injouable au premier cycle |
| Zone nettoyée | **Elle le reste** : le combat est une conquête, pas un péage. Rien d'autre dans le jeu ne se dégrade tout seul |
| Armes | **Équipement durable**, jamais consommé : la Forge est un palier à franchir, pas un robinet à tenir ouvert. Un homme sans arme part quand même — aucune chaîne de production ne décide du rythme militaire |
| Mort d'un Medjaÿ | **Permanente**, aux taux du doc 03. C'est la seule perte sans recours du jeu, et elle vient d'un risque choisi — jamais d'un événement subi |
| Pillage des convois | **Système inventé**, faute de tout document qui en décrive un, mais **ancré** sur le nombre de bandes encore tenues (doc 02) : une même règle sert deux systèmes plutôt qu'un hasard de plus |
| Routes de Memphis | **Elles suivent le règne**, sur un socle qui ne dépend d'aucun roi. C'est ce qui donne à la succession une conséquence économique plutôt qu'un habillage |
| Fin du mode Aventure | **Oui, à la fin de la succession.** Bac à sable *long*, pas *sans fin*. La borne visée est la fin du Nouvel Empire ; d'ici là, c'est le dernier règne connu qui fait fin — **la liste est une donnée, jamais une constante** |
| Ce qui nourrit la lignée | **Les deux modes** : la campagne à l'achèvement d'une mission, l'Aventure à chaque fin de règne. La renommée appartient à la famille, pas au mode |
| Succession familiale | **Le chef change, la maison non** : la renommée, les contacts, la faveur divine et la ville traversent. Les héritiers **ne se persistent pas** — seule la graine qui les décide |
| Effectif de Medjaÿ | **Caserne et Résidence s'ajoutent** (`3 + 2 × niveau`, plus un homme par palier de Résidence). Sans Caserne, aucun homme : la Résidence ajoute des places, elle n'en crée pas |
