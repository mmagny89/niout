# niout — socle Docker

Genere par `.claude/scripts/setup-symfony.sh` le 2026-08-27. Symfony sur
FrankenPHP (Caddy integre, mode worker), PostgreSQL, observabilite via Ember.
Rendu Twig, styles TailwindCSS via AssetMapper (`symfonycasts/tailwind-bundle`,
pas de Node cote PHP). Projet **non headless** : pas de front separe, pas de
service Node.

- **Staging** : serveur dedie durablement a ce projet. FrankenPHP bind
  directement 80/443 (+443/udp), TLS Let's Encrypt gere par Caddy.
- **Production** : VPS partage. Traefik tient seul 80 et 443 et termine le TLS ;
  Caddy y sert du HTTP nu (`SERVER_NAME=":80"`), et le projet ne publie aucun
  port. Prerequis d'hote, une seule fois : `docker network create traefik`.

## Nommage — le suffixe d'environnement

Conteneurs, reseau et volumes portent `niout-<env>-…`, ou `<env>` vient de la
variable `ENV` : `dev` dans le `.env` racine, `staging` / `prod` dans les
fichiers de secrets. Sans ce suffixe, deux environnements du meme projet
deployes sur une meme machine partageraient **le meme volume de base de
donnees**, sans qu'aucune erreur ne le signale (conventions, section 2).

**Migration d'un stack anterieur a cette regle.** Les anciens volumes
s'appellent `niout-caddy-data`, `niout-database-data`… et ne seront plus
trouves : le stack demarrerait sur des volumes neufs, donc une base vide. Avant
de redemarrer, recopier chaque volume sous son nouveau nom, puis verifier la
donnee **apres** bascule :

```sh
for v in caddy-data caddy-config caddy-admin database-data; do
  docker volume create "niout-<env>-$v"
  docker run --rm -v "niout-$v":/s:ro -v "niout-<env>-$v":/c alpine sh -c 'cp -a /s/. /c/'
done
```

Garder les anciens volumes jusqu'a la verification : ils sont le seul filet.

## Inventaire

| Fichier | Role |
|---|---|
| `.env` | Variables Docker Compose : `ENV`, versions, ports, UID/GID, `POSTGRES_*` |
| `.env.staging.local.dist` / `.env.prod.local.dist` | Modeles de secrets, committes |
| `.env.staging.local` / `.env.prod.local` | Secrets reels — jamais committes |
| `compose.yml` | Socle : services `php`, `database`, `ember` |
| `compose.dev.yml` / `compose.staging.yml` / `compose.prod.yml` | Overrides par environnement |
| `docker/php/**` | Dockerfile, Caddyfile(s), scripts d'entrypoint/healthcheck/installation |
| `outils/deployer.sh` | Deploiement d'un environnement, execute **sur le serveur** |
| `outils/installer-deploiement.sh` | Pose les acces de deploiement (volet `serveur`, puis `projet`) |
| `outils/renseigner-secrets.sh` | Produit les valeurs d'un fichier de secrets |
| `outils/diagnostic-traefik.sh` | Releve la configuration du Traefik de l'hote |
| `.github/workflows/qualite.yml` | Portes qualite, puis deploiement sur push vers `main` |
| `app/` | Code applicatif Symfony |

`outils/` est **versionne** : `.claude/` n'arrive jamais sur le serveur.

## Demarrage

```sh
docker compose up -d --wait
```

Reconstruire les styles apres modification des classes :

```sh
docker compose exec php php bin/console tailwind:build --watch
```

Toute commande utilise la **cle de service** (`php`, `database`, `ember`),
jamais le `container_name` (conventions, section 2).

## Ports

| Variable | Valeur par defaut | Publie par |
|---|---|---|
| `HTTP_PORT` | 80 | dev, staging |
| `HTTPS_PORT` | 443 | dev, staging |
| `HTTP3_PORT` | 443/udp | dev, staging |
| `POSTGRES_PORT` | 5432 | dev uniquement, sur `127.0.0.1` |
| `EMBER_PORT` | 9191 | dev et, sur `127.0.0.1`, staging/prod |
| `METRICS_PORT` | 2020 | **jamais publie** — metriques Caddy, joignables depuis le reseau seulement |

En production, aucun port n'est publie : Traefik route par domaine.

Pour faire tourner ce projet en parallele d'un autre, decaler
`HTTP_PORT`/`HTTPS_PORT`/`HTTP3_PORT` dans le `.env` — jamais en inspectant
l'hote (conventions, section 4).

## Environnements

### Branches et clones

| Branche | Environnement | Dossier du clone | Domaine |
|---|---|---|---|
| `develop` | pre-production | `<racine>/niout-staging` | `ppd.<domaine>` |
| `main` | production | `<racine>/niout-prod` | `<domaine>` |

**Le dossier du clone porte le nom du projet Compose, suffixe compris.** La
*forced command* de `authorized_keys` porte le chemin absolu de
`outils/deployer.sh` : renommer le dossier ensuite la laisse pointer dans le
vide, et le deploiement echoue sur `No such file or directory`, code 127.

Chaque environnement a son clone, sa branche et son fichier de secrets.

### Secrets — a produire avant tout build

Les variables obligatoires etant declarees `${VAR:?}`, `docker compose build`
refuse de demarrer tant que l'une d'elles est vide. Les valeurs se produisent
donc avec des outils qui ne doivent rien au projet — jamais par une commande qui
passe par l'image du projet, qui serait circulaire :

```sh
sh outils/renseigner-secrets.sh prod
```

### Staging

```sh
cp .env.staging.local.dist .env.staging.local   # une fois, puis renseigner
docker compose -f compose.yml -f compose.staging.yml --env-file .env.staging.local up -d --wait
```

### Production

```sh
docker compose -f compose.yml -f compose.prod.yml --env-file .env.prod.local up -d --build --wait
docker compose -f compose.yml -f compose.prod.yml --env-file .env.prod.local exec php php bin/console doctrine:migrations:migrate --no-interaction
```

`RUN_MIGRATIONS=0` en production : mise a jour de schema jouee explicitement au
deploiement, jamais au demarrage du conteneur.

Les volumes survivent au rebuild. **Ne jamais passer `--volumes` a
`docker compose down`** sur un environnement portant des donnees.

### Deploiement depuis GitHub

Une **cle de deploiement en lecture seule**, propre au depot, donne au serveur
l'acces au code — jamais une cle personnelle. Les acces se posent avec :

```sh
sh outils/installer-deploiement.sh serveur          # une fois par machine
sh outils/installer-deploiement.sh projet prod      # depuis le clone niout-prod
```

Le script pose la *forced command* qui limite la cle confiee a GitHub au seul
`outils/deployer.sh`. La cle privee ne s'affiche jamais au terminal.

Quatre secrets GitHub sont a poser dans ce depot — `VPS_HOST`, `VPS_USER`,
`VPS_SSH_KEY`, `VPS_KNOWN_HOSTS`. Ils sont **propres a ce depot** : rien ne se
partage avec un autre projet, sauf le compte machine qui lit les depots et le
reseau Traefik.

Deploiement manuel, a verifier **avant** le declenchement automatique :

```sh
sh outils/deployer.sh prod
```

### Verification — le `healthy` du conteneur ne prouve rien

Derriere un proxy, un conteneur peut etre `healthy` et le site inaccessible. Le
controle qui compte se fait depuis l'exterieur :

```sh
curl -sI https://<domaine>/ | head -3
```

## Observabilite

Deux sources, complementaires mais pas equivalentes.

**Metriques Caddy — la source de verite.** Ecoute interne `:2020`, jamais
publiee sur l'hote. Elle porte de vrais histogrammes etiquetes par code,
handler et methode, donc des percentiles calculables. C'est elle qu'un
Prometheus doit scruter :

```sh
docker compose exec php curl -s http://127.0.0.1:2020/metrics | head
```

**Ember — outil de diagnostic.** Il n'ajoute qu'une metrique unique,
`frankenphp_threads_total` : l'etat du pool de threads, mode de panne propre a
FrankenPHP ou les requetes s'empilent pendant que le processeur parait calme.
Aucun panneau de trafic ou de latence ne se batit sur ses metriques.

Ce qu'il apporte reellement est son **interface**, qui ne s'obtient qu'en le
lancant sans `--daemon`, dans un conteneur ephemere attache au meme socket :

```sh
docker compose run --rm -it ember --addr unix//run/caddy/admin.sock
```

Instantane complet pour un script, bien meilleur que de parser `/metrics` —
il porte notamment la profondeur de file d'attente, absente de l'endpoint :

```sh
docker compose run --rm ember --addr unix//run/caddy/admin.sock --json --once
```

En dev, Ember demarre avec le reste du stack. En staging/prod :
`--profile observability`.

Cibles a declarer cote hote, dans le `prometheus.yml` : `niout-<env>-php:2020`
et `niout-<env>-ember:9191`.
