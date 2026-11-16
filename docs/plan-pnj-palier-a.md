# Niout — PNJ, palier A : le monde commerçant qui bouge

**Statut : cadrage détaillé, rien n'est codé.** Suite de
[`plan-pnj-autonomes.md`](plan-pnj-autonomes.md) (§ 5.1, palier A). Ce document dit
**quoi** faire des partenaires commerciaux, **où** cela s'accroche dans le code
existant, **jusqu'où** cela peut peser sur le joueur, et **comment on le
vérifie**. Il se lit avant de toucher à `CataloguePartenaires`,
`PartenaireCommercial`, `Commerce` ou `AvantageDeNegoce`.

## 1. Ce que le palier A est — et n'est pas

Les partenaires (Byblos, Pount, Thèbes, le Delta…) sont aujourd'hui des
**fonctions pures** : un prix local, une fourchette, un empressement. Ils ne
savent rien du temps qui passe. Le palier A leur donne **une conjoncture** : un
marché qui a ses saisons, ses disettes, ses fêtes, et qui **répond** à ce que le
joueur y fait.

**Un correctif honnête sur le plan initial.** Le palier A n'a **pas besoin de
choix par score d'utilité** : un partenaire n'y *décide* rien, il *subit* et
*réagit*. De la V1 de `simulation`, on ne reprend ici que les **besoins** (une
pénurie, une abondance) et leurs **urgences**, ainsi que la **graine** et le
**« pourquoi »**. Le score d'utilité et l'inertie de projet arrivent au palier B,
quand des personnages commencent à *vouloir* quelque chose. Habiller en agent ce
qui n'est qu'une météo serait de la décoration — et coûterait des écritures.

**Ce que le joueur y gagne**, et c'est ce qui justifie l'écran : **regarder a une
valeur économique.** Savoir que Byblos manque de lin cette quinzaine, c'est savoir
où le vendre. Le monde n'est pas un fond d'écran, c'est de l'information jouable.

## 2. Ce que le code fait aujourd'hui (vérifié)

| Fait | Où | Conséquence pour le palier A |
|---|---|---|
| Un partenaire n'a **aucun état** ; seule sa **clé** est stockée sur `RouteCommerciale` | `PartenaireCommercial`, `RouteCommerciale` | Le contenu reste du contenu ; l'état est à créer, pas à greffer |
| Prix, fourchette et empressement se **déduisent** du cours local (`PrixDuMarche`) | `prixMaximumALaVente()`, `prixMinimumALAchat()`, `empressement()` | Un seul point d'entrée à moduler : la même fonction qui prend déjà un `$avantage` |
| **Le prix décide du volume**, pas de la quantité fixée : le joueur pose un prix, le partenaire répond par un empressement | `Commerce::quantiteAuDepart()` | La conjoncture agit sur le volume et la fourchette, **jamais en changeant le prix que le joueur a posé** |
| Le convoi porte **sa propre copie** de l'échange et débite au départ | `Convoi`, règle « un convoi parti est un engagement » | **Un convoi en route n'est jamais touché** par un changement de conjoncture |
| L'avantage de négoce a **un plafond unique de 40**, et la raison est chiffrée : à 50, le plancher d'import rejoint le cours local et la distance ne pèse plus | `AvantageDeNegoce::PLAFOND_TOTAL` | **Toute source nouvelle entre sous ce plafond.** C'est la contrainte qui dimensionne tout le reste |
| Un avantage est ajouté **au coefficient existant**, jamais un facteur de plus (« trois divisions entières perdent des deben ») | `AvantageDeNegoce`, `Commerce::avantageDeNegoce()` | La conjoncture s'**ajoute** comme `carnet` et `Négociateur`, elle ne se compose pas |
| `avantageDeNegoce(partie, ?Ressource)` **accepte déjà une ressource** : le carnet de contacts ne joue que sur celles de sa région | `Commerce::avantageDeNegoce()` | Un avantage **par ressource** a déjà son précédent |
| Un rival rogne un volume, **jamais sous une unité** | `Commerce::apresLeRival()` | Même plancher pour tout effet de monde |
| Le risque de pillage existe, borné, par bande de la région | `Commerce::risqueDePillage()` | Un événement « troubles » ne peut que **s'y ajouter, plafonné et affiché avant l'engagement** |
| **Aucune graine n'est stockée.** Tout le hasard passe par un `Randomizer` injecté, non semé en production | `Rivaux`, `Epidemies`, `TirageDeLaCrue`… | **Prérequis bloquant** (§ 4.1) : sans graine, une conjoncture déduite ne se rejoue pas |
| `CategorieDEvenement` a `Commerce`, `Rivaux`, `Royaume`, mais **rien pour le lointain** | `CategorieDEvenement` | Une catégorie à ajouter, avec son pictogramme |
| Cross-partenaire : Thèbes **vend** des tissus, Pount en **achète** (sous Hatchepsout) | `CataloguePartenaires` | Des boucles d'arbitrage existent déjà, bornées par les fenêtres de 150 %/200 % et par le coût des routes. **La conjoncture ne doit pas les élargir** |

## 3. Le principe : la conjoncture est un avantage signé

Une conjoncture est **un entier, par partenaire et par ressource**, de
**−20 à +20 points de pourcentage**, qui entre dans la chaîne de l'avantage de
négoce **comme une source de plus**, au côté du Négociateur, de la renommée et du
carnet.

- **Favorable** au joueur (le partenaire manque de ce qu'on lui vend, ou
  regorge de ce qu'on lui achète) : points positifs, **comptés dans le plafond
  commun de 40**. Un monde qui s'ajoute au plafond le rend caduc.
- **Défavorable** (le partenaire regorge, ou manque de ce qu'il vend) : points
  négatifs, qui **rognent** l'avantage acquis, jamais sous une fourchette
  nulle.
- **Ce qui change pour le joueur** : le volume qu'un prix donné obtient, et la
  limite haute ou basse acceptée. **Jamais** le prix qu'il a posé, ni un convoi
  parti.

Choix de conception, et sa raison : moduler l'**empressement** plutôt qu'inventer
des prix par partenaire, parce que le jeu a déjà arbitré ce point — *« les
fourchettes se déduisent, jamais d'une table par partenaire et par ressource »*.
Dix partenaires par vingt-cinq ressources feraient des centaines de nombres à
tenir ; une règle et une graine n'en demandent aucun.

**À trancher à l'implantation** : `AvantageDeNegoce::total()` borne aujourd'hui
ses sources à zéro au moins (`max(0, $autresSources)`). Accepter un avantage
**négatif** jusqu'à −20 touche tous les appelants et leurs tests ; l'alternative
est de ne porter que le positif et de rendre le défavorable par une **baisse
d'empressement** séparée. Penchant : l'avantage signé, plus simple à expliquer
(« −8 points sur le lin chez Byblos »).

## 4. Trois couches, dont deux se déduisent

La règle du projet — *« un état qui se déduit ne se stocke pas »* — tranche
l'architecture.

### 4.1 La météo : dérivée, jamais stockée

La conjoncture de base d'un partenaire pour une quinzaine est **une fonction
pure** de `(graine de la partie, clé du partenaire, ressource, quinzaine)` :
un bruit lissé sur les quinzaines, ramené vers zéro (la météo ne dérive pas à
l'infini), modulé par la saison.

Ce que cela donne : **aucune ligne en base**, un test de déterminisme trivial, un
rejeu exact de n'importe quelle quinzaine — et la possibilité de **prévoir** la
suivante (§ 7.3), puisqu'elle se calcule avant d'arriver.

**Prérequis bloquant : `GameSave` doit porter une graine**, posée au lancement.
Une migration, avec valeur de rattrapage pour les parties existantes
(`§` « Migration » de `database-standards` : défaut conservé, base de test
migrée, suite complète avant merge). Sans elle, le palier tout entier tombe sur
le même défaut que `Rivaux` : un tirage qu'on ne peut ni rejouer ni tester.

La **saison** se lit sur `Saison` (Akhèt, Perèt, Chémou) ; les partenaires
**fluviaux** (le Delta, Thèbes, Koush, Coptos…) suivent aussi la **qualité de la
crue** de l'année de la partie (`QualiteDeCrue`) : une crue forte rend le blé
abondant partout sur le fleuve. Un couplage que le joueur subit déjà chez lui,
et qu'il retrouve ailleurs — le monde est un.

### 4.2 Les événements : dérivés aussi, bornés

Un événement est un **fait discret, daté, de durée finie** (2 à 6 quinzaines),
tiré **par fenêtre** et non par itération : on découpe le temps en fenêtres de
`K` quinzaines, et pour chaque partenaire une fonction de la graine dit si une
fenêtre porte un événement, lequel, et quand il commence. **On ne rejoue jamais
l'historique pour connaître le présent** — c'est ce qui permet de tout déduire.

Un événement a une **cause lisible** (c'est le « pourquoi » de la V1) et un
**effet borné** (§ 5). Catalogue d'amorce — **tout est inventé, à dire inventé**
(`plan-pnj-autonomes.md` § 2), même quand le thème est attesté :

| Événement | Thème | Effet de monde |
|---|---|---|
| **Disette** | Le Levant et la Nubie connaissent des mauvaises années | +conjoncture sur les vivres qu'on lui vend |
| **Bonne récolte** | Inverse | −conjoncture sur les vivres, + sur ce qu'il vend |
| **Deuil de cour / avènement** | Un règne qui change ailleurs, comme chez nous (`SuccessionDesRegnes`) | Route **ralentie** : empressement plafonné, **jamais sous 50 %** |
| **Foire** | Un marché saisonnier | + empressement général, durée courte |
| **Troubles sur la route** | Une piste moins sûre | + risque de pillage, **plafonné** et **affiché avant** le départ |
| **Besoin d'un ouvrage** | Un chantier royal, une guerre qui appelle des armes | + conjoncture sur outils, armes, ou pierre |

Le **plancher de 50 %** est celui des épidémies (« des malades, jamais des
morts ») transposé : un événement de monde **ralentit**, il **ne ferme pas** —
exactement comme « un rival gêne, il ne ferme pas une route ».

### 4.3 La mémoire de la famille : la seule couche stockée

Deux petites choses ne se déduisent pas, parce qu'elles viennent **du joueur** :

- **La saturation**, par partenaire et par ressource : plus on lui vend de lin,
  moins il en veut. Elle monte quand un convoi **rentre** — dans
  `Commerce::faireRentrerLesConvois`, le seul point par où l'échange est acquis —
  et **s'érode** d'un tiers par quinzaine. C'est ce qui fait que **le monde
  répond** et qu'on ne vide pas une conjoncture favorable en un seul ordre
  permanent. C'est aussi le meilleur garde-fou contre l'exploitation.
- **La relation** de la famille avec la cité, de 0 à 100. Elle monte avec les
  convois menés à bien, **redescend vers un neutre — jamais en dessous** : ne rien
  faire est une décision, pas une faute. Elle ouvre un **petit avantage**
  (0 à 10 points au plus), **dans le plafond commun** et **sans cumul avec le
  contact** de la même région (`CarnetDeContacts`) au-delà de ce plafond.

Deux écritures par quinzaine et par route, un seul `flush`.

## 5. Les bornes — ce que le monde ne peut jamais faire

C'est la section à relire avant tout calibrage.

| Borne | Valeur | Pourquoi |
|---|---|---|
| Amplitude de conjoncture | **−20 à +20** points | Un monde qui pèse plus que la renommée pleine (20) écraserait ce que le joueur a bâti |
| Avantage total, monde compris | **≤ 40**, `AvantageDeNegoce::PLAFOND_TOTAL` | Au-delà, le plancher d'import rejoint le cours local et les routes ne coûtent plus rien |
| Plancher d'achat | **jamais sous 110 % du cours local** | Idem : c'est le chiffre qui rend l'import moins rentable que produire |
| Volume d'une route ralentie | **≥ 50 %** | Plancher des épidémies |
| Volume après tout effet | **≥ 1 unité** tant que la route porte quelque chose | Plancher du rival |
| Convoi en route | **jamais modifié** | Engagement pris |
| Pillage ajouté par un événement | **+10 points au plus**, affiché avant l'engagement | Le risque est existant et lisible ; il ne devient pas un piège |
| Effet sur ce que le joueur **possède** | **aucun** | Un PNJ rogne, il ne détruit rien |
| Un partenaire qui vend ce qu'il achète | **jamais** | Règle existante : une route ne devient pas une machine à arbitrer |

## 6. Ce que le joueur voit

Trois lieux, du plus rapide au plus profond, qui prolongent ceux du plan général.

### 6.1 L'étal (déjà là, enrichi)

`EtalsDesRoutes` montre aujourd'hui, pour chaque ressource, le plancher, le
plafond et **l'empressement que le prix posé produit**. On y ajoute, **à côté
de chaque ligne**, la conjoncture du moment et sa cause : *« Byblos manque de
lin — +12 »*, *« Foire à Thèbes — empressement relevé »*. Le joueur y comprend
**pourquoi** la même annonce rend plus cette quinzaine que la précédente. C'est le
lieu où le monde se voit **le plus utile**, donc le premier à bâtir.

### 6.2 Le récapitulatif de la quinzaine

Une catégorie nouvelle de `CategorieDEvenement`, « Au loin » — trois ou quatre
lignes au plus —, qui dit **ce qui a changé** et **ce qui s'annonce**. Elle se
construit à partir de **faits structurés**, jamais lus dans un texte stocké.

### 6.3 La fenêtre « Le monde »

Une fenêtre au-dessus de la carte (`plan-fenetres.md`) : la liste des cités
atteignables, une carte par cité — nom, distance, type de route, **relation**,
**conjoncture actuelle**, événement en cours, **les dernières nouvelles**. Un
bouton par prise : ouvrir la route, régler l'étal, et (A3) envoyer un présent.

**L'information se mérite**, comme au plan général :

| Cité | Ce qu'on en sait |
|---|---|
| **Route ouverte** | Tout, pour la quinzaine en cours |
| **Route ouverte + Négociateur à l'Entrepôt** | Et **la quinzaine suivante** : c'est ce que fait un Négociateur, voir loin |
| **Cité atteignable, route fermée** | Une **rumeur**, en retard de deux quinzaines, selon le niveau de l'Auberge (et du Port pour les routes d'eau) |
| **Cité hors de portée** | Rien |

L'**Auberge** n'a aujourd'hui que ses chefs et ses énigmes : lui donner les
nouvelles des voyageurs est un emploi neuf, cohérent avec ce qu'elle est. **À
confirmer** avec `interface.md` avant de poser quoi que ce soit.

Un test d'**accessibilité** s'impose dès la première maquette : une conjoncture
signalée par une couleur seule ne se lit pas (texte et icône, comme les niveaux
d'eau de la V1).

## 7. Les sollicitations (lot A3) — le monde parle au joueur

Sans prise, la fenêtre est un économiseur d'écran. Une **sollicitation** est une
demande ou une offre d'un partenaire, **datée, bornée, ignorable** :

- *offre* : « Byblos cède du cèdre à prix de faveur jusqu'à la quinzaine N » ;
- *demande* : « Thèbes réclame 40 de grain, la crue a été basse — elle paiera
  en grès » ;
- *occasion* : « Une caravane part pour Pount, il reste de la place ».

Règles, héritées de `QueteDeChantier` et de `Rivaux` : **ignorer est une réponse
valide** et ne coûte rien ; **une seule à la fois** par cité ; **aucune ne retire
de ressource sans accord** ; sa **récompense passe par les mêmes services** que le
commerce ordinaire, donc par le plafond commun.

Une sollicitation est un **fait stocké** (elle a un état : proposée, acceptée,
refusée, échue). C'est la troisième et dernière chose persistée au palier A.

## 8. Ce qui se persiste

| Entité (nom provisoire) | Contenu | Rattachement |
|---|---|---|
| `GameSave::$graine` | Une graine entière, posée au lancement | Colonne de la partie, migration avec rattrapage |
| `EtatDePartenaire` | `partie`, `cle`, `relation`, `saturations` (JSON `{ressource: niveau}`) | `GameSave`, cascade `remove` ; créée à la première route ouverte |
| `Sollicitation` (A3) | `partie`, `cle`, nature, ressource, quantité, échéance, statut | `GameSave`, cascade `remove` |
| `FaitDeMonde` | `partie`, quinzaine, type, partenaire, ressource nullable, quantité nullable | `GameSave`, cascade `remove`, **purge au-delà de 40 quinzaines** |

Points de vigilance :
- **La colonne JSON `saturations` porte un défaut `'{}'`** dans la migration : un
  défaut retiré ne casse rien à l'exécution et fait échouer un test sans rapport
  plus loin (incident déjà payé, règle `tests.md`).
- Tout est **rattaché à la partie** : rien ne référence `User`,
  `Security\SuppressionDeCompte` n'est donc pas touchée. Si l'on décide un jour de
  faire survivre la relation d'une cité **entre les parties**, elle ira sur
  `Lignee` et devra être ajoutée à la suppression explicite.
- `FaitDeMonde` porte des **faits structurés**, jamais du texte : le texte se rend
  en Twig. Une colonne de texte divergerait du fait qu'elle raconte.
- Un **`flush` unique** par quinzaine, comme le reste de `PassageDeCycle`.

## 9. Où cela s'accroche dans le cycle

La météo et les événements étant **dérivés**, il n'y a **presque rien à exécuter** :
ils se calculent à la lecture, par `ConjonctureDeMarche::pour(partie, partenaire,
ressource)`. Ce qui s'exécute dans `PassageDeCycle` :

1. au **retour d'un convoi** (déjà dans `Commerce::avancerDUnCycle`) : saturation
   et relation s'écrivent ;
2. **en fin de cycle**, après `Commerce` : l'érosion de la saturation, le
   retour de la relation vers le neutre, l'écriture des `FaitDeMonde` de la
   quinzaine, l'expiration des sollicitations échues ;
3. **avant le récapitulatif** : la catégorie « Au loin » se compose.

Le partenaire n'est donc **jamais** mis à jour pendant le départ des convois :
`fairePartirLesConvois` lit une conjoncture **déjà figée pour la quinzaine**, ce
qui évite qu'un convoi dépende de l'ordre d'exécution. L'ordre se documentera
dans `PassageDeCycle`, qui est le seul endroit où il se décide.

## 10. Comment on le vérifie

| Contrôle | Ce qu'il prouve |
|---|---|
| **Déterminisme** | Même graine, partenaire, ressource, quinzaine → même conjoncture ; deux graines → deux mondes |
| **Bornes** (§ 5) | Sur **tout le catalogue**, sur 200 quinzaines et plusieurs graines : aucune conjoncture hors de −20 / +20, aucun avantage total au-dessus de 40, aucun plancher d'achat sous 110 % |
| **Pas d'arbitrage nouveau** | Une commande console calcule, pour chaque couple (cité qui vend, cité qui achète) du catalogue, la marge d'un aller-retour **avec et sans** conjoncture ; l'écart maximal est affiché et **doit tenir sous un seuil posé avant d'écrire la règle**. Le cas Thèbes/Pount en est le témoin |
| **Chaque mesure bouge** | La saturation monte à un retour de convoi **et** s'érode ; la relation monte **et** redescend ; un test par compteur — jamais un drapeau déclaré (piège d'`ajusterRenommee()`) |
| **Un convoi parti reste intact** | On change la conjoncture entre départ et retour : le résultat est celui du départ |
| **Le plancher de 50 %** | Un événement « deuil de cour » ne produit jamais moins de la moitié, et jamais zéro |
| **Rejeu** | Rejouer une quinzaine passée donne les mêmes faits |
| **Navigateur** | L'étal, la fenêtre « Le monde » et le récapitulatif, **à 390 px et en desktop** (skill `verification-ui-navigateur`) |

Ce qu'aucun test ne dira : **si le joueur lit l'étal enrichi et en tire quelque
chose.** C'est la question du playtest (§ 11, après A2).

## 11. Découpage

| Lot | Contenu | Sortie | Verrou |
|---|---|---|---|
| **A0** | Ce document, les décisions du § 13 | Cadrage validé | — |
| **A1** | **Graine** sur la partie ; `ConjonctureDeMarche` (météo + événements dérivés) ; avantage signé dans `AvantageDeNegoce` ; tests de bornes, de déterminisme et d'arbitrage ; **commande de banc d'essai** | Un monde qui change **déjà** les volumes, **sans écran** | Bornes tenues sur 200 quinzaines |
| **A2** | Conjoncture dans l'étal ; saturation et relation ; `FaitDeMonde` ; catégorie « Au loin » | **Le premier écran utile** | **Playtest : le joueur s'en sert-il ?** |
| **A3** | Fenêtre « Le monde », rumeurs par l'Auberge, prévision par le Négociateur, sollicitations | Le monde se regarde | Playtest, mobile et accessibilité |
| **A4** | Calibrage sur parties réelles ; les valeurs du § 12 passent au § 6 du plan de bataille | Équilibre | — |

**A1 est livrable seul et laisse le jeu jouable** : sans écran, le monde existe
déjà et ne peut rien casser, le banc d'essai le prouve. **On ne démarre pas A3 sans
le résultat du playtest d'A2** : bâtir la fenêtre avant de savoir si l'étal
enrichi est lu serait payer le plus cher pour le moins vérifié.

## 12. Valeurs inventées — à calibrer

À reporter au § 6 du plan de bataille dès A1 :

| Valeur | Amorce | Ce qu'elle infléchit |
|---|---|---|
| Amplitude de conjoncture | ±20 points | Le poids du monde face à la renommée |
| Longueur d'une fenêtre d'événement `K` | 12 quinzaines | La fréquence des histoires |
| Probabilité d'un événement par fenêtre et par cité | 1 sur 2 | Idem |
| Durée d'un événement | 2 à 6 quinzaines | De quoi réagir sans y être forcé |
| Érosion de la saturation | un tiers par quinzaine | Combien de temps une bonne conjoncture se « vide » |
| Gain de relation par convoi mené à bien | +2 | La vitesse à laquelle une cité s'attache |
| Avantage maximal de la relation | 10 points | Sa place dans le plafond commun |
| Retard d'une rumeur | 2 quinzaines | La valeur de l'Auberge |

## 13. Questions ouvertes

| Question | Penchant |
|---|---|
| **Avantage négatif dans `AvantageDeNegoce::total()`**, ou baisse d'empressement séparée ? (§ 3) | Avantage signé |
| **Quelle graine pour les parties existantes ?** | Dérivée de l'identifiant de la partie, à la migration |
| **La relation survit-elle entre les parties** (`Lignee`), et nourrit-elle `CarnetDeContacts` ? | Non d'abord : une relation propre à la partie, le carnet reste statique |
| **L'Auberge porte-t-elle les rumeurs ?** (§ 6.3) | Oui, à confirmer avec `interface.md` |
| **Des événements propres à la Campagne** (un par mission) ou un seul catalogue ? | Un catalogue commun d'abord, des événements propres quand le playtest les réclame |
| **Une cité peut-elle durablement fermer** (guerre longue) ? | Non : ralentie, jamais fermée — mais c'est la question que le playtest rouvrira |
| **Les Medjaÿ répondent-ils à un événement « troubles » ?** | Oui, par l'**escorte**, ce qui répond à la question déjà ouverte au plan de bataille — **à cadrer avec `Commerce::risqueDePillage`** |
| **Et en Aventure (Memphis) ?** | `pourMemphis($regne)` rend des partenaires qui changent avec le pharaon : un changement de règne **retire des cités** de la liste atteignable. Que devient la relation avec elles ? **À étudier avec la succession** avant A3 |
