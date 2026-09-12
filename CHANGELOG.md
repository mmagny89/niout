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
