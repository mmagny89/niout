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
(`CoutDeConstruction::de(roseaux: …, argile: …)`) : un grenier coûte des
roseaux et de l'argile, un temple du calcaire. Rien ne se substitue à rien —
une région qui ne porte pas un matériau doit l'importer (commerce, Phase 5).
Ne jamais réintroduire un compteur générique ni une famille de matériaux : un
« bois » qui agrégerait roseaux et cèdre cacherait au joueur ce qu'il possède
réellement, ce qui a été le défaut corrigé ici.

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
directement tout à portée) ; une case fertile ou une berge du Nil qui n'a pas
tiré de champ reste marquée `ContenuDeZone::TerreNonCultivable`, distincte du
« rien » générique.

**Piège payé** : `Zone::poserUnGisement()` ne doit **jamais** écraser un
contenu déjà posé (`ContenuDeZone::ChampEligible`, `Evenement`) — seul
`Rien` peut devenir `Ressource`. Sans cette garde, un gisement ajouté après
coup (garantie de matériau, garantie de poisson) effaçait silencieusement le
champ qu'une garantie précédente venait de poser sur la même case.

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
mise à part. Toute règle qui rendrait le Marché inatteignable fige la partie :
c'est ce que vérifie
`DotationRoyaleTest::testLaDotationPermetLeGrenierPuisLeMarche()`.

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

**Toute route qui modifie l'état d'une partie doit utiliser
`PartieVoter::JOUER`**, pas `VOIR` : `JOUER` refuse en plus une partie
`StatutDePartie::Echouee` (famine prolongée, `Subsistance`). `VOIR` ne
vérifie que la propriété — une action mutante gardée par `VOIR` seul resterait
jouable sur une partie déjà terminée.

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
