# Niout — journal des phases livrées

Le détail de chaque phase du développement : intention, lots, règles qui en
sortent, pièges payés, ce qu'elle laisse ouvert. C'est un **journal**, pas une
feuille de route — celle-ci vit dans
[`plan-de-bataille.md`](plan-de-bataille.md).

On l'ouvre pour comprendre **pourquoi** une décision a été prise, quand la
règle seule ne suffit pas. Les règles vives, elles, sont dans
[`regles-du-jeu.md`](regles-du-jeu.md) et [`interface.md`](interface.md) : ce
sont elles qui font foi pour écrire du code.

---

### 5.1 Phase 0 — Fondations techniques  ✅

Dépôt git, stack Docker + squelette Symfony (généré par
`.claude/scripts/setup-symfony.sh --dedicated-server --run`), authentification
de base, thème Tailwind (doc 15 : ocre/sable/terre cuite, accents
lapis-lazuli/or), pipeline CI GitHub Actions, outillage qualité. Stable depuis.

**Versions retenues** (vérifiées le 2026-08-27) : Symfony 8.1.5 · PHP 8.4 ·
FrankenPHP 1.12.7 · Tailwind CSS 4.3.3 · PostgreSQL 18 · Ember 1.6.0. Le stack
répond sur `https://localhost` (certificat auto-signé Caddy).

**Polices** : Marcellus (titrage) et Alegreya Sans (texte), **self-hébergées**
dans `app/assets/fonts/` — pas d'appel runtime à Google Fonts, pour ne pas
transmettre l'IP du visiteur à un tiers.

### 5.2 Phase 1 — Comptes et page d'accueil  ✅

Trois pages et un compte fonctionnel : présentation publique, inscription
(vérification d'email non bloquante, purge à 7 jours), connexion avec mot de
passe oublié.

Quatre écarts au plan initial, tous assumés :

- **Tests fonctionnels en `WebTestCase` plutôt qu'en Behat** — l'outil natif
  couvre les mêmes parcours sans machinerie supplémentaire.
- **URL en français** (`/inscription`, `/connexion`) ; les *noms* de routes
  restent en anglais, référencés par `security.yaml`.
- **Emails en synchrone** — la recette Symfony les route vers Messenger, mais
  aucun worker ne tourne sur ce stack. À rebasculer le jour où il y en aura un.
- **Mot de passe durci à l'inscription**, aligné sur les contraintes déjà
  exigées à la réinitialisation.

**Point de sécurité laissé ouvert** : le formulaire d'inscription révèle qu'une
adresse possède déjà un compte (énumération) — comportement par défaut de
Symfony, le masquer dégraderait l'inscription. Le flux de réinitialisation, lui,
ne fuit rien.

### 5.3 Phase 2 — Lancer une partie et bâtir  ✅

**Intention.** Livrer la plus petite tranche réellement *jouable* : créer une
partie, voir sa ville, lancer un chantier, déclencher un cycle, voir le chantier
avancer puis s'achever. C'est ce qui valide l'architecture d'état par partie et
la résolution de cycle — la fondation de tout le reste.

Livré : modèle de partie (`GameSave`, `Family`, `City`, plafond de 5 parties
actives) ; parcours de création (mode, nom de famille, dotation royale) ; liste
et gestion des parties ; vue de ville avec les 12 bâtiments et leurs conditions
de disponibilité ; cycles et chantiers (calendrier pharaonique, étapes nommées,
accélération ×1,5 en Akhèt) ; stock affiché en permanence dans la barre de jeu.

Deux notes de conception qui ont guidé la suite :

- **L'ordre imposé des missions simplifie le modèle** — un `GameSave` de
  campagne ne porte qu'un numéro de mission incrémenté, aucune notion de région
  débloquée.
- **Le récapitulatif de reprise n'est pas un journal d'événements.** Le jeu
  n'ayant aucun temps réel, rien ne se produit pendant l'absence du joueur : le
  récapitulatif porte sur l'état où la partie a été laissée, pas sur ce qui
  s'est passé depuis.

### 5.4 Phase 3 — Carte, exploration et ressources  ✅

**Intention.** Faire basculer la ville de la dépense à la production : elle tire
ses matériaux de son territoire et sa nourriture de ses champs, plutôt que de
consommer une dotation qui ne se renouvelle pas. Les douze bâtiments du doc 01
deviennent tous atteignables — le Temple par le lin, le Port par la berge.

| Lot | Contenu | |
|---|---|---|
| 3.1 | Généralisation du stock en table `ressource → quantité` | ✅ |
| 3.2 | Génération de la carte selon la géographie réelle | ✅ |
| 3.3 | Découpage des tuiles et écran de carte isométrique | ✅ |
| 3.4 | Reconnaissance par éclaireur | ✅ |
| 3.5 | Ressources de zone, champs et cycle agricole | ✅ |
| 3.6 | Points d'eau, Port et pêche | ✅ |
| 3.7 | Ville et territoire, ajustements de la joueuse | ✅ |

**Les règles qui en sortent**

- **Chaque coût nomme sa ressource réelle**, jamais un générique « bois » ou
  « pierre » : un compteur agrégé cacherait au joueur ce qu'il possède.
- **Le Marché est avancé de la Phase 5** dans sa forme minimale. Sans lui, la
  monnaie n'a aucune source renouvelable et toute partie finit figée.
- **Une case porte jusqu'à deux gisements**, jamais deux fois le même ; un
  minimum de champs et de cases poissonneuses est garanti par région.
- **Un champ ne nourrit qu'à sa récolte**, en quatre étapes nommées. Un champ du
  Nil suit la saison, un champ terrestre son propre compteur.
- **Le poisson est la seule ressource renouvelable** : une pêcherie tarissable
  aurait fait du Port un piège sur une carte à une seule case d'eau.
- **Reconnaître ses abords est gratuit** — ni deben ni vivres à moins de trois
  cases de la ville.
- **La carte est l'écran principal** d'une partie : on ouvre la ville en
  cliquant sa tuile, non l'inverse.

**Écarts assumés aux documents** : un attribut `desertDominant` ensable les
régions que le doc 11 décrit ainsi, le doc 02 ne posant le désert que sur un
bord ; et une case de sable bordant le Nil est exclue du placement de la ville,
les deux règles du doc 02 s'y contredisant.

**Ce qui reste ouvert**

- **Seul le Delta est autosuffisant** en matériaux parmi les dix régions. À
  partir de la mission 2, le commerce (Phase 5) devient une condition de
  jouabilité, pas un confort.
- **L'épuisement des gisements** est réservé par le doc 02 aux régions de
  difficulté 4 et plus, et `PoidsDeTirage::gisementsEpuisables()` existe pour le
  dire — mais n'est appelée nulle part. À trancher quand ces régions arriveront :
  brancher la méthode, ou acter que tout s'épuise.
- **Le doc 15 mériterait une correction** : le prompt de la planche 13 demandait
  des tuiles carrées vues de dessus à fond transparent ; la planche livrée est
  isométrique sur fond opaque.
- **Le modèle de population de ce lot a été entièrement refait en Phase 4** : il
  déduisait les habitants d'une formule de bâtiments sans consulter les docs 01
  et 02, qui les chiffrent. C'est de là que vient la leçon « avant d'inventer,
  vérifier que le document ne dit rien ».

### 5.5 Phase 4 — Population : recrutement, chefs et travailleurs  ✅

**Intention.** Faire cesser la fiction d'une ville que le joueur actionne seul.
Avant cette phase, les bâtiments fonctionnaient parce qu'ils existaient, les
carrières s'exploitaient toutes seules et les champs se moissonnaient sans
personne. Désormais tout réclame des bras — et ces bras ont une famille à
nourrir et un salaire à toucher.

**Deux principes ont structuré la phase.** Le premier vient du doc 03 :
**chiffré en interne, qualitatif à l'affichage** — le moteur manipule des
compétences de 20 à 100, le joueur ne voit que des étoiles et des libellés. Le
second est posé par la joueuse et vaut pour tout le projet : **les chiffres des
documents sont provisoires**. Le critère n'est pas la fidélité au document mais
l'équilibre, et le fait de **pousser le joueur à se servir des mécaniques**.

| Lot | Contenu | |
|---|---|---|
| 4.0 | Le deben devient la monnaie, l'or redevient un métal | ✅ |
| 4.1 | Habitants, actifs, inactifs ; naissances et appel d'habitants | ✅ |
| 4.2 | Le candidat : compétence, traits, spécialité, maisonnée | ✅ |
| 4.3 | L'offre d'emploi : poster, choisir, renvoyer | ✅ |
| 4.4 | Chefs et travailleurs, règle du demi-rendement | ✅ |
| 4.5 | Équipages du territoire et bâtiment gouvernant | ✅ |
| 4.6 | Salaires, masse salariale et calibrage de la phase | ✅ |
| 4.7 | Départs naturels, mécontentement, famine à deux paliers | ✅ |
| 4.8 | Ce que la compétence d'un chef change | ✅ |

**Les règles qui en sortent**

- **La monnaie est le deben** (≈ 91 g), l'Égypte pharaonique n'ayant pas de
  monnaie frappée. L'or est un métal qu'on extrait et qu'on vend.
- **La population se compte en trois nombres** — actifs, enfants, anciens. Le
  bilan tombe une fois l'an ; on naît, mais seulement s'il y a de la place.
- **Peupler passe par le logement, toujours** : l'appel d'habitants comme
  l'embauche d'un chef butent sur le même verrou, et le chef repart avec les
  siens s'il s'en va.
- **Ce sont les chefs qui recrutent** (doc 05). Un bâtiment sans chef ne réclame
  aucun travailleur, donc tourne au plancher : « sans chef, la moitié » est un
  cas de la formule générale `0,5 + 0,5 × (réel / requis)`.
- **Rien ne s'éteint faute d'employés** : embaucher est un investissement, pas
  une taxe — c'est ce qui rend la phase jouable.
- **Le territoire aussi a des salariés**, et chaque exploitation a un bâtiment
  gouvernant dont le niveau élargit l'équipage *et* le rendement.
- **Les salaires tombent avant la production.** L'unité de paiement est le
  bâtiment ou l'exploitation entière ; une unité impayée s'arrête.
- **Le mécontentement a deux causes et un seul mécanisme.** La famine se lit à
  deux paliers : mécontentement à 4 quinzaines, échec à 12.
- **Un chef ne crée jamais un multiplicateur de plus** : il module la qualité de
  direction, aux côtés de l'effectif. Un mauvais chef reste meilleur que pas de
  chef.

**Écarts assumés aux documents**

| Point | Document | Retenu | Pourquoi |
|---|---|---|---|
| Salaire d'un chef | `5 + comp × 0,3` (11-35) | `2 + comp × 0,12` (4-14) | Un seul chef dépassait tout ce qu'une ville du Delta peut gagner. L'écart mauvais/excellent reste de ×3 |
| Travailleurs du Port | 3 | **1** | Un chef et un homme tiennent un quai ; à trois, l'équipage mangeait toute la pêche |
| Bâtiments sans spécialité | — | Ne se dirigent pas | Résidence, Quartier, Auberge : la famille les tient elle-même. Déduction, pas une ligne de document |

**Pièges payés pendant la phase**

- **`ajusterRenommee()` n'était appelé de nulle part.** Toute règle indexée
  dessus serait restée inerte sans qu'aucun test ne le signale. **Avant
  d'indexer une règle sur une valeur, vérifier qu'une source la fait bouger.**
- **Un double comptage de rendement**, introduit au lot 4.4 et retiré au 4.5 :
  deux planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout
  tourne au moins à moitié » que la règle promet.
- **Le rendement propre d'un bâtiment était devenu décoratif** une fois ce
  double comptage retiré — rattrapé au 4.8 : il module désormais le **bonus**
  accordé aux exploitations, jamais leur base.
- **Une offre d'emploi doit figer son tirage**, sans quoi recharger la page
  relancerait les dés jusqu'au cinq étoiles.
- **Semer un `Mt19937` par candidat avec des entiers consécutifs** produit des
  premiers tirages corrélés, ce qui fausse toute mesure de distribution.

**Calibrages vérifiés plutôt que postulés**

- **Démographie**, 200 parties de vingt ans : sans Quartier la population fond
  de 10 à 5, avec un Quartier de niveau 1 elle monte à 13, aucune ville ne
  s'éteint.
- **Économie**, sur la ville d'exemple du lot 4.6 : 15 emplois, ~34 vivres
  produits pour 26 mangés, ~68 deben de revenus pour 39 de salaires.
- **Distribution des traits**, 400 tirages : 46,8 / 38,2 / 15,0 % contre des
  taux visés de 45 / 40 / 15.
- **Espérance de service d'un chef**, 300 tirages : une vingtaine de quinzaines
  pour une ancienneté annoncée de 20.
- **La spirale de mécontentement se redresse** : une ville affamée huit
  quinzaines puis ravitaillée retrouve le calme en huit, sans perdre la partie.

**Ce que la phase abandonne, et ce qu'elle laisse ouvert**

- **Le suivi des âges** permettait à un candidat d'annoncer « deux enfants
  bientôt en âge de travailler ». Le modèle agrégé supprime ce signal : c'est le
  prix de la simplicité demandée.
- **L'ordre de service des bâtiments est alphabétique** quand les bras manquent
  — stable et explicable, mais arbitraire.
- **La dotation avance une année de salaires des bras envoyés**, pas d'un chef,
  qui coûte 200 par an à lui seul. Embaucher avant d'avoir un revenu de Marché
  mène à la faillite en une quinzaine de cycles : tension voulue, mais raide.
- **Une vérification d'équilibrage en conditions réelles** reste à faire : mener
  une partie sur une année complète au navigateur. Les calibrages sont mesurés
  pièce par pièce, leur composition sur une partie entière ne l'est pas.

**Un déplacement d'équilibre à assumer** : la masse salariale dépasse largement
le coût des bâtiments (un Grenier coûte 15 deben, une quinzaine de salaires en
coûte 39). Le poste de dépense principal du jeu cesse d'être la construction
pour devenir l'emploi.

**Hors périmètre, explicitement** : les Medjaÿ et le combat (Phase 10), le
Charrier (Phase 10), le craft et les caravanes (Phase 5), le bonus de
main-d'œuvre d'Akhèt sur le vivier régional, et le kite — sans objet tant que
les prix restent en nombres entiers de deben.

---

### 5.6 Phase 5 — Artisanat et commerce  ✅

**Sources** : docs `08` (ressources, recettes, prix, rivaux), `12` (routes
commerciales par mission), `01` (Atelier, Forge, Entrepôt, Port, stockage).

**Intention.** La ville savait produire des matières premières et les vendre au
Marché local ; elle ne savait ni les **transformer**, ni aller chercher ce que
sa région ne porte pas. C'est le blocage identifié dès le lot 3.5 : **seul le
Delta est autosuffisant** en matériaux parmi les dix régions — à partir de la
mission 2, le commerce cesse d'être un confort pour devenir une condition de
jouabilité.

À la fin de la phase, on peut raconter : *« mon argile part à l'Atelier, où
trois potiers en tirent des jarres pendant deux quinzaines. J'ouvre une route
vers Byblos en y envoyant une première caravane, j'annonce que j'achète du
cèdre à douze deben et que je vends mon lin à cinq. Trois quinzaines plus tard,
un navire entre au port avec le cèdre ; le mien est reparti chargé de lin. »*

**Le principe de commerce universel du doc 08** structure toute la phase :
n'importe quelle ressource peut être achetée ou vendue. Le Marché et l'Entrepôt
sont des points d'échange généralistes, pas des catalogues fermés. La catégorie
d'une ressource ne dit qu'une chose : **où l'obtenir sans commercer**.

| Lot | Contenu | |
|---|---|---|
| 5.0 | Les douze ressources fabriquées, et leurs prix | ✅ |
| 5.1 | Capacité de stockage : plafonner sans périmer | ✅ |
| 5.2 | L'Atelier : des ordres de fabrication | ✅ |
| 5.3 | La Forge : outils et armes | ✅ |
| 5.4 | Les partenaires commerciaux et leurs fourchettes | ✅ |
| 5.5 | Ouvrir une route en y envoyant une caravane | ✅ |
| 5.6 | L'étal : annoncer ce qu'on vend et ce qu'on achète | ✅ |
| 5.7 | Le trafic : caravanes et navires en chemin | ✅ |
| 5.8 | Le craft de luxe, débloqué par l'Entrepôt | ✅ |
| 5.9 | Les chefs de l'Atelier, de la Forge et de l'Entrepôt | ✅ |

**Les règles qui en sortent**

- **Rien de fabriqué ne se trouve sur une carte.** La poterie, les outils et les
  bijoux n'existent que par le travail ou par l'import ; aucune région ne les
  déclare en ressource de zone.
- **Un objet vaut environ 165 % de ce qu'il coûte à produire.** En deçà,
  personne ne fabriquerait ; au-delà, vendre brut n'aurait plus jamais de sens.
  Toute recette ajoutée doit tenir cette marge, et c'est mesuré.
- **Le stock est plafonné, jamais périssable.** Le Grenier tient les vivres,
  l'Entrepôt les matériaux et les objets ; le surplus ne rentre pas, ce qui est
  rangé y reste. **Le deben n'a aucun plafond** — sinon le plafond bloquerait la
  vente, seule issue qu'il pousse à prendre.
- **Fabriquer prend du temps et plusieurs matières.** Les matières sont débitées
  à l'engagement, les pièces n'entrent qu'à l'achèvement, et **un seul ordre à
  la fois par bâtiment** : c'est ce qui donne son coût d'opportunité au craft.
- **L'Atelier et la Forge partagent tout** — un seul service, c'est la recette
  qui dit où elle se travaille.
- **Une route s'ouvre en y envoyant une caravane** : on paie, le convoi part, la
  route n'existe qu'à son arrivée. Le type de route décide du bâtiment —
  Entrepôt pour les pistes, Port pour tout ce qui flotte.
- **Le commerce est un étal, pas un bouton d'échange.** Un ordre ne débite rien,
  c'est une annonce ; les convois l'exécutent. **Le prix décide de l'empressement
  du partenaire**, donc du volume qui bouge — c'est ce qui en fait un levier
  plutôt qu'un curseur à pousser au maximum.
- **Un convoi parti est un engagement pris** : on débite au départ ce qu'on
  engage, on reçoit au retour, et le convoi porte **sa propre copie** de
  l'échange — retirer une annonce n'annule pas ce qui roule.
- **Les fourchettes se déduisent** du cours (200 % à la vente, 150 % à l'achat),
  jamais d'une table par partenaire ; et **un partenaire ne vend jamais ce qu'il
  achète**, sans quoi une route serait une machine à arbitrer.
- **Le luxe se débloque par l'Entrepôt, pas par l'Atelier** : le prestige n'est
  atteignable qu'une fois le commerce établi.
- **Une spécialité d'atelier ne vaut que sur son propre ouvrage**, et passe par
  la qualité de direction. Le Négociateur et le Logisticien font exception —
  leur effet n'est pas une production.

**Pièges payés pendant la phase**

- **Doctrine insère avant de supprimer.** Remplacer une caravane rentrée par une
  neuve dans la même quinzaine faisait sauter la contrainte d'unicité : une
  caravane **repart** plutôt qu'elle n'est recréée. Le piège des gisements,
  repayé.
- **Des plafonds de stock trop bas** (150) faisaient démarrer la ville à 95 % de
  saturation, la dotation valant déjà 143. Portés à 250.
- **Un plafond de vente à 140 %** ne laissait au lin que deux prix entiers
  possibles — un levier sans amplitude n'est pas un levier. Porté à 200 %.
- **Mesurer une vente de blé mesure aussi le dîner de la ville**, et une
  caravane rentrée repart aussitôt : deux tests faux avant d'être justes.
- **Compter les quinzaines ne mesure pas la qualité d'un chef** : elles sont
  entières, et n'y distinguent pas 134 % de 114 %.

**Calibrages vérifiés plutôt que postulés**

- **La marge de transformation**, sur les douze recettes : chacune reste au
  voisinage de 165 %, et le test tombe si une recette ajoutée s'en écarte.
- **La courbe d'empressement** d'un partenaire : le prix annoncé change bien le
  volume qui bouge, et l'écran le montre **avant** l'engagement.
- **Le trajet d'un convoi** vaut exactement deux fois la distance, mesuré et non
  supposé.

**Ce que la phase laisse ouvert**

- **Les marchands rivaux** (doc 08) : reportés en bloc après les enquêtes
  (Phase 7), décision de la joueuse — l'une de leurs trois issues est une
  enquête.
- **La péremption du surplus** (doc 01) : écartée, on plafonne sans dégrader.
- **L'héritage commercial inter-missions** (doc 12) : suppose une campagne qui
  enchaîne ses missions, donc la Phase 8.
- **L'usage des armes et des outils** : Phase 10 pour les unes, indéfini pour
  les autres. Ils se vendent, c'est tout, et l'interface le dit.
- **Le kite**, dixième du deben : sans objet tant que les prix restent entiers.
- **La vérification d'équilibrage en conditions réelles** — mener une partie sur
  une année complète au navigateur — reste due, comme à la fin de la Phase 4.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Le craft est-il instantané ? | **Non** : un ordre produit plusieurs pièces sur plusieurs quinzaines, à partir de plusieurs ressources |
| Comment commerce-t-on à distance ? | On **ouvre une route en envoyant une première caravane**, on annonce ses prix, puis les convois vont et viennent au rythme de la distance |
| Les rivaux commerciaux ? | **Reportés** après les enquêtes |
| Le stockage est-il limité ? | **Oui, plafonné** par le Grenier et l'Entrepôt — mais **rien ne se périme** |

---

### 5.7 Phase 6 — Faveur divine et événements  ✅

**Sources** : doc `07` (panthéon, paliers, offrandes, épidémies), doc `01`
(Temple), doc `03` (trait « Pieux », spécialité « Dévot »).

**Intention.** Le Temple existait, se construisait, montait en niveau — et ne
servait à rien. Un trait de candidat et une spécialité de chef étaient tirés,
affichés, et annonçaient eux-mêmes leur inertie. Cette phase leur donne leur
système d'accueil.

Elle apporte au jeu ce qui lui manquait après cinq phases d'économie : **une
variable que le joueur choisit d'alimenter sans contrepartie immédiate**. Tout
le reste se calcule — un Grenier rapporte tant, un convoi rapporte tant. Une
offrande est un pari.

| Lot | Contenu | |
|---|---|---|
| 6.0 | Le panthéon : huit divinités, leurs domaines, l'échelle de faveur | ✅ |
| 6.1 | Le Temple : offrir, et ce que le niveau autorise | ✅ |
| 6.2 | La négligence : décroissance vers le neutre, jamais en dessous | ✅ |
| 6.3 | Ce que la faveur change réellement, branché sur l'existant | ✅ |
| 6.4 | Les fêtes calendaires attestées | ✅ |
| 6.5 | Bénédictions et malédictions ponctuelles | ✅ |
| 6.6 | Les épidémies | ✅ |
| 6.7 | Le trait « Pieux » et la spécialité « Dévot » | ✅ |

**Les règles qui en sortent**

- **Le panthéon est du contenu, la faveur est de l'état.** Seule la clé d'un
  dieu et la valeur de sa faveur sont persistées, et **une ligne naît au premier
  geste, jamais au lancement**.
- **Le Temple est la seule limite de la dévotion**, et il en pose deux qui ne
  disent pas la même chose : combien de dieux on porte au-dessus du neutre (un
  par niveau), et jusqu'où leur faveur monte (`50 + 5 × niveau`). La première
  oblige un Temple modeste à choisir, la seconde fait du palier Dévoué une
  conquête — il demande un niveau 6.
- **On offre en deben ou en marchandise**, converties au cours du Marché et par
  aucun autre barème. C'est aussi le premier débouché du surplus que le plafond
  de stock refuse.
- **La négligence s'arrête au neutre.** Un dieu délaissé cesse de favoriser, il
  ne punit pas : une partie menée sans mettre les pieds au Temple finit comme
  elle a commencé.
- **La faveur n'ajoute jamais un multiplicateur à une chaîne qui en a déjà un.**
  Là où un facteur existe, elle déplace ce qui l'alimente ; là où il n'en existe
  aucun, elle agit directement.
- **Un dieu favorable ne pénalise jamais une production.** L'hostilité se paie
  par une crue moins généreuse ou par la fièvre, jamais par un malus de
  rendement.
- **Les fêtes sont datées par les sources**, jamais étalées pour l'équilibre, et
  leur supplément est **forfaitaire** : c'est le moment qui compte, pas la
  générosité.
- **Une malédiction retarde et coûte, elle n'efface pas** — et **jamais
  d'échec** : la famine reste la seule cause de défaite.
- **Une épidémie couche des bras, elle ne tue personne**, et passe par le canal
  existant du rendement d'effectif.
- **Un dieu sans emploi le dit.** Deux à la clôture de cette phase — Isis et
  Thot ; le lot 7.7 a depuis réveillé le second.

**Le partage des canaux, dieu par dieu**

| Dieu | Ce qu'il change | Par où |
|---|---|---|
| Hâpi | La crue de l'année | Infléchit le **tirage**, d'un cran — la récolte garde son unique modificateur |
| Ptah | Les chantiers | **S'ajoute** au facteur de saison, même unité |
| Osiris | Les champs terrestres | Raccourcit la **jachère** : la récolte revient plus tôt, elle n'est pas plus grosse |
| Amon-Rê | L'attractivité | Allège l'appel d'habitants, ajoute à ce que la renommée a ouvert |
| Sobek | Les trajets **par eau** | Jamais sous une quinzaine |
| Sekhmet | La fièvre | L'écarte, l'abrège, et se laisse fléchir pendant qu'elle dure |

**Pièges payés pendant la phase**

- **Une contradiction du doc 07**, tranchée : il annonce un départ « neutre à
  50 » tout en plaçant le palier Favorable à 50. À la lettre, huit bonus actifs
  pour qui n'a jamais mis les pieds au Temple. Départ à **40**.
- **Sekhmet promettait d'écarter une fièvre qui n'existait pas encore**, depuis
  le lot 6.0. Corrigée au 6.3, réveillée au 6.6.
- **Sobek promettait la pêche** : elle passe déjà par la qualité de direction du
  Port, c'eût été le multiplicateur de trop. Effet réduit à la navigation.
- **Une invention corrigée par la mesure** : j'avais écrit que les trois fêtes
  tombaient hors de la moisson. La Belle Fête de la Vallée est en pleine Chémou,
  là où les sources la placent. C'est l'affirmation qui a sauté, pas la date.
- **La branche « malédiction » n'aurait jamais tourné** : rien ne rendait un dieu
  hostile. Le piège d'`ajusterRenommee()` — une règle indexée sur une valeur que
  rien ne fait bouger — évité de justesse en lui donnant sa source, la famine.
- **Un arrondi vidait les épidémies de leur substance** : 20 % de quatre actifs
  fait zéro. La fièvre couche désormais au moins une paire de bras.
- **Mesurer Ptah sur un Grenier de deux cycles** ne montrait rien : les
  quinzaines sont entières. La leçon du lot 5.9, repayée une demi-fois.

**Ce que la phase laisse ouvert**

- **Les quêtes de temple** (doc 07 : +15 en réussite, −10 en échec) : elles
  supposent le système de quêtes de la campagne, Phase 8. Elles apporteront à
  l'hostilité une seconde source, aujourd'hui unique.
- **Les choix moraux** alignés ou contraires à un domaine : ils supposent la
  narration du fil rouge, Phase 7.
- **Isis et Thot** restaient offrables et inertes, et le disaient. Thot a
  trouvé son emploi au lot 7.7 ; il ne reste qu'Isis, pour la Phase 10.
- **Les divinités au-delà des huit** : le doc les laisse ouvertes, on s'en tient
  aux huit attestées.
- **Le barème d'offrande reste provisoire** — 30 deben pour amener un dieu au
  plafond d'un Temple de niveau 1, 80 pour atteindre Dévoué sous un niveau 6,
  contre ~39 deben la quinzaine de salaires. À reprendre au playtest.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Peut-on offrir **en ressources** autant qu'en deben ? | **Oui**, converties au cours du Marché |
| Le barème de 10 deben pour 5 points tient-il ? | **Oui pour le moment**, à corriger au playtest |
| Une malédiction peut-elle faire échouer une partie ? | **Non.** Elle retarde et elle coûte, elle ne termine jamais |

---

### 5.8 Phase 7 — Énigmes, enquêtes et fil rouge  ✅

**Sources** : doc `10` (énigmes, enquêtes, trois actes), `09` (lore et fil
rouge), `01` (Maison des scribes), `04` (rôles d'exploration), `08` (rivaux).

**Intention.** Le jeu savait faire prospérer une ville ; il ne savait rien
**raconter**. La Maison des scribes se construisait sans rien porter, les cases
d'événement de la carte étaient posées depuis le lot 3.2 et ne menaient nulle
part, et la commande du pharaon affichée au lancement n'avait pas de suite.
Cette phase branche les trois.

Elle porte aussi le seul objectif **pédagogique** explicite du projet : faire
découvrir l'écriture, l'iconographie et l'astronomie égyptiennes **en jouant**,
jamais par un encart documentaire.

| Lot | Contenu | |
|---|---|---|
| 7.0 | La clé de lecture : vingt signes, et la Maison des scribes qui les ouvre | ✅ |
| 7.1 | Le déchiffrage : remettre les sens en face des signes | ✅ |
| 7.2 | Devinettes, oracles, associations et astronomie | ✅ |
| 7.3 | Le dossier d'enquête : collecter des indices | ✅ |
| 7.4 | La déduction, et le droit de se tromper | ✅ |
| 7.5 | D'où viennent les indices : l'Éclaireur et l'Émissaire | ✅ |
| 7.6 | Le fil rouge en trois actes, sur la mission 1 | ✅ |
| 7.7 | Les deux Scribes, et Thot qui cesse d'attendre | ✅ |
| 7.8 | Les marchands rivaux, reportés de la Phase 5 | ✅ |

**Les règles qui en sortent**

- **Les signes sont vrais, les combinaisons sont du jeu.** Vingt hiéroglyphes de
  la liste de Gardiner, avec leur vrai code, leur vrai glyphe et une glose
  fidèle ; les inscriptions, elles, sont des **indices en rébus**, jamais des
  phrases d'égyptien. Le dire évite de faire croire au joueur qu'il apprend à
  lire.
- **Quatre signes sont connus d'emblée**, pour que la première énigme soit
  tentable avant d'avoir rien bâti — sans quoi le tutoriel de l'acte I
  n'ouvrirait sur rien.
- **La clé s'enrichit par deux voies, une seule est persistée** : ce que le
  niveau ouvre se calcule, ce qu'une énigme apprend se stocke.
- **Une énigme ne punit jamais** : se tromper sur une inscription ne coûte ni
  ressource ni cycle. Une énigme qui punit est une énigme qu'on cesse de
  tenter.
- **Une question à choix multiple ne se retente pas**, en revanche : avec
  quatre propositions et un droit de reprise, on essaie tout et il ne reste
  qu'un formulaire.
- **L'explication tombe qu'on ait raison ou tort.** Le vrai gain d'une énigme
  est ce qu'elle apprend ; la récompense passe.
- **Une enquête se démêle, elle ne se compte pas** : seuls les indices
  concordants rapprochent de la conclusion, et **le joueur ne sait jamais**
  lequel est une fausse piste.
- **Une principale se rejoue, une secondaire se perd** (décision de la
  joueuse) : le temps est la seule monnaie d'une erreur.
- **Un éclaireur va vers l'inconnu, un émissaire va vers les gens.**
- **L'acte d'un fil rouge se déduit, il ne se stocke pas.**
- **Un rival rogne, il ne ferme rien**, et c'est la renommée qui l'attire.

**Ce qui a trouvé son emploi**

| Posé depuis | Ce qui dormait | Réveillé par |
|---|---|---|
| Lot 3.2 | Le contenu `Evenement` d'une case | La fouille (7.3) |
| Lot 3.4 | Le rôle d'Émissaire, qui doublait l'éclaireur en trois fois plus cher | Les témoignages (7.5) |
| Lot 4.2 | Les spécialités Déchiffreur et Oraculaire | 7.7 |
| Phase 2 | La Maison des scribes, l'Auberge | La clé (7.0), les devinettes (7.2) |
| Phase 6 | Thot, dieu offrable et inerte | 7.7 |

**Pièges payés pendant la phase**

- **Deux contradictions de documents**, tranchées comme celle du doc 07 au lot
  6.0 : le doc 10 annonce « 4 signes aux niveaux 1-2, puis 2 par niveau,
  jusqu'à 20 au niveau 8 » — les trois nombres ne s'accordent pas.
- **Fouiller sans Maison des scribes** rendait un indice qu'aucun dossier ne
  recueillait. C'est le bâtiment qui conduit les enquêtes (doc 01) : sans lui,
  on ne fouille pas.
- **La réponse se lisait dans la source de la page** : jetons du déchiffrage,
  propositions d'énigme et conclusions d'enquête sont mélangés **au rendu**.
- **Une interaction bâtie sur le seul `dragstart`** est inutilisable au clavier,
  et aucun test fonctionnel ne le signalerait. Le clavier d'abord, la souris
  par-dessus.

**Ce que la phase laisse ouvert**

- **Les dix fils rouges** : Phase 8. Un seul est écrit, celui de la mission 1 —
  écrire les dix avant d'avoir joué le premier reviendrait à écrire dix fois la
  même erreur.
- **La répartition des énigmes secondaires par mission** (5 à 8, doc 10) :
  Phase 8. Le catalogue est commun et se filtre par les bâtiments dressés.
- **Le Chef d'expédition**, dernier rôle d'exploration sans emploi : Phase 10.
- **Le débauchage d'un contact commercial** par un rival (doc 08) : il suppose
  le carnet d'adresses de la Phase 9.
- **Isis** et le trait « Bagarreur », tous deux pour la Phase 10.

**Points tranchés avec la joueuse**

| Question | Décision |
|---|---|
| Le déchiffrage se fait-il par glisser-déposer ? | **Oui** — la manipulation des signes fait partie de ce qu'on apprend |
| Les énigmes secondaires sont-elles obligatoires ? | **Toutes les missions en portent, aucune n'est requise** pour finir la mission |
| Une enquête peut-elle échouer définitivement ? | **Une secondaire, oui. Une principale, non** — elle se rejoue jusqu'à être résolue |

---

### 5.9 Phase 8 — La campagne : dix missions, un pharaon chacune  ✅

**Sources** : doc `09` (lore, objectifs, quêtes de chantier, réussite
partielle), `11` (les dix régions et leur difficulté), `10` (les trois actes).

**Intention.** Dix missions existent déjà — leur région, leur ville, leur
pharaon, leur difficulté et leur carte. Ce qui manque est **ce qui en fait une
campagne** : on ne peut ni gagner une mission, ni passer à la suivante. Une
partie s'ouvre sur la commande d'un roi et n'a aucune façon de s'achever
autrement que par la famine.

À la fin de la phase, on doit pouvoir raconter : *« Avaris est repeuplée, la
route rouverte, et la stèle dressée. Ahmôsis me reconnaît une réussite aux
trois quarts — je n'ai pas atteint le volume d'échanges qu'il attendait. Il me
lègue quelque chose pour la suite, et Thoutmôsis Ier m'envoie à Saï. »*

| Lot | Contenu | |
|---|---|---|
| 8.0 | Les objectifs d'une mission, visibles dès le premier jour | ✅ |
| 8.1 | Mesurer l'avancement : ce que le jeu sait déjà compter, et ce qu'il ignore | ✅ |
| 8.2 | Achever une mission : réussite totale, réussite partielle | ✅ |
| 8.3 | Les quêtes de chantier du pharaon | ✅ |
| 8.4 | Les dix fils rouges | ✅ |
| 8.5 | Enchaîner : de la mission N à la mission N+1 | ✅ |
| 8.6 | Le legs du pharaon | ✅ |

#### 8.0 — Les objectifs, visibles dès le premier jour  ✅

Le doc 09 tranche deux choses, et elles vont ensemble : chaque mission combine
**un objectif narratif obligatoire** — le fil rouge résolu — et **deux à trois
objectifs quantitatifs** tirés d'un pool de six (richesse, population,
commerce, infrastructure, renommée, ressource précise).

**Ils sont affichés dès le début**, façon liste de quêtes : « la transparence
évite au joueur de découvrir tardivement des conditions qu'il n'a pas pu
anticiper ». Ce n'est pas un jeu de mystère sur ses propres objectifs.

Les seuils croissent avec la difficulté de la région (doc 09) : richesse
`200 + 50 × difficulté`, population `20 + 10 × difficulté`, commerce
`500 + 100 × difficulté`, ressource `100 + 20 × difficulté`, infrastructure
niveau `4 + difficulté / 3`, renommée palier `2 + difficulté / 4`.

**Les seuils ont été recalibrés sur l'économie du jeu** (décision de la
joueuse), pas recopiés : le document les a chiffrés avant les Phases 4 et 5, et
comptait encore en or comme si c'était la monnaie. Les documents du Drive
seront repris ensuite.

| Type | Doc 09 | Retenu | Étalon |
|---|---|---|---|
| Richesse | `200 + 50 × d` **en or** | `250 + 75 × d` **en deben** | La ville d'exemple du lot 4.6 dégage une trentaine de deben nets par quinzaine |
| Population | `20 + 10 × d` travailleurs | `12 + 4 × d` habitants | Deux cents parties de vingt ans : une ville à Quartier 1 monte à treize |
| Commerce | `500 + 100 × d` | `400 + 120 × d` | Un gros contrat vaut 40 deben |
| Ressource | `100 + 20 × d` | `60 + 15 × d` | Une extraction rend une vingtaine d'unités par quinzaine, au plein rendement |
| Infrastructure | `4 + d / 3` | inchangé | Seul seuil du document qui tienne : la borne régionale (`5 + d`) est plus généreuse à toutes les missions |
| Renommée | palier `2 + d / 4` | inchangé | Le Marché donne un point par gros contrat |

**Une contradiction entre documents, tranchée par l'histoire** : le doc 09
demande de l'or à Éléphantine, le doc 08 place les mines d'or ailleurs. Les
deux ont raison — Éléphantine était un **poste douanier**, l'or de Nubie y
transitait sans qu'on l'y extraie. L'objectif tient donc, mais par le commerce.
L'invariant testé n'est pas « la ressource est sur le terrain » mais **« un
chemin y mène »** : le terrain, ou une route que la région ouvre.

**Deux mesures n'existent pas encore** — la valeur échangée et la ressource
rapportée. L'écran l'écrit (« mesure à venir ») plutôt que d'afficher un zéro
qui ne bougerait jamais, et un objectif non mesuré n'est **jamais** compté
comme atteint. Un test verrouille la liste : elle doit rétrécir au lot 8.1,
jamais s'allonger.

**Un défaut d'ergonomie attrapé au passage** : le contrôleur d'onglets apparie
onglets et panneaux **par rang**. Ajouter le panneau « Mission » ailleurs que
dans l'ordre de son onglet décalait tout ce qui suit — on cliquait sur Mission
et l'on ouvrait les Bâtiments. C'est pour cela que le test compare les deux
listes **dans l'ordre**.

#### 8.1 — Mesurer l'avancement  ✅

Quatre des six mesures existent déjà : la trésorerie, la population, la
renommée et le niveau d'un bâtiment se lisent sans rien ajouter. **Deux
manquent** :

- **La valeur totale échangée** (Marché + convois) : rien ne la cumule
  aujourd'hui. C'est un compteur à ajouter sur la partie, alimenté aux deux
  endroits où des deben changent de main.
- **La quantité d'une ressource « récoltée/rapportée »** : à distinguer du
  stock courant, qui monte et descend. Un objectif atteint puis dépensé doit
  rester atteint — sans quoi on serait puni d'avoir joué.

**Les deux comptes se tiennent au seul passage obligé.** Les ressources
rapportées se cumulent dans `City::crediterRessources()` — même raison que le
plafond de réserve : le poser ailleurs obligerait chaque nouvelle source à s'en
souvenir, et l'une d'elles finirait par l'oublier. La valeur échangée se compte
au Marché et **au retour d'un convoi**, jamais à son départ : au départ, la
marchandise est engagée, elle n'est pas encore échangée.

**Le piège du lot 4.8, évité autrement qu'annoncé.** Le lot 8.0 portait un
drapeau `seMesureDeja()` disant quels types n'étaient pas encore mesurés ; une
fois les six mesures en place, ce drapeau devenait un mensonge que PHPStan a
signalé (une branche `null` devenue inatteignable). Il a été **remplacé par un
test qui fait bouger chaque mesure, une par une** — un drapeau déclaratif
n'aurait de toute façon pas empêché un objectif indexé sur une valeur inerte ;
ce test-là, si, et tout type ajouté au pool y tombera tant que rien ne le fait
avancer.

#### 8.2 — Achever une mission  ✅

**Pas de « game over » brutal** (doc 09), et la réussite partielle est tranchée
par le document : la mission **se termine quand même** si le fil rouge est
résolu, même objectifs quantitatifs non atteints. Le score vaut
`objectifs atteints / objectifs totaux`, affiché comme un résultat partiel et
non comme un échec.

Conséquence sur `StatutDePartie`, qui ne connaît aujourd'hui que `EnCours` et
`Echouee` : il lui faut un état **achevé**, avec son score. Une partie achevée
reste consultable, comme une partie échouée — c'est le principe du doc 00.

**La couture est traitée dans les deux sens** : `GameSave::achever()` ne fait
rien sur une partie déjà close, et `echouer()` ne fait rien sur une partie
accomplie. La famine qui rattraperait une mission accomplie n'a plus d'objet —
la volonté du roi est accomplie, et c'est ce qui compte.

**Le score est celui du moment où le fil rouge se résout**, et ne se recalcule
jamais : une ville qui s'enrichirait après coup ne doit pas voir sa réussite
monter, ni une ville qui se viderait la voir se dégrader. La mission est finie.

#### 8.3 — Les quêtes de chantier  ✅

Le pharaon commanditaire réclame ponctuellement des ressources pour un chantier
qu'il a **réellement fait bâtir** : la pyramide d'Ahmôsis à Abydos, les
obélisques de Thoutmôsis Ier, Deir el-Bahari pour Hatchepsout, l'Akh-menou de
Thoutmôsis III, les talatat d'Akhenaton, Médinet Habou pour Ramsès III.

Tous les quatre cycles, 20 à 50 unités, `+5` de renommée et `+10` de faveur
envers la divinité du monument. **Refuser coûte 2 points de renommée et rien
d'autre** — le joueur reste libre de sa stratégie.

Chaque quête porte **deux ou trois phrases pédagogiques** sur le monument
réel : c'est la même exigence qu'aux lots 7.0 et 7.2, et le même garde-fou —
ce qu'on apprend doit être vrai. Un test vérifie que **chaque pharaon de la
campagne a bien bâti quelque chose**.

**Il réclame ce que la région porte** : envoyer chercher au loin ce qu'on a
sous les pieds n'aurait pas de sens, et rendrait la quête impossible dans la
moitié des missions.

**Laisser filer le délai revient à refuser** — sans cela, attendre serait
toujours meilleur que décliner, et le délai ne voudrait rien dire.

**Le chantier d'Akhenaton ne rapporte aucune faveur**, et c'est
historiquement juste : il n'honorait qu'Aton, absent du panthéon du jeu.

#### 8.4 — Les dix fils rouges  ✅

La mission 1 en a un (lot 7.6). Les neuf autres suivent la même structure en
trois actes, chacun avec son obstacle local cohérent avec son pharaon et son
type de mission : **fonder** (Akhetaton, Malkata, Saï), **restaurer ou
développer** (Avaris, Shedet, Serabit, Mersa Gaouasis, Éléphantine),
**sécuriser** (Megiddo).

**C'est le plus gros morceau d'écriture de la phase.** Chacun porte une
tablette d'ouverture, une enquête de quatre indices, quatre conclusions
possibles et un dénouement.

| # | Ville | L'obstacle |
|---|---|---|
| 1 | Avaris | Le passage coupé |
| 2 | Saï | Les bornes déplacées |
| 3 | Mersa Gaouasis | La flotte qui ne part pas |
| 4 | Megiddo | La porte laissée ouverte |
| 5 | Malkata | Le chantier qui n'avance pas |
| 6 | Akhetaton | L'eau qui manque |
| 7 | Éléphantine | L'or qui s'évapore |
| 8 | Shedet | Le canal envasé |
| 9 | Ouadi Hammamat | Les hommes qui désertent |
| 10 | Serabit el-Khadim | La galerie effondrée |

**Une contrainte que le jeu impose à l'écriture** : la clé de lecture repart de
quatre signes à chaque mission. Les dix tablettes d'ouverture ne peuvent donc
employer que ceux-là — eau, homme, maison, marche —, ce qui leur donne leur ton
lapidaire. Les stèles finales, elles, en comptent cinq : à la fin d'une
mission, les scribes ont appris. Un test le vérifie sur les dix.

**On ne ramasse que les indices de son enquête** : trouver au Delta une borne
déplacée du Sinaï remplirait un dossier qu'on n'ouvrirait jamais. De même,
la tablette d'une autre mission ne se lit pas ici — elle ne raconte pas cette
histoire-là.

**Chaque fil doit pouvoir se dénouer**, et c'est testé sur les dix : assez
d'indices concordants pour conclure, au moins un qui ne l'est pas, et au moins
un qui se trouve sur le terrain — sinon l'enquête n'aurait aucune porte
d'entrée. C'est l'exercice d'`OuvertureDePartieTest`, appliqué aux dix
histoires plutôt qu'aux dix cartes.

#### 8.5 — Enchaîner les missions  ✅

L'ordre est imposé, de 1 à 10 (décision déjà actée). Ce qui manque est la
**transition** : une mission achevée ouvre la suivante, et le joueur la lance
depuis son compte. Rien ne se transporte d'une ville à l'autre — sauf le legs.

**La mission 9 suit les mêmes règles** (décision de la joueuse) : on ne change
pas de mécanisme pour un camp minier. Le Ouadi Hammamat est déjà une région
sans Nil ni agriculture, ce qui suffit à le rendre différent sans qu'on lui
écrive un jeu à part.

**Le contrôle vit dans le lanceur**, pas seulement dans le formulaire : un POST
forgé n'ouvre pas le Sinaï à qui sort du Delta. Et **une réussite partielle
ouvre la suite** comme une réussite pleine — ce serait la punir deux fois que
de bloquer la campagne dessus.

**On peut rejouer une mission déjà faite** : rien ne l'interdit, et cela vaut
mieux que d'enfermer un joueur qui voudrait refaire Avaris autrement.

#### 8.6 — Le legs du pharaon  ✅

À la réussite, totale ou partielle : un bonus de renommée proportionnel au
score, et un **objet légué** — un petit avantage de départ pour la mission
suivante. C'est le premier fil entre deux parties, et la porte d'entrée de la
Phase 9 (héritage familial, doc 13).

**Un vrai avantage, pas un ornement** (décision de la joueuse) : 120 deben pour
une réussite pleine, et jusqu'à quatre points de renommée — le pharaon parle de
vous à son successeur.

**Mais modeste, et pour une raison précise** : il s'ajoute à la dotation
royale, il ne la remplace pas. Une première mission et une cinquième démarrent
donc sur le même socle, ce qui garde chaque mission jouable seule — un legs qui
changerait l'équilibre rendrait la suivante dépendante de la précédente, et
**punirait une réussite partielle deux fois**, une fois par le score et une
fois par la difficulté de la suite.

**Il est stocké sur la partie**, pas recalculé : celle qui l'a mérité peut être
abandonnée ensuite, et ce qui a été donné reste donné.

#### Hors périmètre, explicitement

- **L'héritage familial complet** et le carnet de contacts : Phase 9.
- **L'héritage commercial inter-missions** (doc 12), reporté de la Phase 5 :
  il suppose l'enchaînement, donc il devient possible — mais il relève de la
  Phase 9, avec le reste de ce qui se transmet.
- **Le mode Aventure** : Phase 11.

#### Tranché en cours de route

| Question | Décision |
|---|---|
| La mission 9 (camp minier) suit-elle les mêmes règles ? | **Oui, les mêmes.** Un système allégé aurait dédoublé toutes les mécaniques pour une seule mission. C'est la géographie qui la distingue : ni fleuve ni mer, donc ni crue, ni Hâpi, ni Sobek, ni route par eau |
| Le legs doit-il être cosmétique ou donner un vrai avantage ? | **Un vrai avantage, mais qui s'ajoute à la dotation sans la remplacer** : une première mission et une cinquième démarrent sur le même socle, ce qui garde chaque mission jouable seule |
| Les objectifs quantitatifs se recalibrent-ils sur l'économie mesurée ? | **Oui**, et le tableau ci-dessus en garde la trace : les seuils du doc 09 datent d'avant les Phases 4 et 5, et comptaient encore en or |

#### Définition de « fini »

Parcours de bout en bout : lancer la mission 1 → voir ses objectifs dès le
premier écran → les voir avancer → résoudre le fil rouge → recevoir la
reconnaissance du pharaon, totale ou partielle → recevoir un legs → lancer la
mission 2 depuis son compte.

Tests sur les invariants : un objectif atteint le reste, une mission achevée ne
bascule jamais en échec, refuser une quête de chantier ne bloque rien, et
**aucun seuil n'est inatteignable** — un test qui vérifie, région par région,
que les objectifs tiennent dans ce que la carte et l'économie permettent. C'est
l'exercice d'`OuvertureDePartieTest`, appliqué à la fin d'une mission plutôt
qu'à son début.

---

### 5.10 Phase 8 bis — Finition : cohérence et lisibilité  ✅

Un lot sans document de conception attitré : ce que le jeu de bout en bout a
révélé une fois jouable. Il ne pose aucune mécanique neuve, il rend jouable et
cohérent ce qui existait.

| Lot | Contenu | |
|---|---|---|
| F.1 | Rouvrir un filon épuisé — le rôle de Prospecteur | ✅ |
| F.2 | Le Marché borné, distinct du commerce par caravanes | ✅ |
| F.3 | Un onglet par bâtiment, la Résidence en point de chute | ✅ |
| F.4 | Le tableau de bord de la ville, et la renommée enfin visible | ✅ |
| F.5 | Fermer une carrière tarie, dire ce qui produit | ✅ |
| F.6 | L'état de la ville lisible depuis la carte | ✅ |
| F.7 | Rien du Nil là où il n'y a pas de Nil | ✅ |

#### F.1 — Le filon épuisé n'est plus une impasse  ✅

La dernière unité extraite d'une carrière fermait définitivement la production
de ce matériau. Sur une petite carte, épuiser l'unique gisement d'argile figeait
la partie sans qu'aucun geste ne puisse y remédier.

Le **Prospecteur** est ce geste : un rôle d'exploration de plus, envoyé sur une
case déjà reconnue. Retrouver une veine tarie est **certain** — les galeries
sont creusées, la géologie connue ; l'épuisement doit coûter du temps et de
l'argent, jamais fermer une région. Chercher du neuf reste un pari : 45 % sur
une case déjà minéralisée, 20 % sur une case vierge, que le terrain infléchit
ensuite.

Le filon tari **se ferme de lui-même** et rend ses bras. Il les retenait, et le
passage de cycle répétait son épuisement à chaque quinzaine, indéfiniment.

#### F.2 — À qui vend-on ?  ✅

Le Marché et l'Entrepôt faisaient doublon : les deux écoulaient du surplus,
sans que rien ne les distingue. Le Marché vend désormais **aux gens de la ville
et aux passants** — cours de base, encaissement immédiat, mais un débouché
plafonné par quinzaine (`population × niveau × 4` deben). Les routes gardent
les vrais volumes, à 150 ou 200 % du cours, contre le délai d'un convoi.

#### F.3 et F.4 — Un onglet, un bâtiment  ✅

L'écran de ville se découpait par thème — Direction, Commerce, Ateliers — et il
fallait deviner dans quel panneau ranger quoi. Il porte un **onglet par bâtiment
dressé**, chacun avec sa direction, ses ouvrages, ses routes, ses énigmes. La
**Résidence familiale** recueille ce qui n'appartient à aucun bâtiment : elle
est présente dès le premier jour, et l'y mettre est la seule façon que ces
écrans restent atteignables pour une ville qui n'a rien dressé.

Elle porte un **tableau de bord** — habitants, travail, réserves, bourse et
renom — et les alertes, dites une fois. Deux valeurs y apparaissent qui
n'existaient nulle part : les postes ouverts par les chefs, et l'autonomie en
vivres.

#### F.7 — Rien du Nil là où il n'y a pas de Nil  ✅

`GeographieDeRegion::connaitLaCrue()` existait depuis le début et n'était
appelée de nulle part. Quatre missions se jouent loin du fleuve, et le jeu y
annonçait une crue chaque année, accélérait les chantiers d'Akhèt sous une
inondation qui n'avait pas lieu, et laissait offrir à Hâpi dans un désert.

L'audit a remonté deux défauts que personne ne cherchait :

- **Le Fayoum n'avait aucune route commerciale atteignable.** Son unique
  partenaire est fluvial, donc suspendu à un Port, donc à un point d'eau que sa
  géographie ne produisait pas. Le Bahr Youssef, branche du Nil, alimente
  pourtant le lac Moeris sur la rive duquel Shedet est bâtie : la région a de
  l'eau, et Sobek — dont Shedet est le siège du culte — y était inerte.
- **Le bilan démographique tombait dans le bloc de la crue**, et l'y laisser
  aurait figé la population de quatre missions.

Un test de cohérence garde désormais l'invariant région par région : aucune
n'est murée, une route d'eau suppose de l'eau, et un dieu déclaré sans domaine
l'est pour une raison vérifiable.

---
