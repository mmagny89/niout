# Niout — PNJ, palier C : des cités qui se gouvernent seules

**Statut : cadrage détaillé, rien n'est codé, et le palier le plus loin du
calendrier.** Suite de [`plan-pnj-autonomes.md`](plan-pnj-autonomes.md) (§ 5.1),
de [`plan-pnj-palier-a.md`](plan-pnj-palier-a.md) et de
[`plan-pnj-palier-b.md`](plan-pnj-palier-b.md). **Dépend des deux**, et ne s'engage
qu'**après le playtest de B2** (§ 10). C'est ici que se réalise l'idée de départ —
*une civilisation autonome* — **sans que le jeu cesse d'être un jeu** : le joueur
reste au centre, des cités vivent autour de lui.

## 1. Ce que le palier C est

D'autres **familles**, qui possèdent chacune **une cité**, la font **croître, commercer
et décliner** selon leurs aspirations, **quinzaine après quinzaine, sans le joueur**.
Il les voit exister, les croise sur les routes, les compare, en tire ou leur donne
quelque chose.

Ce n'est **ni un mode spectateur de la ville du joueur** (écarté, § 1 du plan
général), **ni une copie de la ville du joueur** : c'est un **modèle abstrait**, plus
simple, dit simple. Raison de fond : une `City` complète par PNJ multiplierait les
écritures par quinzaine et les règles à tenir à jour, pour un détail que personne ne
verrait.

**Ce que C change pour A et B**, et c'est son vrai intérêt :

- Au palier A, la conjoncture d'un partenaire est **une météo déduite** d'une graine.
  Au palier C, elle devient **émergente** : Byblos manque de lin **parce que Byblos
  manque de lin**, et ses stocks le disent. L'étal du joueur ne change pas ; ce qui
  le nourrit, si.
- Au palier B, une personne lit le monde. Au palier C, **le monde contient les
  personnes** : le rival est le marchand d'une cité qui existe, le chef parti est
  l'habitant d'une cité où il travaille.

## 2. Ce que le code fait aujourd'hui (vérifié)

| Fait | Où | Ce que C en fait |
|---|---|---|
| Une **partie** porte **une** ville, une famille, une carte, un mode (Campagne, Aventure). Tout est rattaché par cascade | `GameSave`, `City`, `Family` | Les cités autonomes sont **rattachées à la partie**, comme tout |
| La **population** est un modèle de **cohortes** : actifs, enfants, anciens, avec des chances **entières** de naître, grandir, vieillir, mourir par quinzaine | `Population`, `Demographie` | **Le modèle à reprendre tel quel**, à l'échelle d'une cité abstraite : pas d'individus |
| La **subsistance** se mesure en rations, avec un seuil de famine (4) et d'échec (12) | `Subsistance` | Une cité abstraite a **le même seuil**, lu à la même source |
| L'**impôt** est de 1 deben par actif et par mois ; les **chefs** sont payés | `Impots`, `Salaires` | Idem : la **paie** est une règle du jeu, pas de la cité |
| Les **récoltes** suivent la **crue** (faible 20 %, normale 60 %, forte 20 %) et les saisons ; **sans Nil, pas de crue** | `TirageDeLaCrue`, `Recoltes`, `GeographieDeLaPartie` | Une cité **au bord du fleuve** suit la même crue que le joueur : le monde est un |
| Les **partenaires commerciaux** sont des **cités sans état**, par mission, et par règne à Memphis | `CataloguePartenaires` | **Les cités autonomes en sont l'état.** Le catalogue reste le contenu |
| Le **catalogue de la ville** donne le coût de chaque bâtiment par niveau | `CatalogueDeLaVille`, `CoutDeConstruction` | Un « niveau de développement » abstrait se **calcule** sur ces coûts, il ne les recopie pas |
| Le **fil rouge**, les énigmes et les enquêtes sont conçus pour **un humain** | `FilRouge`, `Enigmes`, `Enquetes` | **Hors de C** : une cité autonome ne les joue pas |
| La **succession familiale** : 60 cycles par génération (±20), héritiers déduits d'une graine | `SuccessionFamiliale` | Les familles voisines **ont des générations** (§ 6) |

## 3. Les principes propres à C

Ceux d'A et de B tiennent. C y ajoute :

1. **Une valeur du jeu n'a qu'un exemplaire.** Une cité abstraite lit **les
   constantes des règles** (naissance, rations, impôt, rendement des champs,
   marge de transformation), elle ne les copie pas. Si le joueur gagne un point de
   rendement, **les cités le gagnent avec lui**, sans qu'on y pense. C'est la règle
   que le jeu s'est donnée pour les prix (« les fourchettes se déduisent »), étendue.
2. **Abstraite, et on le dit.** Une cité autonome n'a **ni bâtiments, ni carte, ni
   travailleurs nommés** : elle a un **niveau de développement**, des **cohortes**,
   des **réserves**, un **projet**. On ne prétend jamais qu'elle joue comme le
   joueur ; on **ne lui donne pas de bonus caché pour qu'elle « tienne »**.
3. **Des cités qui déclinent, pas qui meurent.** « Des malades, jamais des morts » :
   une cité en famine **perd des habitants jusqu'à un plancher**, puis **stagne**. Elle
   ne disparaît pas de l'écran. Ce qui change au long d'une Aventure, c'est son
   **rang**, pas son existence (§ 6 pour la question de la fondation).
4. **Aucun effet direct sur la ville du joueur** autre que par **les routes et les
   personnes** : le commerce (palier A, ses bornes, son plafond de 40), les
   rencontres (palier B). Une cité ne **bâtit pas sur la carte du joueur** et ne lui
   prend rien. Elle vit **hors grille**, comme les partenaires.
5. **Pas de rubber-banding caché.** Si la croissance d'une cité doit suivre celle du
   joueur pour rester un voisin crédible, c'est **une courbe publique**, écrite dans
   le scénario (par mission, par règne), pas une correction silencieuse. Un monde qui
   triche en secret est un monde qu'on cesse de croire.
6. **Un moteur pur.** Comme la V1 de `simulation` (`avancer(monde, durée, données)`,
   sans DOM, sans réseau, sans horloge), **une cité se calcule par une fonction pure** :
   `avancer(cité, vue, graine) → cité + faits`. Doctrine n'y entre pas. C'est ce qui
   permet de faire tourner **cinq cents quinzaines en mémoire** pour calibrer, avant
   d'écrire une seule table.

## 4. Le modèle d'une cité

### 4.1 L'état

| Champ | Ce qu'il porte | D'où il vient |
|---|---|---|
| **Famille** | Un nom, un chef, un **trait de lignée**, une génération | `Heritier`, `TraitDeCandidat`, `PrenomEgyptien` |
| **Région et type** | Delta, vallée, désert, port, mine… | `GeographieDeRegion` : une cité **hérite** de la géographie, comme le joueur |
| **Cohortes** | Actifs, enfants, anciens | `Population` |
| **Niveau de développement** | Un entier, de 1 à N | **Calculé** sur les coûts du catalogue |
| **Réserves** | Vivres, matériaux, deben, **quelques** ressources caractéristiques | Un petit dictionnaire, JSON |
| **Projet** | Ce qu'elle entreprend, jusqu'où, depuis quand | Palier B (inertie) |
| **Aspirations pondérées** | Prospérité, prestige, sécurité, piété, loyauté | Palier B |
| **Relations** | Avec le joueur, avec chaque autre cité : **un entier** | Palier A § 4.3 |
| **Graine** | Dérivée de (partie, cité) | Palier A |

**Vingt-cinq ressources par cité sont de trop** : une cité abstraite ne garde que ce
qui **change ses décisions** — ses vivres, ses matériaux, son deben, et **les
ressources qu'elle vend ou achète au catalogue** (celles qu'un partenaire traite
déjà). Le reste n'existe pas pour elle.

### 4.2 Ce qui se passe à chaque quinzaine

La même boucle que le joueur, **en moins de gestes** :

1. **Subsistance** : rations contre habitants ; famine → perte de population jusqu'au
   plancher, avec un fait.
2. **Production** : d'après son niveau, sa géographie, la saison et **la crue de
   l'année** — c'est la même crue que celle du joueur.
3. **Démographie** : naissances, vieillissement, décès, **par cohortes**, avec les
   chances de `Population`.
4. **Décision** (palier B) : urgence ? projet en cours ? sinon score des actions
   possibles — **bâtir** (monter d'un niveau), **commercer** (offrir ou demander une
   ressource), **offrir aux dieux**, **armer**, **s'allier**, **s'entendre**,
   **attendre**.
5. **Effet** : appliqué à la cité, et seulement **au joueur par la route**, selon
   A.
6. **Faits** : écrits, avec leurs raisons.

La **succession** : tous les soixante cycles (±20), le chef change **comme celui du
joueur**. Une famille a donc un **rythme de génération** qui se lit sur l'écran.

### 4.3 Ce que les cités ne font pas

- **Pas de combat détaillé.** Une guerre est un **événement** (palier A, § 4.4) avec
  un **vainqueur** déduit de la force relative, un **butin** borné, un **terme**.
  Pas de Medjaÿ nommés, pas d'expéditions.
- **Pas de fil rouge, pas d'énigmes.**
- **Pas de mission.** Une cité n'a pas d'objectif de campagne, **seulement des
  aspirations**.
- **Pas de carte.** Une cité n'occupe aucune case.

## 5. L'économie : l'endroit où C peut tout casser

Une cité qui **possède de vrais stocks** change la nature du commerce du joueur : le
monde cesse d'être un robinet, il devient **une économie**. C'est l'intérêt, et le
danger.

| Risque | Parade |
|---|---|
| **Le joueur vide la cité** de cuivre, qui ne vaut plus rien ou n'existe plus | La conjoncture qui en découle est **une fonction bornée de ses stocks** : **−20 à +20**, quel que soit l'état. **L'interface d'A ne change pas** : `ConjonctureDeMarche::pour(partie, partenaire, ressource)` garde son contrat |
| **L'écrasement des prix** : on inonde une cité de lin pour la ruiner | La **saturation** d'A absorbe le choc ; une cité **ne descend pas sous son plancher de réserve** : elle cesse d'**acheter**, elle ne s'effondre pas |
| **L'arbitrage entre cités** (acheter chez l'une, vendre à l'autre) | Le banc d'essai d'A (« marge d'un aller-retour ») **s'étend** aux cités réelles ; **le plafond commun de 40 tient** |
| **Une cité qui s'enrichit sans fin** | **Courbe publique** par scénario (§ 3, 5) et mesure : **coefficient de Gini des richesses**, rang, nombre de cités qui stagnent |
| **Une cité qui ne décolle jamais** | Idem : une cité **sans croissance sur N quinzaines** est un défaut du réglage, pas du joueur |
| **Le joueur ruiné par l'effondrement d'une cité cliente** | Les convois partis **arrivent** ; **jamais la dernière route** (A § 4.4) ; la cité **n'est jamais vidée** (plancher) |

**Le banc d'essai est la condition d'existence de C.** Comme la V1 de `simulation`,
qui calibrait ses espèces **avant** son interface, C **commence par la fonction pure,
en mémoire, sur cinq cents quinzaines et plusieurs graines**, **sans un seul écran**.

## 6. Campagne, Aventure, et l'histoire longue

### 6.1 En Campagne

Les dix missions sont **ancrées dans des villes attestées** et des pharaons réels.
Les cités autonomes y sont **les voisins déjà nommés par le catalogue** : on
n'invente pas de nouveaux noms de villes. Chaque mission en reçoit **deux ou trois**,
**au plus**, parmi ses partenaires — c'est assez pour que la région vive, et peu
pour que le scénario reste lisible. Une mission dure une trentaine de cycles ; **une
cité n'a donc qu'une histoire courte**, qui se lit comme un contexte, pas comme une
saga.

### 6.2 En Aventure

C'est **là que C se justifie** : plus de deux cents cycles à Memphis, une succession
de règnes, et **aucune fin scriptée**. Les cités y traversent **les règnes** : elles
montent sous un pharaon qui leur est favorable, déclinent sous un autre, **changent
de chef** tous les soixante cycles. Le joueur y voit **une histoire qu'aucun document
n'a écrite**.

**Une frise** — un écran de **chronique longue** — en est la trace : *« Sous
Amenhotep III, Byblos prospère ; sous Aÿ, elle s'éteint »*, **dite inventée**.

**Question ouverte, la plus lourde du palier** : une cité qui décline **longtemps**
peut-elle être **abandonnée, puis refondée** par une autre famille ? Cela donne une
histoire riche, et **contredit** « des malades, jamais des morts » à l'échelle d'une
cité. Penchant : **non en C**, plafond de déclin et fin du récit à la **stagnation** ;
à rouvrir si le playtest d'Aventure la réclame.

### 6.3 Ce que C ne dit jamais

Une cité autonome **n'a pas de vérité historique** : sa prospérité et son déclin sont
**des événements de jeu**, **dits inventés**, même quand son nom est attesté. Le jeu
n'affirme pas que Byblos a connu une famine sous tel pharaon.

## 7. Comment le joueur en profite

C **n'ajoute pas** un type d'écran, il en **remplit** trois :

1. **« Le monde »** (A3) gagne l'onglet **Cités** : une fiche par cité — famille,
   rang, **santé** (famine, stagnation, essor), **projet en cours**, relation,
   **ce qu'elle vend et achète**, **le pourquoi** de sa dernière décision. Les
   routes ouvertes y sont directement reliées à l'étal.
2. **« Les annales »** : un classement **sans prétention de rang officiel** (par
   population, par richesse, par renommée), et **la frise des règnes** (§ 6.2).
3. **L'étal** (A2) montre une conjoncture **qui a une cause réelle** : *« Byblos manque
   de lin : sa famine a duré trois quinzaines »*.

**Des prises**, pour que « regarder » reste un geste de jeu : commercer (déjà là),
**aider** (un don en cas de famine, qui gonfle la relation), **s'allier**
(sollicitation d'A) ou **se brouiller** (le refus d'un accord, sans punition),
**envoyer un émissaire** (le rôle existe : *« un émissaire va vers les gens »*).

**L'information se mérite** comme en A et B : on ne voit **ni les stocks exacts ni
les raisons** d'une cité qu'on n'a pas atteinte. Une rumeur dit *« Byblos souffre »*,
pas *« Byblos a 43 de blé »*.

Vérifier à 390 px et en desktop ; **aucun état signalé par la couleur seule**.

## 8. Ce qui se persiste

| Entité (nom provisoire) | Contenu | Rattachement |
|---|---|---|
| `CiteAutonome` | `partie`, `cle` (identité de cité, A), `famille` (nom, chef, trait, génération), cohortes, `niveau`, `reserves` (JSON, défaut `'{}'`), `projet` (JSON, défaut `'{}'`), `relations` (JSON `{cité: entier}`, défaut `'{}'`) | `GameSave`, cascade `remove` |
| `FaitDeMonde` *(posé en A)* | Gagne `cite` nullable | Inchangé ; purge à 40 quinzaines |
| `Annale` (optionnel) | Une ligne **par règne** et par cité : rang, population, richesse | `GameSave`, cascade `remove` ; **trois mesures seulement**, pour la frise |

Points de vigilance :

- **Une cité se crée paresseusement**, à la première fois que le joueur **atteint**
  son partenaire. Un partenaire non atteint reste **dérivé** (le palier A), **un
  niveau de détail plus bas** : c'est la technique classique du **niveau de détail** —
  on **simule de près**, on **déduit de loin**. Le coût du palier C est donc **borné
  par ce que le joueur touche**, non par ce que le catalogue contient.
- **L'état d'une cité tient en un petit objet JSON** ; l'ensemble pèse quelques
  centaines d'octets par cité, **un `flush`** par quinzaine.
- **Les colonnes JSON portent un défaut** dans la migration (règle `tests.md`).
- **Rien ne référence `User`** hors `Lignee` ; **la relation avec une cité** survit
  par `Lignee::$relations` (A § 4.3), sans suppression de plus.
- Une partie **existante** n'a pas de cité autonome : l'activation se fait **à la
  prochaine ouverture de route**, jamais par une migration de masse.

## 9. Comment on le vérifie

Le moteur pur permet de **tester sans base**, comme la V1 de `simulation`
(`node --test`). L'équivalent, en PHPUnit sur des objets de domaine.

| Contrôle | Ce qu'il prouve |
|---|---|
| **Déterminisme** | Même cité, même vue, même graine → même résultat, **à l'octet** |
| **Sérialisation** | `etat → JSON → etat` est sans perte (exigence EXG-03 de la V1) |
| **Une valeur, un exemplaire** | Les cités **lisent** les constantes du jeu : un test change une constante de `Population` et vérifie que la cité suit |
| **Plancher de déclin** | Sur 500 quinzaines et plusieurs graines, **aucune cité ne descend sous son plancher**, aucune ne s'éteint |
| **Pas d'emballement** | Aucune cité ne dépasse **N fois** la courbe publique du scénario |
| **Pas de stagnation** | Au moins **X %** des cités changent de niveau en 100 quinzaines |
| **Conjoncture bornée** | Quel que soit l'état des stocks, la conjoncture reste **dans −20/+20** : le plafond de 40 **tient** |
| **Arbitrage** | La marge d'un aller-retour entre deux cités **ne dépasse pas** celle d'A |
| **Chaque mesure bouge** | Un test par compteur : population, niveau, réserves, relation |
| **Rejeu** | N'importe quelle quinzaine se rejoue **sans stocker l'historique** |
| **Banc d'essai** | Une commande console : 500 quinzaines, 5 graines, tableau de **dispersion** (richesse, rang, Gini, cités stagnantes) |
| **Navigateur** | Onglet Cités, annales, frise ; 390 px et desktop |

Ce qu'aucun test ne dira : **si un monde qui vit se sent comme un monde qui vit**, ou
comme une feuille de calcul. C'est la question du playtest de **C3**.

## 10. Découpage

| Lot | Contenu | Sortie | Verrou |
|---|---|---|---|
| **C0** | Ce document, les décisions du § 12 | Cadrage validé | **Playtest de B2 lu** : s'attache-t-on à une personne ? **Si non, C ne se justifie pas** |
| **C1** | **Le moteur pur**, **en mémoire, sans persistance ni écran** : cohortes, subsistance, production, démographie, décision. **Banc d'essai à 500 quinzaines** | Un monde qui tient, **prouvé** | Pas d'emballement, pas d'extinction, Gini acceptable |
| **C2** | **Persistance** de `CiteAutonome` ; **une seule** cité, **à la place de la conjoncture dérivée**, pour **un** partenaire ; interface d'A **inchangée** | La preuve que le niveau de détail marche | Les bornes d'A tiennent sur de vrais stocks |
| **C3** | **Plusieurs cités**, leurs **échanges entre elles**, l'étal nourri par leurs stocks ; onglet « Cités » | **Le premier monde qui vit** | **Playtest : le monde se sent-il vivant, ou calculé ?** |
| **C4** | Relations entre cités, **alliances et brouilles**, guerres comme événements ; sollicitations de paix | De la diplomatie | — |
| **C5** | **Aventure** : générations, règnes, annales, frise | L'histoire longue | Décision sur la refondation (§ 12) |
| **C6** | Calibrage sur parties réelles ; courbes publiques ; valeurs au § 6 du plan de bataille | Équilibre | — |

**C1 est livrable seul et ne casse rien** : un moteur qui n'est branché à rien. C'est
**le lot qui répond à la question « est-ce faisable ? »**, avant d'engager quoi que
ce soit d'autre.

## 11. Valeurs inventées — à calibrer

| Valeur | Amorce | Ce qu'elle infléchit |
|---|---|---|
| Cités par partie | 2 à 3 en Campagne, 4 à 6 en Aventure | La densité du monde |
| Niveaux de développement | 1 à 10 | La profondeur d'une croissance |
| Plancher de population d'une cité | 25 % de son pic | Le déclin sans mort |
| Courbe publique de croissance | à poser par scénario | La crédibilité d'un voisin |
| Réserves conservées par cité | 6 à 10 ressources | Le coût du modèle |
| Génération d'une famille voisine | 60 cycles (±20), comme le joueur | Le rythme de l'histoire |
| Seuil de stagnation | N quinzaines sans projet | Le « calme plat » |
| Poids des aspirations d'une cité | banc d'essai | Son caractère |

## 12. Questions ouvertes

| Question | Penchant |
|---|---|
| **Le palier C se justifie-t-il ?** | Seulement si le playtest de B2 montre qu'on s'attache à des personnes. Sinon, **A et B suffisent**, et c'est une bonne issue |
| **Une cité peut-elle être abandonnée puis refondée ?** (§ 6.2) | Non en C ; à rouvrir après le playtest d'Aventure |
| **Une cité peut-elle occuper une case de la carte du joueur ?** | Non : hors grille. Un chantier de carte à part si l'on y tient |
| **Les cités se marient-elles entre elles** (héritiers) ? | Non en C ; une extension belle mais lourde |
| **Une cité peut-elle être jouée** (changer de famille) ? | Non : le jeu est celui d'**une** famille |
| **Rubber-banding public ou aucun ?** | **Public**, par scénario ; jamais silencieux |
| **Les cités autonomes sont-elles les mêmes d'une partie à l'autre ?** | Leur **identité** oui (le catalogue), leur **histoire** non — hors **relation** qui survit par la lignée |
| **Quel nom** (`CiteAutonome` ou un mot de l'univers) ? | `CiteAutonome` pour la classe, vocabulaire de l'univers pour le reste |
| **Les annales ont-elles une valeur de jeu** (un classement qui rapporte de la renommée) ? | Non : un récit, pas un objectif |
