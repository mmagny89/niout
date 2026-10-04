# Niout — les écrans

Comment se construisent les écrans du jeu, et les contraintes qui les tiennent.
Un test fonctionnel n'a pas de fenêtre et n'exécute pas le JavaScript : la
plupart de ces règles ne se vérifient que par des **assertions de structure**,
et c'est pourquoi elles sont écrites ici plutôt que laissées à l'œil.

Complète [`CLAUDE.md`](../CLAUDE.md) et [`regles-du-jeu.md`](regles-du-jeu.md).

---

## Deux coques, jamais une seule

`base.html.twig` sert la **présentation** — accueil, inscription, compte,
lancement d'une partie : colonne étroite, en-tête public, pied de page, la page
défile normalement. `base_jeu.html.twig` sert le **jeu** : plein écran,
`h-screen overflow-hidden`, ni en-tête public ni pied de page.

**Rien ne défile au niveau de la page pendant une partie.** Ce qui déborde
défile dans son propre panneau — sinon la barre de jeu s'en va avec, alors que
le doc 15 la veut visible en permanence. Conséquence pour tout nouvel écran de
partie : il hérite de `templates/partie/_layout.html.twig`, et **son contenu
doit porter lui-même son défilement** (`h-full overflow-y-auto`, ou une colonne
`flex h-full min-h-0` dont un seul panneau défile). Un écran qui l'oublie voit
son bas coupé, sans erreur ni avertissement.

Les messages flash y flottent au-dessus du contenu : un bandeau qui pousse la
mise en page ferait apparaître une barre de défilement au moment précis où le
joueur vient d'agir. **Chacun se ferme** (`flash_controller.js`) : le
journal d'une quinzaine est long, et la pile recouvrait le haut du panneau
ouvert jusqu'à la navigation suivante — il fallait avancer d'une quinzaine pour
retrouver son écran. Rien ne s'efface tout seul pour autant : un message qui
s'évanouit est un message qu'on n'a pas fini de lire. Aucun test fonctionnel
n'exécutant le JavaScript, la parade est une assertion de structure sur le
contrôleur et l'action — comme pour le jeton CSRF sans état.

**Tout ce qui ne défile pas est de la hauteur en moins.** Dans la coque du
jeu, un bandeau fixe se paie sur le panneau ouvert — et s'il dépasse la
fenêtre, le bas est coupé sans erreur ni avertissement, puisque la page ne
défile pas. Un outil ou une explication longue prend donc un **onglet**, jamais
une place fixe : c'est ce qui est arrivé au bloc du mode d'essai, qui mangeait
à lui seul un tiers de l'écran.

## La barre de jeu

**La barre de jeu porte `relative z-50`, et ce n'est pas décoratif** : sans
position ni z-index, elle ne crée aucun contexte d'empilement et ses volets
déroulants passent **sous** le contenu de la page — sous la carte en
particulier, dont le `transform: scale()` crée le sien. Un z-index ne
s'applique qu'à un élément positionné.

**Les compteurs sont rangés par famille** (`FamilleDeRessource`), chacune
derrière un `<details>` natif — qui s'ouvre au clavier et ne coûte pas un
contrôleur. C'est un **regroupement d'affichage seulement** : aucune règle du
jeu ne s'y adosse, et aucune ne doit s'y adosser. Il n'existe toujours ni
ressource « bois » ni ressource « pierre ».

**La carte se met à l'échelle, elle ne déborde pas** (`carte_controller.js`) :
`transform: scale()` sur la grille entière, jamais un redimensionnement des
tuiles — la couche cliquable subit ainsi exactement la même transformation que
l'image, et les losanges continuent de tomber juste.

## L'écran de ville : un onglet, un bâtiment

**Un onglet, un bâtiment** (décision de la joueuse, `ongletsDeLaVille()`,
`templates/partie/batiments/`) : l'écran de ville porte **un onglet par bâtiment
dressé**, chacun avec ce qui relève de sa fonction — sa direction, ses ouvrages,
ses routes, ses énigmes. Le découpage par thème (« Direction », « Commerce »,
« Ateliers ») obligeait à deviner dans quel panneau ranger quoi, et le Temple
avait même son écran propre : porter une offrande obligeait à quitter la ville.
Son ancienne adresse survit et redirige, un signet ne devant pas tomber dans le
vide.

**La Résidence familiale recueille tout ce qui n'appartient à aucun bâtiment** :
mission, objectifs, renommée, main-d'œuvre, chantiers, liste de ce qui reste à
bâtir — et, en mode Aventure, le règne en cours, le score cumulatif et la
succession familiale. Elle est le foyer de la lignée, présente dès le premier jour et jamais
construite — l'y mettre est la seule façon de garder ces écrans atteignables
pour une ville qui n'a encore rien dressé. C'est aussi le point de chute par
défaut de toute fonctionnalité qu'on ne sait pas rattacher à un bâtiment.

**La Résidence porte un tableau de bord, et les alertes sont dites une fois.**
Les chiffres de gouvernement — habitants, travail, réserves, bourse et renom —
vivaient en cartes de prose qui les noyaient : comparer deux nombres demandait
de lire deux phrases. Ils sont rangés en quatre petits tableaux, un par
domaine, chacun avec sa légende et des `<th scope="row">` — un lecteur d'écran
annonce ainsi le domaine puis ce que le nombre mesure. **Ce qui cloche est dit
en dessous, une seule fois** : une remarque accrochée à chaque ligne rendrait
illisible ce que le tableau existe pour rendre lisible. Chaque alerte nomme la
cause **et** le geste — un diagnostic sans remède se subit —, et une ville sans
souci le dit plutôt que d'afficher une liste vide.

**La Résidence se range en quatre sections** (`_residence_familiale.html.twig`),
parce qu'un seul défilement de sept cents lignes ne laissait plus rien trouver :
**Vue d'ensemble** (tableau de bord, écritures, alertes, bonnes nouvelles),
**Mission** — ou **Règne** en Aventure : objectifs, score, succession —,
**Gouvernement** (salaire des bras, ce qu'on attend, chantiers en cours) et
**Bâtiments** (dressés, à bâtir). Elles **réutilisent `onglets_controller.js`**,
imbriqué dans l'onglet de la ville : mêmes panneaux masqués plutôt qu'absents,
donc le contenu reste dans le document. Deux précautions : les identifiants
portent le préfixe `residence-`, parce que le test de structure des onglets de
la ville ne regarde que `panneau-*` ; et la section ouverte est **retenue**
(`data-onglets-memoire-value`, `sessionStorage`), car régler un salaire ou
engager un chantier recharge la page et ramenait sinon à la première section.
Le découpage se fait **sur des frontières de blocs Twig**, jamais au milieu d'un
commentaire : un `{#` non refermé avale le panneau suivant, sans erreur.

**Le Quartier d'habitation dessine ses habitants** (`Maisonnees`,
`_maisonnees.html.twig`). Le jeu ne tient que trois nombres — actifs, enfants,
anciens — et nulle part qui habite avec qui : la répartition en maisons est donc
une **représentation déterministe**, jamais persistée ni tirée au sort, pour
qu'un rechargement ne recompose pas le quartier. Elle respecte les invariants :
autant de maisons occupées que `Population::foyersPour()`, aucune au-delà de
`PERSONNES_PAR_FOYER`, les âges distribués à tour de rôle pour se mêler. **Les
âges se lisent par la forme** (grand, petit, canne) **et l'activité par la
couleur** — jamais la couleur seule. Chaque maison porte une description écrite
(`aria-label`), le dessin étant masqué aux lecteurs d'écran ; les symboles sont
définis une fois (`<symbol>`/`<use>`), une ville pleine comptant plus de cent
soixante maisons.

**Le Grenier et l'Entrepôt montrent leur réserve en cases** (`VueDeLaReserve`,
`_reserve_visuelle.html.twig`) : ce qu'elle contient, et la place qu'il reste
avant que le surplus ne se perde. Une **représentation**, rien n'en est
persisté. Chaque case vaut un `pas` — le plus petit pas rond qui tienne la
réserve en soixante cases au plus. **Les vivres se rangent ressource par
ressource** (six au plus), **les matériaux par famille** (`FamilleDeRessource`),
parce qu'une trentaine de teintes ne se distinguent plus ; le détail reste dans
la légende. Les cases se comptent par arrondi au supérieur **sur le cumul** : un
lot minuscule garde sa case, et le total n'excède pas l'occupation. Le dessin
est masqué aux lecteurs d'écran, la légende dit la même chose en lettres, et la
couleur n'est jamais seule.

**Simplifier un écran, c'est ranger sans rien retirer** (Marché, exploitations).
Trois gestes, repris partout : **l'essentiel d'abord** — l'état et le geste
courant en haut —, **les explications de fond dans un repli** (`<details>`), lues
une fois et non relues à chaque visite, ouvert d'office quand le problème qu'elles
expliquent se présente ; et **une valeur proposée par défaut** plutôt qu'un champ
à remplir. Le Marché propose ainsi, pour chaque lot, la plus grande quantité que
la place absorbe encore (`Marche::quantiteQueLaPlaceAbsorbe()`, la même formule
que `vendre()` : une quantité calculée autrement serait refusée par le plafond
qu'on vient d'annoncer). **Les exploitations** — champs au Grenier, carrières à
l'Entrepôt, pêcheries au Port — se voient en tuiles : état en lettres, équipage
en pastilles ; le tableau reste dessous, détail ouvert d'office s'il y a une
ligne muette ou épuisée. Le contenu replié reste dans le document : les tests
et les lecteurs d'écran le lisent.

**La Maison des scribes et les routes appliquent les mêmes gestes.** La Maison
se range en trois sections (`scribes-section-*`, même contrôleur d'onglets
imbriqué que la Résidence, même mémoire de la section ouverte) : **À lire et à
résoudre** (ce qui attend une réponse), **Clé de lecture**, **Alphabet**. Les
explications — pourquoi trois dessins se retrouvent dans les deux tables, la
convention des musées —, ainsi que les inscriptions déjà lues, vont dans des
replis. **Les routes** se rangent par état : ouvertes (celles où l'on agit
chaque quinzaine), convois en chemin, à ouvrir. Une caravane se suit sur sa
**piste** (`_piste_de_route.html.twig`) : une case par quinzaine de marche, aller
et retour compris pour un convoi, le texte disant la même chose que le dessin.

**Des cartes, pas des bandeaux** : une liste de cartes occupant toute la
largeur gaspille l'écran large et oblige à défiler. Les listes de cartes
(bâtiments, candidats, routes, recettes, dossiers, questions, dieux, troupe)
se rangent donc en **grille de deux ou trois colonnes** (`md:grid-cols-2`,
`2xl:grid-cols-3`) avec des cartes serrées (`p-4`), et reviennent à une colonne
sur téléphone. Quand une carte porte un formulaire, elle est une colonne
(`flex flex-col`) dont le formulaire se range en bas (`mt-auto`), pour que les
boutons s'alignent d'une carte à l'autre. **Ne pas replier une liste en grille
quand l'ordre se lit de haut en bas** (un déroulé, un classement) : la grille
s'adresse aux choses interchangeables.

**L'Atelier et la Forge** mettent la **consigne permanente dans un repli**, un
réglage qu'on pose une fois — ouvert d'office quand l'atelier est à l'arrêt
faute de matières —, et les matières d'un lot en pastilles. **La Caserne** montre
sa troupe en cases : un homme levé, une case pleine ; un blessé, terre cuite ;
une place libre, des pointillés.

**Chaque panneau de bâtiment range ses sections en sous-onglets**
(`partie/_sous_onglets.html.twig`, trois macros : `debut`, `panneau`, et le
`</div>` du conteneur). Même contrôleur que les onglets de la ville, appariement
**par rang**, section ouverte retenue (`memoire`). Un panneau ouvre le conteneur,
range ses sections, le referme ; les identifiants portent le préfixe du bâtiment
(`port-section-peche`), le test de structure des onglets de la ville ne regardant
que `panneau-*`. **La Direction est une section comme une autre**, la dernière,
marquée « vacante » quand aucun chef n'est en poste — c'est ce qui la rend
visible sans qu'elle occupe la hauteur du panneau. Ce qui se règle rarement (la
répartition de l'Entrepôt, le prix du Marché, la consigne d'un atelier) a sa
section plutôt que sa place en tête. `SousOngletsTest` contrôle toutes les barres
d'un coup : onglets et panneaux dans le même ordre, un seul panneau ouvert,
aucun identifiant en double. **L'Auberge n'en a pas** : une page courte n'a pas à
payer un clic de plus.

**Aucune page de bâtiment n'échappe aux sous-onglets**, la Résidence, la Maison
des scribes et l'Auberge comprises : `SousOngletsTest` en compte douze et
échoue si l'une revient à une construction maison. **Les sous-onglets se posent
juste sous l'en-tête**, avant tout bloc de contenu — la Direction, l'encart des
écritures, une explication qui les précéderaient repousseraient la barre en bas
d'écran, là où personne ne la cherche (défaut réel, constaté à la Maison des
scribes). **Le panneau occupe toute la largeur de la fenêtre** : seuls les
paragraphes gardent une mesure de lecture (`max-w-4xl`), les blocs, tableaux et
replis n'ont plus de plafond. Une carte lourde, comme une route commerciale, se
coupe en deux colonnes dès `lg` : ce qu'est la cité à gauche, ce qu'on y fait à
droite.

**Le territoire, la commande, la reprise et la liste des parties suivent la
même règle** (`SousOngletsTest` les contrôle avec les bâtiments). Trois
particularités : **la mémoire de la section ouverte est coupée sur la carte**
(`retenir = false`) — cliquer une case recharge la page et doit rouvrir le
détail de cette case, pas le dernier onglet regardé ; **l'action principale d'une
page reste hors des sous-onglets** (« Prendre mes fonctions », « Reprendre la
partie ») pour qu'aucun clic ne la sépare du joueur ; et **un formulaire n'a pas
de sous-onglets** — la création d'une partie se range sur deux colonnes, ce qu'on
peut jouer à gauche et le formulaire à droite. Les pages hors jeu (accueil,
connexion, compte, administration) n'entrent pas dans cette règle.

**La carte ouvre ses écrans dans une fenêtre** (chantier décrit dans
[`plan-fenetres.md`](plan-fenetres.md), phases 1 à 3 livrées). Un `<dialog>` **non
modal** porte un `<turbo-frame id="fenetre">` ; un lien `data-turbo-frame="fenetre"`
y charge sa cible sans quitter la carte. **Non modal parce que le bouton de cycle
est dans la barre** : la fenêtre ne couvre que la zone de la carte, jamais la
barre, et `fenetre_controller.js` rend à la main ce que `showModal()` donnait —
Échap ferme, le focus entre dans la fenêtre et retourne à ce qui l'a ouverte.
**L'état est dans l'URL** : `carte?ouvre=/partie/12/ville?onglet=grenier`. Le serveur rend la
carte avec la fenêtre déjà remplie (`OuvertureDeFenetre`, par sous-requête avec
l'en-tête `Turbo-Frame`), recharger la page la rouvre au même endroit, et
`replaceState` tient l'adresse à jour. **Le paramètre vient du visiteur et ne se
suit jamais sans validation** : un chemin interne à *cette* partie, d'une route
qui sait répondre en cadre (`ROUTES_DE_CADRE`), ni un autre site, ni la carte
elle-même. Chaque route de fenêtre répond en cadre avec l'en-tête, et renvoie
vers la carte ouverte sans lui. **La barre de jeu est un cadre** (`barre`,
`app_partie_barre`) : elle se recharge seule après chaque action de la fenêtre,
et la carte se rafraîchit une fois à la fermeture si quelque chose a changé. Les
routes de fenêtre vivent dans `FenetreController`, pas dans `PartieController`.

**Fermer proprement** (phase 3). Le bouton retour du navigateur ferme la fenêtre :
ouvrir depuis la carte ajoute une entrée d'historique (`pushState`), naviguer d'un
bâtiment à l'autre la remplace (`replaceState`), et fermer par la croix ou Échap
**défait** l'entrée ajoutée, de sorte que la fermeture ne laisse aucune trace. Un
piège payé d'avance : le rafraîchissement de la carte, quand quelque chose a
changé, **attend que l'adresse ait fini de reculer** — le lancer tout de suite
visiterait encore l'adresse « ouverte » et rouvrirait la fenêtre qu'on vient de
fermer. Une page rechargée avec la fenêtre déjà ouverte n'a pas d'entrée à elle :
le retour quitte alors la carte, ce qui est l'attendu d'un rechargement. **Il n'y a
pas de « clic sur le fond »** : la fenêtre est non modale, la carte reste vivante
derrière, et cliquer une case charge sa page, ce qui referme la fenêtre de fait.

**La ville est la première fenêtre** (`fenetre/ville.html.twig`) : **un rail de
carrés à gauche — la cité —, le panneau du bâtiment choisi à droite**. Cliquer un
carré change le contenu du cadre sans fermer la fenêtre ; cliquer la tuile de la
ville sur la carte l'ouvre sur la Résidence. **On ne rend que le panneau ouvert**,
plus tous les onglets de bâtiment qu'il fallait rendre puis masquer : la moitié du
travail en moins, et une page qui se charge plus vite. Le panneau garde
l'identifiant `panneau-<bâtiment>` qu'il avait du temps des onglets, et ses
sous-onglets (`_sous_onglets.html.twig`) ne changent pas. **Sans l'en-tête
`Turbo-Frame`, `GET /ville` ne redirige pas : elle rend la carte avec la fenêtre
ouverte** (`forward` vers la carte, qui relance la sous-requête de cadre), de sorte
qu'une adresse tapée, un lien partagé et une redirection après action retombent
sur le bon écran — et que les tests lisent encore le contenu. Les liens qui
quittent la ville vers une case de la carte portent `data-turbo-frame="_top"`.
Le bouton de cycle, quand la ville est rendue en fenêtre, ramène à la **carte**
(`ouvre` conservé), pas à `/ville` : la fenêtre se rouvre au même endroit.

**La carte occupe tout l'écran, le reste est fenêtre** (phase 5). Plus de panneau de
droite : le **détail d'une case** s'ouvre en *feuille* posée à droite
(`app_partie_case`, `fenetre/case.html.twig`), qui laisse la carte visible — on
regarde une case en cliquant ses voisines. Le contenu se déclare feuille par
`data-forme="feuille"` et la fenêtre s'y adapte par CSS (`:has()`), sans JavaScript.
Les actions d'une case (éclaireur, carrière, semis, fouille) redirigent vers cette
même fenêtre, comme celles de la ville. **Les signaux** (fièvre, disette, fête…) et
les **expéditions en route** sont des pastilles dans la barre de jeu (`_barre.html.twig`,
seulement là où `signaux` est connu) ; un signal ouvre la Résidence, la pastille
d'expéditions ouvre `app_partie_expeditions`. Les anciennes adresses `carte?zone=x-y`
ouvrent la fenêtre de la case : les liens d'avant restent valables. Les données de la
case vivent dans `Fenetre\DetailDeCase`, plus dans le contrôleur de la carte.

**La ville vue d'en haut** (`carte?vue=ville`, en cliquant la tuile de la ville) remplace
le territoire par un visuel — `ville`, ou `ville_port` quand `City::jouxteUnPointDEau()` —
sur lequel chaque bâtiment dressé est posé sur son **lot**. Le visuel ne porte que de la
terre, des chemins et de la végétation : quinze clairières nues, que les sprites recouvrent.

**Le lot fait l'échelle.** Chaque sprite porte son propre lot — plate-forme de terre battue
à muret bas — **identique à ses quatre paliers** ; on le pose pour que son lot ait la
largeur de la clairière du plan et que les deux centres coïncident. Un petit bâtiment du
palier un n'est donc jamais gonflé à la taille d'un temple : il occupe une partie d'un lot
qui a toujours la bonne taille. (La première série, mise à la largeur de l'enclos sprite par
sprite, rendait « bizarre » : échelles et perspectives incohérentes.)

- `Game\EmplacementsDeLaVille` : les quinze lots — centre en pixels du visuel (1376 × 768),
  classe `l`/`m`/`s` (4×4, 3×3, 2×2 unités du plan guide) et bâtiment. **Un lot fixe par
  bâtiment** : on ne choisit pas où bâtir, on choisit quoi. Trois lots libres, rendus par un
  lot vide, sont du décor.
- `Game\AncragesDesSprites` — **généré**, ne pas éditer : centre et largeur du lot dans chaque
  sprite, mesurés par `outils/decouper-batiments.py`.
- `Fenetre\VueDeLaVille` convertit le tout en pourcentages du visuel : le gabarit
  (`partie/_vue_de_la_ville.html.twig`) n'a ni pixel ni échelle à connaître.
- Un bâtiment dressé est un lien — son sprite — vers sa fenêtre ; un lot pas encore bâti
  montre un lot vide et mène à la Résidence (ce qu'il reste à bâtir). Palier de sprite :
  `min(niveau, 4)` — les planches en livrent quatre, les bâtiments montent au niveau cinq.

**Les sprites se régénèrent par prompts** (`docs/prompts-images-ville.md`) puis se découpent par
`outils/decouper-batiments.py` à partir de `sources-sprites/v2/` (ignoré par git) : fond
blanc retiré par remplissage depuis les bords, quatre paliers recadrés sur un cadre commun,
WebP à fond transparent dans `app/assets/images/ville/{batiments,lots}/`. Le sprite du Port est
privé de son eau (bleu clair) : le fleuve est celui du plan. **Pour déplacer un lot**, changer
ses coordonnées dans `EmplacementsDeLaVille` ; **pour remplacer un sprite**, relancer l'outil.

**Chaque carré a son emplacement de visuel** : déposer
`app/assets/images/batiments/<type>.webp` (`grenier.webp`, `maison_des_scribes.webp`…)
suffit, `BatimentsDeLaCite` teste l'existence du fichier et le gabarit n'a pas à
changer ; sans image, un monogramme tient la place dans le même cadre, pour que
la grille ne bouge pas à l'arrivée des images. Les chantiers de bâtiments qui
n'existent pas encore ne font pas un carré : ils figurent dans la Résidence.

## Signaux, alertes et reprise d'onglet

**L'état de la ville se lit depuis les deux écrans** (`EtatDeLaVille`,
`_signaux.html.twig`). La fièvre, la disette et le mécontentement ne
s'affichaient que dans la ville, alors qu'on passe des quinzaines entières sur
la carte à explorer et à exploiter : on découvrait la maladie en rentrant,
plusieurs quinzaines trop tard. Un seul service produit la liste, et les deux
écrans la lisent — deux listes écrites séparément auraient fini par diverger,
et c'est la carte qui aurait cessé de dire la vérité. **Le bon compte autant
que le mauvais** (décision de la joueuse) : une fête, une crue forte, des dieux
acquis, un renom qui attire sont des moments à saisir, et n'annoncer que les
ennuis ferait du jeu une liste de pannes. Chaque signal nomme la **cause et le
geste** — un diagnostic sans remède se subit. Sur la carte et en tête de ville,
ils tiennent en une ligne de pastilles avec leur détail dans un `<details>`
natif : tout ce qui ne défile pas est de la hauteur en moins.

**Chaque réglage vit là où il se comprend** (playtest) : la **répartition des
réserves** — ce qu'on garde de chaque ressource, donc ce qui part au Marché —
est à l'**Entrepôt**, qui tient les stocks, en un seul tableau plutôt qu'une
ligne par ressource dispersée ailleurs ; le **prix fait aux habitants** est au
**Marché**, avec ce qu'il coûte en mécontentement dit à côté ; le **salaire des
bras** est à la **Résidence familiale**, avec la main-d'œuvre. Un même réglage
à deux endroits finirait par diverger, et ce sont les écrans qui cesseraient de
dire la vérité.

**La Résidence porte « ce que vous attendez »** (`TravauxEnCours`) : chantiers,
ouvrages d'atelier, expéditions, convois et présents du roi en une seule liste,
triés du plus proche au plus lointain. Ils vivaient chacun dans leur panneau —
pour savoir ce qu'on attendait avant d'avancer d'une quinzaine, il fallait en
ouvrir six. **Cette liste ne porte aucune action** : chaque chose garde son
écran, avec son détail et ses boutons. Elle ne répond qu'à une question, et il
faut qu'elle se lise d'un coup d'œil.

**Une action ne renvoie jamais sur le premier onglet** (`retourALaVille()`,
`retourDemande()`). Toute interaction de la ville se solde par une redirection,
donc par un rechargement complet : sans reprise, vendre au Marché ramenait sur
la Résidence familiale et il fallait rouvrir son onglet à chaque geste.
L'onglet voyage par la **requête** — chaque formulaire porte un champ caché
`onglet`, et le contrôleur le repasse en paramètre —, jamais par une session ni
un fragment d'URL : un fragment ne parvient pas au serveur et ne survit pas à
une redirection. L'adresse obtenue reste partageable, comme la case détaillée
de la carte. Toute route qui redirige vers la ville passe par ces deux
helpers ; toute forme ajoutée à un panneau doit porter le champ caché, sans
quoi elle rouvre le premier onglet sans qu'aucun test ne le dise. **La case
sélectionnée suit la même règle** : elle survit à la quinzaine, qu'on avance
souvent en surveillant une expédition ou une carrière. Et **une clé
venue de la requête est confrontée aux onglets réellement rendus**
(`ongletDemande()`) : une clé forgée ouvrirait un panneau inexistant, laissant
la barre entièrement fermée.

Trois règles à ne pas défaire : les deux boucles du gabarit lisent **la même
liste** dans le même ordre — `onglets_controller.js` apparie par rang, et deux
listes construites séparément finiraient par diverger ; l'ordre est celui de
`TypeDeBatiment`, stable d'un rendu à l'autre ; et c'est `Enigme::lieu()` qui
décide où tombe une énigme, jamais l'écran.

**Embaucher un chef ouvre des postes** (`Effectifs::bilan()`) : un bâtiment sans
chef ne réclame personne et tourne au plancher, un bâtiment dirigé réclame ses
travailleurs. Retenir un candidat faisait donc baisser le rendement ailleurs
sans que rien ne le dise — les bras servis à la Forge n'étaient plus au Grenier.
L'écran nomme désormais les deux situations, **des bras oisifs** ou **des postes
vides**, qui ne peuvent pas coexister : la répartition sert jusqu'à épuisement,
bâtiments d'abord, territoire ensuite.

**La Caserne montre la troupe et ce qui empêche d'en lever un de plus** (lot
10.2) : l'effectif sur son plafond, l'entretien par quinzaine, les armes en
réserve, et pour chaque spécialisation le niveau requis, la caserne pleine ou la
bourse courte — dit avant la tentative, jamais par un refus. Un homme sans arme
le dit aussi : il part quand même, mais mal.

**La renommée s'affiche** (`PalierDeRenommee::suivant()`, `seuilDEntree()`) :
elle fixe le prix d'un appel d'habitants, fait venir des maisonnées seules à
partir de « Respectée » et attire les rivaux, mais n'était nulle part à l'écran
— ce qu'elle change se subissait sans se comprendre. Elle dit aussi ce qui reste
à faire pour le palier suivant : un compteur nu se subit, un objectif se joue.

**En Aventure, la mission cède la place au règne** (lot 11.1) : la Résidence
nomme le pharaon régnant, son cartouche quand il est établi, le rang du règne
dans la succession et le fait de son avènement. Un cartouche dont la lecture ne
s'établit pas **ne s'affiche pas** — c'est la règle des hiéroglyphes, et elle
prime sur l'envie de remplir la ligne.

**Le score cumulatif se montre en détail, pas seulement en total** (lot 11.4) :
un total nu ne se joue pas, on ne sait pas quoi faire pour le faire monter.
L'écran dit donc ce que chaque grandeur pèse — habitants, deben, renommée,
marchandises échangées — et rappelle qu'il n'y a rien à atteindre.

**La succession familiale ne s'affiche que lorsqu'elle s'ouvre** (lot 11.5), et
elle dit d'abord ce qui **ne** change **pas** : la ville, la renommée et les
contacts restent. Sans cela, le joueur croirait risquer sa partie au moment où
on lui demande de choisir un héritier.

**Et ce qu'elle vaut sur les prix** (lot 9.3) : la Résidence familiale annonce
la remise à l'achat et la majoration à la vente, puis l'avantage **total** dès
qu'un Négociateur s'y ajoute. Les deux chiffres sont montrés séparément à
dessein — l'avantage étant plafonné, ne montrer que la somme laisserait croire
que les sources s'additionnent sans fin. Le carnet de contacts s'y lit au même
endroit, et le rabais d'une route déjà connue s'annonce à l'ouverture, jamais
au moment du débit : une remise découverte au débit ne se joue pas.

**Onglets et panneaux s'apparient par rang**, pas par identifiant
(`onglets_controller.js`) : un panneau ajouté ailleurs que dans l'ordre de son
onglet décale tout ce qui suit, et l'on ouvre le voisin. `ErgonomieTest`
compare les deux listes **dans l'ordre** — c'est pour cela.

**L'écran de ville est en onglets** (`onglets_controller.js`), et **tous les
panneaux restent dans le document**, seulement masqués : la page est rendue
d'un bloc, changer d'onglet ne demande aucun aller-retour, et les tests
fonctionnels continuent de lire des sections que le joueur n'a pas ouvertes.

Un test fonctionnel n'a pas de fenêtre et n'exécute pas le JavaScript : il ne
peut pas prouver l'absence de défilement. La parade est une **assertion de
structure** — coque, onglets appariés à leurs panneaux, familles présentes —
comme pour le jeton CSRF sans état. Voir `ErgonomieTest`.

## Les hiéroglyphes à l'écran

**Tout glyphe affiché porte `font-hieroglyphes`** : aucun système
d'exploitation courant ne couvre le bloc égyptien d'Unicode, et la police est
embarquée — self-hébergée comme les deux autres familles, le jeu n'appelant
aucun CDN. L'oublier ne casse rien de visible en développement, où un repli du
navigateur sauve parfois la mise ; ailleurs, le joueur voit des carrés.

La police est **sous-ensemblée** aux seuls signes déclarés par le code. Après
tout ajout de signe, rejouer
`.claude/scripts/sous-ensembler-hieroglyphes.sh` : un signe absent du
sous-ensemble s'affiche en carré vide, **sans erreur ni avertissement**.

Les signes se manipulent au **glisser-déposer** — même contrôleur pour le
déchiffrage d'une inscription et pour la leçon qui écrit « Niout » —, et la
règle ci-dessous vaut telle quelle : l'interaction se construit au clavier
d'abord.

## Interactions

**Une interaction se construit au clavier, puis se décore à la souris**
(`dechiffrage_controller.js`) : le glisser-déposer appelle les mêmes actions
que le clic, et rien ne passe par le seul `dragstart`. Aucun test fonctionnel
n'exécute le JavaScript — la parade est une assertion de structure sur les
actions portées par chaque bouton.

## La carte isométrique

**La géométrie de la carte se mesure sur les tuiles, jamais sur la taille de
l'image** (`templates/partie/carte.html.twig`). Une tuile de 188 × 116 porte une
face supérieure de 186 × 90 : le reste est l'épaisseur du prisme, en bas, et ce
qui dépasse par le haut — les arbres d'une forêt, les roseaux d'une berge. Le
pas de la grille vaut la **moitié du losange** (93 × 45), pas la moitié de
l'image ; le prendre sur l'image écarterait les cases et ouvrirait des marches
entre elles. La zone cliquable se découpe des deux mêmes nombres, pour qu'aucun
des deux réglages ne dérive de l'autre. Après tout changement de planche,
remesurer : c'est le canal alpha qui fait foi.

**Une case tenue par des brigands se dit avant qu'on tente quoi que ce soit**
(lot 10.1) : le panneau de détail annonce la bande et la résistance qu'elle
oppose *réellement*, renfort de région compris, puis combien d'hommes on peut
lui opposer. Découvrir le danger par un refus d'exploitation serait le subir au
lieu de le jouer — même discipline que le débouché du Marché ou le coût d'une
route.

**On n'attaque pas d'un bouton.** Le formulaire de la case gardée envoie une
*expédition* menée par le Chef d'expédition, avec sa durée et ses vivres ; le
combat se résout à l'arrivée, sans écran de bataille (lots 10.4 et 10.5). La
réquisition de chars s'y ajoute quand la ville y a droit, et ce qui l'en empêche
s'affiche à côté plutôt que de laisser un champ inerte sans explication.

La planche « tuiles » se redécoupe avec `.claude/scripts/decouper-tuiles.py`,
jamais à la main : il détoure le damier — **peint dans les pixels du JPEG**, pas
une vraie transparence — par remplissage depuis les bords, et met **toutes les
tuiles à la même échelle**. Les mettre chacune à l'échelle de sa propre boîte
donnerait des losanges de tailles différentes et désalignerait la grille
isométrique.
