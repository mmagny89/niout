# Niout

Jeu de gestion jouable au navigateur, situé dans l'Égypte du Nouvel Empire
(~1550-1070 av. J.-C.). Le joueur incarne une famille chargée par un pharaon de
fonder, restaurer ou sécuriser une ville réelle : commerce, artisanat,
exploration, énigmes et faveur des dieux s'y entremêlent.

Le parti pris central : **aucune attente en temps réel**. Un chantier ou une
expédition prennent du temps, mais ce temps n'avance que lorsque le joueur
déclenche un cycle. Rien ne tourne pendant qu'il est ailleurs.

*Niout* (niwt) signifie « la ville » en égyptien ancien.

## État du projet

En cours de développement. Ce qui fonctionne aujourd'hui :

- Page de présentation publique
- Inscription, connexion, déconnexion, mot de passe oublié
- Vérification d'adresse email non bloquante, avec suppression du compte après
  7 jours sans validation

Le jeu lui-même (ville, carte, cycles, exploration) reste à construire — voir la
feuille de route dans [`docs/plan-de-bataille.md`](docs/plan-de-bataille.md).

## Stack

| | |
|---|---|
| Framework | Symfony 8.1, rendu serveur (Twig) |
| Interactivité | Symfony UX — Turbo et Stimulus. **Pas de React, pas d'API headless** |
| Styles | Tailwind CSS 4.3 via AssetMapper, sans Node.js |
| Base de données | PostgreSQL 18 |
| Exécution | Docker, FrankenPHP en mode worker (Caddy intégré) |

## Démarrer

Docker est le seul prérequis : PHP, Composer et Tailwind vivent dans l'image.

```bash
docker compose up -d --wait
```

Le site répond sur <https://localhost> (certificat auto-signé en développement).

Sur un clone neuf, générer un secret applicatif local — il n'est jamais committé :

```bash
docker compose exec php sh -c 'echo "APP_SECRET=$(openssl rand -hex 16)" >> .env.local'
```

Puis créer le schéma :

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

Pendant le développement, reconstruire les styles à la volée :

```bash
docker compose exec php php bin/console tailwind:build --watch
```

## Structure

La racine ne porte que l'infrastructure Docker ; le code applicatif vit dans
`app/`. Toutes les commandes PHP se lancent donc dans le conteneur, dont le
répertoire de travail est `/app`.

```
.
├── app/          application Symfony
├── docker/       image PHP, configuration Caddy, scripts d'entrée
├── docs/         plan de bataille et documents de projet
└── compose*.yml  socle et surcharges dev / staging / prod
```

## Qualité

Les quatre vérifications ci-dessous tournent aussi en intégration continue
(GitHub Actions) et conditionnent la fusion :

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php composer audit
docker compose exec php vendor/bin/phpunit
```

PHPStan est réglé au **niveau 8**, sans erreur tolérée.

## Secrets

Aucun secret réel ne doit entrer dans un fichier suivi par git. Sont committés,
et le restent sans valeur sensible : le `.env` racine (valeurs de développement),
les deux modèles `.env.*.local.dist` (valeurs vides) et les `app/.env*`.

Les vrais secrets vont exclusivement dans `.env.staging.local` et
`.env.prod.local`, ignorés par git comme par Docker. Staging et production
refusent de démarrer si l'un d'eux manque — c'est voulu.

## Documentation

| Document | Contenu |
|---|---|
| [`docs/plan-de-bataille.md`](docs/plan-de-bataille.md) | Cadrage technique, phases, décisions actées |
| [`README.docker.md`](README.docker.md) | Détail du stack Docker, environnements, observabilité |
| [`CLAUDE.md`](CLAUDE.md) | Contexte projet pour Claude Code : commandes, conventions, pièges |

La conception du jeu (systèmes, économie, lore, direction artistique) vit dans un
dossier Google Drive séparé, en seize documents numérotés `00` à `15`. Ils font
foi sur le plan fonctionnel.
