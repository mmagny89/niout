# niout — socle Docker

Genere par `.claude/scripts/setup-symfony.sh` le 2026-08-27. Symfony sur
FrankenPHP (Caddy integre, mode worker), PostgreSQL, 
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
| `compose.yml` | Socle : services `php`, `database` |
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

Toute commande utilise la **cle de service** (`php`, `database`),
jamais le `container_name` (conventions, section 2).

## Ports

| Variable | Valeur par defaut | Publie par |
|---|---|---|
| `HTTP_PORT` | 80 | dev, staging |
| `HTTPS_PORT` | 443 | dev, staging |
| `HTTP3_PORT` | 443/udp | dev, staging |
| `POSTGRES_PORT` | 5432 | dev uniquement, sur `127.0.0.1` |
| `MAILPIT_PORT` | 8025 | dev uniquement, sur `127.0.0.1` — interface web de l'attrapeur d'emails ; son port SMTP (1025) reste interne |
| `METRICS_PORT` | 2020 | **jamais publie** — metriques Caddy, joignables depuis le reseau seulement |

En production, aucun port n'est publie : Traefik route par domaine.

Pour faire tourner ce projet en parallele d'un autre, decaler
`HTTP_PORT`/`HTTPS_PORT`/`HTTP3_PORT` dans le `.env` — jamais en inspectant
l'hote (conventions, section 4).

## Environnements

### Branches et clones

| Branche | Environnement | Dossier du clone | Domaine |
|---|---|---|---|
| `develop` | pre-production | `/docker/niout-staging` | `ppd.<domaine>` |
| `main` | production | `/docker/niout-prod` | `<domaine>` |

Les clones vivent sous `/docker` sur le VPS — la racine est libre, seul le nom
de la feuille est normatif.

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
sh outils/installer-deploiement.sh projet staging   # depuis le clone niout-staging
```

Le script pose la *forced command* qui limite la cle confiee a GitHub au seul
`outils/deployer.sh`. La cle privee ne s'affiche jamais au terminal.

Quatre secrets GitHub par environnement sont a poser dans ce depot, sous leurs
noms normatifs (conventions, §ci/qualite). La production garde les noms
nus, la pre-production les prefixe de `STAGING_` :

| Secret — production | Secret — pre-production | Contenu |
|---|---|---|
| `DEPLOIEMENT_HOTE` | `STAGING_DEPLOIEMENT_HOTE` | adresse du serveur |
| `DEPLOIEMENT_UTILISATEUR` | `STAGING_DEPLOIEMENT_UTILISATEUR` | utilisateur de deploiement |
| `DEPLOIEMENT_CLE_PRIVEE` | `STAGING_DEPLOIEMENT_CLE_PRIVEE` | cle privee dont la publique est dans `authorized_keys` |
| `DEPLOIEMENT_KNOWN_HOSTS` | `STAGING_DEPLOIEMENT_KNOWN_HOSTS` | empreinte du serveur, epinglee |

Chaque environnement a sa propre cle : la *forced command* fige `prod` ou
`staging`, et les deux jeux ne sont pas interchangeables. Ils sont **propres a
ce depot** : rien ne se partage avec un autre projet, sauf le compte machine qui
lit les depots et le reseau Traefik.

**Un deploiement ne part que si sa variable de depot vaut `oui`** (Settings →
Secrets and variables → Actions → onglet Variables) :

| Variable | Effet |
|---|---|
| `DEPLOIEMENT_PROD` | un push sur `main` deploie la production |
| `DEPLOIEMENT_STAGING` | un push sur `develop` deploie la pre-production |

Absente ou differente de `oui`, le job est ignore : la CI reste verte et rien
n'est livre. C'est aussi l'interrupteur pour suspendre un deploiement sans
toucher aux secrets. Tout le reste de ce qui se regle dans GitHub est dans
`GITHUB.md`, a la racine.

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

## Emails

`MAILER_DSN` et `MAILER_FROM` sont injectees par `compose.yml` et declarees
obligatoires : **staging et production refusent de demarrer sans elles**. C'est
voulu. Le transport `null://null` que depose la recette Symfony accepte chaque
message et le jette, sans erreur ni journal ; un echec au demarrage vaut mieux
qu'un silence qui avale les emails de verification.

### Developpement

Rien a faire : le service `mailpit` de `compose.dev.yml` attrape tout et
l'affiche sur <http://localhost:8025>. Aucun message ne quitte la machine.

```sh
docker compose exec php php bin/console mailer:test vous@example.com
```

### Staging et production

Les deux valeurs vivent dans le fichier de secrets de l'environnement
(`.env.staging.local`, `.env.prod.local`), jamais dans un fichier committe.

```
MAILER_DSN=smtp://IDENTIFIANT:CLE_SMTP@smtp-relay.brevo.com:587
MAILER_FROM=noreply@niout.mmagny.fr
```

Un DSN SMTP nu plutot qu'un pont dedie (`brevo+api://`, `brevo+smtp://`) :
aucun paquet a installer, donc aucune dependance de plus a suivre, et changer
de fournisseur ne demande qu'une ligne.

**L'adresse expeditrice doit appartenir au domaine exactement authentifie chez
le fournisseur.** Les enregistrements DNS a poser sont affiches par lui ; ils se
recopient tels quels, ils ne s'inventent pas.

Piege deja paye : un sous-domaine n'herite pas de l'authentification de son
parent cote fournisseur, meme quand il en herite cote DNS. Avec `mmagny.fr`
authentifie, un envoi depuis `noreply@niout.mmagny.fr` est refuse net —
« the sender you used is not valid » — alors que DKIM et DMARC sont pourtant
alignes. Soit on authentifie aussi le sous-domaine, soit on expedie depuis le
domaine authentifie.

Apres deploiement, eprouver la chaine depuis le conteneur, et **verifier la
reception**, pas seulement l'absence d'erreur :

```sh
docker compose -f compose.yml -f compose.prod.yml --env-file .env.prod.local \
  exec php php bin/console mailer:test vous@example.com
```

Une commande qui rend la main sans rien dire signifie que le transport a accepte
le message, pas qu'il est arrive. Le tableau de bord du fournisseur dit le reste.

## Observabilite

**Metriques Caddy.** Ecoute interne `:2020`, jamais publiee sur l'hote : un port ouvert exposerait la latence, les codes d'erreur et le volume de trafic du site. De vrais histogrammes, etiquetes par code et par handler, donc des percentiles calculables.

```sh
docker compose exec php curl -s http://127.0.0.1:2020/metrics | head
```
