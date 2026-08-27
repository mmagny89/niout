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

PHPStan a besoin du container compilé : lancer `cache:warmup` avant l'analyse si le cache est vide.

Le site répond sur `https://localhost` (certificat auto-signé Caddy en dev).

## Conventions

Standards de code : voir skills `symfony-coding-standards`, `phpstan-analysis`,
`phpunit-testing-standards`, `functional-e2e-testing`, `web-security-checklist`,
`web-accessibility-a11y`, `git-commit-conventions`. Ne pas dupliquer leurs règles ici.

Écarts et précisions propres à ce projet :
- Messages de commit et documentation **en français**.
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

## Conception du jeu

Les 16 documents de game design (systèmes, économie, lore, direction artistique) vivent
sur Google Drive, dossier `Niout/`, numérotés `00` à `15`. Ils sont la source de vérité
fonctionnelle : ne pas réinventer une mécanique déjà spécifiée, s'y référer.

Les 18 planches de sprites sont dans le sous-dossier `Niout/Sprites/` du Drive — déjà
générées, à découper, pas à recréer.
