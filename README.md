# Niout

[![Qualité et déploiement](https://github.com/mmagny89/niout/actions/workflows/qualite.yml/badge.svg?branch=main)](https://github.com/mmagny89/niout/actions/workflows/qualite.yml)
[![Release](https://github.com/mmagny89/niout/actions/workflows/release.yml/badge.svg)](https://github.com/mmagny89/niout/actions/workflows/release.yml)
[![Version](https://img.shields.io/github/v/tag/mmagny89/niout?label=version&sort=semver)](CHANGELOG.md)
[![Licence MIT](https://img.shields.io/badge/licence-MIT-blue)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777bb4)](https://www.php.net/)
[![Symfony 8.1](https://img.shields.io/badge/Symfony-8.1-000000)](https://symfony.com/)
[![PostgreSQL 18](https://img.shields.io/badge/PostgreSQL-18-336791?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![FrankenPHP](https://img.shields.io/badge/FrankenPHP-1.12-6e40c9)](https://frankenphp.dev/)
[![Tailwind CSS 4](https://img.shields.io/badge/Tailwind_CSS-4-06b6d4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com/)
[![PHPStan niveau 8](https://img.shields.io/badge/PHPStan-niveau%208-2a5ea7)](https://phpstan.org/)
[![Conventional Commits](https://img.shields.io/badge/Conventional_Commits-1.0.0-fe5196?logo=conventionalcommits&logoColor=white)](https://www.conventionalcommits.org/fr/v1.0.0/)

Jeu de gestion jouable au navigateur, situé dans l'Égypte du Nouvel Empire
(~1550-1070 av. J.-C.). Le joueur incarne une famille chargée par un pharaon de
fonder, restaurer ou sécuriser une ville réelle : commerce, artisanat,
exploration, énigmes et faveur des dieux s'y entremêlent.

Le parti pris central : **aucune attente en temps réel**. Un chantier ou une
expédition prennent du temps, mais ce temps n'avance que lorsque le joueur
déclenche un cycle. Rien ne tourne pendant qu'il est ailleurs.

*Niout* (niwt) signifie « la ville » en égyptien ancien.

## État du projet

En cours de développement. La boucle de jeu tient de bout en bout : fonder,
doter, bâtir, explorer, produire, employer, nourrir, commercer, honorer,
déchiffrer, enquêter et clore une mission.

| Domaine | Ce qui fonctionne aujourd'hui |
|---|---|
| **Comptes** | Présentation publique, inscription, connexion, mot de passe oublié ou modifié depuis le compte. Vérification d'adresse non bloquante : le compte sert tout de suite, mais est supprimé après 7 jours sans validation. |
| **Parties** | Mode Campagne (dix missions dans l'ordre, d'Avaris au Sinaï) ou Aventure (Memphis, réglages libres), avec la commande du pharaon et sa dotation royale. Jusqu'à cinq parties de front, reprenables et abandonnables. |
| **Ville et chantiers** | Les douze bâtiments, leurs coûts, plafonds de niveau et durées. Fonder se paie en matériaux seuls ; monter de niveau coûte des deben. Les travaux n'avancent qu'aux quinzaines déclenchées, plus vite pendant la crue d'Akhèt. |
| **Carte et territoire** | Carte isométrique générée à la création, révélée case par case par des éclaireurs. Gisements, pêcheries, champs (semis, pousse, récolte, repos) ; un filon tari se retrouve par prospection. |
| **Artisanat** | Atelier, Forge, orfèvrerie : un ordre paie ses matières à l'engagement, occupe un travailleur plusieurs quinzaines et ne livre qu'à la fin — un bâtiment mène autant d'ordres de front que de travailleurs, chacun avec sa consigne permanente. Les chefs spécialisés dirigent mieux leur propre ouvrage. |
| **Commerce et marché** | Cités partenaires sur des routes attestées, première caravane pour ouvrir, puis étal à prix libre : le prix décide de l'empressement. Le Marché local absorbe un volume limité par quinzaine. |
| **Réserves, population, emploi** | Plafonds de stockage par bâtiment ; population en trois nombres (actifs, enfants, anciens), vivres à chaque quinzaine, famine si elles manquent ; chefs embauchés sur annonce, bras à payer à chaque quinzaine. Un bâtiment réclame ses travailleurs, chef ou non : sans personne il ne fonctionne pas, au complet il atteint 50 % sans chef et 100 % avec. |
| **Faveur divine** | Huit divinités cultivées au Temple par des offrandes. Un dieu délaissé cesse de favoriser, il ne punit pas ; la fièvre passe parfois, sans jamais tuer. |
| **Écriture et énigmes** | Clé de lecture (vingt hiéroglyphes de Gardiner vérifiés contre Unicode) et apprentissage des vingt-quatre unilitères. Les signes sont vrais, les combinaisons sont des rébus, jamais de l'égyptien. Chaque mission s'ancre dans une pierre réelle (stèle d'Ahmôsis, Tombos, Pount…) et affiche le cartouche du pharaon quand sa lecture est établie. |
| **Enquêtes, fil rouge, rivaux** | Cases à fouiller, indices contradictoires, dossier à trancher ; chaque mission raconte quelque chose ; un marchand rival peut s'installer sur vos routes. |
| **Une seule page de jeu : la carte** | La carte occupe tout l'écran ; la ville, la commande du pharaon, le détail d'une case et les expéditions s'ouvrent en **fenêtre** par-dessus. Un rail de bâtiments en liste (sprite, niveau, rendement), la Résidence familiale qui recueille le tableau de bord, des pastilles de signaux dans la barre de jeu. Chaque bâtiment dit ses chefs, ses travailleurs et son rendement en pastilles. Au téléphone, la fenêtre est une feuille plein écran. |
| **La ville vue d'en haut** | Un clic sur la ville remplace la carte par son plan : chaque bâtiment dressé est posé sur son lot, son aspect suivant son niveau, et un clic ouvre sa fenêtre. Une ville au bord de l'eau s'affiche avec son fleuve et son ponton. |
| **Illustrations** | Trente-deux ressources et objets fabriqués, huit portraits de dieux, douze bâtiments en quatre paliers. |
| **Missions** | Dix missions enchaînées : objectifs, quêtes du pharaon, reconnaissance, legs vers la suivante. La région compte : loin du Nil, ni crue ni offrandes à Hâpi. |

Reste l'épreuve du jeu au navigateur — le calibrage se décide en jouant —, les
icônes d'interface et les ressources encore sans image, le contenu des XIXᵉ et
XXᵉ dynasties : feuille de route détaillée dans
[`docs/plan-de-bataille.md`](docs/plan-de-bataille.md), règles précises dans
[`docs/regles-du-jeu.md`](docs/regles-du-jeu.md).

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

Les emails envoyés en développement (vérification d'adresse, mot de passe
oublié) ne quittent pas la machine : ils s'affichent sur <http://localhost:8025>
(Mailpit).

## Structure

La racine ne porte que l'infrastructure Docker ; le code applicatif vit dans
`app/`. Toutes les commandes PHP se lancent donc dans le conteneur, dont le
répertoire de travail est `/app`.

```
.
├── app/          application Symfony
│   └── src/
│       ├── Entity/   état persisté d'une partie
│       └── Game/     règles et contenu du jeu, jamais persistés
├── docker/       image PHP, configuration Caddy, scripts d'entrée
├── docs/         règles du jeu, interface, plan de bataille, journal des phases
├── outils/       outillage d'exploitation (secrets, déploiement)
└── compose*.yml  socle et surcharges dev / staging / prod
```

La distinction `Entity` / `Game` compte : le catalogue des missions ou la formule
de la dotation royale décrivent le **contenu** du jeu, ils n'ont rien à faire en
base. Seul l'état d'une partie y est stocké.

## Qualité

Quatre portes — style, analyse statique, audit des dépendances, tests — tournent
aussi en intégration continue (GitHub Actions) et conditionnent la fusion :

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php composer audit
docker compose exec php bin/console tailwind:build   # requis avant les tests
docker compose exec php vendor/bin/phpunit
```

PHPStan est réglé au **niveau 8**, sans erreur tolérée.

Les tests fonctionnels rendent de vraies pages : sans CSS compilée, ils échouent
tous d'un coup, avec un message qui ne mentionne pas Tailwind clairement. D'où le
`tailwind:build` ci-dessus.

## Mode d'essai

Éprouver le commerce longue distance ou une région du Sinaï demanderait des
heures de jeu. Un compte privilégié dispose donc du **mode d'essai**, qui ouvre les dix
missions à la création d'une partie, comble ses réserves d'un million de chaque
ressource, plafonds levés, et lève le brouillard sur toute la carte :

```bash
docker compose exec php php bin/console app:users:admin vous@example.com
```

Le rôle ne s'accorde que par cette commande — aucun écran ne le propose ;
`--retirer` le reprend. C'est le même rôle qui ouvre l'administration des comptes. Une
partie d'essai l'affiche en toutes lettres : elle ne se confond jamais avec une
partie jouée.

## Secrets

Aucun secret réel ne doit entrer dans un fichier suivi par git. Sont committés,
et le restent sans valeur sensible : le `.env` racine (valeurs de développement),
les deux modèles `.env.*.local.dist` (valeurs vides) et les `app/.env*`.

Les vrais secrets vont exclusivement dans `.env.staging.local` et
`.env.prod.local`, ignorés par git comme par Docker. Staging et production
refusent de démarrer si l'un d'eux manque — c'est voulu.

## Licence et contributions

Le code est publié sous [licence MIT](LICENSE) : réutilisable librement, y
compris commercialement, à condition de conserver la mention de copyright.

Le vocabulaire, les textes de mission et les règles du jeu sont une création
originale : les reprendre tels quels pour un autre jeu relève de la politesse
autant que du droit d'auteur, la licence MIT ne portant que sur le code.

Les versions sont consignées dans le [CHANGELOG](CHANGELOG.md), les failles se
signalent selon la [politique de sécurité](SECURITY.md), et la marche à suivre
pour proposer un changement est dans [CONTRIBUTING.md](CONTRIBUTING.md).

C'est un projet personnel : les contributions extérieures ne sont pas
attendues, mais une issue qui signale un défaut est toujours la bienvenue.

## Documentation

| Document | Contenu |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Stack, commandes, architecture — le point d'entrée |
| [`docs/regles-du-jeu.md`](docs/regles-du-jeu.md) | Les invariants du jeu et leur raison d'être |
| [`docs/interface.md`](docs/interface.md) | Les écrans : coques, barre de jeu, fenêtres, carte, ville vue d'en haut |
| [`docs/plan-fenetres.md`](docs/plan-fenetres.md) | Le chantier « tout en fenêtres » : décisions, sept phases, journal |
| [`docs/prompts-images-ville.md`](docs/prompts-images-ville.md) | Les prompts des images de la ville (plan, lots, bâtiments) |
| [`docs/plan-de-bataille.md`](docs/plan-de-bataille.md) | Feuille de route, ce qui vient, décisions actées |
| [`docs/phases-livrees.md`](docs/phases-livrees.md) | Journal des phases : intention, lots, pièges payés |
| [`README.docker.md`](README.docker.md) | Détail du stack Docker, environnements, observabilité |
| [`CHANGELOG.md`](CHANGELOG.md) | Ce qui a changé, version par version |
| [`SECURITY.md`](SECURITY.md) | Comment signaler une faille, et ce qui est suivi |

La conception du jeu (systèmes, économie, lore, direction artistique) vit dans un
dossier Google Drive séparé, en seize documents numérotés `00` à `15`. Ils font
foi sur le plan fonctionnel.
