# niout — socle Docker

Genere par `.claude/scripts/setup-symfony.sh` le 2026-08-27. Symfony sur
FrankenPHP (Caddy integre, mode worker), PostgreSQL, observabilite via
Ember. Rendu Twig, styles TailwindCSS via AssetMapper (`symfonycasts/tailwind-bundle`, pas de Node cote PHP).
Projet **non headless** : pas de front separe, pas de service Node.

Serveur staging/prod : dedie durablement a ce seul projet. FrankenPHP bind directement 80/443 (+443/udp), TLS Let's Encrypt gere par Caddy, aucun Traefik..

## Inventaire

| Fichier | Role |
|---|---|
| `.env` | Variables Docker Compose : versions, ports, UID/GID, `POSTGRES_*` |
| `.env.staging.local.dist` / `.env.prod.local.dist` | Modeles de secrets, committes |
| `.env.staging.local` / `.env.prod.local` | Secrets reels — jamais committes |
| `compose.yml` | Socle : services `php`, `database`, `ember` |
| `compose.dev.yml` / `compose.staging.yml` / `compose.prod.yml` | Overrides par environnement |
| `docker/php/**` | Dockerfile, Caddyfile(s), scripts d'entrypoint/healthcheck/installation |

| `app/` | Code applicatif Symfony (installe apres coup) |


## Demarrage

```sh
docker compose up -d --wait
docker compose exec php install-symfony
```

`install-symfony` installe le pack `webapp` (Twig, AssetMapper) et
`symfonycasts/tailwind-bundle`, puis lance un premier `tailwind:build`. En
developpement, reconstruire les styles apres modification des classes :

```sh
docker compose exec php php bin/console tailwind:build --watch
```

## Ports

| Variable | Valeur par defaut | Publie par |
|---|---|---|
| `HTTP_PORT` | 80 | dev, staging, prod |
| `HTTPS_PORT` | 443 | dev, staging, prod |
| `HTTP3_PORT` | 443/udp | dev, staging, prod |
| `POSTGRES_PORT` | 5432 | dev uniquement, sur `127.0.0.1` |
| `EMBER_PORT` | 9191 | dev et, sur `127.0.0.1`, staging/prod |


Pour faire tourner ce projet en parallele d'un autre, decaler
`HTTP_PORT`/`HTTPS_PORT`/`HTTP3_PORT` dans le `.env` — jamais en
inspectant l'hote (conventions, section 4).

## Environnements

### Staging

```sh
cp .env.staging.local.dist .env.staging.local   # une fois, puis renseigner
docker compose -f compose.yml -f compose.staging.yml --env-file .env.staging.local up -d --wait
docker compose -f compose.yml -f compose.staging.yml --env-file .env.staging.local exec php install-symfony
```

### Production

```sh
docker compose -f compose.yml -f compose.prod.yml --env-file .env.prod.local up -d --wait
docker compose -f compose.yml -f compose.prod.yml --env-file .env.prod.local exec php php bin/console doctrine:migrations:migrate --no-interaction
```

`RUN_MIGRATIONS=0` en production : mise a jour de schema jouee
explicitement au deploiement, jamais au demarrage du conteneur.

### Observabilite (Ember)

```sh
curl http://127.0.0.1:${EMBER_PORT}/metrics
curl http://127.0.0.1:${EMBER_PORT}/healthz
```

En dev, demarre avec le reste du stack. En staging/prod :
`--profile observability`.
