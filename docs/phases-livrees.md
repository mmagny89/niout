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

---

### 5.11 Phase 8 ter — L'écriture : l'alphabet des scribes et les stèles  ✅

Née d'une **relecture des documents 09 et 10** après que la joueuse les eut
repris sur le Drive. La confrontation au code a remonté deux ajouts de fond et
trois écarts plus petits ; le premier ajout est livré.

| Lot | Contenu | |
|---|---|---|
| A.1 | La police hiéroglyphique embarquée, sous-ensemblée | ✅ |
| A.2 | Les vingt-quatre signes unilitères et leur déblocage | ✅ |
| A.3 | La leçon fondatrice : écrire « Niout » | ✅ |
| A.4 | La transcription du nom de famille | ✅ |
| A.5 | Les cartouches royaux à l'introduction d'une mission | ✅ |
| B | Les stèles historiques par mission | ✅ |
| C | La mission 9 est de type « Exploiter » | ✅ |
| D | Un corpus commun d'énigmes, pas un corpus par mission | ✅ |

#### A — L'alphabet des scribes  ✅

Le doc 10 a gagné une **piste pédagogique entièrement neuve**, explicitement
distincte de la clé de lecture : les vingt-quatre signes unilitères, ceux qui
notent un son. Le code écrivait l'inverse en toutes lettres — « on ne fait pas
apprendre l'égyptien, on fait lire des rébus ».

**La question du rendu, tranchée par la mesure.** Le document laissait ouvert
le choix entre texte Unicode et planche de sprites. Vérification faite au
navigateur sur les vingt-quatre : ils s'affichent, mais **aucune police
hiéroglyphique n'est installée sur la machine de développement** — le rendu
venait d'un repli qu'on ne contrôle pas, et six signes y prenaient une forme
moins juste. Un joueur sous Windows ou Android n'aurait vu que des carrés.
D'où : du texte, et **la police embarquée**, self-hébergée comme les deux
autres familles. Sous-ensemblée aux seuls signes déclarés, elle pèse 12 Ko au
lieu de 978.

**Ce qui est livré** : les vingt-quatre signes avec leur code de Gardiner, leur
translittération et leur son ; leur ouverture à trois par niveau de Maison des
scribes, qui tombe juste sur vingt-quatre au niveau 8 ; la leçon fondatrice qui
écrit *n · i · w · t* ; la transcription du nom de famille à la manière des
cartouches de musée ; et les neuf cartouches royaux à l'introduction de leur
mission.

**Deux cartouches ont demandé deux sources concordantes.** Les noms de trône
composés d'Akhenaton et de Ramsès IV portent, dans la notation source, des
opérateurs de disposition dont l'ordre de lecture n'est pas évident : ils
n'affichaient rien tant qu'il restait un doute, la règle du projet étant nette
— jamais un signe sans son code ni son sens attesté. Deux détails d'histoire en
sont sortis : Ramsès IV a changé de nom de trône en cours de règne, et ses deux
missions se jouant à l'an 3, c'est le second qui est montré ; Akhenaton porte
deux fois le disque solaire, son nom disant Rê deux fois.

#### Un défaut payé, et sa parade

La question de la joueuse — « `N35` a deux sens, est-ce véridique ? » — a
révélé une erreur ancienne. **Non** : une seule ondulation est le phonogramme
*n*, l'eau s'écrit avec trois, `N35A`. La clé portait le code de l'un tout en
décrivant l'autre, et enseignait donc un signe faux dans un jeu dont c'est
l'objet d'enseigner les vrais.

La parade est mécanique et exacte : **Unicode nomme chaque caractère par son
code de Gardiner**, et un test confronte les deux pour les soixante-dix signes
déclarés (`CodesDeGardinerTest`). Aucune table recopiée, donc rien qui puisse
diverger.

Une fois l'eau corrigée, le recouvrement entre les deux tables tombe à **trois
dessins** — le roseau, le pain, la bouche —, et ceux-là sont de vrais cas
d'écriture mixte. Les deux tables se **relient** donc l'une à l'autre plutôt que
de laisser croire à une redite.

#### Ce que la conception a tranché, et pourquoi

**Deux pistes qui ne se mélangent jamais.** La clé de lecture porte des
logogrammes — un signe, une chose — et sert à lire les inscriptions du fil
rouge ; l'alphabet porte des phonogrammes — un signe, un son — et sert à
écrire. Trois dessins leur sont communs, et le premier réflexe eût été de les
dédupliquer : c'eût été enseigner le contraire de ce que le document veut faire
comprendre. Les deux tables se **relient** donc, et l'écran dit le double
emploi.

**L'alphabet ne se persiste pas.** La clé stocke ce qu'une énigme apprend ;
l'alphabet ne s'ouvre que par le niveau du bâtiment, donc il se calcule. Une
colonne n'aurait fait que dupliquer l'état de la Maison des scribes. Les quatre
signes de Niout sont en revanche **connus d'emblée**, comme les quatre de la
clé et pour la même raison : la leçon fondatrice devait être tentable dès la
première quinzaine, comme le doc 10 le demande.

**La leçon fondatrice se retente.** La règle « on ne répond qu'une fois » vaut
contre quatre propositions qu'on épuiserait en essayant tout ; remettre quatre
signes dans l'ordre a vingt-quatre arrangements, et c'est un exercice — on y
apprend en recommençant. La récompense, elle, ne tombe qu'une fois, sans quoi
l'exercice deviendrait une rente. Elle ne touche pas au fil rouge : les deux
pistes restent séparées jusqu'au bout.

**La transcription d'un nom est la convention des musées, pas de
l'égyptologie**, et l'écran le dit. Les voyelles reçoivent les semi-voyelles —
s'en tenir aux consonnes rendrait le résultat illisible — et le *l* emprunte le
*r* que l'égyptien ne distinguait pas. Aucun signe n'est inventé pour boucher un
trou. Elle paraît **entière** dès le bâtiment dressé, les signes non appris en
creux : mesuré, il faut la Maison des scribes au niveau 6 ou 7 pour qu'un nom
courant soit entièrement écrivable, et la cacher jusque-là l'aurait rendue
invisible presque toute la partie.

**Les cartouches sourcés, ou pas de cartouche.** Neuf pharaons commanditent les
dix missions ; sept ont leur nom de trône établi. Les deux autres — Akhenaton
et Ramsès IV — portent dans la notation source des opérateurs de disposition
dont l'ordre de lecture ne s'établit pas sûrement, et n'affichent donc rien. La
conversion des codes de Gardiner vers Unicode, elle, est exacte : elle passe par
le nom que le caractère porte dans Unicode, jamais par une table recopiée.

#### B — Les stèles historiques  ✅

Le doc 09 a gagné une table des vraies stèles par commanditaire, que les
déchiffrages devaient rappeler plutôt que d'inventer de toutes pièces. Les dix
missions ont désormais la leur, nommée et située — Karnak, Tombos, Deir
el-Bahari, les falaises d'Amarna, l'Ouadi Mia.

Les cinq que le document ne donnait que pour « bien établies » ont été
**vérifiées avant d'être nommées**, comme il le demandait lui-même.

**La stèle n'est pas l'inscription qu'on déchiffre**, et l'écran le dit : les
dalles du jeu restent des rébus — signes vrais, combinaisons inventées —, et la
pierre est ce à quoi elles font écho. Ce qui s'affiche en est un **résumé**,
jamais une citation : la contrainte est de droits autant que d'honnêteté, et un
test refuse tout texte entre guillemets. Un **papyrus n'est pas une stèle**, et
le grand papyrus Harris n'est pas appelé ainsi.

#### C et D — deux contradictions du document, tranchées  ✅

**La mission 9 est de type « Exploiter ».** Le doc 09 se contredit : sa section
n'annonce que trois types, son tableau en donne un quatrième à l'Ouadi
Hammamat. Le tableau, plus précis, l'emporte — un camp minier temporaire n'est
ni une fondation ni un développement. Le type ne sert qu'à l'affichage : aucune
règle ne change.

**Le corpus d'énigmes reste commun.** Le doc 10 les chiffre à cinq ou huit par
mission ; le jeu en porte onze, valables partout. C'est le **lieu** où on les
entend qui les situe — l'oracle au Temple, les devinettes de voyageurs à
l'Auberge —, pas la région. Conséquence assumée : une partie qui enchaîne les
dix missions les épuise vers la deuxième. Écrire cinquante à quatre-vingts
énigmes sourcées est un projet de contenu à part, que l'enum accueillera sans
rien changer.

#### Ce qui a coûté

- **La police est self-hébergée**, comme les deux autres familles du jeu : la
  conception prévoyait un chargement depuis un CDN, ce que le projet interdit —
  un appel runtime transmettrait l'IP du visiteur à un tiers. Elle est en outre
  sous-ensemblée aux seuls signes déclarés : 12 Ko au lieu de 978.
- **Le meilleur exemple de la conception était faux.** Elle donnait `N35` —
  « l'eau » dans la clé, le son *n* dans l'alphabet — comme cas d'école de
  l'écriture mixte. C'était l'erreur du code repris sans vérification. L'exemple
  juste était la bouche depuis le début.

---

### 5.12 Phase 9 — Renommée, héritage et succession familiale  ✅

Le doc 13 était le seul document de conception dont le jeu appliquait **la
moitié sans le savoir** : les cinq paliers de renommée existaient, aux plages
exactes du document, et six mécanismes la faisaient bouger. Ce qui manquait
n'était pas la jauge, c'était **ce qu'elle traverse** — une mission, une
génération, une campagne.

| Lot | Contenu | |
|---|---|---|
| 9.0 | Les arbitrages tranchés, et la forme qu'ils donnent à la phase | ✅ |
| 9.1 | La renommée devient un acquis de lignée, qui traverse la campagne | ✅ |
| 9.2 | Les deux sources manquantes : énigme résolue, enquête résolue | ✅ |
| 9.3 | La renommée infléchit les prix, à l'achat comme à la vente | ✅ |
| 9.4 | Le carnet de contacts, et l'héritage des routes du doc 12 | ✅ |
| 9.5 | Le bonus de départ par missions accomplies | ✅ |
| 9.6 | La succession : générations, héritiers et leur trait | ↦ Phase 11 |

#### 9.0 — Trancher avant d'écrire

Cinq questions gouvernaient la forme de tous les lots suivants, et les
découvrir en codant aurait coûté une reprise. Elles ont donc été posées avant
la première ligne. Ce qui en est sorti :

- **la renommée cumulée facilite les dernières missions, et on l'assume.**
  C'est ce que le document veut. L'invariant « chaque mission jouable seule »
  se relit : aucune mission ne devient *injouable* sans héritage, mais la
  dixième se joue avantagée — c'est la récompense de la campagne ;
- **un plafond unique sur la remise totale**, jamais un par source : trois
  plafonds séparés se cumulent et n'en plafonnent aucun ;
- **un contact fait une remise, il ne débloque rien.** Un raccourci de
  progression aurait obligé à recalibrer les missions tardives ;
- **l'enquête résolue vaut +2**, entre le +3 du document et le +1 du code ;
- **la succession part en Phase 11.** Une génération dure 60 cycles ± 20 et une
  mission de campagne les dépasse rarement : le lot ne se déclencherait presque
  jamais.

#### La distinction qui a débloqué toute la phase

Le cadrage portait deux exigences qui semblaient s'exclure : « une seule jauge
de renommée par famille, persistante d'une mission à l'autre » et « deux
parties menées de front ne se volent pas leur renommée ».

Elles se concilient en séparant deux choses que le mot « renommée »
confondait :

- l'**acquis**, sur `Lignee` — un par joueur, qui ne descend jamais, et que
  chaque nouvelle partie reçoit au lancement ;
- la **jauge de la mission**, sur `Family` — qui bouge librement, à la baisse
  comprise, et reste propre à sa partie.

Un seul point du jeu écrit dans la lignée : `AchevementDeMission`, à la clôture
d'une mission de campagne, et il ne fait que la relever. C'est la même
discipline que le plancher du neutre de la négligence divine.

**`Legs` a perdu son volet renommée.** Il en donnait quatre points au plus,
depuis zéro, d'après la seule mission précédente. Avec l'acquis transmis en
entier, les deux auraient compté deux fois la même réussite.

#### Ce que le facteur existant a épargné

Le lot 9.3 s'annonçait comme le plus délicat : la discipline du lot 6.3 veut
qu'un nouveau modificateur **entre dans un facteur existant** plutôt que d'en
ajouter un troisième. La pièce était déjà là — `$avantage`, ce que le
Négociateur arrache aux partenaires, élargit la fourchette des deux côtés en
points de pourcentage entiers. La renommée y entre, le carnet de contacts aussi.

Au Marché, elle s'**ajoute au coefficient** de qualité de direction, qui reste
appliqué en une multiplication et une division : deux divisions entières
enchaînées perdraient des deben à chaque étape, d'une façon que personne ne
saurait plus expliquer six mois après.

**Le plafond vaut quarante, et sa valeur est arithmétique.** Le plancher d'un
partenaire vaut 150 % du cours local moins l'avantage : à cinquante, il rejoint
le cours, et importer ne coûterait plus rien de plus que produire sur place — la
distance, les routes et les convois cesseraient de peser. Conséquence assumée :
un Négociateur (25) chez une famille illustre (20) est rogné, le plafond portant
sur la somme.

#### Trois plafonds, trois raisons différentes

La phase en a posé trois, et il vaut la peine de ne pas les confondre :

| Plafond | Où | Pourquoi cette valeur |
|---|---|---|
| 8 points par mission | `Family::RENOMMEE_MAX_DES_AFFAIRES` | Dix missions résolues en entier dépasseraient les cent points de l'échelle : la jauge ne mesurerait plus une réputation mais l'assiduité à deux mini-jeux |
| 40 points d'avantage | `AvantageDeNegoce::PLAFOND_TOTAL` | Au-delà, le plancher d'achat rejoint le cours local et la distance cesse de peser |
| La dotation elle-même | `BonusDeDepart` | Neuf missions vaudraient 180 deben, plus que ce que le pharaon envoie : son don cesserait d'être le socle de la partie pour n'en être plus que l'appoint |

Le troisième ne porte aucun chiffre : il se lit sur la dotation, ressource par
ressource. Rien à calibrer, et il suit tout changement de coût des bâtiments
d'ouverture.

#### Ce qui ne se persiste pas, et pourquoi

Ni le carnet de contacts ni l'héritage des routes n'ont ajouté de colonne. Le
carnet se déduit des missions accomplies, l'héritage des parties du joueur —
comme les partenaires commerciaux se déduisent du catalogue. Le nom, la région
et les ressources sont du **contenu** : une colonne de plus ne dirait rien que
`MissionCatalogue` ne sache déjà.

Une seule table est née, `lignee`, et une seule colonne, `renommee_des_affaires`.

**La migration de la lignée rétro-alimente.** Sans elle, un joueur ayant déjà
bouclé des missions aurait vu son acquis repartir de zéro le jour où la table
apparaît. Elle reprend la plus haute renommée de ses parties de campagne
achevées — exactement ce que `Lignees::encaisser()` aurait versé.

#### Ce qui a coûté

- **Sous une dizaine de deben, la division entière avale tout l'avantage.**
  Mesuré en écrivant les tests, pas anticipé : sur du calcaire à 3, −40 % ne
  change rien. L'invariant a été écrit tel qu'il est vrai — « le plancher
  d'achat ne descend jamais sous le cours local » — plutôt que tel qu'il avait
  été supposé. Cela ne dessert personne : le commerce lointain ne porte pas de
  l'argile.
- **Une partie abandonnée ne lègue plus rien de neuf non plus.** Elle est
  supprimée en cascade, donc ni contact ni route connue n'en survivent — cohérent
  avec les deben et la renommée, mais à savoir avant d'abandonner.
- **Le plafond de cinq parties comptait aussi les parties achevées**
  (`GameSaveRepository`). Un joueur qui accomplissait cinq missions ne pouvait
  plus en lancer une sixième : **la campagne de dix missions était infinissable**
  sans supprimer des parties — alors qu'une partie close est précisément ce
  qu'on ne supprime jamais. Le docblock et le plan disaient tous deux « parties
  **en cours** simultanément » : c'était l'implémentation qui divergeait de
  l'intention, et rien ne le signalait.

  Découvert en écrivant `BonusDeDepartTest`, qui a dû contourner le lanceur
  pour simuler neuf missions accomplies. **Corrigé depuis** : le plafond lit
  `compterEnCoursPourJoueur()`, et deux tests le tiennent — vérifiés en
  réintroduisant le défaut, sans quoi ils n'auraient rien prouvé.

  **La leçon vaut au-delà du défaut** : un contournement dans un test est un
  symptôme. Quand un test doit éviter le chemin normal pour arriver à son
  scénario, c'est souvent le chemin normal qui a tort.

#### Ce que la phase laisse ouvert

- Le **lot 9.6** — générations, héritiers et leur trait — attend la Phase 11.
  `TraitDeCandidat` et `GenerateurDeCandidat` existent déjà, et le mécanisme de
  l'offre d'emploi se transpose presque tel quel ; il manque une liste de
  prénoms égyptiens attestés, du même travail de sourcing que les cartouches.
- Le **+2 de l'enquête** et les **8 points d'affaires par mission** sont des
  valeurs inventées, à calibrer sur l'économie mesurée comme les seuils du
  doc 09 l'ont été.
- Le **mode Aventure lit l'acquis mais ne l'alimente pas** : il ne s'achève pas,
  ses règnes se succèdent dans la même partie. À revoir en Phase 11, où ce mode
  reçoit son contenu.

*Ce qui suit est le **cadrage**, écrit avant la phase et tenu à jour pendant :
le raisonnement au moment des décisions, avec ce qu'on savait alors. Il vivait
au plan de bataille, qui ne garde plus que ce qui reste à faire.*

Le doc 13 est le seul document de conception dont le jeu applique **la moitié
sans le savoir** : les cinq paliers de renommée existent, aux plages exactes du
document, et six mécanismes la font bouger. Ce qui manque n'est pas la jauge,
c'est **ce qu'elle traverse** — une mission, une génération, une campagne.

#### Ce qui existe déjà, et qu'il ne faut pas refaire

| Le document demande | Le jeu fait |
|---|---|
| Cinq paliers, 0-19 / 20-39 / 40-59 / 60-79 / 80-100 | `PalierDeRenommee`, aux mêmes plages |
| L'attractivité varie par palier | Le prix d'un appel d'habitants et la migration spontanée en dépendent |
| Gros contrat commercial : +1 | `Marche::RECETTE_DUN_GROS_CONTRAT` |
| Quête de chantier réussie : +5, refusée : −2 | `QuetesDeChantier` |
| Bonus proportionnel au score de mission | `Legs::renommeePour()` |

Deux sources que le jeu ajoute et que le document ne prévoit pas : la
**providence** (±1 selon qu'un dieu bénit ou maudit) et le **mécontentement**
(−1 par quinzaine de colère). Elles sont cohérentes avec le reste et se
gardent ; le document se corrigera.

#### Le défaut de fond : la renommée ne traverse rien

**`Family` naît avec chaque partie.** La renommée repart donc de zéro à chaque
mission, augmentée du seul legs de la mission **immédiatement précédente**,
plafonné à quatre points. Le document veut l'inverse, et le dit en toutes
lettres : « une seule jauge de renommée par famille, **persistante d'une
mission à l'autre** […] elle ne fait que croître au fil de la campagne ».

C'est le cœur de la phase, et ce n'est pas qu'une colonne à déplacer : cela
touche à un invariant déjà posé — **le legs s'ajoute à la dotation, il ne la
remplace pas**, pour que chaque mission reste jouable seule. Une renommée
cumulée qui infléchit les prix rend mécaniquement les dernières missions plus
faciles. C'est **voulu** par le document ; il faut le décider en connaissance
de cause plutôt que le découvrir au playtest.

#### Les lots

| Lot | Contenu | |
|---|---|---|
| 9.0 | Les arbitrages tranchés, et la forme qu'ils donnent à la phase | ✅ |
| 9.1 | La renommée devient une jauge de famille, qui traverse la campagne | ✅ |
| 9.2 | Les deux sources manquantes : énigme résolue, enquête résolue | ✅ |
| 9.3 | La renommée infléchit les prix, à l'achat comme à la vente | ✅ |
| 9.4 | Le carnet de contacts : ce qu'une région visitée laisse | ✅ |
| 9.5 | Le bonus de départ par missions accomplies | ✅ |
| 9.6 | La succession : générations, héritiers et leur trait | ↦ Phase 11 |

##### 9.0 — Les arbitrages, tranchés

Le bloc « À trancher avec la joueuse » ci-dessous a été posé avant d'écrire une
ligne de code : chacune de ces questions change la forme des lots suivants, et
les découvrir en cours de route aurait coûté une reprise. Les réponses sont
donc actées ici, avec leur raison.

| Question | Tranché | Ce que cela impose |
|---|---|---|
| La renommée cumulée facilite les dernières missions — on assume ? | **Oui**, comme le doc 13 le demande | La renommée traverse la campagne sans réserve. L'invariant « chaque mission jouable seule » se relit : aucune mission ne devient *injouable* sans héritage, mais la dixième se joue avantagée — c'est la récompense de la campagne, pas un défaut d'équilibrage |
| Un plafond global au cumul renommée + contacts + missions accomplies ? | **Oui**, un plafond unique sur la remise totale | Les trois sources ne s'additionnent pas sans limite. Le plafond porte sur le **résultat**, pas sur chaque source — sans quoi trois plafonds séparés se cumulent et n'en plafonnent aucun |
| Un contact débloque-t-il une ressource ? | **Non**, remise seule | Le carnet reste une commodité économique. Il ne touche ni aux objectifs de mission ni à ce qui est atteignable : un raccourci de progression aurait demandé de recalibrer les missions tardives |
| L'enquête résolue vaut +3 ou +1 ? | **+2**, avec plafond de contribution | +3 mettrait quatre-vingt-dix points sur cent à la portée d'un seul système ; +1 ne paie pas un système qui demande plusieurs quinzaines de collecte. **Valeur inventée**, à calibrer comme les seuils du doc 09 |
| La succession maintenant, ou en Phase 11 ? | **Phase 11** | Une génération dure 60 cycles ± 20, une mission de campagne les dépasse rarement : le lot ne se déclencherait presque jamais. Il rejoint le mode Aventure, où il a un sens. **La Phase 9 se livre donc en 9.1 à 9.5**, et le lot 9.6 est reporté |

**Une conséquence de forme, qui décide du 9.1.** Deux exigences du cadrage
paraissent s'opposer : « une seule jauge par famille, persistante d'une mission
à l'autre », et « deux parties menées de front ne se volent pas leur renommée ».
Elles se concilient en séparant deux choses que le mot « renommée » confond :

- **l'acquis**, porté par la lignée — le plancher, qui ne descend jamais et que
  chaque nouvelle partie reçoit au lancement ;
- **la jauge de la mission**, portée par `Family` — celle qui bouge, que le
  mécontentement fait baisser, et qui reste propre à sa partie.

Une mission accomplie relève l'acquis ; elle ne l'abaisse jamais. C'est la même
discipline que le plancher du neutre de la négligence divine, et c'est ce qui
permet à deux parties de coexister sans se contaminer.

##### 9.1 — Une jauge de famille, pas de partie  *(livré)*

**Retenu : l'entité `Lignee`**, une par joueur, créée paresseusement au premier
lancement qui en a besoin — elle accueillera le carnet de contacts du 9.4. Elle
porte l'**acquis** ; `Family` garde la **jauge de la mission** (cf. 9.0).
`Lignees` est le seul accès : lecture au lancement, relèvement à la clôture
d'une mission de campagne par `AchevementDeMission`. La migration
rétro-alimente les joueurs déjà avancés depuis leurs parties achevées.

`Legs` perd son volet renommée : quatre points depuis zéro et l'acquis entier
auraient compté deux fois la même réussite. Il reste le legs en deben.

La renommée quitte `GameSave` pour suivre le **joueur**. Trois façons de le
faire, à trancher :

- **une entité `Lignee` par joueur**, que chaque partie référence — le plus
  propre, le plus coûteux en migration ;
- **un calcul** à partir des parties achevées, comme `Legs` le fait déjà —
  aucune colonne, mais la renommée gagnée *en cours de mission* ne survit pas ;
- **la valeur recopiée au lancement** depuis la plus haute atteinte — simple,
  mais deux parties menées de front divergent.

La première est la seule qui tienne la phrase du document. Les deux autres
sont des raccourcis qu'il faudra défaire.

**Le plancher est la règle qui compte** : « la renommée ne redescend jamais en
dessous de son niveau de fin de mission précédente ». Les pertes ponctuelles —
refus d'une requête, mécontentement — jouent **dans** la mission, jamais en
travers de la campagne. C'est la même discipline que le plancher du neutre de
la négligence divine.

##### 9.2 — Deux sources qui manquent  *(livré)*

**Retenu : +1 par énigme, +2 par enquête, sous un plafond de huit points par
mission** (`Family::RENOMMEE_MAX_DES_AFFAIRES`). Le plafond est la moitié du
lot : la renommée traversant désormais la campagne, dix missions où l'on résout
tout verseraient bien au-delà des cent points de l'échelle, et la jauge
cesserait de mesurer une réputation pour ne compter que l'assiduité à deux
mini-jeux. Il se compte **par mission**, pas par campagne — sinon deux systèmes
qui la traversent de bout en bout n'y vaudraient que huit points en tout.

Il ne borne que les affaires, jamais la jauge : elle bouge pour six raisons, et
un plafond qui la lirait plafonnerait les cinq autres. Le gain réellement versé
est rendu à l'appelant, pour que l'écran se taise au lieu d'annoncer un gain nul.

Le document accorde **+1 par énigme secondaire résolue** et **+3 par enquête
complète** ; le jeu ne donne rien pour la première et +1 pour la seconde.

L'écart sur l'enquête est à trancher : +3 récompense un système qui demande
plusieurs quinzaines de collecte, ce qui se défend ; mais la renommée se
compte sur cent points, et une campagne porte une trentaine d'enquêtes.
**À calibrer sur l'économie mesurée**, comme les seuils du doc 09 l'ont été.

##### 9.3 — La renommée dans les prix  *(livré)*

**La pièce qui décidait du lot existait déjà** : `$avantage`, le facteur du
Négociateur, élargit la fourchette d'un partenaire des deux côtés, en points de
pourcentage entiers. La renommée y entre (`AvantageDeNegoce`), elle n'ajoute
aucun troisième multiplicateur. Au Marché, elle s'**ajoute au coefficient** de
qualité de direction, qui reste appliqué en une seule multiplication et une
seule division.

**Le plafond global du 9.0 vaut 40 points**, et la raison est arithmétique : le
plancher d'un partenaire vaut 150 % du cours local moins l'avantage, donc à
cinquante il rejoint le cours et importer ne coûterait plus rien de plus que
produire sur place. Conséquence assumée : un Négociateur (25) chez une famille
illustre (20) est rogné — le plafond porte sur la somme, c'est ce qui a été
tranché.

Une limite mesurée en écrivant les tests : sous une dizaine de deben, la
division entière avale tout l'avantage. Cela ne dessert personne — le commerce
lointain ne porte pas de l'argile.

`reductionAchat = −0,2 % par point, plafonné à −20 %`, et la majoration
symétrique à la vente. Deux pièges connus du projet s'y appliquent :

- **jamais en flottants** — cela se compte en centièmes, comme les rendements
  et la qualité de direction ;
- **un seul multiplicateur par chaîne** : la vente au Marché porte déjà la
  qualité de direction du bâtiment, et l'ordre commercial porte déjà
  l'empressement du partenaire. La renommée doit **entrer dans un facteur
  existant**, pas en ajouter un troisième — c'est la discipline du lot 6.3.

##### 9.4 — Le carnet de contacts  *(livré)*

**Rien ne se persiste** : le carnet se déduit des missions accomplies
(`CarnetDeContacts`), comme les partenaires se déduisent du catalogue. Une
colonne de plus ne dirait rien que `MissionCatalogue` ne sache déjà.

Le contact vaut +2 sur ce que **sa région porte en gisement**, et sur rien
d'autre — c'est ce qui l'empêche d'être une remise générale de plus. Il entre
dans le même facteur que la renommée, donc sous le même plafond : l'avantage se
compte désormais **marchandise par marchandise**, ce que la signature
`avantageDeNegoce($partie, ?$ressource)` porte. La ville de la mission en cours
n'est jamais un contact — on ne se fait pas de prix à soi-même.

**L'héritage du doc 12 est fait avec**, comme le cadrage le recommandait :
`Commerce::RABAIS_DUNE_ROUTE_HERITEE`, −20 % sur une route déjà armée dans une
autre partie, **et `VOLUME_DUNE_ROUTE_HERITEE`, +10 % de volume** — le document
donne deux effets, et le second a été oublié à la livraison du 9.4, puis
rattrapé au 10.5. Il se déduit lui aussi, en interrogeant les parties du joueur.
Une partie ne s'hérite pas elle-même, et une partie abandonnée ne lègue rien —
cohérent avec le fait qu'elle ne lègue ni deben ni renommée.

Chaque mission accomplie laisse un **contact** — la ville elle-même — qui
donne +2 % sur les ressources caractéristiques de sa région, cumulables avec le
bonus de renommée. Comme les partenaires commerciaux, **seule la clé se
persiste** ; le nom, la région et les ressources sont du contenu.

Le document laisse ouvert un point qui change la nature du système : un contact
donne-t-il seulement un rabais, ou **débloque-t-il une ressource** qu'on
devrait autrement importer ? La seconde lecture en ferait un raccourci de
progression, et non plus une remise.

**Il croise l'héritage du doc 12**, qui n'est pas non plus implémenté : −20 %
sur l'ouverture d'une route déjà exploitée dans une partie précédente. Les deux
se complètent — l'un porte sur les routes, l'autre sur les prix courants — et
gagnent à être faits ensemble.

##### 9.5 — Le bonus de départ  *(livré)*

`BonusDeDepart` compte **toutes** les missions accomplies, hors celle qu'on
lance — rejouer une mission ne la compte pas deux fois, même règle que le
carnet. Il s'ajoute au legs, qui reste distinct : l'un vient du roi et suit le
score de la seule mission d'avant, l'autre vient de la maisonnée et suit la
campagne entière.

**Le plafond du 9.0 se lit sur la dotation elle-même**, ressource par
ressource : neuf missions vaudraient cent quatre-vingts deben, davantage que ce
que le pharaon envoie, et le don du roi cesserait d'être le socle de la partie
pour n'en être plus que l'appoint. Rien à calibrer — le plafond suit tout
changement de coût des bâtiments d'ouverture.

Les **vivres en sont exclus** : la dotation les taille sur la consommation
réelle de la maisonnée expédiée, et un forfait par-dessus casserait ce calcul.

`20 deben` et `5 unités` par mission accomplie, **superposés à la dotation
royale**, jamais à sa place. `Progression::plusHauteAchevee()` sait déjà
compter ; `Legs`, lui, ne regarde que la mission précédente et devra compter
toutes les accomplies.

La dotation, elle, ne change pas : elle reste calculée sur les **coûts réels**
des quatre bâtiments d'ouverture, et non sur le `50 + 10 × difficulté` du
document, qui compte encore en or.

##### 9.6 — La succession  *(reporté en Phase 11, cf. 9.0)*

Une génération dure **60 cycles ± 20**. À son terme, le joueur choisit parmi
deux ou trois héritiers, chacun tiré avec un ou deux des huit traits déjà
définis — `TraitDeCandidat` existe, `GenerateurDeCandidat` aussi, et le
mécanisme de l'offre d'emploi se transpose presque tel quel.

**Ce qui persiste** : la renommée, les contacts, la faveur divine, la ville
entière. **Ce qui se renouvelle** : le trait actif et le nom du chef de
famille. Rien ne se perd — c'est la ville qui compte, pas la personne.

Deux réserves :

- le document dit lui-même que la succession est **surtout pertinente en mode
  Aventure**, une mission de campagne dépassant rarement soixante cycles. Ce
  lot pourrait donc attendre la Phase 11 — sauf à vouloir l'éprouver plus tôt ;
- les **noms d'héritiers** demandent une liste de prénoms égyptiens attestés,
  du même travail de sourcing que les cartouches. `Nakht` est déjà justifié
  ainsi dans le doc 09.

##### Les questions telles qu'elles se posaient

Conservées pour l'enjeu qu'elles portent ; les réponses sont au 9.0.

| Question | Enjeu |
|---|---|
| La renommée cumulée rend-elle les dernières missions plus faciles, et l'assume-t-on ? | Le document le veut. Cela heurte l'invariant « chaque mission jouable seule », qui a justifié que le legs s'ajoute sans remplacer |
| Faut-il un **plafond global** au cumul renommée + contacts + missions accomplies ? | Le document pose la question sans y répondre. Sans plafond, la mission 10 se joue avec −20 % à l'achat, +20 % à la vente, neuf contacts et 180 deben de bonus |
| Un contact **débloque-t-il** une ressource, ou se contente-t-il d'une remise ? | La première lecture en fait un raccourci de progression, la seconde une commodité |
| La succession maintenant, ou avec le mode Aventure (Phase 11) ? | Elle ne se déclenche presque jamais en campagne : soixante cycles dépassent la durée d'une mission |
| L'enquête résolue vaut-elle +3 comme le document le dit, ou +1 comme aujourd'hui ? | Trente enquêtes à +3 font quatre-vingt-dix points sur une échelle de cent |

##### Définition de « fini »

Parcours de bout en bout : accomplir la mission 1 → voir la renommée gagnée
**rester** au lancement de la mission 2 → constater un prix d'achat plus bas et
un contact « Delta » au carnet → recevoir le bonus de départ par-dessus la
dotation royale.

Tests sur les invariants : la renommée ne redescend jamais sous son niveau de
fin de mission, aucune chaîne de production ne gagne un multiplicateur de plus,
le bonus de départ **s'ajoute** à la dotation sans la remplacer, et deux
parties menées de front ne se volent pas leur renommée.

---

### 5.13 Phase 10 — Medjaÿ et combat automatique  ✅

Le doc 03 est le document le plus **entièrement chiffré** du projet : formule de
résolution, forces d'unité, coûts, taux de blessure et de mort, protection
d'Isis par palier. Il n'y avait presque rien à inventer — ce qui distinguait
cette phase de la précédente, où le doc 13 nommait des faits sans les compter.

Le jeu en appliquait déjà toute la **moitié civile** : offres d'emploi, stats
chiffrées affichées en qualitatif, huit traits et leurs incompatibilités,
spécialités par bâtiment, barème d'étoiles. C'est la moitié militaire qui
manquait, entièrement.

| Lot | Contenu | |
|---|---|---|
| 10.0 | Les arbitrages, avant d'écrire | ✅ |
| 10.1 | Le danger sur la carte | ✅ |
| 10.2 | Les Medjaÿ : fantassin, archer, recrutement, entretien | ✅ |
| 10.3 | L'équipement, et les armes qui cessent d'être une marchandise | ✅ |
| 10.4 | La résolution automatique, et ce qu'elle coûte en hommes | ✅ |
| 10.5 | L'escorte : expéditions en armes et convois pillés | ✅ |
| 10.6 | Le Charrier : une réquisition, jamais un recrutement | ✅ |
| 10.7 | Les derniers branchements dormants | ✅ |

#### Le défaut de fond n'était pas le combat, c'était la carte

`scoreDefense` multiplie par le nombre de zones à bandits de la région. Or
`ContenuDeZone` ne connaissait que `Rien`, `Ressource`, `ChampEligible` et
`Evenement` : **la formule référençait un état que la carte n'avait pas**. La
phase a donc commencé par une addition au générateur, pas par une bataille.

**Une contradiction du doc 02, tranchée.** Il donne deux comptes qui ne
s'accordent pas — un tableau de poids de tirage (0 / 8 / 15 % par palier) et une
formule de paramètre, `partieEntiere(difficulté × 0,5)`. Sur la grille 12×12 de
la dixième mission, le premier donnerait une vingtaine de zones là où le second
en donne quatre. La formule l'emporte : le tableau décrit un tirage de
*contenu*, et le danger n'en est pas un depuis l'arbitrage 10.0.

**L'anneau de la ville est exclu, et l'invariant est testé sur les dix
régions.** Le générateur y garantit un gisement de chaque matériau vital ; une
bande dessus rendrait la première carrière imprenable sans Caserne, donc la
partie injouable au premier cycle.

#### Une phase qui réveille autant qu'elle construit

Six choses avaient été posées sans emploi, et le disaient elles-mêmes. Toutes
sont branchées, une par lot :

| Ce qui dormait | Réveillé au lot |
|---|---|
| L'écran de Caserne, « vos Medjaÿ ne sont pas encore levés » | 10.2 |
| `Ressource::Armes`, « sans usage propre » | 10.3 |
| `Divinite::Isis`, seule divinité déclarée inactive | 10.4 |
| `RoleDExploration::ChefDExpedition`, sans emploi | 10.5 |
| Les deux `CaserneInstructeur*`, déclarés et lus nulle part | 10.7 |
| `TraitDeCandidat::Bagarreur`, « sans effet » | 10.7 |

**Après la Phase 10, plus aucun trait ni aucune divinité ne dort.** Restent deux
spécialités : l'Acheteur du Marché — que le Marché, purement local,
n'accueillera jamais — et le Commerçant naval.

#### Le seul vide documentaire, et ce qu'on en a fait

Le lot 10.5 a buté sur un manque réel : le doc 12 pose « une caravane par
cycle » **sans le moindre aléa**, et les routes vivent hors de la carte. Rien ne
menaçait un convoi, donc l'escorte n'avait rien à protéger — alors que la
Caserne promet « la protection des caravanes » depuis la Phase 2.

Décision prise avec la joueuse : **inventer le risque, mais l'ancrer**. Il suit
le nombre de bandes *encore tenues* dans la région, le paramètre « Danger » du
doc 02. Trois conséquences saines en découlent : une région sans bandit ne perd
jamais un convoi — les deux premières missions gardent l'économie calibrée aux
phases 5 et 9 —, nettoyer une case protège le commerce autant que
l'exploitation, et une même règle sert deux systèmes au lieu d'ajouter un hasard
indépendant.

**La tension du lot tient en une phrase** : les hommes qui vont déloger une
bande sont ceux qui couvrent les convois. Une sortie coûteuse en blessés
découvre les routes, et la garnison cesse d'être une dépense entre deux assauts.

#### Ce que la discipline du projet a imposé

- **Tout en centièmes entiers.** Le document parle en `×0,85`, `×1,15`, `×1,1` ;
  une probabilité en virgule flottante aurait été le premier endroit du jeu où
  deux parties identiques divergent.
- **Un seul facteur par chaîne, une seule division.** La force d'un Medjaÿ
  croise base, expérience, arme, instructeur, poing leste et terrain — et se
  calcule en une multiplication et une division. Six divisions enchaînées
  auraient rogné la force à chaque étape.
- **Les « 100 or » du Charrier sont des deben.** L'Égypte pharaonique n'a pas de
  monnaie d'or ; les docs 09 et 13 avaient déjà été relus ainsi.

#### Trois règles que le document ne donnait pas

- **Les boucliers ne se cumulent pas.** Un mur de boucliers en vaut un, pas
  dix — sans cette borne, lever du fantassin en nombre annulait toute perte, et
  le système se désamorçait lui-même.
- **La qualité d'une arme se fige à la remise.** Monter la Forge n'améliore pas
  rétroactivement ce qui est déjà donné : il faut réarmer ses vétérans, ce qui
  fait du niveau de Forge une décision plutôt qu'un compteur.
- **Le butin se lit sur la défense réelle**, renfort de région compris. Comme
  nettoyer une case affaiblit toutes les autres, **la première victoire d'une
  campagne rapporte plus que la dernière** — courbe descendante là où on
  l'attendrait montante, à surveiller au playtest.

#### Ce qui a coûté

- **Un défaut trouvé dans du travail déjà rendu.** Le doc 12 donne **deux**
  effets à l'héritage commercial — « −20 % sur le coût d'ouverture *et* +10 % de
  volume initial » — et le lot 9.4 n'avait implanté que le premier. Découvert en
  relisant le document pour l'escorte, corrigé au 10.5.
- **Un test qui aurait échoué un jour sur cinq.** Le lot 10.4 envoyait quinze
  hommes non armés contre une bande en tenant la victoire pour acquise : elle
  n'était qu'à 82 %. Ce qu'il vérifie est la conséquence d'une victoire, pas la
  probabilité de l'obtenir — il la fixe donc par une graine.
- **`Divinite::attente()` a failli disparaître.** PHPStan a signalé qu'elle ne
  renvoyait plus jamais de chaîne, Isis étant la dernière inerte. La supprimer a
  cassé douze tests ; elle a été rendue générique plutôt qu'effacée, pour que le
  garde-fou reste entier pour le prochain dieu ajouté.
- **La meilleure arme du jeu ne se forge que dans les régions difficiles.**
  `niveauMaxRegion = 5 + difficulté` plafonne la Forge à 5 dans le Delta. Ce
  sont elles qui portent des bandits, donc le calibrage tombe juste — mais c'est
  un heureux hasard, pas un design.

#### Ce que la phase laisse ouvert

- **Les Medjaÿ ne sont pas « absents » pendant le trajet d'une expédition.** Le
  jeu ne suit pas la position d'un homme, seulement sa disponibilité : pendant
  qu'une expédition est en route, la garnison couvre toujours les convois. Une
  colonne de plus le corrigerait, si le playtest montre que partir doit
  découvrir les routes immédiatement.
- **Le doublon d'effectif du doc 01** : il chiffre l'effectif à la Caserne
  (`3 + 2 × niveau`) et promet aussi des « emplacements Medjaÿ » à la Résidence
  familiale, non chiffrés. Le jeu suit la Caserne ; les effets de Résidence
  rejoindront la Phase 11 avec les traits familiaux.
- **Six valeurs inventées** — défense d'une bande, qualité sans arme, butin,
  risque de pillage, couverture d'un Medjaÿ, les deux moitiés de Bagarreur —
  toutes signalées dans le code et au tableau du plan.

*Ce qui suit est le **cadrage**, écrit avant la phase et tenu à jour pendant :
le raisonnement au moment des décisions, avec ce qu'on savait alors. Il vivait
au plan de bataille, qui ne garde plus que ce qui reste à faire.*

Le doc 03 est le document le plus **entièrement chiffré** du projet : il donne
la formule de résolution, les forces d'unité, les coûts, les taux de blessure et
de mort, les paliers de faveur qui les infléchissent. Il n'y a presque rien à
inventer — et c'est ce qui distingue cette phase de la précédente, où le
document nommait des faits sans les compter.

Le jeu, lui, en applique déjà **la moitié civile** : le recrutement par offres
d'emploi, les stats chiffrées affichées en qualitatif, les huit traits, les
spécialités par bâtiment, le barème d'étoiles. C'est la moitié militaire qui
manque, et elle manque entièrement.

#### Ce qui existe déjà, et qu'il ne faut pas refaire

| Le document demande | Le jeu fait |
|---|---|
| Recrutement par offre, 2-3 candidats, renvoi possible | `JobOffer`, `GenerateurDeCandidat`, `Recrutements` |
| Huit traits, 45/40/15 % et incompatibilités | `TraitDeCandidat`, `PoidsDeTirage` |
| Spécialité tirée, propre au type de bâtiment | `SpecialiteDeChef`, y compris `CaserneInstructeurArcher` et `CaserneInstructeurBouclier` |
| Chiffré en interne, qualitatif à l'écran | Le barème d'étoiles et les libellés d'ancienneté |
| Caserne, ses coûts et ses neuf niveaux | `TypeDeBatiment::Caserne` |
| Armes de cuivre à la Forge | `Recette::Armes`, `Ressource::Armes` |
| Isis protège au combat, Sekhmet décide du sort | `Divinite`, qui les distingue déjà en toutes lettres |

#### Six choses inertes, qui n'attendent que cette phase

C'est la particularité de la Phase 10 : elle ne construit pas seulement du
neuf, elle **réveille** ce qui a été posé sans emploi et qui le dit lui-même.

- `TraitDeCandidat::Bagarreur` — « sans effet tant qu'aucun Medjaÿ n'est
  recruté », écrit dans le code ;
- `SpecialiteDeChef::CaserneInstructeurArcher` et `CaserneInstructeurBouclier` —
  déclarées, **lues nulle part** ;
- `Divinite::Isis` — seule divinité déclarée inactive, avec le message « aucune
  bataille ne se livre encore » ;
- `RoleDExploration::ChefDExpedition` — dernier rôle d'exploration sans emploi ;
- `Ressource::Armes` — fabriquée, et bonne à vendre seulement ;
- le gabarit `_caserne.html.twig` — un écran qui dit « vos Medjaÿ ne sont pas
  encore levés ».

Aucun de ces six n'est un chantier : ce sont des branchements. Le vrai travail
est ailleurs.

#### Le défaut de fond : la carte ne connaît pas le danger

Le document calcule la défense d'une zone ainsi :

```
scoreDefense = valeurBase_zone × (1 + 0,15 × nbZonesBandits_region)
```

Or `ContenuDeZone` ne porte que `Rien`, `Ressource`, `ChampEligible` et
`Evenement`. **Aucune case n'est dangereuse, et aucune région ne compte ses
zones à bandits.** La formule référence un état que la carte n'a pas.

C'est le cœur de la phase, et ce n'est pas un système de combat : c'est une
addition à la **génération de carte** (doc 02), qui touche `GenerateurDeCarte`,
`ContenuDeZone` et la géographie des dix régions. Le combat vient après, et il
sera plus simple.

Conséquence à ne pas manquer : le facteur `0,15 × nbZonesBandits_region` fait
qu'**une région dangereuse est plus dure partout**, pas seulement sur ses cases
à bandits. Le nombre de zones dangereuses par région devient donc un curseur de
difficulté régionale, à poser avec les seuils du doc 11.

#### Les lots

| Lot | Contenu | |
|---|---|---|
| 10.0 | Les arbitrages, avant d'écrire | ✅ |
| 10.1 | Le danger sur la carte : zones à bandits et défense de région | ✅ |
| 10.2 | Les Medjaÿ : fantassin, archer, recrutement et entretien | ✅ |
| 10.3 | L'équipement : les armes de la Forge cessent d'être une marchandise | ✅ |
| 10.4 | La résolution automatique, et ce qu'elle coûte en hommes | ✅ |
| 10.5 | L'escorte : expéditions lourdes et caravanes | ✅ |
| 10.6 | Le Charrier : une réquisition, jamais un recrutement | ✅ |
| 10.7 | Les six branchements dormants | ✅ |

##### 10.0 — Les arbitrages, tranchés

Même méthode qu'au 9.0, pour la même raison : ces réponses décident de la forme
des sept lots, et les découvrir en codant coûterait une reprise.

| Question | Tranché | Ce que cela impose |
|---|---|---|
| Le danger est-il un contenu de case ou un attribut ? | **Un attribut superposé** | Une case garde son contenu **et** porte un danger : le filon gardé par des bandits devient possible, et c'est le cas qui donne envie de lever des Medjaÿ. Une colonne sur `Zone`, et un tirage indépendant de `ContenuDeZone` dans le générateur |
| Une zone nettoyée le reste-t-elle ? | **Oui, définitivement** | Le combat est une conquête, pas un péage. On investit des hommes et de l'équipement une fois, on récolte ensuite. Rien d'autre dans le jeu ne se dégrade tout seul, et il n'y a pas de raison de commencer ici |
| Les armes se consomment-elles ? | **Non, équipement durable** | La Forge est un palier à franchir, pas un robinet à tenir ouvert. Une unité sans arme part quand même, à qualité minimale : **rien ne bloque une expédition**, ce qui évite qu'une chaîne de production décide du rythme militaire |
| La mort permanente ? | **Oui, aux taux du doc 03** | Ce sera le seul endroit du jeu où l'on perd sans recours. Ce n'est pas un « game over » au sens du doc 00 — la partie continue — et c'est ce qui donne son poids à l'expérience accumulée. Isis y trouve enfin son effet propre |

**Deux écarts au document, actés d'avance.** Ils reprennent des décisions déjà
prises deux fois sur d'autres documents, et n'ont pas à être rediscutés :

- les **flottants du document se comptent en centièmes entiers** (`0,85` →
  `85`, `1,15` → `115`). C'est la discipline du projet depuis les rendements,
  et une probabilité en virgule flottante serait le premier endroit du jeu où
  deux parties identiques divergeraient ;
- les **« 100 or » du Charrier deviennent 100 deben.** L'Égypte pharaonique n'a
  pas de monnaie d'or ; les docs 09 et 13 ont été relus ainsi avant celui-ci.

**Deux questions tranchées sans arbitrage**, parce qu'elles ne changent la forme
d'aucun lot :

- **les Medjaÿ ne répondent pas aux rivaux commerciaux.** Ni le doc 03 ni le
  doc 08 ne le prévoient : ce serait une addition, et l'enquête reste la
  réponse au rival. À reprendre si le playtest montre que `Rivaux` manque d'une
  seconde issue ;
- **le combat existe en mode Aventure.** Le danger vient du générateur de carte
  et de la géographie de la région, pas d'un scénario de mission : Memphis en
  porte donc comme les autres. Rien à faire de particulier, à condition que le
  compte de zones dangereuses se lise sur la **carte** et non sur un numéro de
  mission.

##### 10.1 — Le danger sur la carte  *(livré)*

`Zone::$defenseDesBandits` — zéro valant « aucune bande » —, `Bandits` pour les
règles, et une pose à la génération **après** le contenu, pour que le danger s'y
superpose. Une case gardée ne s'exploite ni ne se sème : c'est ce qui donne au
filon gardé sa raison d'être, et aux Medjaÿ la leur.

**Une contradiction du doc 02, tranchée.** Le document donne deux comptes qui ne
s'accordent pas : un tableau de poids de tirage (0 / 8 / 15 % par palier de
difficulté) et une formule de paramètre
`nbZonesBandits = partieEntiere(difficulté × 0,5)`. Sur la grille 12×12 de la
dixième mission, le premier donnerait une vingtaine de zones là où le second en
donne quatre. C'est **la formule qui l'emporte** : le tableau décrit un tirage
de *contenu*, et le danger n'en est pas un depuis l'arbitrage 10.0.

**L'anneau de la ville est exclu, et c'est un invariant testé** sur les dix
régions. Le générateur y garantit un gisement de chaque matériau vital ; une
bande posée dessus rendrait la première carrière imprenable sans Caserne, donc
la partie injouable au premier cycle.

`valeurBase_zone` est une **valeur inventée** — le doc 03 renvoie au doc 02, qui
ne la chiffre nulle part. Vingt : deux fantassins valent vingt, donc une chance
sur deux à mains nues.

Une case dangereuse, un compte par région, et la défense qui en découle. Trois
questions se posent, dont deux touchent au doc 02 plus qu'au doc 03 :

- une zone à bandits est-elle un **contenu** de case (comme `Ressource`) ou un
  **attribut** qui s'y superpose ? La seconde lecture permet un gisement gardé
  par des bandits — ce qui est exactement le cas intéressant ;
- le danger **bloque-t-il** l'exploitation, ou la rend-il seulement risquée ?
- une zone nettoyée le reste-t-elle ? Sans quoi le combat n'est qu'un péage
  répété, et non une conquête.

`GenerateurDeCarte` garantit déjà les matériaux vitaux dans l'anneau des huit
cases autour de la ville. **Le danger doit respecter cette garantie** : une
partie qui ne pourrait pas ouvrir sa première carrière sans Caserne serait
injouable au premier cycle.

##### 10.2 — Les Medjaÿ  *(livré)*

Entité `Medjay`, enum `SpecialisationMedjay`, service `Medjays`. **Le frein est
double** : l'effectif est borné par la Caserne — `3 + 2 × niveau`, chiffré par
le doc 01 — et l'entretien rejoint la masse salariale, si bien qu'une troupe
qu'on ne peut plus payer mécontente la ville comme des chefs impayés. C'est ce
qui empêche de lever dix archers au quatrième niveau et de ne plus y penser.

**Un doublon du doc 01, signalé et non tranché.** Le document donne l'effectif à
la Caserne (`3 + 2 × niveau`, chiffré) *et* promet des « emplacements Medjaÿ »
à la Résidence familiale aux niveaux 1, 3 et 5 (non chiffrés). Le jeu suit la
Caserne, seule des deux à porter un nombre. Les effets de Résidence relèvent des
traits familiaux, alors non implémentés. **Les deux ont été livrés au lot
11.6** : la Caserne et la Résidence s'ajoutent, un homme par palier.

Deux unités seulement, et le document explique pourquoi : les Medjaÿ étaient un
corps de sécurité intérieure, armé d'arc et de bouclier, **jamais de chars** —
ceux-là appartenaient à la *mesha*, l'armée d'État. La distinction est
historique et le jeu la tient.

| Unité | Caserne | Force | Particularité | Recrutement | Entretien |
|---|---|---|---|---|---|
| Fantassin (bouclier) | 1-3 | 10 | Réduit les pertes de 30 % | 15 deben | 1 deben/cycle |
| Archer | 4-6 | 15 | +10 % en terrain désertique | 25 deben | 2 deben/cycle |

Une unité gagne **+5 % par combat gagné, plafonné à +50 %**. C'est ce qui donne
son poids à la mort permanente : ce n'est pas le coût de recrutement qui fait
mal, c'est l'expérience perdue.

**Un Medjaÿ n'est pas un `Employee`.** Le chef de bâtiment a une compétence, un
salaire négocié, une spécialité et une maisonnée ; le Medjaÿ a une force, une
spécialisation et une expérience. Les confondre ferait porter à `Employee` deux
modèles qui n'ont en commun qu'un salaire — nouvelle entité.

##### 10.3 — L'équipement  *(livré)*

`Equipement` porte le compte du doc 01 — « +5 % par niveau de Forge à partir du
niveau 3 » —, `Medjay::$qualiteDeLequipement` la fige à la remise de l'arme.
Les armes cessent d'être un produit « sans usage propre » : c'est le premier
des six branchements dormants qui se réveille.

**Les deux arbitrages du 10.0 tiennent.** L'arme est durable — ce qu'on dépense
est la pièce, prise au stock, jamais une consommation par combat. Et un homme
sans arme part quand même, à `QUALITE_SANS_ARME` : rien ne bloque une
expédition, donc aucune chaîne de production ne décide du rythme militaire.

**La qualité se fige à la remise**, ce que le document ne disait pas : monter la
Forge n'améliore pas rétroactivement les armes déjà données, il faut réarmer ses
vétérans. C'est ce qui fait du niveau de Forge une décision plutôt qu'un
compteur.

**L'Armurier n'entre pas dans la qualité.** Sa spécialité bonifie déjà la
*production* d'armes, comme celles de l'Atelier bonifient leur recette : lui
donner en plus un effet sur la qualité lui en ferait deux, contre la discipline
du lot 6.3.

Une borne trouvée en écrivant les tests : `niveauMaxRegion = 5 + difficulté`
(doc 01) plafonne la Forge à 5 dans le Delta. **La meilleure arme du jeu ne se
forge donc que dans les régions difficiles** — ce qui tombe juste, ce sont elles
qui portent des bandits.

`qualite_equipement` entre directement dans la formule d'attaque, et vient de la
Forge (doc 01). C'est ce qui donne enfin aux **armes** une raison d'exister
autre que la vente, et à la spécialité `ForgeArmurier` son effet.

Deux points à trancher : les armes se **consomment**-elles, ou équipent-elles
durablement ? Et une unité sans arme se bat-elle quand même, à qualité minimale,
ou refuse-t-elle de partir ? La première lecture fait des armes un flux, la
seconde un stock — cela change toute la charge sur la Forge.

##### 10.4 — La résolution  *(livré)*

`Combat` porte la formule du document à la lettre, **en centièmes entiers** :
une probabilité en virgule flottante serait le premier endroit du jeu où deux
parties identiques divergeraient. La qualité d'équipement est déjà dans
`Medjay::force()` (lot 10.3) et n'y entre donc pas deux fois.

**Isis cesse d'être la divinité sans emploi**, et c'est le second des six
branchements dormants qui se réveille. Elle réduit la mort permanente de 25 %
à Favorable, 50 % à Dévoué — jamais les blessures, jamais l'issue du combat.
C'est très exactement ce que le doc 07 en dit : elle protège l'homme quand
Sekhmet décide du sort de tous. **Plus aucun dieu du panthéon n'annonce son
inertie** ; le garde-fou reste pour celui qu'on ajouterait demain.

Deux règles que le document ne donnait pas, et qui se sont imposées :

- **les boucliers ne se cumulent pas.** Dix fantassins ne rendent pas une
  troupe invulnérable — un mur de boucliers en vaut un, pas dix. Sans cette
  borne, lever du fantassin en nombre annulait toute perte ;
- **nettoyer une case affaiblit la région entière**, donc le butin d'une
  première victoire est plus gros que celui de la dernière : `Bandits::defenseDe()`
  compte les bandes encore tenues, et le butin se lit sur la défense réelle.

```
scoreAttaque      = Σ(force × qualité d'équipement) × terrain × faveur
probabilitéVictoire = scoreAttaque / (scoreAttaque + scoreDéfense)
```

Terrain : neutre 1,00 · désert 0,85 (le défenseur est avantagé) · fluvial en
Akhèt 1,15. Faveur : 1,1 à Favorable ou mieux avec une divinité de combat, 0,9 à
Hostile.

**Tout cela se compte en centièmes entiers**, jamais en flottants — c'est la
discipline du projet depuis les rendements, et une probabilité en virgule
flottante serait le premier endroit du jeu où deux parties identiques
divergeraient.

| | Butin | Blessés (indispo. 2 cycles) | Mort permanente |
|---|---|---|---|
| Victoire | proportionnel au score | 0-10 % | 2-5 % par unité |
| Défaite | aucun, retrait | 20-30 % | 10-15 % par unité |

**Isis réduit la mort permanente** de 25 % à Favorable, 50 % à Dévoué. C'est le
seul effet de la phase qui touche un système déjà livré, et il est exactement à
sa place : le doc 07 la distingue de Sekhmet parce qu'elle protège l'homme
quand l'autre décide du sort de tous.

##### 10.5 — L'escorte  *(livré)*

**Le vide documentaire, et ce qu'on en a fait.** Le doc 12 pose « une caravane
par cycle » sans le moindre aléa, et les routes vivent hors de la carte : rien
ne menaçait un convoi, donc l'escorte n'avait rien à protéger. Décision prise
avec la joueuse : **inventer le risque**, mais l'ancrer sur ce qui existe — il
suit le nombre de bandes *encore tenues* dans la région, le paramètre « Danger »
du doc 02. Une région sans bandit ne perd jamais un convoi, et nettoyer une
case protège le commerce autant que l'exploitation : une même règle sert deux
systèmes au lieu d'ajouter un hasard de plus.

**Le Chef d'expédition mène la troupe**, et c'est son emploi propre : seul rôle
à partir en armes, seul à pouvoir viser une case tenue, et le combat se résout
**à son arrivée**. Les autres rôles refusent une case gardée. Cela remplace le
bouton d'attaque direct posé au 10.4 : un assaut se prépare, il ne se déclenche
pas d'un clic sur la carte — et cela justifie enfin ses cinquante deben, là où
il faisait le travail d'un éclaireur pour cinq fois le prix.

**La tension du lot est là** : les hommes qui vont déloger une bande sont ceux
qui couvrent les convois. Une sortie coûteuse en blessés découvre les routes, et
la garnison retrouve un emploi entre deux assauts.

Le document nomme trois emplois : les **expéditions lourdes** (le Chef
d'expédition trouve enfin son rôle), la **protection des caravanes** — que le
docblock de la Caserne promet déjà — et la **garde de la ville**.

Le troisième croise `Rivaux`, qui rogne aujourd'hui le volume d'une route sans
qu'on puisse rien y faire hors de l'enquête. Les Medjaÿ ouvriraient une seconde
réponse — mais c'est une addition au doc 08, pas une lecture du doc 03 : à
trancher plutôt qu'à supposer.

##### 10.6 — Le Charrier  *(livré)*

**Il n'a pas d'entité, et c'est le propos.** Les chars vivent sur
`Expedition::$charriers` et nulle part ailleurs : ils ne rejoignent jamais
l'effectif, ne progressent pas, ne coûtent aucun entretien et disparaissent avec
la sortie. C'est la distinction historique — Medjaÿ contre *mesha* — portée
jusque dans le schéma de base.

Ils entrent dans le score d'attaque et **n'en sortent jamais blessés** : ce sont
les hommes du pharaon, pas les nôtres.

Caserne 7, Forge 4, force 25, **100 deben par expédition**, aucun entretien, il
disparaît à la fin et ne progresse jamais. Le document le veut ainsi : une
famille de marchands, même prospère, n'entretient pas de force de chars.

Le document écrit « 100 or ». Le projet a déjà tranché ce point deux fois
(doc 09, doc 13) : **l'Égypte pharaonique n'a pas de monnaie d'or**, le jeu
compte en deben, et la valeur se relit en conséquence.

##### 10.7 — Les branchements dormants  *(livré)*

Les six sont réveillés, un par lot : les armes au 10.3, Isis au 10.4, le Chef
d'expédition au 10.5, l'écran de Caserne au 10.2, et ici les **deux
Instructeurs** — chacun n'aiguisant que la spécialisation qu'il enseigne, ce qui
donne un sens au choix entre eux à l'embauche.

**Bagarreur a retrouvé ses deux moitiés.** Le jeu n'appliquait ni le bonus de
combat ni le malus civil — il le disait franchement dans le libellé du trait.
Les Medjaÿ n'étant pas des employés, « affecté aux Medjaÿ » se lit « en poste à
la Caserne », seul bâtiment militaire du jeu ; le malus, lui, pèse sur la
compétence quel que soit le poste, si bien qu'un chef qui se bat bien dirige mal
partout ailleurs.

**Ce qui dort encore, après la Phase 10** : l'Acheteur du Marché — que le
Marché, purement local, n'accueillera jamais — et le Commerçant naval, qui
attend un commerce naval avancé. **Aucun trait, aucune divinité.**

Les six ci-dessus, plus un écart réel : le document donne à **Bagarreur** un
« bonus combat si affecté aux Medjaÿ, **malus si poste civil** ». Le jeu n'a ni
l'un ni l'autre — il le dit franchement dans le libellé du trait. Le malus est
la moitié oubliée, et c'est elle qui rend le trait intéressant : un candidat
qu'on ne veut pas au Grenier devient bon à la Caserne.

##### Les questions telles qu'elles se posaient

Conservées pour l'enjeu qu'elles portent ; les réponses sont au 10.0.

| Question | Enjeu |
|---|---|
| Le danger est-il un contenu de case ou un attribut qui s'y superpose ? | La seconde lecture permet un gisement gardé — le cas intéressant. La première est plus simple et suit `ContenuDeZone` tel qu'il est |
| Une zone nettoyée le reste-t-elle ? | Sinon le combat est un péage répété, pas une conquête |
| Les armes se consomment-elles à chaque combat, ou équipent-elles durablement ? | Un flux met la Forge sous tension permanente ; un stock en fait un palier à franchir une fois |
| La mort permanente reste-t-elle à 2-15 %, dans un jeu qui n'a **aucun échec définitif** ailleurs ? | C'est le seul endroit du jeu où le joueur perd quelque chose sans recours. Le doc 00 tient à l'absence de « game over » ; une unité perdue n'en est pas un, mais c'est un changement de ton |
| Les Medjaÿ répondent-ils aux rivaux commerciaux ? | Le doc 03 ne le dit pas, le doc 08 non plus. Ce serait une addition, pas une lecture |
| Le combat existe-t-il en mode Aventure ? | Memphis n'a pas de mission, donc pas de zones à bandits placées par un scénario |

##### Définition de « fini »

Parcours de bout en bout : bâtir une Caserne → lever deux fantassins → les
équiper à la Forge → envoyer une expédition sur une case gardée → voir le
combat se résoudre sans écran de bataille → constater le butin, un blessé
indisponible deux cycles, et l'expérience gagnée par les survivants → revenir
sur la même case et la trouver libre.

Tests sur les invariants : aucune probabilité en flottant, une unité morte
repart à zéro d'expérience quand une unité blessée garde la sienne, le Charrier
ne rejoint jamais l'effectif permanent, l'anneau des huit cases autour de la
ville reste exploitable sans Caserne, et Isis réduit bien la mort permanente
sans toucher à l'issue du combat.

---

### 5.14 Phase 11 — Mode Aventure, succession des règnes et héritage familial  ✅

Le mode Aventure **existait déjà comme choix au lancement** : on pouvait ouvrir
une partie à Memphis et y jouer tout ce que les dix phases précédentes avaient
posé. Ce qui manquait n'était pas le mode, c'était **ce qui le distingue de la
campagne** — une succession de règnes, un contenu royal qui se renouvelle, une
fin qu'on ne subit pas.

| Lot | Contenu | |
|---|---|---|
| 11.0 | Les arbitrages, avant d'écrire | ✅ |
| 11.1 | La succession des règnes | ✅ |
| 11.2 | Memphis commerce, et ses routes suivent le règne | ✅ |
| 11.3 | Le contenu royal qui se renouvelle | ✅ |
| 11.4 | Le score cumulatif et la fin de partie | ✅ |
| 11.5 | La succession familiale *(ex-lot 9.6)* | ✅ |
| 11.6 | Les effets de Résidence familiale | ✅ |

#### Le défaut de fond : Memphis ne commerçait avec personne

`Commerce::partenairesDe()` lisait le catalogue **par numéro de mission** et
rendait un tableau vide quand il n'y en avait pas. En mode Aventure, **aucune
route n'était ouvrable** : l'Entrepôt et le Port ne servaient à rien.

C'était le contraire exact de ce que le doc 14 dit de Memphis. Il lui refuse
**délibérément** l'or, le cuivre et la turquoise en zone locale, parce que « son
atout réel est l'accès privilégié aux ressources importées » : le commerce
devait compenser l'absence de mines, et il n'existait pas.

#### Ce que « les routes suivent le règne » a changé

L'arbitrage du 11.0 a réordonné la phase : des partenaires qui suivent le règne
ne pouvaient pas être posés avant que le règne existe. La succession est donc
passée devant le commerce.

Il a surtout donné à la succession une **conséquence économique** plutôt qu'un
habillage narratif. Pount s'ouvre sous Hatchepsout, Babylone et Alashiya au
temps des lettres d'Amarna, le Naharina sous les règnes qui portent la frontière
vers l'Euphrate. **Aÿ n'ouvre que le fleuve** — quatre ans de règne, tout entier
à l'intérieur : un règne maigre se sent alors dans l'économie et pas seulement
dans le texte.

Un **socle** demeure sous tous les règnes — le Delta au nord, Thèbes au sud —
sans quoi un pharaon tourné vers l'intérieur aurait reproduit le défaut qu'on
venait de corriger.

#### Ce qui se déduit plutôt que de se persister

Trois fois dans la phase, la donnée s'est déduite au lieu de se garder :

- le **règne en cours**, de la somme des durées — allonger la succession
  jusqu'à Ramsès XI ne demandera donc ni migration ni changement de code ;
- les **partenaires de Memphis**, du règne ;
- les **héritiers proposés**, d'une graine gardée sur la famille — seule la
  graine se persiste, si bien que deux visites du même écran montrent les mêmes
  héritiers sans qu'aucune table ne les porte.

C'est la même discipline que le carnet de contacts de la Phase 9. Une seule
migration a été nécessaire dans toute la phase, pour la succession familiale.

#### Le sourcing, encore une fois le gros du travail

Sept cartouches et sept chantiers, un par pharaon que la campagne ne
commandite pas. Trente prénoms de chefs de famille, tirés des registres de Deir
el-Médineh et des tombes de particuliers thébaines — **jamais de nom de roi**,
la famille du joueur n'étant pas royale.

**Les glyphes ont été générés depuis leurs codes de Gardiner**, jamais saisis à
la main : Unicode nomme chaque caractère par son code, ce qui rend la
correspondance exacte et supprime la seule table qui pouvait diverger.
`CodesDeGardinerTest` en confronte désormais 114, contre 85 avant la phase.

**Smenkhkarê n'est pas dans la succession**, et c'est délibéré : son existence
propre, sa durée et jusqu'à son identité sont débattues. La règle du projet
interdit d'afficher ce qui ne s'établit pas. **Aÿ y figure**, lui, bien que
l'exemple du doc 14 l'omette : il est solidement attesté.

#### Deux doublons supprimés

- **Le nom de trône était écrit deux fois**, sur `Regne` et sur
  `CartoucheRoyal`, et les deux avaient déjà divergé d'un accent —
  `Nebpehtyré` d'un côté, `Nebpehtyrê` de l'autre. Il se lit désormais sur le
  cartouche seul.
- **Le doublon d'effectif Medjaÿ du doc 01**, signalé au lot 10.2 et tranché
  ici : la Caserne et la Résidence familiale **s'ajoutent**, un homme par palier
  de Résidence. Sans Caserne il n'y a toujours aucun homme — elle ajoute des
  places, elle n'en crée pas de nulle part.

#### Un écart au document, assumé

Le doc 01 promet un **trait familial** aux niveaux 2 et 5 de Résidence. Il est
livré, mais sous une autre forme : il ne se choisit pas à un palier de bâtiment,
il **vient avec l'héritier** qu'on retient. C'est plus fidèle à l'esprit du
doc 13 — un trait appartient à quelqu'un — et cela évite deux mécanismes pour
une seule idée. Le bonus de renommée passif du niveau 4 reste ouvert.

#### Ce qui a coûté

- **Une classe `readonly` ne peut pas porter de propriété statique à valeur par
  défaut.** La mémoïsation de la liste des règnes a donc été abandonnée plutôt
  que de contorsionner la classe : treize objets par appel ne coûtent rien, et
  c'est le test qui présumait l'identité des instances qui a été corrigé.
- **Deux annotations de type fausses de suite**, attrapées par PHPStan. C'est le
  bon ordre des choses, mais du temps perdu à écrire des types non vérifiés.
- **`Rivaux` se repliait sur la mission 1** quand il n'y avait pas de mission
  (`getMission() ?? 1`). Inoffensif tant que l'Aventure n'avait aucune route ; il
  serait devenu visible dès le lot 11.2.

#### Ce que la phase laisse ouvert

- **Les XIXᵉ et XXᵉ dynasties**, jusqu'à Ramsès XI. La borne visée est la fin du
  Nouvel Empire ; d'ici là, c'est le dernier règne connu qui fait fin. Les
  ajouter ne demande **aucun code**, seulement du sourcing — un cartouche et un
  chantier par pharaon.
- **Les stèles restent hors du mode.** Elles closent l'acte III d'un fil rouge,
  et l'Aventure n'en a pas : les y forcer aurait demandé d'inventer une intrigue
  par règne, ce que le document ne demande nulle part.
- **Les paramètres personnalisables du doc 14** — point de départ dans la
  succession, vitesse de succession — ne sont pas offerts au lancement. La
  taille de grille et la difficulté le sont déjà.
- **Le bonus de renommée passif** du niveau 4 de Résidence.
- **Cinq valeurs inventées** : les poids du score, et le seuil de conversion du
  score en centièmes de réussite.

*Ce qui suit est le **cadrage**, écrit avant la phase et tenu à jour pendant :
le raisonnement au moment des décisions, avec ce qu'on savait alors. Il vivait
au plan de bataille, qui ne garde plus que ce qui reste à faire.*

Le mode Aventure **existe déjà comme choix au lancement** : on peut ouvrir une
partie à Memphis, avec sa difficulté et sa taille de grille, et y jouer tout ce
que les dix phases précédentes ont posé. Ce qui manque n'est pas le mode, c'est
**ce qui le distingue de la campagne** — une succession de règnes, un contenu
royal qui se renouvelle, et une fin qu'on ne subit pas.

Elle hérite aussi de deux lots reportés : la **succession familiale** (9.6) et
les **effets de Résidence familiale** du doc 01, jamais implantés.

#### Ce qui existe déjà, et qu'il ne faut pas refaire

| Le document demande | Le jeu fait |
|---|---|
| Une seule ville, Memphis, absente de la campagne | `LanceurDePartie::VILLE_DU_MODE_AVENTURE` |
| Nil et désert, ni Méditerranée ni mer Rouge | `LanceurDePartie::geographieDuModeAventure()` |
| Argile, roseaux, calcaire, natron, poisson | La même méthode, aux quatre ressources de zone près |
| Grille plus généreuse, difficulté au choix | Le formulaire de lancement les propose déjà |
| Toutes les mécaniques du jeu, sans « une mission = un objectif » | `FilRouge`, `AchevementDeMission`, `Legs` et `Progression` se taisent hors campagne |
| La faveur divine n'appartient pas au règne | `FaveurDivine` est portée par la ville, jamais par la mission |

#### Le défaut de fond : Memphis ne commerce avec personne

`Commerce::partenairesDe()` lit le catalogue **par numéro de mission**, et rend
un tableau vide quand il n'y en a pas. En mode Aventure, **aucune route n'est
donc ouvrable** : l'Entrepôt et le Port ne servent à rien, et les ressources
importées sont hors d'atteinte.

C'est le contraire exact de ce que le doc 14 dit de Memphis : « son atout réel
est l'**accès privilégié aux ressources importées** de tout le pays, pas une
richesse minière propre ». Le document lui refuse délibérément l'or, le cuivre
et la turquoise en zone locale — c'est le commerce qui devait compenser, et il
n'existe pas.

Trois autres systèmes se taisent pour la même raison, mais celui-là rend le
mode structurellement injouable sur la durée, ce qui est précisément son propos.

#### Les lots

| Lot | Contenu | |
|---|---|---|
| 11.0 | Les arbitrages, avant d'écrire | ✅ |
| 11.1 | La succession des règnes : durées, transitions, pharaons | ✅ |
| 11.2 | Memphis commerce : des partenaires qui suivent le règne | ✅ |
| 11.3 | Le contenu royal qui se renouvelle : chantiers, cartouches, stèles | ✅ |
| 11.4 | Le score cumulatif, et une fin qu'on choisit | ✅ |
| 11.5 | La succession familiale : générations et héritiers *(ex-lot 9.6)* | ✅ |
| 11.6 | Les effets de Résidence familiale du doc 01 | ✅ |

##### 11.0 — Les arbitrages, tranchés

Même méthode qu'aux 9.0 et 10.0 : ces réponses décident de la forme des lots, et
les découvrir en codant coûterait une reprise.

| Question | Tranché | Ce que cela impose |
|---|---|---|
| Les partenaires de Memphis suivent-ils le règne ? | **Oui** | La succession cesse d'être un habillage : un pharaon tourné vers la Nubie n'ouvre pas les mêmes routes qu'un pharaon tourné vers le Levant, et le commerce change avec le règne. **Cela réordonne les lots** — voir ci-dessous |
| La partie Aventure a-t-elle une fin ? | **Oui, la fin du Nouvel Empire** | La succession n'est pas une boucle infinie : elle a un dernier règne, et la partie s'y achève. Le mode reste un bac à sable *long*, il n'est pas *sans fin* |
| Combien de règnes sourcer avant de livrer ? | **La XVIIIᵉ dynastie d'abord** | Les treize règnes de l'exemple du doc 14, d'Ahmôsis Iᵉʳ à Horemheb — dont huit déjà documentés par la campagne. La boucle est complète et éprouvable ; les XIXᵉ et XXᵉ s'ajoutent ensuite **sans toucher au code**, la liste étant une donnée |
| Le mode Aventure alimente-t-il la lignée ? | **Oui, à chaque fin de règne** | La renommée appartient à la famille, pas au mode : l'asymétrie actuelle ne se justifiait par aucun document. Un règne achevé relève l'acquis comme une mission accomplie |
| La Résidence familiale pèse-t-elle sur l'effectif Medjaÿ ? | **Oui, elle s'ajoute** | `3 + 2 × niveau de Caserne`, **plus un homme par palier de Résidence atteint** (niveaux 1, 3, 5). La Caserne décide de l'essentiel, la Résidence retrouve un effet concret, et trois hommes au plus ne dérèglent rien |

**Une tension à assumer, entre les deux premières réponses.** La fin est celle
du Nouvel Empire, mais la première livraison ne portera que la XVIIIᵉ dynastie :
**la partie s'achèvera donc à Horemheb**, et non à Ramsès XI. C'est cohérent —
la fin est la fin de la liste, et la liste s'allonge — mais il faut que le code
en tire la conséquence : la **succession est une donnée, jamais une constante**,
et rien ne doit supposer qu'elle s'arrête à un pharaon nommé.

**Une conséquence de forme qui réordonne les lots.** Si les partenaires suivent
le règne, le commerce ne peut pas être réparé avant que le règne existe : le lot
qui posait Memphis comme carrefour dépend désormais de celui qui pose la
succession. **Les lots 11.1 et 11.2 échangent donc leur place** — la succession
d'abord, le commerce ensuite.

**Une conséquence de fond à connaître, sur le lot 11.4.** L'Aventure nourrissant
la lignée, une longue partie à Memphis peut faire monter la renommée dont
profiteront ensuite les missions de campagne. C'est **assumé** : la renommée est
un acquis de famille, et le doc 13 ne l'a jamais restreinte à la campagne. Si le
playtest montre qu'un bac à sable devient le chemin court vers une campagne
facile, le remède sera un plafond, pas un cloisonnement.

##### 11.1 — La succession des règnes  *(livré)*

`SuccessionDesRegnes` porte la liste — du contenu, comme le catalogue des
missions —, `Successions` les règles, `LongueurDeRegne` les trois catégories.

**Le règne se déduit du cycle**, il ne se persiste pas : les durées étant du
contenu, la somme est connue d'avance, et une colonne n'aurait rien dit que la
liste ne sache déjà. Allonger la succession jusqu'à Ramsès XI ne demandera donc
aucune migration.

**Treize règnes, et un absent nommé.** Smenkhkarê ne figure pas dans la liste,
et c'est délibéré : son existence propre, sa durée et jusqu'à son identité sont
débattues — on l'a tour à tour confondu avec Neferneferouaton et placé avant ou
après elle. La règle du projet interdit d'afficher ce qui ne s'établit pas ; il
rejoindra la liste si la question se tranche. Aÿ, lui, est solidement attesté et
y figure, bien que l'exemple du doc 14 l'omette.

**Six des treize ont déjà leur cartouche**, hérité de la campagne. Les sept
autres n'affichent rien plutôt qu'un signe approché — le lot 11.3 les sourcera.

`Lignees::encaisser()` **perd sa restriction à la campagne** (arbitrage 11.0) :
c'est désormais l'appelant qui décide du jalon — une mission achevée, ou un
règne qui s'achève.

Le document est chiffré, et se garde d'une fausse précision : un règne ne se
convertit **pas** année pour année, mais par **catégorie de longueur** —
court (< 15 ans) 10-15 cycles, moyen (15-30) 16-25, long (> 30) 26-35. L'ordre
relatif est respecté sans prétendre simuler un calendrier.

Il donne treize règnes en exemple, de la XVIIIᵉ à la XIXᵉ dynastie, et laisse
la liste extensible. À chaque succession : un **texte de transition**, de
nouvelles **quêtes de chantiers royaux**, et la faveur divine **inchangée** —
elle appartient à la famille, pas au règne.

**Le sourcing est le vrai travail**, comme pour les cartouches et les stèles :
huit pharaons de la campagne sont déjà documentés, les autres — Amenhotep Iᵉʳ,
Thoutmôsis II, Amenhotep II, Thoutmôsis IV, Toutânkhamon, Horemheb — demandent
le même soin. **La règle du projet vaut ici comme pour les hiéroglyphes** : rien
d'inventé, et l'on n'affiche rien plutôt qu'une approximation.

**La liste est une donnée, jamais une constante** (arbitrage 11.0). La première
livraison porte la XVIIIᵉ dynastie, donc la partie s'achève à Horemheb ; les
XIXᵉ et XXᵉ s'ajouteront sans toucher au code, jusqu'à Ramsès XI. Rien ne doit
supposer qu'elle s'arrête à un pharaon nommé.

##### 11.2 — Memphis commerce  *(livré)*

`CataloguePartenaires::pourMemphis(?Regne)` : un **socle** — le Delta au nord,
Thèbes au sud, sous tous les règnes — et ce que le pharaon ouvre en propre.
Toutes les relations sont attestées pour le règne où elles figurent : Pount sous
Hatchepsout, Babylone et Alashiya sous Amenhotep III et Akhenaton, le Naharina
sous les règnes qui touchent l'Euphrate, Koush sous ceux qui regardent au sud.

**Aÿ n'ouvre que le fleuve**, et c'est voulu : un règne de quatre ans, tout
entier à l'intérieur. La contrainte de jeu suit le fait.

**Un second défaut corrigé au passage** : `Rivaux` cherchait le partenaire d'une
route dans le catalogue de **la mission 1** quand il n'y avait pas de mission
(`getMission() ?? 1`). Inoffensif tant que l'Aventure n'avait aucune route ; il
serait devenu visible dès celle-ci.

Le catalogue des partenaires est indexé par mission ; il faut lui donner une
entrée pour un mode qui n'en a pas. Le doc 14 dit quoi y mettre : Memphis est un
**carrefour**, pas un site d'extraction, et son profil doit donc être plus large
que celui d'aucune mission — le Nil vers le nord et le sud, les pistes vers
l'est et l'ouest.

C'est aussi ce qui rend le lot 11.2 jouable : un règne qui change sans que le
commerce suive ne changerait rien à la partie.

**Tranché : ils suivent le règne** (arbitrage 11.0). Un pharaon tourné vers la
Nubie n'ouvre pas les mêmes routes qu'un pharaon tourné vers le Levant, et c'est
ce qui fait de la succession autre chose qu'un habillage narratif. C'est aussi
pourquoi ce lot vient **après** le 11.1 : on ne fait pas suivre un règne qui
n'existe pas encore.

Un socle demeure quoi qu'il arrive — le Nil vers le nord et le sud —, sans quoi
un règne mal tourné laisserait Memphis sans commerce du tout, ce qui reproduirait
le défaut qu'on vient corriger.

##### 11.3 — Le contenu royal qui se renouvelle  *(livré)*

**Sept cartouches et sept chantiers sourcés**, un par pharaon que la campagne ne
commandite pas. Les treize règnes ont désormais leur cartouche réel et leur
monument attesté ; deux règnes ne réclament jamais le même, et c'est testé.

**Les glyphes ont été générés depuis leurs codes de Gardiner**, jamais saisis à
la main : Unicode nomme chaque caractère par son code, ce qui rend la
correspondance exacte et supprime la seule table qui pouvait diverger.
`CodesDeGardinerTest` en confronte désormais 114. La police sous-ensemblée a été
régénérée — 55 signes, 16 Ko — sans quoi les nouveaux se seraient affichés en
carré vide, sans erreur ni avertissement.

**Une duplication supprimée en chemin** : le nom de trône était écrit deux fois,
sur `Regne` et sur `CartoucheRoyal`, et les deux avaient déjà divergé —
`Nebpehtyré` d'un côté, `Nebpehtyrê` de l'autre. Il se lit maintenant sur le
cartouche seul.

**Les stèles restent hors du mode.** Elles closent l'acte III d'un fil rouge, et
l'Aventure n'en a pas : les y forcer aurait demandé d'inventer une intrigue par
règne, ce que le document ne demande nulle part.

`QuetesDeChantier` refuse aujourd'hui de rien réclamer hors campagne, et le
`CartoucheRoyal` comme la `SteleHistorique` sont attachés à un numéro de
mission. C'est la conséquence directe du 11.2 : un règne apporte son pharaon,
donc son cartouche, ses chantiers et sa stèle.

**Le gain pédagogique est l'argument du document** : une partie Aventure fait
rencontrer bien plus de pharaons que les huit de la campagne. C'est aussi le
plus gros volume de contenu de la phase.

##### 11.4 — Le score et la fin  *(livré)*

`ScoreDAventure` **lit ce que le jeu sait déjà compter** — les quatre grandeurs
qu'`ObjectifDeMission` mesure pour la campagne. Rien n'y réinvente une mesure
existante ; seuls les **poids sont inventés**, pour qu'aucune grandeur n'écrase
les autres : une ville riche compte ses deben par milliers quand elle compte ses
habitants par dizaines.

Le détail est montré autant que le total : **un total nu ne se joue pas**, on ne
sait pas quoi faire pour le faire monter.

**La succession épuisée clôt la partie**, et le dernier règne compte comme les
autres — son acquis rejoint la lignée avant la fermeture. Le score final se
range dans le champ du score de mission : les deux disent la même chose, ce
qu'une run a valu, et l'écran de reprise n'a pas à connaître deux notions pour
une idée.

Pas d'objectif fermé : un **score cumulatif** — richesse, population, renommée —
que le joueur suit à tout moment, et qu'il arrête quand il veut. Le jeu sait
déjà tout compter : `ObjectifsDeMission` mesure exactement ces grandeurs pour
la campagne, et le score n'en est qu'une lecture continue.

**Tranché : elle s'arrête à la fin de la succession** (arbitrage 11.0). Le mode
est un bac à sable *long*, il n'est pas *sans fin* : le dernier règne clôt la
partie, qui reste consultable avec son meilleur score — comme une partie de
campagne achevée. La borne visée est la fin du Nouvel Empire ; d'ici que la liste
y parvienne, c'est le dernier règne connu qui fait fin.

**C'est aussi le jalon qui nourrit la lignée** : chaque règne achevé relève
l'acquis de renommée, ce que le mode ne faisait pas faute de mission à
terminer (arbitrage 11.0). Une longue partie à Memphis peut donc profiter aux
missions de campagne suivantes — assumé : la renommée est un acquis de famille,
et le doc 13 ne l'a jamais restreinte à la campagne.

##### 11.5 — La succession familiale  *(livré)*

**Rien ne se persiste des héritiers, seulement la graine qui les décide** :
deux visites du même écran en montrent les mêmes, sans qu'aucune table ne les
porte. Même principe que le carnet de contacts et la succession des règnes — la
donnée se déduit, elle ne se garde pas.

Le nom de famille demeure, le **chef** change : la lignée porte le nom, la
génération porte quelqu'un. Ce qui persiste — renommée, contacts, faveur divine,
ville entière — traverse la succession sans y toucher.

`PrenomEgyptien` porte trente prénoms attestés, tirés des registres de Deir
el-Médineh et des tombes de particuliers thébaines. **Jamais de nom de roi** :
la famille du joueur n'est pas royale, et le doc 09 posait déjà la règle pour
`Family::NOM_PAR_DEFAUT`. **Hommes et femmes ensemble** — le doc 02 rappelle que
les Égyptiennes travaillaient et disposaient d'une autonomie juridique
inhabituelle.

Reporté depuis la Phase 9, et pour une raison qui tient toujours : une
génération dure **60 cycles ± 20**, quand une mission de campagne les dépasse
rarement. Ici, une partie qui traverse treize règnes en compte plusieurs
centaines : le lot trouve enfin le mode où il se déclenche.

Le mécanisme est déjà à moitié écrit — `TraitDeCandidat`, `GenerateurDeCandidat`
et l'offre d'emploi se transposent presque tels quels. **Ce qui persiste** : la
renommée, les contacts, la faveur divine, la ville. **Ce qui se renouvelle** :
le trait actif et le nom du chef de famille. Il manque une liste de **prénoms
égyptiens attestés**, du même travail de sourcing que les cartouches.

##### 11.6 — Les effets de Résidence familiale  *(livré)*

**Les emplacements Medjaÿ s'ajoutent à la Caserne** (arbitrage 11.0) : un homme
par palier atteint — niveaux 1, 3 et 5. La Caserne décide de l'essentiel, trois
hommes au plus viennent de la Résidence, et **sans Caserne il n'y a toujours
aucun homme** : elle ajoute des places, elle n'en crée pas de nulle part.

Le **trait familial** des niveaux 2 et 5 est livré par le 11.5, sous une forme
que le document ne prévoyait pas tout à fait : il ne se choisit pas à un palier
de bâtiment mais **vient avec l'héritier** qu'on retient. C'est plus fidèle à
l'esprit du doc 13 — un trait appartient à quelqu'un — et cela évite deux
mécanismes pour une idée. Le bonus de renommée passif du niveau 4 reste ouvert.

Le doc 01 leur donne cinq niveaux, dont le jeu n'applique aucun : emplacement
Medjaÿ aux niveaux 1, 3 et 5, **trait familial** aux niveaux 2 et 5, bonus de
renommée passif au niveau 4.

**Le doublon signalé au lot 10.2 est tranché : les deux s'ajoutent**
(arbitrage 11.0). L'effectif vaut `3 + 2 × niveau de Caserne`, **plus un homme
par palier de Résidence atteint** — niveaux 1, 3 et 5. La Caserne décide de
l'essentiel, la Résidence retrouve un effet concret, et trois hommes au plus ne
dérèglent aucun calibrage.

Le **trait familial** est le vrai contenu neuf : un trait qui appartient à la
lignée et non à un employé, choisi par le joueur — le premier effet du jeu qui
se choisisse plutôt qu'il ne se tire.

##### Les questions telles qu'elles se posaient

Conservées pour l'enjeu qu'elles portent ; les réponses sont au 11.0.

| Question | Enjeu |
|---|---|
| Les partenaires de Memphis sont-ils fixes ou liés au règne ? | La seconde lecture donne à la succession une conséquence économique ; la première la réduit à un habillage narratif |
| La partie Aventure a-t-elle une fin ? | Le document pose la question sans y répondre. Une fin au Nouvel Empire donne une dernière image ; l'infini tient la promesse du bac à sable |
| Le mode Aventure alimente-t-il la lignée ? | Laissé ouvert en Phase 9 : il lit l'acquis de renommée sans jamais le nourrir, faute de mission à achever. Une succession de règnes offre enfin des jalons où l'encaisser |
| La Résidence familiale pèse-t-elle sur l'effectif Medjaÿ ? | Le doc 01 le promet sans le chiffrer, alors qu'il chiffre celui de la Caserne. Deux sources pour un même nombre demandent une règle de composition |
| Combien de règnes sourcer avant de livrer ? | Les treize de l'exemple, ou la XVIIIᵉ dynastie seule ? Chaque règne demande le sourcing des cartouches et des stèles — c'est le poste le plus lourd de la phase |

##### Définition de « fini »

Parcours de bout en bout : lancer une partie Aventure à Memphis → ouvrir une
route commerciale et faire rentrer un convoi → jouer assez de cycles pour voir
un règne s'achever → lire le texte de transition, voir le cartouche changer, les
routes ouvertes changer avec lui et de nouvelles quêtes de chantier apparaître →
constater que l'acquis de la lignée a monté → consulter son score cumulatif →
mener une génération à son terme et choisir un héritier → atteindre le dernier
règne de la liste et voir la partie s'achever.

Tests sur les invariants : la faveur divine **ne se réinitialise pas** à un
changement de règne ; aucun système de campagne ne se réveille en Aventure — ni
fil rouge, ni achèvement de mission, ni legs ; une partie Aventure ne consomme ni
n'ouvre de mission de campagne ; **Memphis n'est jamais sans partenaire**, quel
que soit le règne ; et rien ne suppose que la succession s'arrête à un pharaon
nommé — allonger la liste ne doit toucher aucun code.

---
