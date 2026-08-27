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

Trois pièges déjà payés, à ne pas refaire :

- **`or` est un mot réservé du SQL.** Doctrine échappe les noms à la création de
  table, jamais dans les `SELECT` qu'il génère ensuite : la colonne s'appelle
  donc `stock_or`. Vérifier tout nom de colonne qui serait un mot-clé.
- **Les Voters ont changé de signature en Symfony 8** : `voteOnAttribute()` prend
  un quatrième paramètre `?Vote $vote = null`. L'oublier produit une erreur
  fatale au chargement, qui fait échouer jusqu'à `make:migration`.
- **Aucune valeur de jeu ne se compare en flottants.** L'avancement des chantiers
  se compte en dixièmes de cycle, parce que le facteur ×1,5 de la crue finirait
  par laisser un chantier bloqué à un cheveu de son terme.

Les écrans de partie héritent de `templates/partie/_layout.html.twig`, qui porte
la barre de jeu — compteurs, date pharaonique, passage de cycle. Un nouvel écran
de partie doit en hériter plutôt que de `base.html.twig`.

## Conception du jeu

Les 16 documents de game design (systèmes, économie, lore, direction artistique) vivent
sur Google Drive, dossier `Niout/`, numérotés `00` à `15`. Ils sont la source de vérité
fonctionnelle : ne pas réinventer une mécanique déjà spécifiée, s'y référer.

Les 18 planches de sprites sont dans le sous-dossier `Niout/Sprites/` du Drive — déjà
générées, à découper, pas à recréer.
