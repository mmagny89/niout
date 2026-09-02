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
bâtir. Elle est le foyer de la lignée, présente dès le premier jour et jamais
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

**La renommée s'affiche** (`PalierDeRenommee::suivant()`, `seuilDEntree()`) :
elle fixe le prix d'un appel d'habitants, fait venir des maisonnées seules à
partir de « Respectée » et attire les rivaux, mais n'était nulle part à l'écran
— ce qu'elle change se subissait sans se comprendre. Elle dit aussi ce qui reste
à faire pour le palier suivant : un compteur nu se subit, un objectif se joue.

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

La planche « tuiles » se redécoupe avec `.claude/scripts/decouper-tuiles.py`,
jamais à la main : il détoure le damier — **peint dans les pixels du JPEG**, pas
une vraie transparence — par remplissage depuis les bords, et met **toutes les
tuiles à la même échelle**. Les mettre chacune à l'échelle de sa propre boîte
donnerait des losanges de tailles différentes et désalignerait la grille
isométrique.
