# Niout — Dialogues-indices : faire parler les enquêtes et les énigmes

**Statut : cadrage, rien n'est codé.** Suite du lot V4a (`Dialogues`, dialogues d'ambiance — livré)
dans [`plan-pnj-ville-vivante.md`](plan-pnj-ville-vivante.md). **Ce document tranche une
question de conception**, que V4a avait laissée ouverte : jusqu'où des gens qui parlent peuvent-ils
aider le joueur à résoudre les enquêtes et les énigmes ? Il se lit **avec le doc 10 du Drive**
(« Énigmes et enquêtes »), qui est la source de vérité, et avant de toucher à `Dialogues`,
`Enquetes`, `Enigmes` ou `EffetDeChef`.

## 1. La réponse courte, et honnête

**On peut faire parler les enquêtes et les énigmes. On ne peut pas, sans défaire le doc 10, faire
parler les gens *pour résoudre à la place du joueur*.** Le doc 10 pose des règles qui rendent
presque tous les raccourcis interdits ; ce qui reste est utile, mais c'est **de la mise en scène
et de la mise en ordre du raisonnement**, pas un nouvel indice.

## 2. Ce que le doc 10 interdit à un dialogue

Chaque ligne est une règle déjà écrite, et la plupart sont déjà gardées par des tests.

| Règle du doc 10 | Ce qu'elle interdit à une réplique |
|---|---|
| **Une enquête se démêle, elle ne se compte pas** : seuls les indices concordants rapprochent ; **le joueur ne sait jamais lequel** — une fausse piste et un indice de contexte s'affichent tous deux « à vérifier » | **Révéler la nature d'un indice**, même par le ton. « Ça, c'est important » trahit tout |
| **Une case ne se fouille qu'une fois** ; l'exploration a un coût | **Dire où chercher.** Une rumeur « il y a eu du bruit au gué » donne l'emplacement d'une case pour rien : elle dévalue l'exploration |
| **On ne ramasse que les indices de l'enquête qu'on peut mener ici** ; un dossier naît au premier indice ; il faut une Maison des scribes | **Créer un indice.** Une réplique ne rend jamais un indice nouveau : elle ne dit que ce que le dossier contient déjà |
| **Une énigme à choix multiple ne se retente pas** ; elle ne punit jamais ; **l'explication tombe dans les deux cas**, le gain est ce qu'elle apprend | **Donner la bonne réponse**, ou rouvrir une énigme jouée |
| **L'Oraculaire écarte une mauvaise proposition — à l'affichage seulement** ; Déchiffreur et Thot ouvrent des signes | **Faire de l'aide gratuite et générale.** Le jeu a déjà des aides, **qui sont des spécialités de chef** : une réplique qui aide aussi bien rendrait l'Oraculaire inutile |
| **Les hiéroglyphes du jeu sont vrais** ; « quand une lecture ne s'établit pas, on n'affiche rien » ; les inscriptions sont des rébus, pas de l'égyptien | **Approximer un signe**, ou faire croire qu'on apprend à lire l'égyptien |
| **Une stèle s'affiche en résumé, jamais en citation** ; chaque énigme **dit d'où elle vient** | **Citer** une source, ou en inventer une |
| **Une énigme tombe dans l'onglet du bâtiment qui l'héberge** (Temple, Auberge, Maison des scribes) : *c'est le lieu qui décide, jamais l'écran* | **Déplacer une énigme** : celle de l'Auberge se dit à l'Auberge |

## 3. Ce qu'un dialogue peut faire — cinq pistes, du plus sûr au plus risqué

Aucune ne crée de pouvoir nouveau : **chacune est la voix de ce que le jeu fait déjà.**

### I1. La voix des aides qui existent déjà

- **L'Oraculaire** écarte une mauvaise proposition (`EffetDeChef::PROPOSITIONS_ECARTEES_PAR_LORACULAIRE`,
  `Enigmes::propositions()`). Aujourd'hui, une réponse disparaît de la liste, sans visage. Demain :
  *« Hori l'oraculaire souffle : ce n'est pas « l'ibis ». »* — **la même proposition**, tirée de la
  **même source** que l'écran (`Enigmes::propositions()`), jamais d'une seconde logique.
- **Le Déchiffreur** et **Thot** ouvrent des signes de plus : une ligne qui dit *qui* les a appris.

C'est **la piste qui répond le plus directement à « aider à résoudre »**, et la seule qui **respecte la
conception** : le chef a un prénom (lot N), l'aide a donc un visage. Coût : très faible.

### I2. L'énigme dans la bouche de celui qui la pose

La devinette de voyageur se dit **à l'Auberge**, l'oracle **au Temple** : le doc 10 le dit déjà. On y
pose **une silhouette de passage** — un voyageur, un prêtre — avec un **marqueur** et une bulle (« Une
devinette, étranger ? »), qui ouvre l'énigme **existante**. **Les textes ne changent pas** ; la
source attestée reste affichée. **Elle n'apparaît que si l'énigme est jouable** (non jouée, bâtiment
debout). Coût : faible, mais c'est une silhouette nouvelle (pas une personne de la ville).

### I3. Le témoignage rapporté

Seize indices sont de source **témoignage** (`Indice::source()`) : le récit du batelier, la plainte
d'un paysan, le murmure de la garnison… Aujourd'hui, l'émissaire les rapporte en une ligne. Quand il
en a **recueilli** un (`Enquetes::recueillirUnTemoignage()`), on le **rejoue en réplique**, à la
Maison des scribes ou à l'Auberge : *« Un batelier dit avoir vu… »*.

**Règles** : le texte est **celui de l'indice déjà acquis** — rien de plus ; tous s'affichent « à
vérifier », **la même voix pour un concordant et pour un trompeur** (un témoin se trompe, ment, ou
dit vrai, et le joueur doit lire) ; et un témoignage **qui n'est pas dans le dossier ne se dit pas**.
Coût : un texte parlé par témoignage — **seize textes à écrire**, au vrai chantier de contenu.

### I4. La mémoire du dossier

Le chef de la Maison des scribes **résume ce que le joueur sait déjà** et pose la question de la
déduction **sans y répondre** : *« Tu as noté trois choses. Qu'en penses-tu ? »*, puis les nomme.

**C'est la plus risquée**, parce que **le choix de ce qu'on nomme peut trahir la nature** : ne citer
que des concordants dirait tout. **Règle** : nommer **tous** les indices du dossier, dans l'ordre où
ils ont été trouvés, **ou aucun**. Elle **organise le raisonnement** — remettre sous les yeux ce
qu'on a oublié — sans le raccourcir. Coût : moyen, et un test d'**uniformité** (même phrase pour
toute nature).

### I5. L'explication qui enseigne

L'**explication tombe dans les deux cas** (doc 10) : c'est le gain d'une énigme. La dire en deux
répliques, par le personnage qui l'a posée (I2), **sans rien changer à son contenu** — et en
gardant sa mention d'origine. Coût : très faible.

### Écartée : la rumeur qui oriente

Faire dire aux gens **où chercher** est la piste la plus tentante et **la plus mauvaise** (§ 2) : elle
vide l'exploration, qui est le cœur de l'enquête de terrain. **Non**, même en la rendant vague.

## 4. Garde-fous à écrire avec le code

Des **tests**, pas des intentions — comme `DialoguesTest` pour V4a :

| Test | Ce qu'il prouve |
|---|---|
| **Aucun indice ne naît d'une réplique** | Le dossier est **identique** avant et après le dialogue |
| **La nature ne se lit pas** | Aucune réplique ne contient « concordant », « trompeur », « optionnel », ni de jugement de valeur ; **le même gabarit pour toute nature** (I3, I4) |
| **Une seule source de vérité** | La proposition que l'Oraculaire « souffle » **est** celle que `Enigmes::propositions()` écarte (I1) |
| **Pas de réponse** | Une réplique d'énigme ne contient jamais la bonne réponse ni son libellé |
| **Rien sans fait** | Un témoignage ne se dit que s'il est au dossier ; une énigme ne se pose que si elle est jouable |
| **Le lieu décide** | I2 : la devinette n'est posée qu'à l'Auberge, l'oracle qu'au Temple |
| **Pas de chiffre, des phrases courtes** | Comme `DialoguesTest` |
| **Texte équivalent** | Tout ce qui est dit en bulle est **aussi** en texte dans la fenêtre : le téléphone et le lecteur d'écran n'ont pas de bulle |

## 5. Découpage proposé

| Lot | Contenu | Coût | Verrou |
|---|---|---|---|
| **I1** | La voix de l'Oraculaire, du Déchiffreur et de Thot, dans la fenêtre de l'énigme et le journal | Très faible | Source unique, test d'identité |
| **I5** | L'explication dite en répliques | Très faible | Contenu inchangé |
| **I3** | Le témoignage rapporté (**seize textes**) | Moyen, **contenu** | Nature cachée, texte du dossier seul |
| **I2** | Le voyageur de l'Auberge, le prêtre du Temple (silhouettes de passage) | Faible à moyen | Le lieu décide, énigme jouable |
| **I4** | La mémoire du dossier | Moyen | **Uniformité** — à ne livrer qu'avec son test |

**Mon ordre : I1 et I5 d'abord** (les plus petits, et I1 est ce qui aide vraiment), **puis I3**.
**I4 en dernier** : c'est la seule piste où une erreur d'écriture trahit une enquête sans qu'aucun
test d'écran ne le voie.

## 6. Questions ouvertes

| Question | Penchant |
|---|---|
| **Le scribe peut-il résumer le dossier (I4) ?** | Oui, **à condition de tout nommer ou rien**, avec son test |
| **Faut-il des textes parlés pour les seize témoignages (I3), ou rejouer le libellé existant ?** | Rejouer d'abord le libellé existant à la première personne du témoin ; réécrire plus tard |
| **Un voyageur de passage est-il une « personne » du palier B ?** | Non : une silhouette de décor attachée à une énigme, sans nom ni mémoire |
| **Les dialogues doivent-ils aider pour le déchiffrage d'écriture ?** | **Non en V4** : les jetons sont mélangés au rendu, la réponse ne doit pas se lire, et le doc 10 ne prévoit aucune aide hors Déchiffreur et Thot |
| **Une énigme jouable peut-elle se lire dans la ville sans ouvrir l'Auberge ?** | Seulement par le marqueur d'I2, qui ouvre l'énigme existante : jamais son texte dans une bulle |

## 7. Livré

### Lot I3 — les témoignages parlés (2026-11-16, dans `develop`)

- **Contenu** : `Indice::temoin()` et `parole()` pour les seize témoignages (`null` pour le terrain). La parole reprend le texte
  de l'indice à la première personne, sans rien y ajouter : c'est la réponse à « rejouer le libellé existant d'abord ».
- **Mécanisme** : un témoignage de plus dans les dossiers d'une photographie à l'autre devient le fait `TemoignageRecueilli`
  (`DifferenceDeQuinzaine`, clé `temoignages` de la photographie, absente des traces plus anciennes — testé). La chorégraphie
  le joue l'après-midi du jour : le chef de la Maison des scribes (sinon la famille) parle, un autre répond ; les deux sont
  exclus des conversations d'ambiance ce moment-là.
- **Dossier** : « Témoin : « parole » » pour un témoignage, texte inchangé pour le terrain — même gabarit, le ton ne trahit pas la nature.
- **Verrous tenus** : `IndiceTest` (aucun mot de nature, aucun chiffre, fidélité au texte) ; la trace ne garde que la valeur de l'indice.
- **Piège payé** : la première parole du charpentier s'écartait du texte (un seul mot commun) — c'est le test de fidélité qui l'a vu.
- **Reste** : I1 et I5 (voix des aides), I2 (voyageur, prêtre), I4 (mémoire du dossier, en dernier).

### Lots I1 et I5 — la voix des aides (2026-11-16, dans `develop`)

- **I1** : `VoixDesAides` (`souffle()`, `signesAppris()`). `Enigmes::propositionsEcartees()` est l'unique source de ce que
  l'Oraculaire écarte ; `propositionsMontrees()` en est dérivée, la voix la nomme. `EffetDeChef::chefDeSpecialite()` donne le chef
  qui agit, et `chefSpecialise()` s'y appuie : l'aide nommée est celle qui agit.
- **I5** : l'explication est précédée de la voix du chef des scribes, **sans l'altérer** (testé : texte identique sans chef ou sans prénom).
  Faute d'I2, le « personnage qui a posé l'énigme » est le chef des scribes, quel que soit le lieu de l'énigme.
- **Écart au cadrage** : la voix n'est pas au journal de la quinzaine, seulement dans la fenêtre — l'aide agit à l'affichage, pas
  pendant la quinzaine, donc rien à y rejouer.
- **Verrous tenus** : identité de la liste écartée / montrée, la bonne réponse jamais nommée, silence sans aide, gabarit unique.

### Lot I2 — voyageur de l'Auberge, prêtre du Temple (2026-11-16, dans `develop`)

- `VisiteurDePassage::pour()` : un voyageur à l'Auberge, un prêtre au Temple, **tant qu'une énigme de ce lieu est jouable**
  (`Enigme::disponiblesPour()`). Rôles `Voyageur` et `Pretre`, deux symboles SVG. Posés par `GensDeLaVille` en plus du plafond.
- **Hors photographie** : donc hors film, hors trace, absents d'une quinzaine rejouée. Fiche et ligne de la liste (voie équivalente).
- **Écarts au cadrage** : pas de bulle (le film ne les joue pas) ; le « marqueur » est la fiche, qui renvoie au bâtiment plutôt
  que d'ouvrir l'énigme directement.
- **Verrous tenus** : le lieu décide, rien sans énigme jouable, la fiche ne contient ni énoncé, ni réponse, ni explication.
- **Non vérifié au navigateur** : lisibilité des deux silhouettes à l'échelle de la ville.
