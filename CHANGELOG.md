# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/).
Le projet suit le [versionnage sémantique](https://semver.org/lang/fr/) : tant
que la version majeure vaut `0`, l'interface de jeu et le schéma de base de
données peuvent changer sans préavis d'une version mineure à l'autre.

Ce journal dit **ce qui a changé pour qui joue**. Le *pourquoi* d'une décision
de conception — l'intention d'une phase, les arbitrages tranchés, les pièges
payés — vit dans [`docs/phases-livrees.md`](docs/phases-livrees.md), qui reste
la lecture de référence pour comprendre le code.

## [Non publié]

### Ajouté

- **La page d'accueil se lit en largeur** : l'accroche et la carte côte à côte,
  les deux captures en vis-à-vis, la minuterie et les deux modes de jeu dans une
  même bande, cinq activités sur une rangée — dont une nouvelle, « Apprendre »,
  pour les hiéroglyphes. La page tient en trois écrans au lieu de cinq.
- **Les dernières pages rejoignent les sous-onglets** : le territoire (la case
  regardée, les expéditions en route, l'état de la ville), la commande du
  pharaon (le cartouche, la dotation, la partie), la reprise d'une partie (où
  l'on en est, la famille, les options), la liste des parties (en cours,
  closes) et l'onglet d'essai. Le panneau du territoire s'élargit sur grand
  écran, et la page de création d'une partie passe sur deux colonnes.
- **Toutes les pages de bâtiment se construisent de la même manière** : la
  Résidence, la Maison des scribes et l'Auberge rejoignent les autres avec des
  sous-onglets posés juste sous l'en-tête — la Direction et l'encart des
  écritures n'étirent plus la page avant eux. Les routes occupent toute la
  largeur, sur deux colonnes (la cité à gauche, l'étal et les convois à
  droite), et les blocs ne sont plus bridés à une colonne de lecture étroite.
- **Chaque bâtiment range ses sections en sous-onglets**, pour que l'écran de
  ville ne dépasse plus la fenêtre : Entrepôt (Réserve, Répartition, Carrières,
  Routes), Grenier (Réserve, Champs), Port (Pêcheries, Routes), Marché (Étal,
  Prix), Atelier et Forge (À l'ouvrage, Consigne), Caserne (Troupe, Recruter),
  Temple (Offrandes, Oracle) et Quartier d'habitation (Habitants, Faire venir du
  monde). La **Direction** de chaque bâtiment a son sous-onglet, marqué
  « vacante » quand aucun chef n'y est en poste. La section ouverte est retenue
  d'un rechargement à l'autre.
- **Les écrans de bâtiment sont plus compacts et plus horizontaux** : les
  listes de cartes — bâtiments dressés et à bâtir, candidats, routes, étal du
  Marché, dossiers d'enquête, questions, dieux, recettes, troupe — se rangent
  sur deux ou trois colonnes quand la fenêtre le permet, avec des cartes plus
  serrées, au lieu d'occuper toute la largeur l'une sous l'autre.
- **L'Atelier, la Forge et la Caserne se simplifient** : la consigne permanente
  passe dans un repli (ouvert d'office si l'atelier est à l'arrêt), les
  matières d'un lot s'affichent en pastilles, l'ordre en cours est un bloc
  compact, et la troupe se voit en cases (hommes levés, blessés, places libres).
- **La Maison des scribes se range en trois sections** — À lire et à résoudre,
  Clé de lecture, Alphabet — au lieu de quatre cents lignes d'un seul tenant.
  Les explications de fond passent dans des replis, les inscriptions déjà lues
  aussi, et la section ouverte est retenue d'un rechargement à l'autre.
- **Les routes commerciales se lisent d'un coup d'œil** : ouvertes d'abord, puis
  convois en chemin, puis routes à ouvrir ; chaque caravane se suit sur une
  piste d'une case par quinzaine de marche, et l'explication de l'ouverture
  d'une route est dans un repli.
- **Le Marché est plus simple à pratiquer** : la place du jour se lit en cases
  (écoulé en terre cuite, reste en pointillés), chaque lot propose d'emblée la
  quantité que la place absorbe encore — un clic suffit —, et les explications
  comme le réglage du prix passent dans des replis, ouverts d'office quand la
  ville se plaint des prix.
- **Champs, carrières et pêcheries se voient en tuiles** : chaque exploitation a
  la sienne, avec son état en toutes lettres et son équipage en pastilles
  (pleines pour les bras présents, creuses pour ceux qui manquent). Le tableau
  détaillé reste en dessous, ouvert d'office dès que quelque chose cloche.
- **Le Grenier et l'Entrepôt se voient** : la réserve est rangée en cases —
  chaque case vaut quelques unités —, pleines par ressource au Grenier, par
  famille à l'Entrepôt, vides en pointillés pour la place qui reste. Une
  légende dit chaque quantité en toutes lettres.
- **Le Quartier d'habitation se voit** : les habitants sont rangés en
  maisonnées dessinées — adultes qui travaillent, enfants, anciens à la canne,
  alités par la fièvre —, avec les maisons encore libres en pointillés. Les
  âges se lisent par la forme et l'activité par la couleur.
- **La Résidence familiale se range en quatre sections** — Vue d'ensemble,
  Mission (ou Règne en Aventure), Gouvernement, Bâtiments — au lieu d'un seul
  long défilement. La section ouverte est retenue : régler un salaire ou
  engager un chantier ne ramène plus à la première.
- **Un impôt chaque mois** : les scribes du pharaon lèvent un deben par actif
  valide à la fin de chaque mois (une quinzaine sur deux, rien pendant les
  jours épagomènes). C'est un filet — il couvre la moitié d'un salaire — pour
  qu'une ville qui n'a pas encore ouvert de route ne s'éteigne plus faute de
  deben. La Résidence annonce le montant et l'échéance.
- **Savoir lire rapporte** : la ville gagne un degré d'érudition — Lettré,
  Scribe confirmé, Maître des écritures — à mesure qu'elle apprend de signes,
  clé de lecture et alphabet confondus. Chaque degré majore l'impôt du mois de
  10 %, 20 % puis 30 %. Un encart « Les écritures de votre ville », à la
  Résidence et à la Maison des scribes, dit où l'on en est et ce que rapporte le
  prochain degré.
- **Les chefs se font valoir** : une annonce dit ce qu'un bâtiment sans chef
  perd (il tourne à 50 %) et liste les spécialités possibles ; chaque candidat
  est jugé en mots, et le plus compétent comme le moins cher sont signalés. La
  Résidence liste les bâtiments encore sans chef.

- **« Mon compte » permet de changer son mot de passe**, en donnant l'ancien —
  sans passer par le parcours « mot de passe oublié », qui suppose d'attendre
  un message.
- **L'écran de saisie d'un mot de passe dit ce qu'on attend** : les trois
  règles, une jauge de force qui se met à jour à la frappe, et le conseil qui
  vaut mieux qu'elles — quatre mots sans rapport battent un mot court truffé de
  symboles. L'estimation est celle de Symfony, reproduite trait pour trait.
- **La carte se manipule au doigt** : un doigt la déplace, deux la zooment
  autour du point tenu, la molette aussi. Un glissement n'ouvre plus la case
  qu'il traverse.

### Corrigé

- **La carte était invisible sur un téléphone.** Les deux panneaux étaient en
  rangée : le détail prenait toute la largeur, le territoire était comprimé à
  zéro. Ils s'empilent désormais en dessous de 768 px.
- **« Ajuster » n'ajustait pas vraiment**, sur toutes les tailles d'écran : la
  grille était comprimée par la mise en page, ses tuiles débordaient, et le
  calcul lisait une grille trois fois trop étroite.
- La barre de jeu passe de 189 à 83 pixels sur un téléphone, et les onglets de
  la ville de quatre rangées à une seule — sans qu'aucun compteur ni aucun
  onglet ne soit masqué.
- Le titre de la page d'accueil ne prend plus cinq lignes sur un téléphone.
- Les tableaux du Port, de l'Entrepôt et de la Résidence décalaient l'écran
  entier sur un téléphone, barre de jeu comprise : ils défilent désormais dans
  leur propre cadre.
- L'onglet ouvert de la ville se ramène dans la bande visible, et prend enfin
  sa couleur — seule sa soulignure était posée.

## [0.13.0] - 2026-09-12

### Ajouté

- **« Mes parties » a son propre écran** (`/parties`), séparé de « Mon compte ».
  La liste occupait les trois quarts d'une page où l'on venait pour jouer, pas
  pour relire son adresse email.
- Pied de page : numéro de version, journal des versions et code source.
- La page d'accueil mène aux parties quand on est déjà connecté, au lieu de
  proposer de créer un compte.
- **Un bandeau dit que le jeu est en cours d'équilibrage**, repliable et qui se
  souvient du choix d'une page à l'autre. Il n'entre pas dans la coque du jeu,
  qui occupe exactement la hauteur de la fenêtre.
- **Trois captures d'écran sur la page d'accueil** : le territoire, la conduite
  de la ville, un cartouche royal. Le jeu se voyait décrit sans jamais se
  montrer.

### Corrigé

- **Se connecter mène enfin dans le jeu.** Faute de cible par défaut, une
  connexion retombait sur la page de présentation publique, qui propose encore
  « Se connecter » : on croyait que rien ne s'était passé. Un lien profond
  demandé avant la connexion garde la priorité.
- Le curseur redevient une main au survol des boutons, partout dans le jeu.

## [0.12.0] - 2026-09-12

Première version étiquetée. Elle ne marque pas un début de développement mais
la fin de onze phases livrées depuis le 2026-08-27 ; le détail de chacune est
au journal des phases.

### Ajouté

- **Comptes** — inscription, connexion, mot de passe oublié, vérification
  d'adresse non bloquante avec purge après sept jours. Écran d'administration
  des comptes et de leurs parties.
- **Parties** — modes Campagne (dix missions, d'Avaris au Sinaï) et Aventure
  (Memphis, réglages libres), jusqu'à cinq parties de front.
- **Ville** — douze bâtiments, chantiers en quatre étapes, calendrier
  pharaonique avançant par quinzaines déclenchées par le joueur.
- **Carte** — carte isométrique générée par région, brouillard levé case par
  case par l'éclaireur, gisements, champs et pêche.
- **Population** — foyers, enfants qui grandissent, emploi et salaires, chefs
  de métier, mécontentement et famine.
- **Artisanat et commerce** — Atelier, Forge, orfèvrerie, partenaires, routes,
  étal et convois exposés au risque.
- **Dieux** — huit divinités, faveur, dons au Temple, trois fêtes datées
  d'après les sources, effets agissant sans qu'on les sollicite.
- **Scribes et énigmes** — vingt signes de Gardiner vérifiés contre Unicode,
  alphabet, cartouches royaux, énigmes et enquêtes.
- **Medjaÿ** — danger sur la carte, levée de troupe, équipement issu de la
  Forge, combat résolu d'un bloc, escorte des convois.
- **Lignée** — renommée acquise à la lignée et non à la partie, héritage des
  routes et des contacts, succession de générations et de règnes.

### Sécurité

- Portes qualité bloquantes avant toute fusion dans `main` : PHP-CS-Fixer,
  PHPStan niveau 8 sans erreur, `composer audit`, PHPUnit.
- Protection CSRF sans état (double-submit cookie) sur tous les formulaires.
- Déploiement par clé SSH restreinte à un script unique (*forced command*),
  empreinte d'hôte épinglée.

[Non publié]: https://github.com/mmagny89/niout/compare/v0.13.0...HEAD
[0.13.0]: https://github.com/mmagny89/niout/releases/tag/v0.13.0
[0.12.0]: https://github.com/mmagny89/niout/releases/tag/v0.12.0
