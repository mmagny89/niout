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

**Comptes** — page de présentation publique, inscription, connexion, mot de passe
oublié. Vérification d'adresse non bloquante : le compte est utilisable tout de
suite, mais supprimé après 7 jours sans validation.

**Parties** — création en mode Campagne (dix missions dans l'ordre, d'Avaris au
Sinaï) ou Aventure (Memphis, réglages libres), avec la commande du pharaon et sa
dotation royale. Jusqu'à cinq parties de front, reprenables et abandonnables.

**Ville et chantiers** — les douze bâtiments du jeu, avec leurs coûts, leurs
plafonds de niveau et leurs durées de travaux. Fonder se paie en matériaux
seuls — argile, roseaux, bois local ; c'est monter de niveau qui coûte des
deben. Engager un chantier débite les ressources ; les travaux n'avancent
ensuite qu'aux quinzaines que le joueur déclenche, plus vite pendant la crue
d'Akhèt. Le calendrier pharaonique tourne, mois par mois et saison par saison.

**Carte et territoire** — une carte isométrique générée à la création de la
partie (Nil, Méditerranée, mer Rouge, désert, terre broussailleuse, oasis selon
la région), révélée case par case par des éclaireurs. Une case reconnue peut
porter un gisement à exploiter, une eau à pêcher une fois le Port dressé, ou un
champ à semer ; les champs traversent quatre étapes — semis, pousse, récolte,
repos — et ne nourrissent qu'à la récolte, sur le Nil comme en terre.

**Artisanat** — l'Atelier transforme : une jarre demande de l'argile et du bois
pour la cuire, un pain du blé et un four, une bière des pains d'orge émiettés
et mis à fermenter. Un ordre paie ses matières à l'engagement, occupe l'Atelier
plusieurs quinzaines et ne livre qu'à la fin. Le niveau du bâtiment ouvre les
recettes et élargit les lots ; les bras qui le tiennent en décident le rythme.
La Forge suit la même mécanique sur une matière que le Delta ne porte pas — le
cuivre —, et travaille de front avec l'Atelier. Au-delà vient l'orfèvrerie —
bijoux, statuettes, vases —, qui ne s'ouvre pas en montant l'Atelier mais en
portant l'**Entrepôt** au niveau 8, et réclame de l'or, de la turquoise, du
cèdre, de l'ivoire, de l'albâtre : rien de tout cela ne pousse chez soi, il
faut donc commercer avant d'espérer produire du prestige.

Les chefs comptent : un Potier, un Brasseur, un Armurier dirigent mieux **leur
propre ouvrage**, et seulement lui. À l'Entrepôt, deux spécialités ne
produisent rien mais changent le commerce — le Négociateur obtient de
meilleurs prix des deux côtés de l'étal, le Logisticien raccourcit les
trajets de caravane.

**Faveur divine** — huit divinités du panthéon du Nouvel Empire se cultivent en
parallèle : Hâpi pour la crue, Ptah pour bâtir vite, Sekhmet avant que la fièvre
ne passe. On les honore au Temple, en deben ou en marchandise — l'Égypte offrait
ce qu'elle avait —, et c'est le seul geste du jeu sans contrepartie immédiate.
Le Temple décide de tout : combien de dieux on peut porter haut, et jusqu'où.
Ne rien offrir ne coûte rien ; c'est la négligence prolongée qui fâche — et
même là, un dieu délaissé cesse de vous favoriser, il ne vous punit pas.
Chacun agit à sa manière : Hâpi incline la crue de l'année, Ptah presse les
chantiers, Osiris fait revenir les champs plus tôt, Amon-Rê attire le monde,
Sobek raccourcit ce qui voyage par l'eau. Et la fièvre passe parfois : elle
couche des bras quelques quinzaines sans jamais tuer personne, plus volontiers
sur une ville qui déborde de son logement — une offrande à Sekhmet, dont les
prêtres étaient les médecins de l'Égypte, en abrège le cours. Un chef pieux, où qu'il serve, fait entretenir les rites
par sa maisonnée : la ville oublie ses dieux moins vite.

**Commerce** — chaque région a ses cités partenaires, sur des routes réellement
attestées : le Chemin d'Horus vers Canaan, le Bahr Yousef vers le Fayoum, la
traversée de la mer Rouge vers Pount. Ouvrir une route, c'est y envoyer une
première caravane : on la paie, elle prend le temps du trajet, et la route
n'existe qu'à son arrivée. L'Entrepôt arme les pistes, le Port ce qui flotte —
une ville sans quai ne commerce que par la terre. Une fois la route ouverte, on
y tient un étal : « je vends du lin à 5 deben, j'achète du cèdre jusqu'à 19 ».
Le prix décide de l'empressement de la cité, donc de ce qui bouge à chaque
convoi — trop gourmand, personne n'achète ; généreux, les convois se pressent.
Les caravanes partent alors d'elles-mêmes, chargées de ce qu'on a engagé, et
reviennent au bout du trajet : la distance décide de la fréquence, le prix du
volume.

**Réserves** — le Grenier tient les vivres, l'Entrepôt les matériaux et les
objets, chacun avec un plafond que son niveau élève. Ce qui déborde ne rentre
pas : il faut écouler son surplus au Marché, ou agrandir. Le deben, lui, ne
s'entasse nulle part.

**Population et emploi** — la ville se compte en trois nombres : ceux qui
travaillent, les enfants, les anciens. Elle les nourrit à chaque quinzaine, et
sans vivres suffisantes la famine s'installe — d'abord le mécontentement, puis
l'échec. On y naît s'il reste de la place, on y fait venir des maisonnées selon
sa renommée, et l'on y embauche des chefs sur annonce : deux ou trois candidats
se présentent, avec leurs étoiles, leurs traits et la famille qu'ils amènent.
Chaque bâtiment, chaque carrière et chaque champ réclame des bras, et il faut
les payer à chaque quinzaine — un poste qu'on ne paie plus s'arrête.

La boucle de jeu tient donc debout de bout en bout : fonder, doter, bâtir,
explorer, produire, employer, nourrir. Restent le commerce et l'artisanat, les
Medjaÿ, la faveur divine et les énigmes — feuille de route détaillée dans
[`docs/plan-de-bataille.md`](docs/plan-de-bataille.md).

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
│   └── src/
│       ├── Entity/   état persisté d'une partie
│       └── Game/     règles et contenu du jeu, jamais persistés
├── docker/       image PHP, configuration Caddy, scripts d'entrée
├── docs/         plan de bataille et documents de projet
└── compose*.yml  socle et surcharges dev / staging / prod
```

La distinction `Entity` / `Game` compte : le catalogue des missions ou la formule
de la dotation royale décrivent le **contenu** du jeu, ils n'ont rien à faire en
base. Seul l'état d'une partie y est stocké.

## Qualité

Les quatre vérifications ci-dessous tournent aussi en intégration continue
(GitHub Actions) et conditionnent la fusion :

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
heures de jeu. Un compte peut donc recevoir le **mode divin**, qui ouvre les dix
missions à la création d'une partie, comble ses réserves d'un million de chaque
ressource, plafonds levés, et lève le brouillard sur toute la carte :

```bash
docker compose exec php php bin/console app:users:goddess vous@example.com
```

Le rôle ne s'accorde que par cette commande — aucun écran ne le propose. Une
partie d'essai l'affiche en toutes lettres : elle ne se confond jamais avec une
partie jouée.

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
