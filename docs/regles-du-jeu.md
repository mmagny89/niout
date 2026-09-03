# Niout — règles et invariants du jeu

Ce que le code du jeu doit respecter, et **pourquoi**. Chaque règle porte sa
raison d'être : une décision de conception, une contrainte historique, ou un
défaut réel déjà payé. Les supprimer sans lire la raison, c'est repayer.

Complète [`CLAUDE.md`](../CLAUDE.md), qui porte la stack, les commandes et
l'architecture, et [`interface.md`](interface.md), qui porte les écrans. La
source de vérité fonctionnelle reste les 16 documents de conception du Drive.

---

## Ressources, matériaux et carte

**Chaque ressource reste distincte, jamais agrégée.** Il n'existe ni ressource
`Bois` ni `Pierre` — le doc 01 chiffre ses bâtiments dans ces matériaux
génériques, mais chaque coût nomme désormais le matériau réel qu'il réclame
(`CoutDeConstruction::de(roseaux: …, argile: …, boisLocal: …)`) : un grenier
coûte des roseaux, de l'argile et du bois local, un temple du calcaire. **Le
bois local et le cèdre sont deux ressources distinctes** — l'un se ramasse au
bord du Nil (acacia, sycomore), l'autre s'importe du Levant à cinq fois le
prix ; les agréger sous « bois » cacherait au joueur ce qu'il possède. Rien ne se substitue à rien —
une région qui ne porte pas un matériau doit l'importer (commerce, Phase 5).
Ne jamais réintroduire un compteur générique ni une famille de matériaux : un
« bois » qui agrégerait roseaux et cèdre cacherait au joueur ce qu'il possède
réellement, ce qui a été le défaut corrigé ici.

**L'argile du désert existe, mais elle est rare** (`poidsDeLArgile()`).
L'Égypte en connaissait deux : le **limon du Nil**, déposé par la crue sur les
berges, dont on faisait la brique crue et la poterie commune ; et l'**argile
marneuse**, tirée de dépôts calcaires dans les ouadis du désert — Qena, Ballas,
Sohag —, celle des vases fins de couleur claire. Une carrière d'argile en plein
sable n'est donc pas une invention, mais elle suppose un affleurement de
calcaire, pas une dune : d'où un poids réduit au tiers au désert, plein partout
où l'eau a travaillé la terre. À parts égales, l'argile sortait du sable aussi
souvent que des berges, ce qui se voyait comme une erreur — et l'était pour
moitié.

**Trois matériaux sont vitaux**, pas deux (`Ressource::materiauxVitaux()`) :
roseaux, argile et **bois local**, dont tout bâtiment réclame depuis le doc 01
révisé. Chacun a sa garantie de génération, et celle du bois local lui est
propre — il ne pousse que sur la terre broussailleuse et, plus rarement, sur
la terre fertile, jamais dans le sable ; la garantie générique l'aurait planté
en plein désert.

**La « terre classique » du doc 02 est un terrain, pas un contenu**
(`TypeDeTerrain::TerreClassique`, affichée « Terre broussailleuse »). Elle
remplace l'ancien `ContenuDeZone::TerreNonCultivable`, qui n'était qu'une case
fertile que le tirage n'avait pas retenue — un manque déguisé en contenu. Elle
ne se cultive **jamais**, et ne se sème que dans les régions bordées par le Nil.

**La fondation d'un bâtiment ne coûte pas de deben, l'amélioration si** (doc 01
révisé) : la brique crue d'un premier niveau relevait de matériaux locaux et
d'une main-d'œuvre familiale. Deux exceptions, qui paient dès la fondation — le
Temple (rituel de dédicace) et le Port (pontons). Les matériaux croissent en
`× (1 + (N-1) × 0,4)`, le deben en `debenParNiveau × (N-1)` : **deux lois
distinctes**, à ne pas réunifier.

**Une case porte jusqu'à deux gisements** (`Zone::GISEMENTS_MAX`), jamais deux
fois le même. À un seul, l'argile et les roseaux — les deux matériaux dont rien
ne tient lieu, tous deux nés de l'eau — se disputaient les rares berges d'une
grille 3×3, et une partie pouvait se figer faute de l'un des deux. La génération
garantit aussi un minimum de champs et de cases poissonneuses par région
(`GenerateurDeCarte::CHAMPS_MINIMUM`, `POISSON_MINIMUM`) — sur la plus petite
carte du jeu (Delta, 3×3), matériaux, champs et poisson se disputent les mêmes
cases, d'où un minimum volontairement bas (1) plutôt que théoriquement
généreux mais irréalisable. Les garanties de matériaux privilégient l'anneau
des 8 cases autour de la ville, **un seul exemplaire de chaque matériau non
alimentaire** dans cet anneau (décision de la joueuse — éviter d'avoir
directement tout à portée).

**Retirer un cas d'enum ne le retire pas de la base** (défaut réel, payé) :
`ContenuDeZone::TerreNonCultivable` a disparu du code avec la terre classique,
sans migration pour les lignes déjà écrites. Doctrine ne sait pas hydrater une
valeur absente de l'enum — **toute partie portant une seule case de ce contenu
devenait illisible**, donc impossible à ouvrir comme à abandonner, et l'erreur
ne nomme ni la partie ni la table. Tout retrait d'un cas persisté se
double d'une migration qui convertit l'existant (`Version20260830190000`).

**Piège payé** : `Zone::poserUnGisement()` ne doit **jamais** écraser un
contenu déjà posé (`ContenuDeZone::ChampEligible`, `Evenement`) — seul
`Rien` peut devenir `Ressource`. Sans cette garde, un gisement ajouté après
coup (garantie de matériau, garantie de poisson) effaçait silencieusement le
champ qu'une garantie précédente venait de poser sur la même case.

## Commerce, artisanat et réserves

**Un convoi parti est un engagement pris** (`Convoi`) : on débite **au départ**
ce qu'on engage — la marchandise pour une vente, les deben pour un achat — et
l'on reçoit au retour. Débiter à l'arrivée permettrait de vendre deux fois la
même chose. Le convoi porte **sa propre copie** de l'échange, jamais un lien
vers l'ordre : retirer une annonce n'annule pas ce qui roule. **Un seul convoi
par ressource et par route**, et une caravane rentrée **repart plutôt que d'être
recréée** — supprimer puis réinsérer dans la même quinzaine fait sauter la
contrainte d'unicité, Doctrine insérant avant de supprimer (le piège des
gisements, repayé).

**Le commerce est un étal, pas un bouton d'échange** (`OrdreCommercial`,
décision de la joueuse) : le joueur annonce ce qu'il vend et achète, à quel
prix, et attend. **Un ordre ne débite rien** — c'est une annonce, les convois
l'exécutent. **Le prix décide de l'empressement du partenaire**
(`PartenaireCommercial::empressement()`), donc du volume qui bouge : c'est ce
qui en fait un levier plutôt qu'un curseur à pousser au maximum, et l'écran
montre l'effet **avant** l'engagement. La quantité par convoi est un garde-fou :
un ordre permanent ne doit jamais vider la ville sans prévenir.

**Le craft de luxe se débloque par l'Entrepôt, pas par l'Atelier**
(`Recette::deblocageSupplementaire()`, docs 01 et 08) : bijoux, statuettes et
vases réclament un Entrepôt de niveau 8, et six matières qu'aucune région de
départ ne porte. C'est voulu — le prestige n'est atteignable qu'une fois le
commerce établi, et non par la seule montée d'un bâtiment.

**Une spécialité d'atelier ne vaut que sur son propre ouvrage**
(`SpecialiteDeChef::favorise()`) : un Brasseur ne fait pas de meilleurs
papyrus. Le bonus passe par la **qualité de direction**, comme tout effet de
chef. Deux spécialités font exception et ne passent pas par elle, parce que
leur effet n'est pas une production : le **Négociateur** élargit la fourchette
des partenaires, le **Logisticien** raccourcit les trajets — jamais sous une
quinzaine, sans quoi la distance cesserait de décider de la fréquence des
convois. Elles se lisent par `EffetDeChef::chefSpecialise()`.

**Mesurer l'effet d'un chef en quinzaines ne prouve rien** : elles se comptent
en entiers, et un ordre de quatre cycles ne distingue pas une qualité de 134 %
d'une de 114 %. Tester la qualité de direction elle-même. Et **ne jamais
mesurer une cadence en menant une partie sur une dizaine de quinzaines** : sur
cette durée un chef peut rendre son tablier — son ancienneté est tirée —, et
l'on mesure alors son départ autant que sa spécialité. Un test de cadence se
fait sur l'ordre lui-même, à qualité imposée (défaut réel, tombé en CI une
fois sur plusieurs).

**Une route commerciale s'ouvre en y envoyant une caravane** (`Commerce`,
`CataloguePartenaires`, décision de la joueuse) : on paie, le convoi part, la
route n'existe qu'à son arrivée. **Le type de route décide du bâtiment** —
Entrepôt pour les pistes, Port pour tout ce qui flotte — et du volume d'un
convoi. Seule la **clé** du partenaire est persistée ; nom, distance et
fourchettes de prix sont du contenu, jamais de l'état. **Les fourchettes se
déduisent** de `PrixDuMarche` (200 % à la vente, 150 % à l'achat), jamais d'une
table par partenaire et par ressource — et **un partenaire ne vend jamais ce
qu'il achète**, sans quoi une route serait une machine à arbitrer.

**Fabriquer prend du temps et plusieurs matières** (`Recette`, `Fabrication`,
décision de la joueuse). **L'Atelier et la Forge partagent tout** — un seul
service, c'est la recette qui dit où elle se travaille. Quatre règles à ne pas
défaire : les matières sont
**débitées à l'engagement** — sans quoi on lancerait dix ordres avec les
ressources d'un seul —, les pièces **n'entrent qu'à l'achèvement** (la règle
des champs), **un seul ordre à la fois et par bâtiment** parce que c'est ce qui donne son
coût d'opportunité à la fabrication, et le rythme vient des bras par
`EffetDeChef::qualiteDeDirection()`, jamais par un multiplicateur de plus.
**Toute recette ajoutée doit tenir la marge de transformation** — le test s'y
adosse et tombe sinon.

**Le stock est plafonné, jamais périssable** (`Stockage`, décision de la
joueuse) : le Grenier tient les vivres, l'Entrepôt les matériaux et les objets,
et les ressources d'une même réserve **se partagent** son plafond. Le surplus
ne rentre pas ; ce qui est rangé y reste. Trois points à ne pas défaire — **le
deben n'a aucun plafond** (sans quoi le plafond bloquerait la vente, seule
issue qu'il pousse à prendre), le plafonnement vit dans
`City::crediterRessources()` pour qu'aucun chemin ne l'oublie, et
`surplusRefuse()` s'interroge **avant** de créditer, l'information n'existant
plus après. Toute nouvelle source de ressources doit annoncer ce qu'elle perd :
un plafond silencieux est une règle qu'on subit sans comprendre.

**Rien de fabriqué ne se trouve sur une carte** (`Ressource::estFabriquee()`,
doc 08) : la poterie, les outils et les bijoux n'existent que par le travail ou
par l'import. Aucune région ne les déclare en ressource de zone, et deux tests
gardent l'invariant — l'un sur la déclaration, l'autre sur de vraies cartes
générées. **Le pain et la bière sont des vivres** : ce sont les deux formes
sous lesquelles l'Égypte consommait son grain.

**Un objet fabriqué vaut environ 165 % de ce qu'il coûte à produire**
(`PrixDuMarche::MARGE_DE_TRANSFORMATION`). En deçà, personne ne fabriquerait —
vendre brut irait aussi vite sans immobiliser l'Atelier ; au-delà, vendre brut
n'aurait plus jamais de sens. Toute recette ajoutée doit garder cette marge, et
c'est mesuré, pas supposé.

**La monnaie est le deben, jamais l'or** (`Ressource::Deben`,
`Ressource::estLaMonnaie()`). L'Égypte pharaonique n'a pas de monnaie frappée —
elle n'apparaît que sous domination perse puis chez les Ptolémées ; le Nouvel
Empire compte en deben, unité pondérale d'environ 91 g attestée par les ostraca
de Deir el-Médineh. **L'or est un métal qu'on extrait** (mines du désert
oriental et de Nubie, doc 08) et qu'on vend, au prix le plus élevé du jeu.
Confondre les deux, comme le faisait le code jusqu'au lot 4.0, faisait de la
mission 2 une carrière de monnaie.

Conséquence pour toute migration future : une ligne de **stock** `or` était de
la monnaie, un **gisement** `or` est une mine. Ne jamais convertir les deux
ensemble — voir `Version20260828140000`.

**La monnaie n'entre que par le Marché** (`Game/Marche`), la dotation royale
mise à part. Toute règle qui rendrait le Marché inatteignable fige la partie.

**La dotation royale se calcule sur les coûts réels des quatre bâtiments
d'ouverture** — Quartier d'habitation, Grenier, Marché, Entrepôt — jamais sur
des nombres recopiés (`DotationRoyale::coutDesBatimentsDouverture()`). Un coût
qui changerait dans le catalogue changerait la dotation avec lui ; l'inverse
laisserait une partie bloquée sans qu'aucun test ne le dise. Elle ne laisse
**aucune marge en matériaux** : les quatre bâtiments, et rien de plus.
`OuvertureDePartieTest` garde l'invariant de bout en bout — dotation, coûts du
catalogue et garanties de génération de carte doivent s'accorder, et chacun
peut être juste de son côté sans que l'ensemble le soit.

## Population, logement et rations

**La population se compte en trois nombres, jamais en individus** (décision de
la joueuse) : `City::$actifs`, `$enfants`, `$anciens`. Aucun habitant n'est
suivi un par un — ce qui compte est de savoir combien de bras la ville a et
combien de bouches. Le Quartier d'habitation ne peuple pas : il **plafonne**
(`20 × niveau` maisonnées, doc 01), et `City::manqueDeLogements()` dit au
joueur quand bâtir avant d'espérer un habitant de plus.

Trois règles à ne pas défaire :

- **Le bilan démographique tombe une fois l'an**, pas à chaque quinzaine, et
  c'est `PassageDeCycle` qui en décide le moment — au changement d'année, avec
  la crue. Laisser `Demographie` vérifier la date lui-même le ferait tomber dès
  le premier cycle d'une partie, où la ville vient d'arriver.
- **Chaque personne est tirée séparément** plutôt qu'un pourcentage appliqué à
  un total (`Demographie::tirer()`) : c'est ce qui permet de rester en entiers
  sans traîner de reliquat — un taux de 3 % sur douze actifs ne donnerait
  sinon jamais rien.
- **On naît, mais seulement s'il y a de la place.** `CHANCE_NAISSANCE_PAR_ACTIF`
  est nulle quand `manqueDeLogements()` — la ville ne déborde jamais de son
  logement, ce qui rend le plafond du Quartier lisible plutôt que théorique.
  Mesuré sur 200 parties de vingt ans : sans Quartier la population fond de 10
  à 5, avec un Quartier de niveau 1 elle monte à 13, et aucune ville ne
  s'éteint. Ne pas bâtir coûte des habitants ; bâtir en fait gagner lentement.
- **Faire venir des habitants passe par la renommée** (`PalierDeRenommee`,
  doc 13) : elle fixe le prix d'un appel et, à partir de « Respectée », fait
  venir des maisonnées toutes seules. Piège déjà payé : `ajusterRenommee()`
  n'était appelé de nulle part, donc la renommée restait à zéro pour toujours
  et toute règle indexée dessus était **inerte**. C'est le Marché qui l'alimente
  désormais (`Marche::RECETTE_DUN_GROS_CONTRAT`) — avant d'indexer une règle
  sur une valeur, vérifier qu'une source la fait bouger.
- **La renommée acquise appartient à la lignée, pas à la partie** (`Lignee`,
  `Lignees`, doc 13). Deux choses que le mot confondait : l'**acquis**, sur
  `Lignee`, un par joueur, qui ne descend jamais et que chaque nouvelle partie
  reçoit au lancement ; et la **jauge de la mission**, sur `Family`, qui bouge
  librement, à la baisse comprise, et reste propre à sa partie. Un seul point du
  jeu écrit dans la lignée — `AchevementDeMission`, à la clôture d'une mission
  de campagne — et il ne fait que la relever. C'est ce qui concilie « une jauge
  persistante d'une mission à l'autre » et « deux parties menées de front ne se
  volent pas leur renommée » : elles lisent le même acquis, chacune a sa jauge.
  Le **legs ne porte donc plus de renommée** : il en donnait quatre points au
  plus, depuis zéro, et aurait compté deux fois la même réussite.
- **Les affaires de l'esprit rapportent, sous plafond** (doc 13) : +1 par énigme
  résolue (`Enigme::RENOMMEE_POUR_UNE_RESOLUE`), +2 par enquête
  (`Enquete::RENOMMEE_POUR_UNE_RESOLUE`), dans la limite de
  `Family::RENOMMEE_MAX_DES_AFFAIRES` **par mission**. Le plafond n'est pas un
  détail d'équilibrage : la renommée traversant la campagne, dix missions où
  l'on résout tout dépasseraient les cent points de l'échelle, et la jauge ne
  mesurerait plus une réputation mais l'assiduité à deux mini-jeux. Il ne borne
  que ces deux sources — un plafond posé sur la jauge elle-même plafonnerait
  les quatre autres. `crediterUneAffaireResolue()` rend **ce qui a réellement
  été versé** : au plafond, l'écran doit se taire plutôt qu'annoncer zéro.
- **La renommée infléchit les prix par un facteur qui existe déjà**
  (`AvantageDeNegoce`, doc 13) : −0,2 % par point à l'achat, la majoration
  symétrique à la vente, tout en points de pourcentage entiers. Elle **entre
  dans** l'avantage du Négociateur côté commerce, et **s'ajoute au** coefficient
  de qualité de direction côté Marché — jamais un troisième multiplicateur,
  jamais deux divisions entières enchaînées, qui perdraient des deben à chaque
  étape sans que personne sache l'expliquer ensuite (discipline du lot 6.3). Le
  plafond de `AvantageDeNegoce::PLAFOND_TOTAL` porte sur la **somme** de toutes
  les sources : sa valeur vient de `PRIX_MINIMUM_A_LACHAT`, qu'un avantage de
  cinquante ramènerait au cours local — importer ne coûterait alors plus rien de
  plus que produire sur place, et la distance cesserait de peser.
- **Le carnet de contacts ne se persiste pas** (`CarnetDeContacts`, doc 13) : il
  se déduit des missions accomplies, comme les partenaires se déduisent du
  catalogue — seule la clé compte, le nom, la région et les ressources sont du
  contenu. Un contact vaut +2 **sur ce que sa région porte en gisement**, et sur
  rien d'autre : sans cette restriction ce serait une remise générale de plus.
  Il entre dans `AvantageDeNegoce`, donc sous le plafond commun, ce qui oblige à
  compter l'avantage **marchandise par marchandise**. La ville de la mission en
  cours n'est jamais un contact.
- **Une route déjà armée s'ouvre à −20 % et porte +10 %**
  (`Commerce::RABAIS_DUNE_ROUTE_HERITEE`, `VOLUME_DUNE_ROUTE_HERITEE`, doc 12).
  Le document donne **deux** effets à l'héritage commercial, pas un : « un accès
  facilité » porte sur ce qui passe autant que sur ce qu'il en coûte d'ouvrir. À ne pas confondre avec le carnet : celui-ci porte sur les prix
  courants, l'héritage sur le droit d'entrée. Il se déduit des autres parties du
  joueur ; la partie en cours ne s'hérite pas elle-même, et une partie abandonnée
  ne lègue rien — elle est supprimée, comme pour les deben et la renommée.
- **Le bonus de départ s'ajoute à la dotation sans la remplacer ni la dépasser**
  (`BonusDeDepart`, doc 13) : 20 deben et 5 unités par mission accomplie, hors
  celle qu'on lance. Le premier membre de la règle garde chaque mission jouable
  seule ; le second garde au don du roi son rôle de socle — neuf missions
  vaudraient sinon plus que la dotation entière. Le plafond se lit **sur la
  dotation elle-même**, ressource par ressource : il n'y a rien à calibrer, et
  il suit tout changement de coût des bâtiments d'ouverture. Les **vivres en
  sont exclus** — la dotation les taille sur la consommation réelle de la
  maisonnée envoyée, et un forfait par-dessus casserait ce calcul.
- **Le danger est un attribut de case, pas un contenu** (`Zone::estGardee()`,
  `Bandits`, doc 02 et doc 03). Une case garde son gisement **et** porte des
  bandits : c'est le filon gardé, et c'est lui qui donne une raison de lever des
  Medjaÿ. Une case gardée ne s'exploite ni ne se sème tant qu'elle l'est ; une
  case pacifiée le reste. **L'anneau des huit cases autour de la ville en est
  exclu** — le générateur y garantit un gisement de chaque matériau vital, et
  une bande dessus rendrait la partie injouable au premier cycle. Le nombre de
  bandes suit `partieEntiere(difficulté ÷ 2)` : le tableau de poids du doc 02
  décrit un tirage de contenu, que le danger n'est pas. Chaque bande renforce
  toutes les autres de 15 % (doc 03), **en centièmes entiers** — donc nettoyer
  une case affaiblit toute la région.
- **Un Medjaÿ n'est pas un `Employee`** (`Medjay`, doc 03). Le chef a une
  compétence, un salaire négocié, une spécialité tirée et une maisonnée ; le
  Medjaÿ a une force, une spécialisation et une expérience gagnée au combat. Les
  deux n'ont en commun qu'un salaire, ce qui ne suffisait pas à en faire une
  seule table. Deux spécialisations, jamais trois : l'arc et le bouclier sont
  leur armement attesté, le char appartenait à la *mesha*, l'armée d'État.
- **Le frein à la troupe est double** : l'effectif tenu par la Caserne
  (`3 + 2 × niveau`, doc 01) et l'entretien, qui rejoint la masse salariale
  comme n'importe quel autre homme payé. Le second compte autant que le premier
  — une troupe impayée mécontente la ville comme des chefs impayés. **Un blessé
  est payé aussi** : on ne renvoie pas un homme parce qu'il s'est fait blesser à
  son service, et il **garde son expérience**, ce qui le distingue d'un mort.
- **L'arme est durable, et sa qualité se fige à la remise** (`Equipement`,
  doc 03 et doc 01). Ce qu'on dépense en armant un homme est la pièce
  elle-même, prise au stock — jamais une consommation par combat : la Forge est
  un palier à franchir, pas un robinet à tenir ouvert. Monter la Forge ensuite
  n'améliore pas ce qui est déjà donné, il faut réarmer. **Un homme sans arme
  part quand même**, à `QUALITE_SANS_ARME` : aucune chaîne de production ne
  décide du rythme militaire, et une carrière gardée ne reste jamais imprenable
  faute de cuivre. La force d'un Medjaÿ croise trois facteurs — base,
  expérience, arme — en **une seule division** : deux divisions enchaînées
  perdraient de la force à chaque étape (discipline du lot 6.3). L'Armurier
  n'entre pas dans la qualité : il bonifie déjà la production d'armes, et deux
  effets pour une spécialité seraient un de trop.
- **Le combat se résout d'un bloc, sans écran de bataille** (`Combat`, doc 03).
  Le joueur agit **en amont** — qui lever, comment les armer, sous quel dieu —
  et la sortie se joue en une fois : c'est ce qui la rend compatible avec le
  principe fondateur du jeu, où rien ne se joue en temps réel. Le récit rendu
  porte les **scores et les chances** : une défaite qu'on ne comprend pas se
  subit, une défaite dont on voit qu'on partait à trois contre vingt s'apprend.
- **Sekhmet décide de l'issue, Isis des pertes** (doc 03, doc 07). Sekhmet
  infléchit le score (±10 %) ; Isis réduit la **mort permanente** de 25 % à
  Favorable, 50 % à Dévoué, et ne touche ni les blessures ni l'issue. Les
  confondre effacerait la distinction que le doc 07 pose en toutes lettres.
  **Depuis ce lot, aucun dieu du panthéon n'est sans emploi.**
- **Les boucliers ne se cumulent pas.** La réduction de pertes du fantassin
  vaut une fois pour la troupe, jamais une fois par homme : dix fantassins ne
  rendent pas une troupe invulnérable, et sans cette borne lever du fantassin
  en nombre annulait toute perte.
- **On n'attaque pas d'un bouton : on y mène une expédition.** Le Chef
  d'expédition est le seul rôle qui parte en armes, le seul à pouvoir viser une
  case tenue — les autres la refusent —, et le combat se résout **à son
  arrivée**. C'est ce qui lui donne son emploi propre : il faisait jusque-là le
  travail d'un éclaireur pour cinq fois le prix.
- **Le pillage des convois est un système inventé, ancré sur le danger de la
  région** (`Commerce::risqueDePillage()`). Aucun des seize documents ne décrit
  de perte de convoi ; il a fallu l'inventer pour que la Caserne tienne sa
  promesse de « protection des caravanes ». Le risque suit les bandes **encore
  tenues** — donc nul dans une région qui n'en porte pas, et décroissant à
  mesure qu'on nettoie. La garnison le couvre du seul fait d'exister. **La
  tension est voulue** : les hommes qui délogent une bande sont ceux qui
  couvrent les routes, et une sortie coûteuse en blessés les découvre.
- **Le Charrier se loue, il ne se recrute pas** (`Charrier`, doc 03, doc 01).
  Les Medjaÿ formaient un corps de sécurité intérieure — arc et bouclier
  attestés ; le char appartenait à la *mesha*, l'armée d'État. Le jeu porte
  cette distinction jusque dans son schéma : les chars vivent sur l'expédition,
  n'ont pas d'entité, ne rejoignent jamais l'effectif, ne coûtent aucun
  entretien et disparaissent avec la sortie. Ils pèsent au combat et n'en
  ressortent jamais blessés — ce sont les hommes du pharaon, pas les nôtres.
- **Un Instructeur n'aiguise que sa spécialisation** (doc 03) : celui du
  bouclier ne fait pas mieux tirer les archers. C'est ce qui donne un sens au
  choix entre les deux à l'embauche.
- **Bagarreur a deux moitiés** (doc 03) : bonus de combat en poste à la Caserne,
  malus de compétence partout — les Medjaÿ n'étant pas des employés, « affecté
  aux Medjaÿ » se lit « en poste à la Caserne ». Un chef qui se bat bien dirige
  mal ailleurs, et c'est ce qui rend le trait intéressant.
- **En Aventure, le règne se déduit du cycle** (`SuccessionDesRegnes`, doc 14).
  Les durées sont du contenu, la somme est connue d'avance : rien ne se
  persiste, et allonger la liste jusqu'à la fin du Nouvel Empire ne demandera
  aucune migration. **Rien ne doit supposer qu'elle s'arrête à un pharaon
  nommé.** Un règne ne se convertit pas année pour année mais par **catégorie de
  longueur** (`LongueurDeRegne`) : l'ordre relatif est respecté sans faire
  passer une durée de jeu pour une donnée historique.
- **Memphis a ses propres débouchés, et ils suivent le règne**
  (`CataloguePartenaires::pourMemphis()`, doc 14). Un **socle** — le Delta et
  Thèbes — vaut sous tous les règnes : sans lui, un pharaon tourné vers
  l'intérieur laisserait la ville sans commerce, ce qui est exactement le défaut
  que ce lot corrige. Le reste suit le pharaon, chaque relation étant attestée
  pour son règne. C'est ce qui fait de la succession autre chose qu'un habillage.
- **Les deux modes alimentent la lignée** : la campagne à l'achèvement d'une
  mission, l'Aventure à chaque fin de règne. La renommée appartient à la
  famille, pas au mode — l'asymétrie d'avant venait d'un manque de jalon, non
  d'une règle. `Lignees::encaisser()` ne sait pas ce qui s'achève, seulement
  qu'il faut relever l'acquis : **c'est l'appelant qui décide du jalon**.
- **Un mort emporte son expérience, un blessé garde la sienne** (doc 03). C'est
  le vrai enjeu de la perte définitive — pas le coût du recrutement, mais tout
  ce qu'il faut réapprendre.
**La consommation se compte en demi-rations** — deux par actif, une par
inactif — et ne se convertit en vivres qu'une fois, à l'échelle de la ville
(`Population::vivresPourDemiRations()`). Jamais de 0,5 en circulation, jamais
d'arrondi groupe par groupe.

**Un champ ne nourrit qu'à sa récolte** (`EtapeDeChamp::Recolte`), jamais
pendant le semis, la pousse ou le repos. Un champ du Nil suit la saison
(`RendementDesChamps` — Akhèt et Perèt rendent 0, seul Chémou moissonne) ; un
champ terrestre (Fertile, Oasis) suit son propre compteur, indépendant de la
saison (`CycleAgricoleTerrestre`, `Zone::quinzainesDepuisSemis`).

## Le territoire : filons, champs et prospection

**Un filon épuisé se ferme de lui-même** (`Gisement::fermer()`, appelé par
`Recoltes`). Tant qu'il restait « en activité » sur un vide, il **retenait son
équipage** — qui manquait ailleurs — et le passage de cycle répétait « le
gisement est épuisé » à chaque quinzaine, indéfiniment. Le message tombe
désormais une fois, au moment de la fermeture. Le filon reste sur la carte : une
prospection y **retrouve la veine à coup sûr** (`CHANCES_SUR_UNE_VEINE_TARIE`,
seul cas à 100 % du jeu — l'épuisement doit coûter du temps et de l'argent,
jamais fermer une région), mais il faut ensuite rouvrir l'exploitation : on ne
rappelle pas des équipes qui sont parties.

**Le joueur doit savoir s'il produit** (`exploitationsParGouvernant()`,
`_exploitations.html.twig`) : une carrière ouverte, une carrière jamais ouverte
et une carrière épuisée se ressemblaient sur la carte, case par case, et rien
ne les réunissait. Le récapitulatif les range **sous le bâtiment qui les
gouverne** — champs au Grenier, carrières à l'Entrepôt, pêcheries au Port —,
avec pour chacune son état, ce qu'il reste au filon, son équipage et si elle
rend quelque chose **cette quinzaine**. Une exploitation ouverte sans un seul
bras ne produit pas, et le dit.

**Le poisson est la seule ressource renouvelable** (`Ressource::estRenouvelable()`,
décision de la joueuse) : `Gisement::extraire()` rend son plein sans décompter
et `estEpuise()` reste faux à jamais. Un Port coûte 50 or, 40 roseaux et
20 calcaire ; une pêcherie tarissable en aurait fait un piège sur une carte
qui ne porte qu'une case d'eau poissonneuse. Il se pêche depuis un Port, ne se
creuse jamais (`Exploitations::exploiter()`), et l'interface écrit
« inépuisable » là où les autres gisements affichent leurs unités restantes.

**Un filon épuisé n'est pas une impasse** (`Prospection`,
`RoleDExploration::Prospecteur`, décision de la joueuse) : on envoie sonder une
case **déjà reconnue**, et la fouille rouvre la veine tarie
(`Gisement::rouvrir()`) ou met au jour un filon neuf. Sans elle, la dernière
unité extraite fermait la production d'un matériau pour toujours, et épuiser
l'unique gisement d'argile d'une petite carte figeait la partie. Trois règles à
ne pas défaire : **le rayon gratuit ne vaut pas pour le prospecteur** — offrir
la fouille sous les murs de la ville ferait de l'épuisement une formalité —, un
départ qui ne peut rien rapporter est **refusé à l'envoi** plutôt qu'annoncé
puis déçu, et la prospection s'appuie sur
`GenerateurDeCarte::materiauxPossiblesSur()`, jamais sur une seconde table de
terrains : deux tables divergeraient, et l'une planterait des acacias en plein
désert.

**Toutes les cases ne se valent pas** (`Prospection::chancesSur()`, décision de
la joueuse) : rouvrir une veine **qu'on exploite encore** est certain — les
équipes sont sur place et savent où le filon s'est perdu, c'est le seul 100 % du
jeu et il récompense qui garde sa carrière en activité ; un filon abandonné se
retrouve à 75 %, une case déjà minéralisée livre du neuf à 45 %, une case vierge
à 20 %. Le terrain module ensuite — le limon d'une berge se lit à l'œil nu, le
sable enfouit —, sans jamais sortir de [5, 95] : ni bouton perdu d'avance, ni
promesse. Zéro est réservé au cas « rien à trouver », qui fait disparaître le
bouton.

**On ne propose jamais un départ qui ne peut rien rapporter.** Vaut pour le
prospecteur comme pour l'émissaire (`Enquetes::resteUnTemoignageARecueillir()`) :
tous les témoignages versés, l'émissaire ne ramènerait qu'un « rien appris de
neuf » payé trente deben. Le bouton disparaît plutôt que de mentir.

**Le Marché vend aux gens de la ville, l'Entrepôt vend au monde**
(`Marche::plafondDeLaQuinzaine()`, décision de la joueuse). Sans cette borne,
les deux faisaient doublon. Le Marché paie au cours de base, **sur l'heure**,
mais sa place n'absorbe que `population × niveau × 4` deben par quinzaine, et
le compteur repart à zéro au cycle suivant (`City::rouvrirLEtal()`). Le
commerce par routes paie 150 à 200 % du cours, sur de vrais volumes, contre le
délai d'un convoi. **Le plafond se vérifie avant le débit** : un lot repris au
stock repasserait par le plafond de réserve, et un Entrepôt plein le refuserait
— le joueur perdrait sa marchandise pour avoir visé trop gros. Et un lot trop
grand est **refusé**, jamais vendu à moitié.

## Mode d'essai, routes mutantes et pièges techniques

**Le mode divin est un outil d'essai, pas une fonctionnalité** (`ModeDivin`,
`User::ROLE_DIVIN`) : un million de chaque ressource, plafonds de réserve levés,
brouillard levé d'un geste, les dix missions ouvertes à la création. **Le rôle ne s'accorde qu'en console**
(`app:users:goddess`) — aucun écran ne le donne, et cacher un bouton n'est pas
une barrière : la route vérifie le rôle en plus de la propriété. Une partie
d'essai le dit en toutes lettres à l'écran, pour ne jamais se confondre avec une
vraie. C'est aussi **la seule chose du jeu qui défait un échec**, ce qui lui vaut
son écart au `JOUER` ci-dessous.

**Toute route qui modifie l'état d'une partie doit utiliser
`PartieVoter::JOUER`**, pas `VOIR` : `JOUER` refuse en plus une partie
`StatutDePartie::Echouee` (famine prolongée, `Subsistance`). `VOIR` ne
vérifie que la propriété — une action mutante gardée par `VOIR` seul resterait
jouable sur une partie déjà terminée. **Une seule exception, documentée** : la
bascule du mode divin, qui doit justement pouvoir remettre debout une partie
échouée — c'est souvent celle qu'on veut examiner.

Cinq pièges déjà payés, à ne pas refaire :

- **`or` est un mot réservé du SQL.** Doctrine échappe les noms à la création de
  table, jamais dans les `SELECT` qu'il génère ensuite. La colonne fautive
  n'existe plus — le lot 3.1 a remplacé les compteurs fixes par une table
  `ressource → quantité`, où `or` n'est qu'une valeur — mais le piège reste
  entier pour tout nouveau nom de colonne qui serait un mot-clé.
- **Les Voters ont changé de signature en Symfony 8** : `voteOnAttribute()` prend
  un quatrième paramètre `?Vote $vote = null`. L'oublier produit une erreur
  fatale au chargement, qui fait échouer jusqu'à `make:migration`.
- **Aucune valeur de jeu ne se compare en flottants.** L'avancement des chantiers
  se compte en dixièmes de cycle, parce que le facteur ×1,5 de la crue finirait
  par laisser un chantier bloqué à un cheveu de son terme.
- **L'ordre des garanties de génération compte**
  (`GenerateurDeCarte::garantirLesMinimums()`) : garantir les champs **avant**
  les matériaux vitaux, jamais après — une case cultivable garde sa vocation
  même si un gisement s'y ajoute ensuite (les deux coexistent), l'inverse est
  impossible (`Zone::poserUnContenu()` efface les gisements). Dans l'autre
  ordre, les garanties de matériaux pouvaient consommer les rares terres
  cultivables d'une petite carte avant que celle des champs ne s'exécute.
- **Un tirage n'impose jamais un gisement que le terrain dément.** Le plafond
  d'un seul exemplaire par matériau dans l'anneau de la ville peut ne laisser
  que le bois local comme possibilité ; si la case est de sable, aucun matériau
  n'y pousse et l'option « ressource » ne doit pas être proposée du tout. Un
  repli qui tirerait alors sans regarder le terrain plante des acacias en plein
  désert — défaut réel, payé.
- **Une garantie probabiliste n'est pas une garantie.** Quinze pour cent par
  case échouent plus d'une fois sur deux sur une grille 3×3 : la terre
  broussailleuse n'apparaissait qu'une fois sur deux au Delta. Toute règle du
  type « la région en porte toujours » se vérifie **sur les dix missions à leurs
  tailles réelles** (`Mission::tailleDeGrille()` vaut `3 + difficulté / 2`, pas
  `3 + difficulté`), et se conclut par un minimum forcé.
- **Un matériau vital passe devant un matériau de confort.** Sur une carte
  saturée, la garantie de bois local déloge un filon non vital plutôt que de
  renoncer (`GenerateurDeCarte::fairePlaceAuBoisLocal()`) — on joue sans or,
  jamais sans charpente. C'est le seul endroit du jeu où un gisement est retiré.
- **Un poids de tirage réduit doit être redistribué, jamais simplement retiré**
  (`GenerateurDeCarte::tirerParmi()`) : le total du tirage rétrécirait sinon,
  gonflant mécaniquement la part des autres options. En pondérant le poids
  « champ » par la distance à la ville, le poids perdu rejoint « vide », pas
  le néant — sinon « ressource » augmente artificiellement et peut saturer de
  gisements les rares cases cultivables d'une petite carte.

## Chefs, effectifs et recrutement

**Ce sont les chefs qui recrutent** (`Effectifs`, doc 05). Un bâtiment sans
chef ne réclame aucun travailleur, donc tourne au plancher : « sans chef, la
moitié » n'est pas une règle à part, c'est un cas de la formule générale
`0,5 + 0,5 × (réel / requis)`, comptée **en centièmes** parce qu'elle
multiplie des ressources à chaque quinzaine. **Rien ne s'éteint faute
d'employés** (décision de la joueuse) : embaucher est un investissement, pas
une taxe. Un chef pas encore en poste ne réclame rien, et les chefs sortent du
vivier de bras — ils ne s'encadrent pas eux-mêmes.

**Un chef ne crée jamais un multiplicateur de plus** (`EffetDeChef`) : sa
compétence module la **qualité de direction** d'un bâtiment, aux côtés de son
effectif, et c'est cette qualité qui pèse sur les productions. Deux invariants
à ne pas défaire — **un mauvais chef reste meilleur que pas de chef** (98 %
contre le plancher de 50 % d'un bâtiment désert), et **une spécialité sans
système d'accueil reste inerte et le dit** (`SpecialiteDeChef::agitDeja()`),
promettre un bonus qui ne s'applique nulle part tromperait le joueur au moment
même où il compare des candidats.

## Ce que la géographie d'une région décide

**Rien du Nil là où il n'y a pas de Nil** (`GeographieDeLaPartie`,
`Divinite::estSansDomaineIci()`). Quatre missions sur dix se jouent loin du
fleuve — Pount, Megiddo, l'Ouadi Hammamat, le Sinaï — et le jeu y annonçait une
crue chaque année, l'affichait dans la barre, accélérait les chantiers d'Akhèt
sous une inondation qui n'avait pas lieu, et laissait porter des offrandes à
Hâpi dans un désert. `GeographieDeRegion::connaitLaCrue()` existait et n'était
appelée de nulle part : le piège d'`ajusterRenommee()`, une règle écrite mais
branchée sur rien.

**Sobek suit la même règle sur l'eau en général** : il ne raccourcit que les
trajets par eau depuis le lot 6.3, et l'Ouadi Hammamat n'a ni mer, ni fleuve,
ni rien qui flotte.

**Aucune région n'est murée** (`CoherenceDesRegionsTest`) : le commerce est le
débouché de tout ce qu'on extrait au-delà de ses propres chantiers, et une
mission dont aucune route n'est atteignable joue avec un système en moins sans
que rien ne le dise. C'est arrivé — le Fayoum n'avait qu'un partenaire fluvial,
donc un Port, donc un point d'eau que sa géographie ne produisait pas. **Le
Fayoum a de l'eau** : le Bahr Youssef, branche du Nil, alimente le lac Moeris
sur la rive duquel Shedet est bâtie. Une mission est un jeu de paramètres —
bords, ressources, partenaires — dont chacun peut être juste isolément sans que
l'ensemble le soit ; c'est l'exercice d'`OuvertureDePartieTest`, appliqué à la
géographie.

Quatre points à ne pas défaire :

- **La géographie d'une partie ne se retrouve que par `GeographieDeLaPartie`.**
  Elle n'est jamais persistée — seule la mission l'est —, et chaque appelant qui
  refaisait le détour par le catalogue pour son compte finissait par l'oublier.
- **Un dieu sans prise ici refuse l'offrande**, là où `agitDeja()` se contente
  de prévenir : ce sont deux manques différents, et il ne faut pas les
  confondre. `agitDeja()` parle d'un système que le jeu n'a pas encore —
  **plus aucun dieu n'est dans ce cas depuis le lot 10.4**, mais le garde-fou
  reste pour celui qu'on ajouterait ; `sansDomaineIci` parle d'un dieu dont le
  domaine n'existe pas **dans cette région-là**, et celui-là refuse. Hâpi dans
  un désert n'aura jamais de crue à incliner, et encaisser serait prendre pour
  rien : il ne se manifeste pas non plus par la Providence, et ne compte pas
  parmi les dieux acquis.
- **Le bilan démographique tombe partout** : on naît et l'on meurt au Levant
  comme au Delta. Le sortir du bloc de la crue est ce qui évite qu'une région
  sans fleuve cesse de vieillir.
- **`|default(true)` ne teste pas l'absence** (piège payé) : le filtre Twig se
  déclenche sur une valeur *vide* autant que sur une variable absente, et
  `false` est vide — la crue restait affichée partout. Écrire
  `x is not defined or x`.

## Dieux, faveur et providence

**Le panthéon est du contenu, la faveur est de l'état** (`Divinite`,
`FaveurDivine`) : nom, domaine et effet d'un dieu vivent dans l'enum, seule sa
**clé** et la valeur de sa faveur sont persistées — comme les partenaires
commerciaux. Trois règles à ne pas défaire :

- **On démarre à 40, pas à 50.** Le doc 07 annonce un départ « neutre à 50 »
  tout en plaçant le palier Favorable à partir de 50 ; à la lettre, il
  offrirait huit bonus actifs à une ville qui n'a jamais mis les pieds au
  Temple. La partie chiffrée du document l'emporte sur sa phrase.
- **Une ligne de faveur naît au premier geste, jamais au lancement.**
  `City::faveurEnvers()` répond la constante pour un dieu sans ligne ;
  `suivreLaFaveurDe()` est le seul chemin par lequel une ligne existe.
- **Les bornes tiennent dans `FaveurDivine::ajuster()`**, pas chez ses
  appelants : offrande, fête, bénédiction et malédiction y passeront tous, et
  aucun n'a à vérifier l'échelle pour son compte.

**Le Temple est la seule limite de la dévotion** (`Temple`, doc 07 : « sans
plafond arbitraire indépendant »), et il en pose **deux, qui ne disent pas la
même chose** : combien de dieux la ville porte au-dessus du neutre (un par
niveau) et jusqu'où leur faveur monte (`50 + 5 × niveau`). La première fait de
la répartition des offrandes une stratégie, la seconde fait du palier Dévoué
une conquête — il demande un Temple de niveau 6. Sans Temple, on n'offre pas,
et la ville n'en est pas punie pour autant : ses dieux restent neutres.

**On offre en deben ou en ressources** (`Offrandes`, décision de la joueuse),
et la conversion passe par **le cours du Marché**, jamais par un second barème
— deux tables de valeurs finiraient par diverger, et l'une deviendrait la
bonne affaire à exploiter. Conséquence assumée : une région qui produit cher
honore ses dieux à moindre effort. C'est aussi le premier débouché du surplus
que le plafond de stock refuse. Deux gardes à ne pas retirer : une offrande
qui ne vaut pas un point est **refusée** plutôt qu'encaissée pour rien, et une
faveur déjà au plafond du Temple refuse l'offrande au lieu de la gaspiller en
silence.

**La faveur n'ajoute jamais un multiplicateur à une chaîne qui en a déjà un**
(`EffetDeFaveur`) — c'est la discipline du lot 6.3, héritée du double comptage
retiré au lot 4.5. Là où un facteur existe, elle **déplace ce qui l'alimente** :
Hâpi infléchit le tirage de la crue et non la récolte, Ptah **s'ajoute** au
facteur de saison des chantiers dans la même unité, Osiris raccourcit la
**jachère** d'un champ plutôt que d'en grossir la gerbe. Là où aucun facteur
n'existe, elle agit directement : Amon-Rê sur l'attractivité, Sobek sur les
trajets **par eau seulement** — la pêche passerait par la qualité de direction
du Port, ce serait le multiplicateur de trop, et c'est pourquoi l'effet annoncé
de Sobek a été réduit à la navigation.

**Un dieu favorable ne pénalise jamais une production** : l'hostilité se paie
par une crue moins généreuse ou par la fièvre, jamais par un malus de
rendement. Deux malus qui se multiplient sont ce qui a fait tomber la chaîne
alimentaire à 25 % au lot 4.4.

**Les fêtes sont datées par les sources, pas étalées sur l'année**
(`FeteCalendaire`) : Opet aux 2ᵉ et 3ᵉ mois de l'inondation, les mystères
d'Osiris au 4ᵉ — *Ka-her-ka*, dont les Grecs ont fait *Khoiak* —, la Belle Fête
de la Vallée au 10ᵉ, en pleine Chémou. Ne jamais déplacer une fête pour
l'équilibre du jeu. Le supplément d'offrande est **forfaitaire** (+10) et ne vaut
que pour **le dieu de la fête** : c'est le moment qui compte, pas la
générosité. Il s'ajoute **après** le seuil de l'offrande dérisoire — un jour
saint ne rend pas remarquable ce qui ne l'est pas. Et **une fête ne mène jamais
vers un dieu qui n'agit pas encore**, sans quoi le jeu inviterait à dépenser
pour rien au moment où il annonce un moment favorable.

**Une malédiction retarde et coûte, elle n'efface pas** (`Providence`,
décision de la joueuse) : jamais de perte définitive, jamais de bâtiment
détruit, **jamais d'échec de partie** — la famine reste la seule cause de
défaite du jeu. La règle vaut pour les dieux, non pour les hommes : depuis le
lot 10.4, **la mort d'un Medjaÿ au combat est la seule perte sans recours du
jeu**, et elle vient d'un risque que le joueur a choisi de courir, jamais d'un
événement qui lui tombe dessus. Un événement divin peut affamer la ville ; c'est alors la
famine qui conclut, à ses douze quinzaines. Aucun n'installe non plus un effet
permanent, qui se confondrait avec le palier lui-même.

**La famine est la seule source d'hostilité du jeu** — une ville qui ne se
nourrit plus ne nourrit plus ses dieux. C'est **la seule perte de faveur qui
franchit le plancher du neutre**, et elle ne frappe que les divinités déjà
engagées : ne jamais mettre les pieds au Temple ne coûte toujours rien. Sans
elle, toute la branche « malédiction » serait du code mort — le piège
d'`ajusterRenommee()` ne se repaie pas. Les quêtes ratées et les choix moraux
du doc 07 ajouteront leurs sources aux Phases 7 et 8.

**Une épidémie couche des bras, elle ne tue personne** (`Epidemies`, doc 07) :
elle retire une part des actifs pour quelques quinzaines, puis les rend. Elle
passe par le **canal existant** — le rendement d'effectif —, jamais par un
multiplicateur de plus : c'est ce qui laisse tenir le plancher de 50 % du
lot 4.5 même en pleine fièvre, et c'est mesuré. Deux points à ne pas défaire :
elle couche **au moins une paire de bras** (20 % de quatre actifs fait zéro, et
toute ville de début de partie aurait reçu un message sans conséquence), et
**une offrande à Sekhmet l'abrège pendant qu'elle dure** — la déesse qui envoie
la maladie est celle qui la guérit, ses prêtres étaient les médecins de
l'Égypte. C'est l'un des rares événements du jeu sur lesquels on peut agir au
lieu de les subir.

**Le Dévot et le chef pieux ne passent pas par la qualité de direction** : leur
effet n'est pas une production, donc ils se lisent comme le Négociateur, par
`EffetDeChef::chefSpecialise()` et `chefsPieux()`. Le **Dévot** du Temple ajoute
des points à chaque offrande ; un chef **pieux**, dans n'importe quel bâtiment,
allonge le délai de grâce avant qu'un dieu ne se détourne — sa maisonnée
entretient les rites. Le trait n'est pas une spécialité du Temple : un
contremaître dévot vaut ici autant qu'un prêtre.

**La négligence s'arrête au neutre** (`Negligence`, doc 07 : « décroissance
lente et naturelle, pas de chute punitive ») : cinq quinzaines de grâce, puis
un point par quinzaine, et **jamais sous `Divinite::FAVEUR_DE_DEPART`**. Seules
une quête ratée ou une malédiction feront descendre une faveur plus bas. Sans
ce plancher, une partie menée sans mettre les pieds au Temple finirait avec
huit dieux hostiles — punie pour n'avoir pas joué à ce système-là. Elle se
compte **dieu par dieu**, ce qui permet d'entretenir Ptah en laissant Sekhmet
filer, et le journal de cycle ne raconte que le **changement de palier** : un
message par dieu et par quinzaine noierait tout le reste.

**Un dieu sans emploi le dit** (`Divinite::agitDeja()`, `attente()`) : il ne
reste qu'**Isis**, qui attend le combat. Même règle que les spécialités de
chef — promettre un effet qui ne s'applique nulle part tromperait le joueur au
moment même où il choisit à qui donner. **Cette liste doit rétrécir, jamais
s'allonger en silence** : un test la verrouille, dieux et spécialités
ensemble.

## Mécontentement, salaires et emploi

**Le mécontentement a deux causes et un seul mécanisme** (`Mecontentement`) :
la faim et les salaires impayés mènent à la même colère, comptée une fois. Il
monte et se résorbe d'un cran par quinzaine — symétrie délibérée, qui interdit
le yo-yo sans rendre la remontée désespérée. Son malus de production est
**délibérément distinct du rendement d'effectif** : le plancher de 50 % vaut
pour le manque de bras, pas pour une ville en colère. Avant de toucher à ses
valeurs, vérifier que **la spirale se redresse encore** — c'est là que ce genre
de mécanisme casse, quand le malus empêche de produire de quoi lever sa propre
cause. La famine se lit à deux paliers : mécontentement à 4 quinzaines, échec
à 12.

**Les salaires tombent à chaque quinzaine, avant la production** (`Salaires`,
`Paie`). C'est la première charge récurrente en deben, et la principale — une
quinzaine de salaires coûte plus qu'un Grenier. **L'unité de paiement est le
bâtiment ou l'exploitation entière, jamais l'homme**, et une unité impayée
**s'arrête** : elle rend donc moins qu'une unité vacante, qui tourne encore à
moitié. C'est assumé — le joueur a intérêt à renvoyer qui il ne peut plus
payer, ce qui lui donne une action à prendre plutôt qu'une spirale subie. La
paie circule dans le cycle (`Recoltes::avancerDUnCycle()` la reçoit) parce que
la recalculer après le débit donnerait un autre résultat.

**Rien ne travaille sans personne, y compris sur le territoire** (lot 4.5) :
un champ semé réclame un homme, un gisement deux, une pêcherie un. Chaque
exploitation a un **bâtiment gouvernant** — Grenier pour les champs, Entrepôt
pour les carrières, Port pour les pêcheries — dont le niveau élargit
l'équipage réclamé *et* le rendement, ce qui referme la boucle du jeu et rend
le niveau coûteux avant d'être payant.

**Un seul multiplicateur de rendement par chaîne de production.** Deux
planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout tourne au
moins à moitié » que la règle promet — c'est ce qui a fait retirer, au lot 4.5,
le modificateur que le lot 4.4 posait sur le stockage du Grenier, devenu un
double comptage dès lors que le Grenier gouvernait les champs. Avant d'ajouter
un multiplicateur à une production, vérifier qu'aucun autre ne s'y applique
déjà : `DemiRendementTest::testLaChaineAlimentaireNeDescendJamaisSousLaMoitie()`
garde l'invariant.

**Une offre d'emploi est persistée, une candidature non** (`JobOffer`,
`Candidat`). L'offre fige son tirage : sans cela, recharger la page relancerait
les dés jusqu'au cinq étoiles, et le choix entre deux ou trois candidats — le
cœur du doc 03 — n'aurait plus de sens. Retirer l'annonce est la seule relance,
et elle est explicite. **Seuls les chefs sont suivis un par un** (`Employee`) ;
les travailleurs se puiseront dans le vivier d'actifs, comme la population se
compte en nombres et non en individus.

**Un chef arrive avec sa maisonnée et repart avec elle** (`City::laisserPartir()`,
le pendant d'`accueillir()`). Sans le second volet, embaucher puis renvoyer
peuplerait la ville gratuitement et rendrait l'appel d'habitants inutile. Les
deux voies de peuplement butent d'ailleurs sur le même verrou :
`manqueDeLogements()`.

## Écriture, énigmes et enquêtes

**Les hiéroglyphes du jeu sont vrais** (`SymboleHieroglyphique`, doc 10) : vrai
code de Gardiner, vrai glyphe Unicode, glose fidèle. L'objectif pédagogique du
doc 10 en dépend — un signe inventé pour les besoins d'une énigme trahirait le
propos du projet. Ne jamais ajouter un signe sans son code ni son sens attesté.

**Deux pistes d'écriture, qui ne se mélangent jamais** (`SymboleHieroglyphique`
et `SigneAlphabetique`, doc 10). La **clé de lecture** porte des logogrammes —
un signe, une chose — et sert à lire les inscriptions du fil rouge.
L'**alphabet des scribes** porte les vingt-quatre unilitères — un signe, un son
— et sert à écrire. Six dessins leur sont communs, et c'est le propos : `N35`
est « l'eau » dans la clé et le son *n* dans l'alphabet, `X1` est « le pain » et
le son *t*. **Ne pas dédupliquer** : les confondre enseignerait le contraire de
ce que le document veut faire comprendre, à savoir que l'écriture égyptienne est
mixte.

**Trois dessins servent aux deux tables, et c'est la leçon** — le roseau, le
pain et la bouche (`SymboleHieroglyphique::sonDeLAlphabet()` et son pendant
`SigneAlphabetique::dessinDeLaCle()`). L'écriture égyptienne est mixte : un même
signe y sert tantôt à montrer une chose, tantôt à noter un son, et la bouche en
est le cas le plus net — elle dit « bouche » et elle note le *r*. Les deux
tables se **relient** donc à l'écran plutôt que de laisser croire à une redite,
et **le lien se fait par le glyphe**, jamais par une table de correspondance
qui finirait par diverger.

**L'eau, c'est trois ondulations, pas une** (défaut réel, payé) : `N35` — une
seule ondulation — est le phonogramme *n* et ne veut pas dire « eau » ; le mot
s'écrit `N35A`. La clé portait le code de l'un tout en décrivant l'autre, et
enseignait donc un signe faux dans un jeu dont c'est l'objet d'enseigner les
vrais. Conséquence de méthode : **un code de Gardiner peut porter un suffixe de
variante** (`N35A`, `C10A`), et toute vérification de format doit l'accepter.

**L'alphabet ne se persiste pas** (`AlphabetDesScribes`) : il n'ouvre que par
le niveau de la Maison des scribes, `3 × niveau`, ce qui tombe juste sur
vingt-quatre au niveau 8. Une colonne dupliquerait l'état du bâtiment. Ni le
Déchiffreur ni Thot n'y touchent — leur effet est écrit pour la clé, et
l'étendre doublerait un bonus que rien ne demande. Les **quatre signes de
Niout** sont connus d'emblée, comme les quatre de la clé et pour la même
raison : la leçon fondatrice doit être tentable tout de suite.

**La leçon fondatrice se retente, et ne se monnaie qu'une fois**
(`LeconDeNiout`) : remettre quatre signes dans l'ordre est un **exercice**, pas
une devinette — la règle « on ne répond qu'une fois » vaut contre quatre
propositions qu'on épuiserait, pas contre vingt-quatre arrangements. Elle **ne
touche pas au fil rouge** : `FilRouge::acte()` se déduit des inscriptions lues,
et y greffer l'alphabet mêlerait les deux pistes.

**Écrire un nom est la convention des musées, pas de l'égyptologie**
(`TranscriptionDuNom`, doc 10). Les voyelles reçoivent les semi-voyelles — le
vautour pour *a*, le roseau pour *i* et *e*, le poussin pour *o* et *ou* —
parce que s'en tenir aux consonnes rendrait le résultat illisible ; et le *l*
s'écrit avec le *r*, l'égyptien du Nouvel Empire ne les distinguant pas.
**Aucun signe n'est inventé pour boucher un trou** : un caractère sans
équivalent est écarté, et l'écran dit lequel. Il dit aussi, en toutes lettres,
que ce n'est pas une traduction — un scribe du Nouvel Empire n'aurait pas écrit
les voyelles du tout.

**La stèle n'est pas l'inscription qu'on déchiffre** (`SteleHistorique`,
doc 09). Chaque pharaon commanditaire a laissé une stèle réelle, nommée et
située à l'écran ; les dalles du jeu restent des **rébus** — signes vrais,
combinaisons inventées — et la stèle est ce à quoi elles font écho. Les
confondre laisserait croire au joueur qu'il lit de l'égyptien. **Ce qu'on
affiche est un résumé, jamais une citation** : la contrainte est de droits
autant que d'honnêteté, et vaut aussi pour les traductions anciennes tombées
dans le domaine public. Un test refuse tout texte entre guillemets. Enfin, **un
papyrus n'est pas une stèle**, et l'écran ne le dit pas — le doc 09 le signale
lui-même pour le grand papyrus Harris.

**Un cartouche ne s'écrit pas avec le seul alphabet** (`CartoucheRoyal`), et
c'est ce qu'il enseigne : il mêle unilitères, bilitères et logogrammes entiers,
le disque solaire valant « Rê » à lui seul — écrit en tête par déférence, lu à
la fin. C'est le **nom de trône** qui est montré, pas le nom de naissance dont
« Ramsès » et « Thoutmôsis » sont les formes grecques. **Les dix missions ont
le leur**, mais deux ont demandé deux sources concordantes avant d'être
retenus — un nom de trône composé se note avec des opérateurs de disposition
dont l'ordre de lecture n'est pas évident. Tant qu'un cartouche n'est pas
établi, **on n'affiche rien** : une approximation donnée pour réelle trahirait
la règle des hiéroglyphes vrais, l'absence ne trompe personne.

Deux cas portent leur histoire : **Ramsès IV a changé de nom de trône en cours
de règne**, et ses deux missions se jouant à l'an 3, c'est le second qui est
montré ; **Akhenaton porte deux fois le disque solaire**, son nom disant Rê
deux fois.

**Tout glyphe affiché porte `font-hieroglyphes`.** Aucun système d'exploitation
courant ne couvre le bloc égyptien d'Unicode : sans la police embarquée, un
joueur sous Windows ou Android verrait des carrés, et même une machine qui en
possède une donne à six signes une forme moins juste. La police est
**sous-ensemblée** aux seuls signes déclarés par les trois enums — clé de
lecture, alphabet et cartouches, 12 Ko contre 978 —, et se régénère avec
`.claude/scripts/sous-ensembler-hieroglyphes.sh`, qui lit les points de code
**depuis le code** plutôt que d'une liste recopiée : ajouter un signe sans
rejouer le script le laisse en carré vide, sans erreur ni avertissement.

**La clé de lecture s'enrichit par deux voies, et une seule est persistée**
(`CleDeLecture`) : ce que le **niveau** de la Maison des scribes ouvre se
calcule (`4 + 2 × niveau`), ce qu'une **énigme** apprend se stocke. Quatre
signes sont connus d'emblée — eau, homme, maison, marche — pour que la première
énigme soit tentable avant d'avoir rien bâti.

**Les signes sont vrais, les combinaisons sont du jeu** (`Inscription`) : une
inscription est un **indice en rébus**, jamais une phrase d'égyptien — la
langue a une grammaire, des phonogrammes et des déterminatifs que le jeu
n'enseigne pas. Dire lequel des deux on manipule évite de faire croire au
joueur qu'il apprend à lire l'égyptien.

**Une énigme ne punit jamais** (décision de la joueuse) : se tromper ne coûte
ni ressource ni cycle. Les deux cycles de retard du doc 10 valent pour une
**déduction d'enquête**, pas pour la lecture d'une pierre. Trois garde-fous à
ne pas défaire : on ne propose que ce que la ville sait entièrement lire, les
jetons sont **mélangés au rendu** (sinon la réponse se lit dans la source), et
une inscription ne se relit pas.

**Un corpus commun d'énigmes, pas un corpus par mission** (décision de la
joueuse). Le doc 10 les chiffre à cinq ou huit **par mission** ; le jeu en porte
onze, valables partout. C'est le **lieu** où on les entend qui les situe —
`Enigme::lieu()` réserve l'oracle au Temple et les devinettes de voyageurs à
l'Auberge —, jamais la région : une devinette sur la brique crue vaut au Delta
comme au Fayoum. Conséquence assumée : une partie qui enchaîne les dix missions
les épuise vers la deuxième. Écrire cinquante à quatre-vingts énigmes sourcées
est un projet de contenu à part, que l'enum accueillera sans rien changer.

**Une énigme à choix multiple ne se retente pas** (`Enigme`, `Enigmes`) : avec
quatre propositions et un droit de reprise, on essaie tout et il ne reste
qu'un formulaire. C'est la contrepartie de leur caractère facultatif — elles ne
bloquent rien, donc elles peuvent se perdre. **L'explication tombe dans les
deux cas** : le gain d'une énigme est ce qu'elle apprend, pas ce qu'elle
rapporte. Et chaque énigme **dit d'où elle vient** (`sourceAttestee()`) —
attestée ou écrite dans l'esprit des sources.

**Une enquête se démêle, elle ne se compte pas** (`Enquete`, `Indice`,
`DossierDEnquete`) : seuls les indices **concordants** rapprochent de la
conclusion, et une enquête porte toujours au moins un indice qui n'en est pas.
**Le joueur ne sait jamais lequel** — `NatureDIndice::libelleAffiche()` dit « à
vérifier » pour une fausse piste comme pour un indice de contexte ; afficher la
nature réelle résoudrait l'enquête à sa place.

**Le Déchiffreur, l'Oraculaire et Thot ne passent pas par la qualité de
direction** : leur effet n'est pas une production. Le **Déchiffreur** ouvre
deux signes de plus que le niveau du bâtiment, **Thot** un ou deux selon son
palier — les deux s'additionnent dans `CleDeLecture`. L'**Oraculaire** écarte
une mauvaise proposition **à l'affichage seulement** : la validation continue
d'accepter toutes les propositions du catalogue, une réponse écartée soumise à
la main étant simplement fausse. Faire mentir le serveur sur ce qui est une
réponse valide se paierait au premier écran qui l'oublierait.

**La clé de lecture dépend d'un chef en poste, donc d'un cycle.**
`CleDeLecture::pour()` et `Inscription::estLisiblePar()` prennent un cycle, par
défaut `0` — sans cycle, aucun chef n'est en poste et aucun bonus ne
s'applique. C'est le défaut sûr : un appel qui l'oublie sous-estime la clé, il
ne l'invente pas.

## Missions, fil rouge et rivaux

**Quatre natures de mission, et le type ne change aucune règle**
(`TypeDeMission`) : fonder, restaurer et développer, sécuriser, **exploiter**.
Il nomme ce qu'on vient faire, rien de plus — la mission 9 se joue comme les
autres, c'est sa géographie qui la distingue. Le doc 09 se contredit sur ce
point, sa section n'annonçant que trois types quand son tableau en donne un
quatrième à l'Ouadi Hammamat : **le tableau l'emporte**, un camp minier
temporaire n'étant ni une fondation ni un développement. Un test garde que les
quatre servent.

**L'ordre des missions se vérifie dans le lanceur, pas dans le formulaire**
(`Progression`, `LanceurDePartie`) : un POST forgé n'ouvre pas le Sinaï à qui
sort du Delta. Une **réussite partielle ouvre la suite** comme une pleine, et
l'on peut **rejouer** une mission déjà faite. Le mode d'essai ouvre les dix.

**Le legs s'ajoute à la dotation, il ne la remplace pas** (`Legs`) : une
première mission et une cinquième démarrent sur le même socle, ce qui garde
chaque mission jouable seule. Un legs qui changerait l'équilibre punirait une
réussite partielle deux fois. Il est **stocké sur la partie** — celle qui l'a
mérité peut être abandonnée ensuite, et ce qui a été donné reste donné.

**Un objectif atteint le reste** (`ObjectifDeMission`, doc 09) : une
trésorerie qu'on dépense, une population qui fond, une ressource qu'on vend ne
doivent jamais reprendre ce qui a été obtenu — le joueur serait puni d'avoir
joué. D'où deux compteurs **cumulatifs** sur la ville : `ressourcesRapportees`,
tenu dans `crediterRessources()` parce que c'est le **seul passage obligé**
(même raison que le plafond de réserve), et `valeurEchangee`, comptée au Marché
et **au retour d'un convoi** — au départ, la marchandise est engagée, pas
encore échangée.

**Chaque type d'objectif doit avoir une mesure qui bouge**, et c'est vérifié
une par une (`ObjectifsDeMissionTest`), jamais déclaré par un drapeau : c'est
le garde-fou contre le piège d'`ajusterRenommee()`. **Les seuils sont
recalibrés sur l'économie mesurée**, pas recopiés du doc 09 — qui les a
chiffrés avant les Phases 4 et 5 et comptait encore en or.

**Une tablette d'ouverture n'emploie que les quatre signes connus d'emblée**
(`FilRouge::ouverture()`) : la clé de lecture repart de zéro à chaque mission,
et un tutoriel qui demanderait un bâtiment n'ouvrirait sur rien. Les stèles
finales en comptent cinq. **On ne ramasse que les indices de l'enquête qu'on
peut mener ici** (`Enquete::seMeneDans()`) — fil rouge de la mission, les deux
secondaires, celle du rival s'il est là ; et la tablette d'une autre mission ne
se lit pas.

**L'acte d'un fil rouge se déduit, il ne se stocke pas** (`FilRouge::acte()`) :
il découle de faits déjà vrais — l'inscription d'ouverture est-elle lue,
l'enquête principale résolue, la stèle finale relue. Une colonne « acte en
cours » finirait par diverger de ces faits, et cela ne se verrait qu'en partie.
Le fil rouge **ne court que sur la mission qu'il raconte** ; ailleurs, ses
inscriptions redeviennent ordinaires plutôt que de rester inaccessibles.

**Un rival rogne, il ne ferme rien** (`Rivaux`, `RivalCommercial`, doc 08) :
il prend 10 à 20 % du volume d'**une** route, jamais moins d'une unité par
convoi, et **s'en va de lui-même** si on le laisse faire. Les trois issues du
doc 08 sont toutes ouvertes — ignorer, payer, enquêter —, et ne rien faire en
est une. **C'est la renommée qui l'attire** : une famille obscure ne dérange
personne, ce qui fait de la renommée autre chose qu'un compteur qui monte.
**Une seule rivalité à la fois** (question laissée ouverte par le doc,
tranchée ici) : deux malus deviendraient deux choses à suivre, et l'enquête
qui en démonte un cesserait de dire lequel. Ses indices ne se ramassent pas
tant qu'aucun rival n'est là — on ne démonte pas un marchand avant qu'il
n'arrive.

**Une principale se rejoue, une secondaire se perd** (décision de la joueuse,
`Enquetes::conclure()`) : l'échec définitif d'une enquête qui porte le fil
rouge bloquerait la campagne, donc elle coûte deux cycles et se retente ; une
secondaire s'enterre, sans quoi conclure au hasard puis recommencer serait
toujours la meilleure stratégie. **Aucune ne retire de ressource** — le temps
est la seule monnaie d'une erreur — et **le dénouement se dit dans les deux
cas**.

**Un éclaireur va vers l'inconnu, un émissaire va vers les gens**
(`RoleDExploration::viseUneCaseInconnue()`) : le premier ne part que vers une
case jamais vue, le second seulement vers une case **déjà reconnue** — on ne
parle pas à des gens qu'on n'a pas trouvés. C'est ce qui donne à l'Émissaire un
emploi propre, lui qui faisait jusqu'ici le travail de l'éclaireur en trois
fois plus cher.

**Une case ne se fouille qu'une fois**, et **il faut une Maison des scribes**
pour qu'un indice aille dans un dossier. Un dossier naît au premier indice,
jamais au lancement de la partie — même règle que la faveur d'un dieu.
