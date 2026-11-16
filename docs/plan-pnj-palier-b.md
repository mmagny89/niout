# Niout — PNJ, palier B : des personnes qui ont un but

**Statut : cadrage détaillé, rien n'est codé.** Suite de
[`plan-pnj-autonomes.md`](plan-pnj-autonomes.md) (§ 5.1) et de
[`plan-pnj-palier-a.md`](plan-pnj-palier-a.md). **Dépend d'A** : la graine de
partie, les faits de monde, la conjoncture et la fenêtre « Le monde » y sont
posés, et B s'y appuie sans les refaire. Il se lit avant de toucher à `Rivaux`,
`RivalCommercial`, `GenerateurDeCandidat`, `DepartsNaturels`, `Bandits` ou
`QuetesDeChantier`.

## 1. Ce que le palier B est — et la raison de son existence

Au palier A, le monde **subit** : une pénurie, une foire, une guerre. Personne
n'y veut rien. Au palier B, **des personnes veulent quelque chose**, et c'est ici —
et ici seulement — que le **choix par score d'utilité** de la V1 de `simulation`
trouve sa place, avec son inertie et ses urgences.

Pourquoi est-ce autre chose qu'un décor plus gros : **une personne a un visage, un
but, une histoire, et le joueur la retrouve.** Un rival qui s'appelle Panehsy,
revient trois quinzaines après avoir été payé, et dont on lit pourquoi il a choisi
*cette* route-là — c'est ce qu'aucun tirage anonyme ne donne.

**Le palier B ne simule pas des vies.** Il simule **une intention par personne**,
lisible en une ligne, et ses conséquences bornées. Le jeu n'a pas besoin de savoir
ce que Panehsy mange.

## 2. Ce que le code fait aujourd'hui (vérifié)

| Fait | Où | Ce que B en fait |
|---|---|---|
| Le **rival** est **tiré** (`renommée / 2` ‰, plafonné à 50 ‰), installé sur **une route tirée au hasard**, avec un nom pris dans huit prénoms, un malus de 10 à 20 %, 8 à 16 quinzaines. Il **s'en va seul** | `Rivaux`, `RivalCommercial` | Il garde ses règles ; il gagne un **but**, un **choix de route raisonné** et une **mémoire** |
| Trois issues : **ignorer, payer** (3 deben par point de renommée, 40 au moins), **enquêter** | `Rivaux::passerUnAccord`, `neutraliserParLEnquete` | Les trois issues **restent** et **laissent une trace** : payé, il devient un contact ; démonté, il disparaît longtemps |
| **Une seule rivalité à la fois** sur la ville | `City::getRival()` | Inchangé : plusieurs personnes peuvent se disputer **entre elles**, une seule pèse sur le joueur |
| Les **candidats** sont **tirés** à chaque annonce, figés dans `JobOffer`, avec compétence, salaire, **traits**, **ancienneté probable**, **maisonnée** | `GenerateurDeCandidat`, `JobOffer` | Ils deviennent des **personnes qui existent avant l'annonce** et **après leur départ** |
| Les chefs **partent seuls** : chance par quinzaine selon l'ancienneté, **doublée** par le mécontentement. Le foyer part avec eux | `DepartsNaturels` | Un départ n'est plus une disparition : il va **quelque part** |
| Huit **traits** existent : travailleur acharné, économe, fidèle, ambitieux, pieux, bagarreur, expérimenté, débutant prometteur | `TraitDeCandidat` | **C'est déjà un vocabulaire d'aspirations** : on ne le double pas, on le réutilise |
| Dix-huit **spécialités** de chef, dont **Négociateur** et **Logisticien**, qui pèsent sur le commerce | `SpecialiteDeChef` | Un chef qui travaille ailleurs **donne** sa spécialité à cet ailleurs (§ 5.2) |
| Les **bandes** sont un **attribut de case** (`nbZonesBandits = ⌊difficulté × 0,5⌋`), résolues par `Combat` ; leur butin décroît quand on nettoie | `Bandits`, `Combat` | Elles gagnent un **chef** et une **parole**, mais **ne bougent pas** (§ 5.3) |
| La **cour** n'existe que par `QuetesDeChantier` : une requête tous les 4 cycles, citant un monument **réellement bâti**, jamais obligatoire, 6 quinzaines de délai, refuser coûte 2 de renommée | `QuetesDeChantier`, `ChantierRoyal` | Un **visage** derrière les requêtes (§ 5.4) |
| Un **héritier** se déduit d'une **graine gardée** : deux visites du même écran montrent les mêmes | `Heritier`, `Family::graineDesHeritiers` | **Le précédent exact** : on garde la graine, pas le tirage |
| `PrenomEgyptien::selonLaGraine(int)` donne un prénom **depuis une graine** | `PrenomEgyptien` | Le nom d'une personne se **déduit**, il ne se stocke pas |

## 3. Les garde-fous

Ceux d'A tiennent (`plan-pnj-palier-a.md` § 5) : **rien de ce que le joueur
possède n'est détruit**, le plafond commun de 40, un convoi parti n'est jamais
touché, une seule adversité à la fois. B y ajoute ce qui est propre aux personnes :

- **Une personne ne meurt pas à l'écran.** Elle vieillit, **se retire**, et
  **un héritier ou un successeur prend la suite** (`SuccessionFamiliale`). Un rival
  qui « se lasse » est déjà dans le jeu : le verbe est le bon.
- **Une personne n'est jamais un obstacle sans réponse.** Les trois issues du
  rival (ignorer, payer, enquêter) sont le **modèle** : toute personne qui pèse sur
  le joueur offre **au moins deux issues dont une gratuite**, et ne rien faire en
  est une.
- **Aucune rancune qui s'accumule.** Une personne se souvient, mais sa mémoire a un
  **plafond** : elle infléchit une probabilité ou un prix **dans des bornes**, elle
  ne construit pas un ennemi qui grandit à chaque partie. Le jeu a déjà refusé ce
  piège (« une famille obscure ne dérange personne »).
- **Pas de nom attesté sans attestation.** Les personnes **génériques** portent des
  noms de gens ordinaires attestés (Panehsy, Hori, Nakhtmin…, comme `Rivaux::unNom()`).
  Un **personnage historique nommé** — un vizir, un général — **n'entre que si son
  existence, son règne et sa fonction sont établis**, et se **vérifie avant** d'être
  écrit, jamais de mémoire. En cas de doute : **« le vizir »**, sans nom, comme la
  règle des hiéroglyphes (« on n'affiche rien plutôt qu'une approximation »).
- **Pas d'ennemi personnel du joueur réel.** Une personne ne cible jamais « la
  joueuse » : elle cible **une route, une ressource, une région**, selon ce que le
  monde lui offre. C'est ce qui garde le jeu juste et lisible.

## 4. Le modèle de décision (ce que la V1 apporte enfin)

Reprise de `simulation` — scores d'utilité, inertie, urgences, explication — en
**PHP**, en **fonction pure** :

```
decider(personne, vueDuMonde, graine) → intention + raisons
```

Aucun accès à Doctrine, au temps ni au hasard global : tout entre par les
paramètres, ce qui rend la fonction **testable seule** et **rejouable**. C'est
l'équivalent de l'exigence EXG-02 de la V1 (« moteur séparé de l'affichage »).

### 4.1 Les aspirations

Peu, lisibles, **les mêmes pour tous** ; ce sont les **traits** et le **rôle** qui
les pondèrent.

| Aspiration | Ce qu'elle pousse à faire | Pondérée par |
|---|---|---|
| **Prospérité** | Aller où le marché paie (conjoncture favorable, lue au palier A) | Rôle marchand, trait *Économe*, *Ambitieux* |
| **Prestige** | Se faire voir : commanditer, offrir, gagner une route célèbre | *Ambitieux*, renommée de la famille |
| **Sécurité** | Éviter les routes de bande, les villes en guerre | *Fidèle*, *Économe* |
| **Piété** | Offrir aux dieux, suivre le calendrier des fêtes | *Pieux* |
| **Loyauté** | Rester là où l'on est bien traité (salaire, ration) | *Fidèle* ; le contraire pour *Ambitieux* |

### 4.2 Ce qui se passe à chaque quinzaine

1. **Urgence ?** Disette dans sa cité, route fermée, salaire non payé : l'urgence
   l'emporte sur l'ambition (c'est la règle de la V1).
2. **Projet en cours ?** Une personne qui a entrepris quelque chose le **poursuit**
   jusqu'à son terme — c'est l'**inertie**. Sans elle, l'écran est du bruit :
   quelqu'un qui change d'avis à chaque quinzaine n'a pas d'histoire.
3. Sinon, **chaque action possible reçoit un score**, somme des aspirations
   pondérées par l'état du monde ; la personne joue la meilleure, ou tire parmi les
   deux ou trois premières **avec la graine**.
4. L'effet est appliqué : à la **personne**, au **monde** (un fait), ou **au joueur
   seulement si les règles du palier A le permettent**.
5. Un **fait structuré** est écrit, avec les **raisons** — des **clés** et des
   valeurs, jamais une phrase : « prospérité 0,7 (Byblos manque de lin), sécurité
   0,2 ». Le texte est rendu en Twig. C'est le **« pourquoi »** de la V1.

**Graine** : `f(graine de la partie, quinzaine, identité de la personne)`. N'importe
quelle quinzaine se rejoue à l'identique.

### 4.3 Ce que B ne fait pas (encore)

- **Pas de mémoire entre personnes.** Chacune lit le **monde**, pas ce que les
  autres pensent d'elle. Cela viendrait au palier C, avec des cités qui se parlent.
- **Pas de planification à long terme.** Un projet dure quelques quinzaines, pas
  des années. L'horizon long, c'est la succession (§ 5.4).
- **Pas d'apprentissage.** Les poids ne changent pas au fil de la partie.

## 5. Les cinq personnes du palier B

Du moins cher au plus cher. **On n'engage pas tout** : § 10 les ordonne.

### 5.1 Le rival commercial : un marchand qui a un but

C'est le cas le plus riche, parce qu'il **existe déjà**. Ce qui change :

- **Il choisit sa route.** Aujourd'hui, une route tirée au hasard. Demain, il
  **score les routes ouvertes de la ville** — et c'est là que le palier A se paie :
  *Panehsy s'installe sur la route de Byblos parce que Byblos manque de lin et que la
  famille y vend beaucoup.* Le joueur **comprend** pourquoi.
- **Il a une identité qui revient.** Un nom déduit de la graine, un trait, un but.
  S'il a été payé, il **revient** plus tard — jamais avant un **délai plancher**
  (mémoire plafonnée, § 3) —, plus riche, et **plus sensible à un accord** : un accord
  passé le fait **s'attacher**, jusqu'à devenir un **contact** (relation avec sa
  cité, palier A § 4.3).
- **Il est démonté pour de bon, ou pour longtemps.** L'enquête (le plus long des
  trois chemins, le plus payant) le **retire pour N quinzaines** et le **fait
  connaître** : sa chute se lit dans la chronique, avec ses raisons.
- **Il ne pèse pas plus qu'avant.** Mêmes bornes : 10 à 20 %, jamais sous une
  unité, un seul à la fois, il s'en va seul.
- **Ses rivalités entre PNJ** (deux marchands qui se disputent Pount) **se lisent
  dans la chronique** et ne touchent pas la ville.

### 5.2 Les chefs voyageurs : ceux qui partent vont quelque part

Aujourd'hui, un chef qui part (`DepartsNaturels`) **disparaît**, avec sa
maisonnée. Demain :

- **Il va quelque part.** Une cité partenaire, ou une autre ville. Le départ devient
  *« Ouser, notre Négociateur, a rejoint Byblos »*.
- **Il y fait quelque chose.** Un **Négociateur** parti à Byblos **élève la relation
  de Byblos** avec la famille qui l'a formé (un geste de plus qu'un convoi) ; un
  **Logisticien** peut raccourcir un trajet vers cette cité. Les bonus passent
  **par les canaux existants** (relation, `EffetDeChef`), **jamais par un facteur de
  plus** — règle de `AvantageDeNegoce`.
- **Il peut revenir**, comme candidat, **plus compétent** de ce qu'il a vu : une
  annonce qui réunit un ancien chef et ce qu'il a appris est un **cadeau de monde**.
  Sa compétence ne monte que **dans les bornes du générateur** (20 à 100).
- **Le pool de candidats est déduit, pas stocké.** Comme les héritiers, il vient
  d'une graine : *« des personnes qui existent avant l'annonce »* veut dire **une
  population de N noms stable par partie**, dont l'annonce tire. Seuls ceux que le
  joueur **touche** — embauchés, renvoyés — sont **persistés**, paresseusement, comme
  un dossier d'enquête naît à son premier indice.
- **Le foyer suit**, comme aujourd'hui : un départ n'ajoute ni ne retire de
  population à la ville au-delà de ce que fait `laisserPartir`.
- **Pas de débauchage hostile.** Une cité ne **vole** pas un chef : il part de lui-même,
  selon ses traits et le mécontentement. Un PNJ qui détruit une capacité du joueur
  (§ 3) serait ici exactement la faute à éviter.

### 5.3 Les bandes : un chef et une parole, pas un déplacement

La bande est aujourd'hui **un attribut de case**, et le calcul des zones gardées
tient une formule. Déplacer des bandes toucherait la **génération de la carte**, les
**garanties de départ** et le **butin décroissant** : un chantier à part.

B se limite à ce qui ne touche pas la carte :

- **Un chef de bande nommé**, déduit de la graine de la case, avec un trait.
- **Une réputation** : combien de convois il a inquiétés (palier A, « troubles »).
  Elle se **lit** dans la chronique et donne un **visage** au risque de pillage que
  le joueur voit déjà avant l'engagement.
- **Une issue de plus**, qui répond à la question restée ouverte *« les Medjaÿ
  répondent-ils aux rivaux ? »* : **payer un droit de passage** — des deben contre
  une réduction du risque pour N quinzaines — **en plus de** l'escorte et du combat.
  Trois issues, comme le rival.

**Les bandes mobiles sont écartées de B** (question ouverte, § 12).

### 5.4 La cour : un visage derrière les requêtes

Les requêtes du pharaon sont aujourd'hui **anonymes**. Le palier B leur donne **un
courtisan** — un **titre** plutôt qu'un nom — qui les porte, avec **une humeur** :
satisfait des services rendus, impatient d'un délai manqué.

- Il **module** les requêtes déjà là : leur **fréquence**, leur **présent**
  (`PRESENT_EN_CENTIEMES`), leur **ton**. Jamais leur **caractère facultatif**.
- Il **accompagne la succession** : `SuccessionDesRegnes` change de pharaon, **et le
  courtisan avec**. C'est ce qui donne à un changement de règne un **sentiment**
  — l'ancien favori s'efface, un nouveau paraît —, au lieu d'un simple changement de
  nom. C'est surtout utile en **Aventure**, où l'on traverse plus de deux cents
  cycles.
- Les **noms attestés** ne s'écrivent qu'après vérification (§ 3).

### 5.5 Les héritiers des familles voisines (réservé au palier C)

Un voisin qui a des enfants et une succession est déjà une **famille** : c'est le
palier C. B n'en pose que le **vocabulaire** — un héritier se déduit d'une graine.

## 6. Ce qui se persiste

Principe : **seul ce que le joueur a touché, ou qui a changé d'état, se stocke.**

| Entité (nom provisoire) | Contenu | Rattachement |
|---|---|---|
| `Npc` *(nom à trancher, voir `plan-pnj-autonomes.md` § 8)* | `partie`, **identité** (graine), rôle, traits, lieu, projet en cours (JSON, défaut `'{}'`), relation, statut (actif, retiré) | `GameSave`, cascade `remove`. **Créée paresseusement** à la première interaction ou à la première intention qui change quelque chose |
| `FaitDeMonde` *(posé en A)* | Gagne `personne` nullable et des **clés de raison** (JSON, défaut `'{}'`) | Inchangé ; **purge à 40 quinzaines** |
| `Lignee::$visagesConnus` | JSON `{identité: {rôle, relation}}`, **plafonné à 12**, défaut `'{}'` | Colonne de `Lignee` : **aucune suppression de plus** |

Points de vigilance :

- **L'identité d'une personne est une graine**, jamais un identifiant autoincrémenté
  seul : retrouver *Panehsy* d'une partie à l'autre repose sur **la même graine
  lignée**, pas sur une ligne qui ne survit pas à sa partie.
- **Les colonnes JSON portent un défaut** dans la migration (`'{}'`) : la règle
  `tests.md` est née d'un défaut retiré qui a cassé un test sans rapport.
- **Rien ne référence `User`** hors `Lignee` : `SuppressionDeCompte` n'est pas
  touchée.
- **Un `flush` par quinzaine.**

## 7. Les visages connus : une lignée qui se souvient

**Cohérent avec la décision du palier A** (la relation survit aux parties), B
prolonge : **une personne que la famille a rencontrée peut se reconnaître d'une
partie à l'autre.**

- La lignée garde **jusqu'à 12 visages connus**, avec leur relation.
- Quand une partie démarre, **l'un d'eux peut figurer** dans le monde — un
  marchand revu à Memphis après Avaris, un chef parti qui revient.
- **Ils ne s'accumulent pas** : au-delà de 12, le plus ancien s'efface. Une mémoire
  sans limite deviendrait un obstacle à chaque partie (§ 3).
- **Aucune rancune ne se transmet** : un rival payé revient **plus sensible aux
  accords**, jamais plus dur.

Ce qui reste à trancher est au § 12.

## 8. Ce que le joueur voit

B **n'ajoute pas d'écran** : il **remplit** ceux d'A.

- **La fenêtre « Le monde »** gagne l'onglet **Personnes** : une fiche par visage —
  portrait, rôle, but du moment, relation, **le pourquoi de sa dernière décision**.
- **La chronique** se filtre **par personne**.
- **Les rencontres** (rival installé, chef revenu) se lisent dans le **récapitulatif
  de la quinzaine**, catégorie « Au loin », déjà posée en A.
- **L'information se mérite**, comme en A : on ne voit les raisons d'un PNJ que si
  l'on **en sait assez** — Auberge, Maison des scribes, un émissaire. Sinon, on voit
  **ce qu'il a fait**, pas **pourquoi**.
- **Des prises** : payer, enquêter, ignorer (rival) ; embaucher, recommander
  (chefs) ; payer un droit de passage (bandes) ; honorer ou refuser (cour).

Une conjoncture ou un but signalé **par la couleur seule** ne se lit pas : texte et
pictogramme, comme au palier A. Vérifier à 390 px et en desktop (skill
`verification-ui-navigateur`).

## 9. Comment on le vérifie

| Contrôle | Ce qu'il prouve |
|---|---|
| **Déterminisme** | Même graine, personne, quinzaine → même intention **et mêmes raisons** |
| **Bornes d'A, rejouées** | Aucun effet de personne ne dépasse −20/+20, 40, 110 %, 50 % de volume, une unité |
| **Une seule adversité** | Sur 200 quinzaines et plusieurs graines : jamais deux personnes pesant sur la ville en même temps |
| **Deux issues dont une gratuite** | Pour toute personne qui pèse, un test énumère ses issues |
| **Inertie** | La durée moyenne d'un projet est dans une fourchette, et **aucune personne ne change de projet deux quinzaines de suite** hors urgence |
| **Pas d'immortel, pas de fantôme** | Sur 200 quinzaines : aucun rival ne reste, aucun chef ne disparaît sans lieu |
| **Chaque mesure bouge** | Un test par compteur (relation, mémoire, réputation de bande) — jamais un drapeau déclaré |
| **Rejeu** | Une quinzaine passée redonne les mêmes faits |
| **Banc d'essai de monde** | Une commande console fait tourner 200 quinzaines **sans joueur** et rapporte : intentions par type, personnes immobiles, oscillations |
| **Navigateur** | Fiches, chronique filtrée, récapitulatif, à 390 px et en desktop |

Ce qu'aucun test ne dira : **si l'on s'attache à une personne.** C'est la question
du playtest d'après B1.

## 10. Découpage

| Lot | Contenu | Sortie | Verrou |
|---|---|---|---|
| **B0** | Ce document, les décisions du § 12 | Cadrage validé | **Playtest d'A2 lu** : le joueur regarde-t-il le monde ? |
| **B1** | **Le rival raisonné** : `decider()` pur, aspirations, score, inertie, raisons ; choix de route par conjoncture ; nom déduit ; mémoire plafonnée ; trois issues avec trace | **Un seul type de personne, entièrement vérifié**, qui **réutilise** une règle déjà jouée | Bornes tenues, banc d'essai propre |
| **B2** | Fiche de personne dans « Le monde » ; chronique filtrée ; raisons affichées | Le visage se voit | **Playtest : s'attache-t-on à lui ?** |
| **B3** | **Les chefs voyageurs** : départ vers un lieu, effet par canal existant, retour comme candidat, pool déduit | Les départs ont un sens | — |
| **B4** | **Les bandes parlantes** : chef nommé, réputation, droit de passage | Le risque a un visage | Cadrage avec `Commerce::risqueDePillage` |
| **B5** | **La cour** : courtisan, humeur, accompagnement de la succession | Le long terme en Aventure | Vérification des noms avant d'écrire |
| **B6** | **Visages connus** sur la lignée ; calibrage ; valeurs au § 6 du plan de bataille | Équilibre | — |

**B1 est livrable seul.** Il commence par **le rival** parce qu'il existe, que ses
bornes sont écrites et jouées, et que ses trois issues sont déjà un **cadre de
conduite pour toute personne**. **On ne démarre pas B3 sans le playtest de B2** :
bâtir des voyageurs avant de savoir si l'on s'attache à un rival serait payer
cher pour du non vérifié.

## 11. Valeurs inventées — à calibrer

| Valeur | Amorce | Ce qu'elle infléchit |
|---|---|---|
| Nombre de personnes actives par partie | 6 à 10 | La densité du monde |
| Poids des cinq aspirations | à poser au banc d'essai | Le caractère d'une personne |
| Durée d'un projet | 3 à 8 quinzaines | L'inertie |
| Hasard parmi les meilleurs scores | les 2 premiers | La variété sans le bruit |
| Délai plancher avant le retour d'un rival payé | 12 quinzaines | La mémoire |
| Quinzaines de retrait d'un rival démonté | 30 | Le poids de l'enquête |
| Sensibilité à l'accord d'un rival déjà payé | −25 % de prix | L'effet d'une relation passée |
| Droit de passage d'une bande | 40 % du risque sur 6 quinzaines, prix indexé sur le butin | La troisième issue |
| Gain de relation par chef parti à Byblos | +1 par quinzaine, plafonné à +10 | Le poids du voyageur |
| Visages connus d'une lignée | 12 | La mémoire de la famille |

## 12. Questions ouvertes

| Question | Penchant |
|---|---|
| **Quel nom pour l'entité** (`Npc`, ou un mot de l'univers) ? | `Npc` pour la classe, vocabulaire de l'univers pour les rôles |
| **Combien de personnes à l'écran** avant que la fiche ne devienne un annuaire ? | 6 à 10 ; au-delà, la chronique, pas la fiche |
| **Les bandes peuvent-elles se déplacer ?** | Non en B ; à rouvrir avec un chantier de carte |
| **Une personne peut-elle changer de rôle** (un marchand devient un chef) ? | Non en B : un rôle, une vie |
| **Les visages connus s'effacent-ils d'eux-mêmes** après N parties sans les revoir ? | Oui, par ancienneté : le plus ancien cède au 13ᵉ |
| **Quels personnages historiques nomme-t-on ?** | Aucun avant vérification ; des titres sans nom d'ici là |
| **La cour a-t-elle un favori en Campagne**, ou seulement en Aventure ? | Aventure d'abord ; en Campagne, une mission est trop courte pour un favori |
