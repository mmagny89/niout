# Niout

Jeu de gestion / aventure au navigateur, Égypte antique du Nouvel Empire.
Le plan de développement fait foi : `docs/plan-de-bataille.md`.

## Stack

Symfony 8.1 (pack `webapp` : Twig, AssetMapper, Stimulus, Turbo) · Tailwind CSS 4.3
via `symfonycasts/tailwind-bundle` · PostgreSQL 18 · FrankenPHP (mode worker) sous Docker.

**Pas de React, pas d'API headless** : tout le rendu est serveur (Twig). L'interactivité
passe par Turbo et Stimulus, jamais par du JS applicatif fait main.

## Où vit le code

Le code applicatif est dans `app/`, **jamais à la racine** — la racine ne porte que
l'infrastructure Docker. Toutes les commandes PHP/Symfony se lancent donc dans le
conteneur, dont le `WORKDIR` est `/app`.

## Commandes

Le stack se lance sans `-f` : `COMPOSE_FILE` dans le `.env` racine chaîne déjà
`compose.yml:compose.dev.yml`.

- Démarrer : `docker compose up -d --wait`
- Console Symfony : `docker compose exec php php bin/console <cmd>`
- Tests : `docker compose exec php php bin/phpunit`
- Rebuild des styles Tailwind : `docker compose exec php php bin/console tailwind:build`
  (ajouter `--watch` pendant le développement)
- Migrations : `docker compose exec php php bin/console doctrine:migrations:migrate`
- Mode d'essai : `docker compose exec php php bin/console app:users:goddess <email>`
  (`--retirer` pour le reprendre)
- Observabilité (Ember) : `curl http://127.0.0.1:9191/metrics`

Portes qualité — les quatre doivent passer avant un merge (mêmes commandes qu'en CI) :

- Style : `docker compose exec php vendor/bin/php-cs-fixer fix` (`--dry-run --diff` pour vérifier)
- Analyse statique : `docker compose exec php vendor/bin/phpstan analyse` — **niveau 8, zéro erreur attendue**
- Audit des dépendances : `docker compose exec php composer audit`
- Tests : `docker compose exec php php bin/phpunit`

Deux prérequis faciles à oublier, tous deux dus à des artefacts vivant dans `app/var/`, ignoré par git :

- PHPStan a besoin du container compilé : lancer `cache:warmup` avant l'analyse si le cache est vide.
- Les tests fonctionnels ont besoin de la CSS compilée : lancer `tailwind:build` d'abord.
  Sans elle, AssetMapper cherche `tailwindcss` comme un fichier réel, `base.html.twig` lève
  une exception, et **tout test qui rend une page échoue** — sans que le message ne mentionne
  Tailwind de façon évidente.

Le site répond sur `https://localhost` (certificat auto-signé Caddy en dev).

## Conventions

Standards de code : voir skills `symfony-coding-standards`, `phpstan-analysis`,
`phpunit-testing-standards`, `functional-e2e-testing`, `web-security-checklist`,
`web-accessibility-a11y`, `git-commit-conventions`. Ne pas dupliquer leurs règles ici.

Écarts et précisions propres à ce projet :
- Messages de commit et documentation **en français**.
- **Nommage** : noms de classes en anglais quand un terme clair existe (`User`,
  `GameSave`, `City`) ; propriétés, méthodes et commentaires en français
  (`$joueur`, `marquerOuverte()`). Le vocabulaire de l'univers reste tel quel,
  jamais traduit ni anglicisé — Medjaÿ, Akhèt, quinzaine, Niout.
- L'interface de jeu est en français ; le vocabulaire égyptien du game design
  (Medjaÿ, Akhèt, Perèt, Chémou, quinzaine, Niout) est normatif — ne pas le traduire
  ni l'inventer, il vient des documents de conception.

## Infrastructure — règle qui prime

`.claude/rules/stack-conventions.md` **fait autorité** sur toute question
d'arborescence, de nommage (services, volumes, réseaux), de ports et de `.env`.
Le lire avant de toucher à `compose*.yml`, `docker/` ou aux `.env`.

Points à ne pas redécouvrir :
- Trois niveaux de `.env` distincts. Le `.env` racine est lu **par Docker Compose seul** ;
  `app/.env` est lu par Symfony. Une variable appartient à un seul niveau.
- **Jamais de secret réel dans un fichier committé.** Sont committés — et le restent
  sans valeur sensible : `.env` (racine, valeurs de dev), les deux `.dist` (valeurs
  vides), `app/.env*`. Les vrais secrets vont **exclusivement** dans
  `.env.staging.local` / `.env.prod.local`, ignorés par git et par Docker.
- `app/.env.dev` ne porte **aucun** `APP_SECRET` : la recette Flex en génère un par
  défaut, ce qui l'envoie dans l'historique d'un dépôt public (incident réel du
  2026-08-27, valeur révoquée). Sur un clone frais, générer le sien :
  `docker compose exec php sh -c 'echo "APP_SECRET=$(openssl rand -hex 16)" >> .env.local'`.
  Vérifier ce point après tout `composer require` qui rejoue une recette Flex.
  Ajouter un mot de passe de production au `.env` racine l'enverrait dans l'historique.
  Staging et prod échouent au démarrage si un secret manque : c'est voulu, ne jamais
  « réparer » ça en donnant une valeur par défaut à un `${VAR:?}`.
- `DATABASE_URL` est injectée par `compose.yml`. Elle est volontairement commentée dans
  `app/.env` (par `neutralize-app-env`) — ne pas la réactiver : la valeur y serait
  masquée en conteneur et fausse hors conteneur.
- Après un `composer require` qui dépose une recette Flex, rejouer
  `docker compose exec php neutralize-app-env`.
- Ports fixes (80/443/5432/9191), jamais déduits de l'hôte. Pour faire tourner un autre
  projet en parallèle, décaler les ports dans le `.env`.
- Le stack a été généré par `.claude/scripts/setup-symfony.sh`. Un défaut trouvé dans un
  fichier issu de `.claude/resources/` se corrige **dans la ressource**, pas seulement
  dans la copie du projet.

## Protection CSRF — stateless

Le projet utilise la protection CSRF **sans état** de Symfony (double-submit
cookie, voir `config/packages/csrf.yaml`). Le serveur ne rend pas un vrai jeton
mais son identifiant ; c'est un script du navigateur qui produit le jeton et
pose le cookie correspondant.

Conséquence sur **tout formulaire écrit à la main** : son champ caché doit
porter `data-controller="csrf-protection"`. Sans lui, Stimulus ne charge jamais
ce script et la soumission échoue sur « Invalid CSRF token ». Les formulaires
construits avec Symfony Form reçoivent l'attribut d'office.

Le client de test n'exécutant pas de JavaScript, **aucun test fonctionnel ne
peut reproduire cette panne** : elle ne se voit qu'en navigateur. La parade est
une assertion de structure sur la présence de l'attribut — voir
`ConnexionTest::testLeChampCsrfDeConnexionActiveLeScriptQuiPoseLeCookie()`.

Les identifiants de jeton absents de `stateless_token_ids` restent classiques et
fonctionnent sans JavaScript (par exemple `renvoyer-verification`).

## Où mettre le code du jeu

- `src/Entity/` — **état** d'une partie, persisté : `GameSave`, `Family`, `City`.
- `src/Game/` — **règles et contenu**, jamais persistés : `MissionCatalogue`,
  `DotationRoyale`, `LanceurDePartie`. Une valeur qui vient des documents de
  conception (coût, formule, seuil, texte de mission) va ici, pas en base.

`Family` et `City` appartiennent à leur `GameSave` (cascade `remove`). Toute
nouvelle entité rattachée à une partie doit suivre le même principe, **et** être
prise en compte par `app:users:purge-unverified` si elle référence `User`
directement.

Les constructeurs nommés (`GameSave::pourCampagne()`) sont préférés à un
constructeur public quand ils rendent un invariant impossible à violer.

**Chaque ressource reste distincte, jamais agrégée.** Il n'existe ni ressource
`Bois` ni `Pierre` — le doc 01 chiffre ses bâtiments dans ces matériaux
génériques, mais chaque coût nomme désormais le matériau réel qu'il réclame
(`CoutDeConstruction::de(roseaux: …, argile: …, boisLocal: …)`) : un grenier
coûte des roseaux, de l'argile et du bois local, un temple du calcaire. **Le
bois local et le cèdre sont deux ressources distinctes** — l'un se ramasse au
bord du Nil (acacia, sycomore), l'autre s'importe du Levant à cinq fois le
prix ; les agréger sous « bois » cacherait au joueur ce qu'il possède. Rien ne se substitue à rien —
une région qui ne porte pas un matériau doit l'importer (commerce, Phase 5).
Ne jamais réintroduire un compteur générique ni une famille de matériaux : un
« bois » qui agrégerait roseaux et cèdre cacherait au joueur ce qu'il possède
réellement, ce qui a été le défaut corrigé ici.

**Trois matériaux sont vitaux**, pas deux (`Ressource::materiauxVitaux()`) :
roseaux, argile et **bois local**, dont tout bâtiment réclame depuis le doc 01
révisé. Chacun a sa garantie de génération, et celle du bois local lui est
propre — il ne pousse que sur la terre broussailleuse et, plus rarement, sur
la terre fertile, jamais dans le sable ; la garantie générique l'aurait planté
en plein désert.

**La « terre classique » du doc 02 est un terrain, pas un contenu**
(`TypeDeTerrain::TerreClassique`, affichée « Terre broussailleuse »). Elle
remplace l'ancien `ContenuDeZone::TerreNonCultivable`, qui n'était qu'une case
fertile que le tirage n'avait pas retenue — un manque déguisé en contenu. Elle
ne se cultive **jamais**, et ne se sème que dans les régions bordées par le Nil.

**La fondation d'un bâtiment ne coûte pas de deben, l'amélioration si** (doc 01
révisé) : la brique crue d'un premier niveau relevait de matériaux locaux et
d'une main-d'œuvre familiale. Deux exceptions, qui paient dès la fondation — le
Temple (rituel de dédicace) et le Port (pontons). Les matériaux croissent en
`× (1 + (N-1) × 0,4)`, le deben en `debenParNiveau × (N-1)` : **deux lois
distinctes**, à ne pas réunifier.

**Une case porte jusqu'à deux gisements** (`Zone::GISEMENTS_MAX`), jamais deux
fois le même. À un seul, l'argile et les roseaux — les deux matériaux dont rien
ne tient lieu, tous deux nés de l'eau — se disputaient les rares berges d'une
grille 3×3, et une partie pouvait se figer faute de l'un des deux. La génération
garantit aussi un minimum de champs et de cases poissonneuses par région
(`GenerateurDeCarte::CHAMPS_MINIMUM`, `POISSON_MINIMUM`) — sur la plus petite
carte du jeu (Delta, 3×3), matériaux, champs et poisson se disputent les mêmes
cases, d'où un minimum volontairement bas (1) plutôt que théoriquement
généreux mais irréalisable. Les garanties de matériaux privilégient l'anneau
des 8 cases autour de la ville, **un seul exemplaire de chaque matériau non
alimentaire** dans cet anneau (décision de la joueuse — éviter d'avoir
directement tout à portée).

**Retirer un cas d'enum ne le retire pas de la base** (défaut réel, payé) :
`ContenuDeZone::TerreNonCultivable` a disparu du code avec la terre classique,
sans migration pour les lignes déjà écrites. Doctrine ne sait pas hydrater une
valeur absente de l'enum — **toute partie portant une seule case de ce contenu
devenait illisible**, donc impossible à ouvrir comme à abandonner, et l'erreur
ne nomme ni la partie ni la table. Tout retrait d'un cas persisté se
double d'une migration qui convertit l'existant (`Version20260830190000`).

**Piège payé** : `Zone::poserUnGisement()` ne doit **jamais** écraser un
contenu déjà posé (`ContenuDeZone::ChampEligible`, `Evenement`) — seul
`Rien` peut devenir `Ressource`. Sans cette garde, un gisement ajouté après
coup (garantie de matériau, garantie de poisson) effaçait silencieusement le
champ qu'une garantie précédente venait de poser sur la même case.

**Un convoi parti est un engagement pris** (`Convoi`) : on débite **au départ**
ce qu'on engage — la marchandise pour une vente, les deben pour un achat — et
l'on reçoit au retour. Débiter à l'arrivée permettrait de vendre deux fois la
même chose. Le convoi porte **sa propre copie** de l'échange, jamais un lien
vers l'ordre : retirer une annonce n'annule pas ce qui roule. **Un seul convoi
par ressource et par route**, et une caravane rentrée **repart plutôt que d'être
recréée** — supprimer puis réinsérer dans la même quinzaine fait sauter la
contrainte d'unicité, Doctrine insérant avant de supprimer (le piège des
gisements, repayé).

**Le commerce est un étal, pas un bouton d'échange** (`OrdreCommercial`,
décision de la joueuse) : le joueur annonce ce qu'il vend et achète, à quel
prix, et attend. **Un ordre ne débite rien** — c'est une annonce, les convois
l'exécutent. **Le prix décide de l'empressement du partenaire**
(`PartenaireCommercial::empressement()`), donc du volume qui bouge : c'est ce
qui en fait un levier plutôt qu'un curseur à pousser au maximum, et l'écran
montre l'effet **avant** l'engagement. La quantité par convoi est un garde-fou :
un ordre permanent ne doit jamais vider la ville sans prévenir.

**Le craft de luxe se débloque par l'Entrepôt, pas par l'Atelier**
(`Recette::deblocageSupplementaire()`, docs 01 et 08) : bijoux, statuettes et
vases réclament un Entrepôt de niveau 8, et six matières qu'aucune région de
départ ne porte. C'est voulu — le prestige n'est atteignable qu'une fois le
commerce établi, et non par la seule montée d'un bâtiment.

**Une spécialité d'atelier ne vaut que sur son propre ouvrage**
(`SpecialiteDeChef::favorise()`) : un Brasseur ne fait pas de meilleurs
papyrus. Le bonus passe par la **qualité de direction**, comme tout effet de
chef. Deux spécialités font exception et ne passent pas par elle, parce que
leur effet n'est pas une production : le **Négociateur** élargit la fourchette
des partenaires, le **Logisticien** raccourcit les trajets — jamais sous une
quinzaine, sans quoi la distance cesserait de décider de la fréquence des
convois. Elles se lisent par `EffetDeChef::chefSpecialise()`.

**Mesurer l'effet d'un chef en quinzaines ne prouve rien** : elles se comptent
en entiers, et un ordre de quatre cycles ne distingue pas une qualité de 134 %
d'une de 114 %. Tester la qualité de direction elle-même. Et **ne jamais
mesurer une cadence en menant une partie sur une dizaine de quinzaines** : sur
cette durée un chef peut rendre son tablier — son ancienneté est tirée —, et
l'on mesure alors son départ autant que sa spécialité. Un test de cadence se
fait sur l'ordre lui-même, à qualité imposée (défaut réel, tombé en CI une
fois sur plusieurs).

**Une route commerciale s'ouvre en y envoyant une caravane** (`Commerce`,
`CataloguePartenaires`, décision de la joueuse) : on paie, le convoi part, la
route n'existe qu'à son arrivée. **Le type de route décide du bâtiment** —
Entrepôt pour les pistes, Port pour tout ce qui flotte — et du volume d'un
convoi. Seule la **clé** du partenaire est persistée ; nom, distance et
fourchettes de prix sont du contenu, jamais de l'état. **Les fourchettes se
déduisent** de `PrixDuMarche` (200 % à la vente, 150 % à l'achat), jamais d'une
table par partenaire et par ressource — et **un partenaire ne vend jamais ce
qu'il achète**, sans quoi une route serait une machine à arbitrer.

**Fabriquer prend du temps et plusieurs matières** (`Recette`, `Fabrication`,
décision de la joueuse). **L'Atelier et la Forge partagent tout** — un seul
service, c'est la recette qui dit où elle se travaille. Quatre règles à ne pas
défaire : les matières sont
**débitées à l'engagement** — sans quoi on lancerait dix ordres avec les
ressources d'un seul —, les pièces **n'entrent qu'à l'achèvement** (la règle
des champs), **un seul ordre à la fois et par bâtiment** parce que c'est ce qui donne son
coût d'opportunité à la fabrication, et le rythme vient des bras par
`EffetDeChef::qualiteDeDirection()`, jamais par un multiplicateur de plus.
**Toute recette ajoutée doit tenir la marge de transformation** — le test s'y
adosse et tombe sinon.

**Le stock est plafonné, jamais périssable** (`Stockage`, décision de la
joueuse) : le Grenier tient les vivres, l'Entrepôt les matériaux et les objets,
et les ressources d'une même réserve **se partagent** son plafond. Le surplus
ne rentre pas ; ce qui est rangé y reste. Trois points à ne pas défaire — **le
deben n'a aucun plafond** (sans quoi le plafond bloquerait la vente, seule
issue qu'il pousse à prendre), le plafonnement vit dans
`City::crediterRessources()` pour qu'aucun chemin ne l'oublie, et
`surplusRefuse()` s'interroge **avant** de créditer, l'information n'existant
plus après. Toute nouvelle source de ressources doit annoncer ce qu'elle perd :
un plafond silencieux est une règle qu'on subit sans comprendre.

**Rien de fabriqué ne se trouve sur une carte** (`Ressource::estFabriquee()`,
doc 08) : la poterie, les outils et les bijoux n'existent que par le travail ou
par l'import. Aucune région ne les déclare en ressource de zone, et deux tests
gardent l'invariant — l'un sur la déclaration, l'autre sur de vraies cartes
générées. **Le pain et la bière sont des vivres** : ce sont les deux formes
sous lesquelles l'Égypte consommait son grain.

**Un objet fabriqué vaut environ 165 % de ce qu'il coûte à produire**
(`PrixDuMarche::MARGE_DE_TRANSFORMATION`). En deçà, personne ne fabriquerait —
vendre brut irait aussi vite sans immobiliser l'Atelier ; au-delà, vendre brut
n'aurait plus jamais de sens. Toute recette ajoutée doit garder cette marge, et
c'est mesuré, pas supposé.

**La monnaie est le deben, jamais l'or** (`Ressource::Deben`,
`Ressource::estLaMonnaie()`). L'Égypte pharaonique n'a pas de monnaie frappée —
elle n'apparaît que sous domination perse puis chez les Ptolémées ; le Nouvel
Empire compte en deben, unité pondérale d'environ 91 g attestée par les ostraca
de Deir el-Médineh. **L'or est un métal qu'on extrait** (mines du désert
oriental et de Nubie, doc 08) et qu'on vend, au prix le plus élevé du jeu.
Confondre les deux, comme le faisait le code jusqu'au lot 4.0, faisait de la
mission 2 une carrière de monnaie.

Conséquence pour toute migration future : une ligne de **stock** `or` était de
la monnaie, un **gisement** `or` est une mine. Ne jamais convertir les deux
ensemble — voir `Version20260828140000`.

**La monnaie n'entre que par le Marché** (`Game/Marche`), la dotation royale
mise à part. Toute règle qui rendrait le Marché inatteignable fige la partie.

**La dotation royale se calcule sur les coûts réels des quatre bâtiments
d'ouverture** — Quartier d'habitation, Grenier, Marché, Entrepôt — jamais sur
des nombres recopiés (`DotationRoyale::coutDesBatimentsDouverture()`). Un coût
qui changerait dans le catalogue changerait la dotation avec lui ; l'inverse
laisserait une partie bloquée sans qu'aucun test ne le dise. Elle ne laisse
**aucune marge en matériaux** : les quatre bâtiments, et rien de plus.
`OuvertureDePartieTest` garde l'invariant de bout en bout — dotation, coûts du
catalogue et garanties de génération de carte doivent s'accorder, et chacun
peut être juste de son côté sans que l'ensemble le soit.

**La population se compte en trois nombres, jamais en individus** (décision de
la joueuse) : `City::$actifs`, `$enfants`, `$anciens`. Aucun habitant n'est
suivi un par un — ce qui compte est de savoir combien de bras la ville a et
combien de bouches. Le Quartier d'habitation ne peuple pas : il **plafonne**
(`20 × niveau` maisonnées, doc 01), et `City::manqueDeLogements()` dit au
joueur quand bâtir avant d'espérer un habitant de plus.

Trois règles à ne pas défaire :

- **Le bilan démographique tombe une fois l'an**, pas à chaque quinzaine, et
  c'est `PassageDeCycle` qui en décide le moment — au changement d'année, avec
  la crue. Laisser `Demographie` vérifier la date lui-même le ferait tomber dès
  le premier cycle d'une partie, où la ville vient d'arriver.
- **Chaque personne est tirée séparément** plutôt qu'un pourcentage appliqué à
  un total (`Demographie::tirer()`) : c'est ce qui permet de rester en entiers
  sans traîner de reliquat — un taux de 3 % sur douze actifs ne donnerait
  sinon jamais rien.
- **On naît, mais seulement s'il y a de la place.** `CHANCE_NAISSANCE_PAR_ACTIF`
  est nulle quand `manqueDeLogements()` — la ville ne déborde jamais de son
  logement, ce qui rend le plafond du Quartier lisible plutôt que théorique.
  Mesuré sur 200 parties de vingt ans : sans Quartier la population fond de 10
  à 5, avec un Quartier de niveau 1 elle monte à 13, et aucune ville ne
  s'éteint. Ne pas bâtir coûte des habitants ; bâtir en fait gagner lentement.
- **Faire venir des habitants passe par la renommée** (`PalierDeRenommee`,
  doc 13) : elle fixe le prix d'un appel et, à partir de « Respectée », fait
  venir des maisonnées toutes seules. Piège déjà payé : `ajusterRenommee()`
  n'était appelé de nulle part, donc la renommée restait à zéro pour toujours
  et toute règle indexée dessus était **inerte**. C'est le Marché qui l'alimente
  désormais (`Marche::RECETTE_DUN_GROS_CONTRAT`) — avant d'indexer une règle
  sur une valeur, vérifier qu'une source la fait bouger.
**La consommation se compte en demi-rations** — deux par actif, une par
inactif — et ne se convertit en vivres qu'une fois, à l'échelle de la ville
(`Population::vivresPourDemiRations()`). Jamais de 0,5 en circulation, jamais
d'arrondi groupe par groupe.

**Un champ ne nourrit qu'à sa récolte** (`EtapeDeChamp::Recolte`), jamais
pendant le semis, la pousse ou le repos. Un champ du Nil suit la saison
(`RendementDesChamps` — Akhèt et Perèt rendent 0, seul Chémou moissonne) ; un
champ terrestre (Fertile, Oasis) suit son propre compteur, indépendant de la
saison (`CycleAgricoleTerrestre`, `Zone::quinzainesDepuisSemis`).

**Le poisson est la seule ressource renouvelable** (`Ressource::estRenouvelable()`,
décision de la joueuse) : `Gisement::extraire()` rend son plein sans décompter
et `estEpuise()` reste faux à jamais. Un Port coûte 50 or, 40 roseaux et
20 calcaire ; une pêcherie tarissable en aurait fait un piège sur une carte
qui ne porte qu'une case d'eau poissonneuse. Il se pêche depuis un Port, ne se
creuse jamais (`Exploitations::exploiter()`), et l'interface écrit
« inépuisable » là où les autres gisements affichent leurs unités restantes.

**Le mode divin est un outil d'essai, pas une fonctionnalité** (`ModeDivin`,
`User::ROLE_DIVIN`) : un million de chaque ressource, plafonds de réserve levés,
brouillard levé d'un geste, les dix missions ouvertes à la création. **Le rôle ne s'accorde qu'en console**
(`app:users:goddess`) — aucun écran ne le donne, et cacher un bouton n'est pas
une barrière : la route vérifie le rôle en plus de la propriété. Une partie
d'essai le dit en toutes lettres à l'écran, pour ne jamais se confondre avec une
vraie. C'est aussi **la seule chose du jeu qui défait un échec**, ce qui lui vaut
son écart au `JOUER` ci-dessous.

**Toute route qui modifie l'état d'une partie doit utiliser
`PartieVoter::JOUER`**, pas `VOIR` : `JOUER` refuse en plus une partie
`StatutDePartie::Echouee` (famine prolongée, `Subsistance`). `VOIR` ne
vérifie que la propriété — une action mutante gardée par `VOIR` seul resterait
jouable sur une partie déjà terminée. **Une seule exception, documentée** : la
bascule du mode divin, qui doit justement pouvoir remettre debout une partie
échouée — c'est souvent celle qu'on veut examiner.

Cinq pièges déjà payés, à ne pas refaire :

- **`or` est un mot réservé du SQL.** Doctrine échappe les noms à la création de
  table, jamais dans les `SELECT` qu'il génère ensuite. La colonne fautive
  n'existe plus — le lot 3.1 a remplacé les compteurs fixes par une table
  `ressource → quantité`, où `or` n'est qu'une valeur — mais le piège reste
  entier pour tout nouveau nom de colonne qui serait un mot-clé.
- **Les Voters ont changé de signature en Symfony 8** : `voteOnAttribute()` prend
  un quatrième paramètre `?Vote $vote = null`. L'oublier produit une erreur
  fatale au chargement, qui fait échouer jusqu'à `make:migration`.
- **Aucune valeur de jeu ne se compare en flottants.** L'avancement des chantiers
  se compte en dixièmes de cycle, parce que le facteur ×1,5 de la crue finirait
  par laisser un chantier bloqué à un cheveu de son terme.
- **L'ordre des garanties de génération compte**
  (`GenerateurDeCarte::garantirLesMinimums()`) : garantir les champs **avant**
  les matériaux vitaux, jamais après — une case cultivable garde sa vocation
  même si un gisement s'y ajoute ensuite (les deux coexistent), l'inverse est
  impossible (`Zone::poserUnContenu()` efface les gisements). Dans l'autre
  ordre, les garanties de matériaux pouvaient consommer les rares terres
  cultivables d'une petite carte avant que celle des champs ne s'exécute.
- **Un tirage n'impose jamais un gisement que le terrain dément.** Le plafond
  d'un seul exemplaire par matériau dans l'anneau de la ville peut ne laisser
  que le bois local comme possibilité ; si la case est de sable, aucun matériau
  n'y pousse et l'option « ressource » ne doit pas être proposée du tout. Un
  repli qui tirerait alors sans regarder le terrain plante des acacias en plein
  désert — défaut réel, payé.
- **Une garantie probabiliste n'est pas une garantie.** Quinze pour cent par
  case échouent plus d'une fois sur deux sur une grille 3×3 : la terre
  broussailleuse n'apparaissait qu'une fois sur deux au Delta. Toute règle du
  type « la région en porte toujours » se vérifie **sur les dix missions à leurs
  tailles réelles** (`Mission::tailleDeGrille()` vaut `3 + difficulté / 2`, pas
  `3 + difficulté`), et se conclut par un minimum forcé.
- **Un matériau vital passe devant un matériau de confort.** Sur une carte
  saturée, la garantie de bois local déloge un filon non vital plutôt que de
  renoncer (`GenerateurDeCarte::fairePlaceAuBoisLocal()`) — on joue sans or,
  jamais sans charpente. C'est le seul endroit du jeu où un gisement est retiré.
- **Un poids de tirage réduit doit être redistribué, jamais simplement retiré**
  (`GenerateurDeCarte::tirerParmi()`) : le total du tirage rétrécirait sinon,
  gonflant mécaniquement la part des autres options. En pondérant le poids
  « champ » par la distance à la ville, le poids perdu rejoint « vide », pas
  le néant — sinon « ressource » augmente artificiellement et peut saturer de
  gisements les rares cases cultivables d'une petite carte.

**Ce sont les chefs qui recrutent** (`Effectifs`, doc 05). Un bâtiment sans
chef ne réclame aucun travailleur, donc tourne au plancher : « sans chef, la
moitié » n'est pas une règle à part, c'est un cas de la formule générale
`0,5 + 0,5 × (réel / requis)`, comptée **en centièmes** parce qu'elle
multiplie des ressources à chaque quinzaine. **Rien ne s'éteint faute
d'employés** (décision de la joueuse) : embaucher est un investissement, pas
une taxe. Un chef pas encore en poste ne réclame rien, et les chefs sortent du
vivier de bras — ils ne s'encadrent pas eux-mêmes.

**Un chef ne crée jamais un multiplicateur de plus** (`EffetDeChef`) : sa
compétence module la **qualité de direction** d'un bâtiment, aux côtés de son
effectif, et c'est cette qualité qui pèse sur les productions. Deux invariants
à ne pas défaire — **un mauvais chef reste meilleur que pas de chef** (98 %
contre le plancher de 50 % d'un bâtiment désert), et **une spécialité sans
système d'accueil reste inerte et le dit** (`SpecialiteDeChef::agitDeja()`),
promettre un bonus qui ne s'applique nulle part tromperait le joueur au moment
même où il compare des candidats.

**Le panthéon est du contenu, la faveur est de l'état** (`Divinite`,
`FaveurDivine`) : nom, domaine et effet d'un dieu vivent dans l'enum, seule sa
**clé** et la valeur de sa faveur sont persistées — comme les partenaires
commerciaux. Trois règles à ne pas défaire :

- **On démarre à 40, pas à 50.** Le doc 07 annonce un départ « neutre à 50 »
  tout en plaçant le palier Favorable à partir de 50 ; à la lettre, il
  offrirait huit bonus actifs à une ville qui n'a jamais mis les pieds au
  Temple. La partie chiffrée du document l'emporte sur sa phrase.
- **Une ligne de faveur naît au premier geste, jamais au lancement.**
  `City::faveurEnvers()` répond la constante pour un dieu sans ligne ;
  `suivreLaFaveurDe()` est le seul chemin par lequel une ligne existe.
- **Les bornes tiennent dans `FaveurDivine::ajuster()`**, pas chez ses
  appelants : offrande, fête, bénédiction et malédiction y passeront tous, et
  aucun n'a à vérifier l'échelle pour son compte.

**Un dieu sans emploi le dit** (`Divinite::agitDeja()`, `attente()`) : Isis
attend le combat, Thot les énigmes. Même règle que les spécialités de chef —
promettre un effet qui ne s'applique nulle part tromperait le joueur au moment
même où il choisit à qui donner.

**Le mécontentement a deux causes et un seul mécanisme** (`Mecontentement`) :
la faim et les salaires impayés mènent à la même colère, comptée une fois. Il
monte et se résorbe d'un cran par quinzaine — symétrie délibérée, qui interdit
le yo-yo sans rendre la remontée désespérée. Son malus de production est
**délibérément distinct du rendement d'effectif** : le plancher de 50 % vaut
pour le manque de bras, pas pour une ville en colère. Avant de toucher à ses
valeurs, vérifier que **la spirale se redresse encore** — c'est là que ce genre
de mécanisme casse, quand le malus empêche de produire de quoi lever sa propre
cause. La famine se lit à deux paliers : mécontentement à 4 quinzaines, échec
à 12.

**Les salaires tombent à chaque quinzaine, avant la production** (`Salaires`,
`Paie`). C'est la première charge récurrente en deben, et la principale — une
quinzaine de salaires coûte plus qu'un Grenier. **L'unité de paiement est le
bâtiment ou l'exploitation entière, jamais l'homme**, et une unité impayée
**s'arrête** : elle rend donc moins qu'une unité vacante, qui tourne encore à
moitié. C'est assumé — le joueur a intérêt à renvoyer qui il ne peut plus
payer, ce qui lui donne une action à prendre plutôt qu'une spirale subie. La
paie circule dans le cycle (`Recoltes::avancerDUnCycle()` la reçoit) parce que
la recalculer après le débit donnerait un autre résultat.

**Rien ne travaille sans personne, y compris sur le territoire** (lot 4.5) :
un champ semé réclame un homme, un gisement deux, une pêcherie un. Chaque
exploitation a un **bâtiment gouvernant** — Grenier pour les champs, Entrepôt
pour les carrières, Port pour les pêcheries — dont le niveau élargit
l'équipage réclamé *et* le rendement, ce qui referme la boucle du jeu et rend
le niveau coûteux avant d'être payant.

**Un seul multiplicateur de rendement par chaîne de production.** Deux
planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout tourne au
moins à moitié » que la règle promet — c'est ce qui a fait retirer, au lot 4.5,
le modificateur que le lot 4.4 posait sur le stockage du Grenier, devenu un
double comptage dès lors que le Grenier gouvernait les champs. Avant d'ajouter
un multiplicateur à une production, vérifier qu'aucun autre ne s'y applique
déjà : `DemiRendementTest::testLaChaineAlimentaireNeDescendJamaisSousLaMoitie()`
garde l'invariant.

**Une offre d'emploi est persistée, une candidature non** (`JobOffer`,
`Candidat`). L'offre fige son tirage : sans cela, recharger la page relancerait
les dés jusqu'au cinq étoiles, et le choix entre deux ou trois candidats — le
cœur du doc 03 — n'aurait plus de sens. Retirer l'annonce est la seule relance,
et elle est explicite. **Seuls les chefs sont suivis un par un** (`Employee`) ;
les travailleurs se puiseront dans le vivier d'actifs, comme la population se
compte en nombres et non en individus.

**Un chef arrive avec sa maisonnée et repart avec elle** (`City::laisserPartir()`,
le pendant d'`accueillir()`). Sans le second volet, embaucher puis renvoyer
peuplerait la ville gratuitement et rendrait l'appel d'habitants inutile. Les
deux voies de peuplement butent d'ailleurs sur le même verrou :
`manqueDeLogements()`.

Les écrans de partie héritent de `templates/partie/_layout.html.twig`, qui porte
la barre de jeu — compteurs, date pharaonique, passage de cycle. Un nouvel écran
de partie doit en hériter plutôt que de `base.html.twig`.

## Conception du jeu

Les 16 documents de game design (systèmes, économie, lore, direction artistique) vivent
sur Google Drive, dossier `Niout/`, numérotés `00` à `15`. Ils sont la source de vérité
fonctionnelle : ne pas réinventer une mécanique déjà spécifiée, s'y référer.

Les 18 planches de sprites sont dans le sous-dossier `Niout/Sprites/` du Drive — déjà
générées, à découper, pas à recréer.

**La géométrie de la carte se mesure sur les tuiles, jamais sur la taille de
l'image** (`templates/partie/carte.html.twig`). Une tuile de 188 × 116 porte une
face supérieure de 186 × 90 : le reste est l'épaisseur du prisme, en bas, et ce
qui dépasse par le haut — les arbres d'une forêt, les roseaux d'une berge. Le
pas de la grille vaut la **moitié du losange** (93 × 45), pas la moitié de
l'image ; le prendre sur l'image écarterait les cases et ouvrirait des marches
entre elles. La zone cliquable se découpe des deux mêmes nombres, pour qu'aucun
des deux réglages ne dérive de l'autre. Après tout changement de planche,
remesurer : c'est le canal alpha qui fait foi.

La planche « tuiles » se redécoupe avec `.claude/scripts/decouper-tuiles.py`,
jamais à la main : il détoure le damier — **peint dans les pixels du JPEG**, pas
une vraie transparence — par remplissage depuis les bords, et met **toutes les
tuiles à la même échelle**. Les mettre chacune à l'échelle de sa propre boîte
donnerait des losanges de tailles différentes et désalignerait la grille
isométrique.
