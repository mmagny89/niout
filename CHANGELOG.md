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

- **La barre de jeu prend des pictogrammes et du mouvement** : le deben, les réserves
  et les habitants sont des pastilles à pictogramme (le libellé reste lisible aux
  lecteurs d'écran), chaque signal de la ville porte le dessin de sa nature, et le
  bouton « Quinzaine suivante » tourne pendant que le temps passe. Un chiffre qui
  bouge s'éclaire et dit de combien. Rien ne bouge pour qui a demandé moins de
  mouvement à son système.
- **Le Temple et la Caserne se lisent d'un coup d'œil** : le Temple montre autant d'ankhs
  que de dieux qu'il peut porter, allumés pour ceux qu'on honore, et ce que vaut une
  offrande ; les jauges de faveur se remplissent à l'ouverture. La Caserne aligne un écu
  par homme (levé, blessé ou place libre) qui apparaît l'un après l'autre. Les cartes se
  soulèvent au survol.
- **La Résidence familiale s'ouvre sur cinq tuiles** — habitants, postes tenus, vivres,
  bourse, renommée — avec leur jauge qui vire à la terre cuite quand quelque chose cloche ;
  les quatre tableaux de chiffres restent dessous, repliés. Les alertes et les bonnes
  nouvelles portent le dessin de leur nature.

- **Des infobulles** sur les compteurs de la barre, les signaux, les tuiles de la Résidence,
  le bandeau du Temple et les écus de la Caserne : elles disent ce que mesure le chiffre et
  quoi faire quand il vire au rouge. Elles s'affichent aussi au clavier.
- **La fenêtre s'ouvre en douceur** : un fondu qui monte, la feuille d'une case glisse depuis
  la droite, et le contenu change en fondu d'un bâtiment à l'autre.

- **Le récapitulatif de la quinzaine** : en passant le temps, une carte descend et dit ce qui
  a changé — bourse, vivres, habitants, matériaux, renommée, en pastilles qui s'allument —
  puis le journal rangé par catégorie (santé, commerce, dieux, royaume…), replié s'il est long.
  Il remplace la pile de messages.
- **Des illustrations de remplacement** pour la grauwacke, le poisson, les dattes, les outils
  et les armes, et le deben reprend son pictogramme : plus de ligne sans image au Marché.
  Les cinq premières sont provisoires.

- **Le Marché montre ce que chaque vente rapporte** : un aperçu du gain qui suit la quantité
  saisie, un bouton « Tout » qui remplit ce que la place absorbe encore, et la place du jour en
  jauge. Le prix se règle aussi au curseur, dont la piste vire à la terre cuite au-delà de ce
  que la ville tolère. Les lots portent leur illustration.
- **Le Grenier s'ouvre sur deux tuiles** — vivres en réserve et ration par quinzaine —, et la
  réserve en cases (Grenier et Entrepôt) montre l'illustration de chaque ressource dans sa
  légende ; ses cases se posent l'une après l'autre.

- **L'Entrepôt répartit ce qu'on garde en cartes à curseur** : chaque ressource a son
  illustration, sa barre (lapis pour ce qu'on garde, terre cuite pour ce qui part), un curseur
  qui double le champ, et la phrase « N partiront au Marché » qui suit en direct. La réserve
  s'ouvre sur deux tuiles : matériaux et place libre.
- **Le Port a son bandeau d'eau** : des vagues qui dérivent, éteintes si l'on a demandé moins
  de mouvement.

- **La Forge et l'Atelier montrent ce qu'un ordre coûte avant de l'engager** : chaque matière
  d'un lot porte son illustration, et les quantités ainsi que les pièces attendues se
  recalculent quand on ajoute ou retire des lots (boutons − et +). L'ouvrage en cours montre
  l'objet qu'on fabrique et une barre rayée qui s'anime ; la consigne permanente fait tourner
  les flèches du cycle, et s'éteint quand elle attend des matières.

- **La Maison des scribes prend vie** : l'inscription à lire est gravée sur une plaque dorée,
  les cases se dorent et se posent en rebondissant quand on y place un signe, et la clé de
  lecture comme l'alphabet deviennent des tuiles de glyphes qui se redressent au survol, avec
  leur code de Gardiner en infobulle et une jauge de progression. Les dossiers d'enquête
  montrent leurs indices concordants en points, et les réponses d'exercice choisies s'éclairent.

- **Le Quartier d'habitation s'ouvre sur trois tuiles** — maisonnées logées, bras à placer,
  masse salariale. Ses maisons se posent une à une et se soulèvent au survol (la description de
  la maisonnée en infobulle), et ses habitants se balancent à peine. « Faire venir du monde »
  annonce les deux verrous — la place et la bourse — en pastilles avant qu'on s'engage.
- **L'Auberge a sa salle** : une braise qui respire et un soir chaud, à côté des devinettes.

- **La carte s'anime et se lit sans cliquer** : le territoire se déploie du fond vers l'avant,
  la case survolée ou sélectionnée se soulève, et des repères flottent au-dessus des cases
  reconnues — brigands (qui battent), quelque chose à fouiller, gisement, champ. Le détail d'une
  case s'ouvre sur son portrait (la tuile, qui flotte), ses pastilles d'état et des boutons à
  pictogramme.

- **L'accueil et la liste des parties s'animent** : les blocs apparaissent en entrant à l'écran,
  les activités et les deux modes de jeu ont leur pictogramme, et chaque partie porte la tuile de
  sa ville, qui flotte, avec ses chiffres en pictogrammes (cycle, deben, renommée).

- **Créer une partie se fait en voyant ce qu'on choisit** : Campagne et Aventure sont deux
  cartes à cocher (la carte choisie s'entoure, avec sa coche), et en Aventure la difficulté
  allume autant de dangers que de crans pendant que la taille de la carte dessine son territoire
  en losanges.

- **La commande du pharaon est un décret** : un papyrus doré, un sceau qui se pose, des lignes
  qui se déroulent, et un bouton « Prendre mes fonctions » dont le halo invite. La dotation royale
  apparaît tuile après tuile, et « La partie » montre la difficulté en signes allumés et la carte en
  losanges à son échelle.

- **Les expéditions en route se suivent sur une piste** : la case visée en miniature, le rôle en
  pictogramme, et un marcheur qui avance de la ville vers la case, à la part du chemin que dit le
  pourcentage. Sans expédition, la fenêtre dit quoi envoyer et où.

- **Les écrans de compte ont une tablette et un décor** : connexion, inscription, mot de passe
  oublié et nouveau mot de passe partagent une tablette à pictogramme, avec la ville qui flotte à
  côté sur grand écran. Chaque page a son dessin, ses boutons aussi, et « Mon compte » montre
  l'état de l'adresse en pastille et la ville de votre partie.

- **Le détail d'une case se range en onglets** — gisements, champs, envois — et ne montre que
  ceux qui servent. Les champs d'une terre tiennent en une seule liste — une ligne par parcelle : sa
  culture (qu'on change sur place), l'étape de son cycle, qui y travaille — et un seul bouton pour
  semer. Chaque expédition est une carte (ce que fait le rôle, ce qu'elle coûte).

- **Les bâtiments de la Résidence ne font plus qu'une liste** : dressés puis à bâtir, sur la même carte.
  Le Gouvernement montre le salaire des bras en curseur (terre cuite sous l'usage, lapis au-delà, avec ce
  que la ville en pense), les échéances en frise de cartes à pictogramme, et les chantiers avec leurs
  quatre étapes reliées. La Mission est un décret : la mission en crans, les objectifs en jauges, la
  requête du pharaon avec l'illustration de ce qu'il réclame, le règne en Aventure avec sa succession.
- **La liste des bâtiments de la Résidence se lit d'un coup d'œil** : chaque bâtiment, dressé ou à
  bâtir, montre son sprite ; le niveau est en crans, le rendement en jauge, et le coût du chantier en
  pastilles avec l'illustration de chaque ressource. Un bilan (dressés, à engager, en attente) ouvre la
  section, et ce qu'on peut engager passe avant ce qui est bloqué.

### Corrigé

- **On ne propose plus d'envoyer un prospecteur sur la ville** : le bouton « ce sol pourrait rendre
  argile, roseaux… » apparaissait sur la case de la ville, qui n'a jamais de gisement. Le serveur
  refuse maintenant ce départ comme l'écran ne le propose plus.
- **Une expédition armée déjà partie ne se propose plus une seconde fois** : la case dit qu'elle est
  en route au lieu de laisser un bouton que le serveur refuserait.

- **Le détail d'une case s'ouvrait parfois au centre au lieu de rester à droite** : la forme de la
  fenêtre dépendait d'un attribut que Turbo ne recopie pas au chargement. Elle est décidée dès le
  clic et confirmée par le contenu ; le cadre s'estompe pendant le chargement.

- **Sur téléphone, les volets de la barre (vivres, habitants) restaient invisibles** : ils
  s'ouvraient dans la rangée des compteurs, qui défile et les rognait. Ils se posent maintenant sous
  la barre, sur la largeur de l'écran. Les signaux défilent sur une seule ligne, et l'en-tête public
  passe à la ligne au lieu de déborder.
- **Mouvement réduit** : à la demande du système, toutes les transitions et animations du jeu
  s'arrêtent (il en restait quatorze qui échappaient au garde).
- **Contrastes** : deux textes sous le seuil de 4,5:1 retrouvent une couleur lisible, et la zone de
  toucher des liens de navigation et de pied de page atteint 24 px.

- **Les listes déroulantes des formulaires étaient invisibles** (créer une partie, mission de
  départ, difficulté, taille de la carte) : sans cadre ni fond, elles se confondaient avec du
  texte. Elles ont maintenant un cadre et un chevron.
- **Le mode d'essai n'écrase plus les écrans** : il donne 200 de chaque ressource (au lieu d'un
  million) et une bourse de 50 000, et recombler une partie atteint ce compte sans l'additionner.
  En mode d'essai les plafonds affichés suivent le stock : aucune réserve ne paraît saturée.

- **Des zones de la carte ne répondaient plus au clic** : les conteneurs de cases, des
  rectangles qui se recouvrent, interceptaient les clics des cases derrière eux dans les coins
  que leur losange ne couvre pas. Ils laissent maintenant tout passer ; seul le losange capte.

### Modifié

- **La barre de jeu tient sur une seule ligne** sur un écran large : les libellés des
  compteurs cèdent la place à leur pictogramme (le nom reste dans l'infobulle).

## [0.14.0] - 2026-10-04

**La carte devient la seule page de jeu.** La ville, la commande du pharaon, le détail
d'une case et les expéditions s'ouvrent en fenêtre par-dessus ; un clic sur la ville la
montre d'en haut, chaque bâtiment posé sur son lot ; ressources, objets et dieux
ont leurs illustrations. Le jeu est désormais en **bêta**.

### Ajouté

- **Les ressources et les dieux ont leur illustration** : trente-deux ressources et
  objets fabriqués s'affichent dans les volets de la barre de jeu, à l'Entrepôt, au
  Marché, dans la dotation royale, aux gisements, aux ateliers et aux exploitations ;
  les huit dieux ont leur portrait au Temple.

- **Les fenêtres se ferment d'un clic à côté**, sur le fond de la carte ou de la
  ville ; les liens, boutons et champs gardent leur rôle, et la barre de jeu reste
  utilisable fenêtre ouverte. Un contenu long a son bouton **« Retour en haut »**,
  collé en bas de la fenêtre. Le rail des bâtiments montre maintenant **leur
  illustration**, qui suit leur niveau, à la place des lettres.

- **La ville se voit d'en haut** : un clic sur la ville remplace la carte par son
  plan — quinze lots, dont douze reçoivent un bâtiment. Chaque bâtiment dressé
  apparaît sur son lot, son aspect suivant son niveau (quatre paliers), et un
  clic l'ouvre directement. Un lot pas encore bâti reste vide et mène à ce qu'il
  reste à bâtir. Une ville au bord de l'eau s'affiche avec son fleuve et son
  ponton. Un bouton ramène au territoire ; avancer d'une quinzaine reste sur la
  ville.

- **Les fenêtres se jouent au téléphone et au clavier** : sur un écran étroit, la
  fenêtre est une feuille plein écran, sa croix et ses boutons font 44 px, et le
  rail des bâtiments défile en ramenant le bâtiment ouvert dans la vue. À
  l'ouverture le lecteur d'écran annonce le titre de la fenêtre ; à la fermeture
  le focus retourne à la tuile ou à la pastille qui l'avait ouverte. Les cases de
  la carte montrent leur focus au clavier, et le bas de l'écran n'est plus masqué
  par la barre d'adresse des navigateurs mobiles.

- **La carte prend tout l'écran** : le panneau de droite disparaît. Le détail d'une
  case s'ouvre en feuille à droite, la carte restant visible — on peut regarder
  la case voisine sans rien fermer. Les signaux de la ville (fièvre, disette, fête…)
  et les expéditions en route sont des pastilles dans la barre de jeu, toujours
  sous les yeux ; un clic en ouvre le détail.

- **La commande du pharaon s'ouvre d'office dans une fenêtre** posée sur la carte,
  dès la partie créée ; « Prendre mes fonctions » la ferme. Reprendre une partie
  ramène directement sur la carte : il n'y a plus d'écran de reprise, la barre de
  jeu dit où en est la partie.

- **Une pastille « Bêta »** à côté du nom Niout, dans l'en-tête.

- **La ville s'ouvre dans une fenêtre posée sur la carte**, qui ne la quitte plus :
  un rail de carrés à gauche — un par bâtiment construit, avec son niveau et ses
  travaux —, le panneau du bâtiment à droite. Passer d'un bâtiment à l'autre ne
  ferme pas la fenêtre, et la barre de jeu, qu'elle ne recouvre pas, se met à jour
  après chaque action : on peut avancer d'une quinzaine fenêtre ouverte, elle se
  rouvre au même endroit. Recharger la page ou partager l'adresse la rouvre aussi.
  Un monogramme tient la place des visuels, à venir.

- **On pratique maintenant les hiéroglyphes, pas seulement on les regarde** : un
  nouvel onglet « Exercices » à la Maison des scribes propose une série de six
  questions sur les sons (signe vers son, son vers signe, objet vers son) et la
  lecture d'un vrai cartouche royal, signe par signe — ce qu'il note, un son ou
  un mot entier — avant de rendre le nom entier, sa translittération et son
  sens. Chaque exercice rapporte dix deben, une fois par quinzaine.
- **L'alphabet s'ouvre dans l'ordre où l'on en a besoin** : le *a*, le *r*, le
  *m*, le *s*, le *d*, le *h*, le *k* d'abord — de quoi écrire un nom dès les
  premiers niveaux —, les consonnes rares à la fin. La table, elle, se lit
  toujours dans l'ordre des grammaires.
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

### Modifié

- **Dépendances mises à jour** : Symfony 8.1, Doctrine ORM, Twig 3.30 et les outils de
  qualité (PHPUnit, PHPStan, PHP-CS-Fixer). PHPStan, plus strict, a fait corriger
  trois lignes de code sans effet sur le jeu.

### Corrigé

- **La fenêtre ne passe plus sous la barre de jeu** : son haut, titre et messages
  compris, était masqué par la barre.

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

[Non publié]: https://github.com/mmagny89/niout/compare/v0.14.0...HEAD
[0.14.0]: https://github.com/mmagny89/niout/releases/tag/v0.14.0
[0.13.0]: https://github.com/mmagny89/niout/releases/tag/v0.13.0
[0.12.0]: https://github.com/mmagny89/niout/releases/tag/v0.12.0
