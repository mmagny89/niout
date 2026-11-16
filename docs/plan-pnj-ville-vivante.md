# Niout — La ville qui vit : panneau visuel et rejeu de la quinzaine

**Statut : cadrage, rien n'est codé.** Complète
[`plan-pnj-autonomes.md`](plan-pnj-autonomes.md) et ses paliers
[A](plan-pnj-palier-a.md), [B](plan-pnj-palier-b.md) et [C](plan-pnj-palier-c.md).
Il se lit avant de toucher à `src/Fenetre/VueDeLaVille.php`,
`Game/EmplacementsDeLaVille.php`, `Game/Maisonnees.php` ou à
`PassageDeCycle`.

## 1. L'intention

Les paliers A, B et C font vivre **le monde autour du joueur**, et ce monde se
lit surtout en **texte** : une chronique, des fiches, un récapitulatif. Ce plan
ajoute ce qui manquait : **voir sa propre ville habitée.**

- Un **panneau visuel** : la ville vue d'en haut, avec **des gens** — chez eux,
  à leur poste, dans les rues.
- On **clique** une personne : on lit **ce qu'elle a fait pendant la quinzaine,
  jour après jour**.
- On peut **rejouer la quinzaine en accéléré** et voir les déplacements dans la
  cité.

**Ce n'est pas la même chose que les paliers A, B, C.** Ceux-ci parlent des gens
**d'ailleurs** ; ce plan parle des gens **d'ici** — les habitants, les chefs, les
Medjaÿ de la ville du joueur. Les deux se rejoignent : un rival du palier B
s'arrête au Marché, la caravane d'une cité du palier C charge au Port.

## 2. Une honnêteté à poser d'abord : c'est une reconstitution

Le jeu **ne simule pas une journée**. Une quinzaine se résout **d'un bloc**, dans
`PassageDeCycle` : la paie, puis la production, les chantiers, le commerce…
Aucune personne n'y a d'emploi du temps. Le panneau **ne le fabrique donc pas en
cachette** : il **reconstitue**, à partir de ce qui a réellement eu lieu, une
journée **plausible** — et le dit.

Cinq règles, dont dépend la confiance qu'on peut lui accorder :

1. **Une scène illustre un fait, elle n'en crée pas.** Si l'Atelier a produit
   quatre poteries, le potier **les fabrique à l'écran** ; il n'y a pas de scène
   « production » sans production. Une scène sans fait est une **routine de
   métier**, étiquetée comme telle (« à son poste »).
2. **Le jour d'une scène n'a aucune valeur mécanique.** Le fait « la caravane est
   partie » a lieu *dans la quinzaine* ; que ce soit le 4ᵉ ou le 9ᵉ jour est un
   **choix de mise en scène**, stable (déduit d'une graine) mais **sans
   conséquence** : le joueur ne doit **rien** pouvoir en déduire sur les règles.
   L'écran porte la mention **« reconstitution »**.
3. **Aucune scène ne contredit l'ordre du cycle.** Ce qui est payé avant la
   production est vu **avant**, un chantier qui s'achève se voit à son dernier
   jour. Les contraintes d'ordre se **testent** (§ 11).
4. **On ne montre pas ce que le jeu ne tient pas.** Le jeu tient **trois nombres**
   de population (`Population`), pas des individus. Les gens anonymes sont donc
   **représentatifs**, dits tels ; seuls les **chefs et les Medjaÿ** sont de
   vraies personnes.
5. **La reconstitution est identique à chaque lecture.** Même quinzaine, mêmes
   gestes, au pixel près : sinon on rejoue autre chose que ce qui s'est passé.

**Le précédent exact existe déjà.** `Maisonnees` *« range la ville en maisonnées,
pour qu'on la voie au lieu de la lire. Une représentation, pas un état […]
déterministe, sans rien persister ni rien tirer au sort, ce qui éviterait qu'un
rechargement recompose le quartier sous les yeux du joueur »*. Ce plan en est
l'**extension dans le temps**.

## 3. Ce que le code fait aujourd'hui (vérifié)

| Fait | Où | Ce que le panneau en fait |
|---|---|---|
| La ville vue d'en haut est **un visuel fixe de 1376 × 768 px** (deux variantes, avec ou sans port), **quinze lots** de terre nue, des sprites posés dessus, **tout en pourcentages** | `VueDeLaVille`, `EmplacementsDeLaVille` | **Le fond du panneau**, tel quel : les figures se posent par-dessus, dans les mêmes pourcentages |
| **Chaque bâtiment a son lot, fixe** : « on ne choisit pas où bâtir, on choisit quoi — la ville se gère, elle ne se dessine pas » (doc 15) | `EmplacementsDeLaVille` | Les **lieux de travail** sont connus et **ne bougent pas** : c'est ce qui rend le panneau faisable |
| **Il n'existe aucun graphe de rues** : les chemins sont dans l'image, pas dans les données | `AncragesDesSprites` (généré) | **Seule vraie donnée à créer** : des points de passage (§ 5) |
| `Maisonnees` répartit la population en **foyers déterministes**, sans rien stocker | `Maisonnees`, `Population::foyersPour()` | La **strate des foyers** (§ 4) : déjà là |
| Les **chefs** sont des personnes : compétence, salaire, spécialité, traits, **foyer amené** ; les **Medjaÿ** aussi : spécialisation, expérience, équipement | `Employee`, `Medjay` | La **strate des nommés** : déjà là |
| Les **effectifs** par bâtiment se **dérivent** (nombre de travailleurs requis, rendement) | `Effectifs` | Combien de **silhouettes** au travail dans chaque bâtiment, sans rien inventer |
| Le **calendrier** compte **15 jours par quinzaine**, 25 cycles par an — **le dernier ne dure que 5 jours** (épagomènes) — et trois **fêtes attestées** : Opet, mystères d'Osiris, Belle Fête de la Vallée | `DateDeJeu`, `FeteCalendaire` | **La frise du rejeu** : 15 jours, **5 en fin d'année** ; les fêtes en sont des **jours marqués** |
| **La corvée d'Akhèt** mobilise les paysans que la crue désœuvre ; sans Nil, pas de corvée | `PassageDeCycle`, `GeographieDeLaPartie` | Une scène qui **n'est pas décorative** : on voit les paysans aux chantiers |
| Le **récapitulatif de la quinzaine** est fait de **deux photographies** de l'état (avant, après) et voyage en **message flash** : **il ne persiste pas** | `RecapitulatifDeQuinzaine` | **Le rejeu a besoin de ce qui n'est pas gardé** : une trace (§ 6) |
| Les **fenêtres** sont des `<dialog>` + Turbo Frame ; l'interactivité passe par **Stimulus**, jamais par du JS applicatif fait main ; **aucun CDN** | `plan-fenetres.md`, `assets/controllers/` | Le panneau est **une fenêtre** ; le JavaScript **joue**, il ne décide pas |
| Les sprites se fabriquent par **prompts**, puis `outils/decouper-*.py` ; `sources-sprites/` est ignoré par git | `docs/prompts-images-ville.md` | Le **coût** des figures est un vrai sujet (§ 9) |

## 4. Qui l'on voit : trois strates

| Strate | Qui | Ce que c'est | Cliquable |
|---|---|---|---|
| **Nommés** | Chefs en poste, Medjaÿ, **chef de famille** et héritier ; plus tard, les **visiteurs** des paliers B et C | **De vraies personnes** : leurs données existent dans le jeu | Oui — fiche complète |
| **Foyers** | Les maisonnées de `Maisonnees` | **Représentatifs** : chaque foyer a un **nom déduit d'une graine** (`PrenomEgyptien`), ses membres (actif, enfant, ancien, malade) | Oui — fiche de foyer, dite *représentative* |
| **Foule** | Les silhouettes qui animent les rues | **Décor** : un échantillon, jamais le compte | Non — mais **la foule montre l'état** (§ 6.2) |

**On ne dessine pas toute la ville.** Une ville a des dizaines d'habitants ;
afficher chaque personne noierait le panneau et coûterait en images. **Un plafond
de figures animées** (valeur inventée, § 12) s'applique, **les nommés d'abord**.

**La foule dit la santé de la ville** et c'est ce qui la rend utile, pas seulement
belle : clairsemée en famine, des silhouettes **couchées** quand l'épidémie
(`Epidemies`) frappe, **plus dense** au Marché les jours de fête. Chaque variation
suit **une donnée réelle** — jamais un effet d'ambiance qui mentirait.

**Ce que le panneau ne montre pas** : les **décès ordinaires** et les
**naissances** (`Demographie`) restent des **nombres** dans la fiche du jour. Pas
de cortège, pas de scène — le ton du jeu est celui de *« des malades, jamais des
morts »*, et un décès d'ancien tiré au sort n'est pas un événement qu'on met en
scène (§ 13).

## 5. Où : les lieux et les chemins

**Les lieux sont connus** : les quinze lots, le **Quartier d'habitation** (les
maisons), les **bords** de la scène (la porte des caravanes, le quai du Port). Il
manque **les chemins**.

- **Une donnée à créer** : un **graphe de points de passage** par visuel — **deux
  visuels, donc deux graphes**, d'une trentaine de points chacun, **écrits à la
  main** dans le même repère que `EmplacementsDeLaVille` (pixels de 1376 × 768,
  convertis en pourcentages).
- **Chaque lot a une porte** : le point où une personne **entre** dans le
  bâtiment, et d'où elle **ressort**.
- **Un outil de contrôle** dessine le graphe **par-dessus** le visuel (une page de
  développement) : on **voit** les chemins qui traversent un bâtiment. C'est
  l'équivalent du contrôle visuel de `decouper-batiments.py`.
- **Le chemin se calcule** : le plus court entre deux portes, **par une fonction
  pure** côté PHP — pas de recherche de chemin dans le navigateur.
- **Hors de la scène** : une expédition, une caravane en route **sortent par le
  bord** (la porte des caravanes, le quai) et **ne reviennent qu'à leur retour** :
  le panneau montre un **départ** et un **retour**, jamais la route elle-même.
- **Un zoom « le quartier »** (lot V4) : un deuxième visuel, **une rue de maisons**,
  où chaque maisonnée a **sa porte**. C'est la réponse à *« dans leur maison »* : on
  voit le foyer rentrer, les enfants dehors, le malade couché. Le coût est un
  visuel de plus ; il vient **après** que le principe a fait ses preuves.

## 6. Quand : la journée, le calendrier, et ce qui se passe vraiment

### 6.1 La journée en moments

Quinze jours de **quatre moments** (aube, matin, après-midi, soir) font **soixante
pas** ; la **nuit** se passe à la maison, sans scène. Un métier a **un rythme** :

| Rôle | Le matin | L'après-midi | Le soir |
|---|---|---|---|
| **Artisan** (Atelier, Forge) | Au poste | Au poste | Chez soi |
| **Négociateur, marchand** | Au Marché ou à l'Entrepôt | Au Port si un convoi part | Chez soi |
| **Prêtre** | Au Temple | Offrande, procession les jours de fête | Au Temple |
| **Scribe** | À la Maison des scribes | Idem | Chez soi |
| **Medjaÿ** | Caserne, ronde | Ronde, entraînement | Caserne |
| **Paysan** | Au champ (hors scène) | **Corvée aux chantiers** en Akhèt | Chez soi |
| **Enfant, ancien** | Autour des maisons, au Marché | À l'ombre | Chez soi |

Le rythme **suit la saison** : en Chémou, **on se repose aux heures chaudes** (les
silhouettes se concentrent à l'ombre à midi). C'est de la mise en scène, **sans
valeur de règle**.

### 6.2 Les faits qui deviennent des scènes

| Fait réel de la quinzaine | Scène | Où |
|---|---|---|
| Production d'un Atelier, d'une Forge | Les travailleurs **à leur poste**, la quantité dans la fiche | Le bâtiment |
| Un chantier avance ou s'achève | Des silhouettes sur le lot ; **le dernier jour** pour un achèvement | Le lot du bâtiment |
| Un **convoi part** ou **rentre** | Une silhouette **charge** au Port ou à l'Entrepôt, puis sort par le bord | Port, Entrepôt |
| **Corvée d'Akhèt** | Les paysans vont **aux chantiers** | Le lot du chantier |
| **Paie** | Le **chef de famille** à la Résidence, **au premier jour** | Résidence |
| **Fête** (Opet, Osiris, Vallée) | **Procession** vers le Temple, **jour marqué** sur la frise | Temple, rues |
| **Épidémie** | Des silhouettes **couchées** chez elles ; moins de monde aux postes | Maisons |
| **Famine** | **File au Grenier** | Grenier |
| **Rival, rencontre** (palier B) | Le personnage **arrive au Marché** | Marché |
| **Une naissance, un décès** | **Un chiffre** dans la fiche du jour | — |
| **Ordinaire** | **Routine de métier**, étiquetée | Le poste |

**Les jours se choisissent par graine** : `f(graine de la partie, quinzaine, fait)`.
Un convoi qui part ne peut pas **avoir déjà** rentré : les contraintes d'ordre
(départ avant retour, paie avant production) s'écrivent comme des **bornes** du
tirage du jour.

**Les trois états de la fenêtre**, du moins cher au plus riche :

1. **Maintenant** : l'état de la ville, **sans temps**. Chacun à sa place **à
   l'instant** (V1).
2. **Jour par jour** : un curseur sur la frise ; les figures **sautent** d'un lieu
   à l'autre, et la fiche liste les gestes du jour (V2).
3. **Rejouer** : les mêmes jours **animés**, déplacements compris (V3).

## 7. La trace : ce qu'il faut garder pour rejouer

Le rejeu **n'a de sens que si l'on sait ce qui s'est passé**. Or le jeu ne garde
rien d'une quinzaine passée : les écarts vivent un instant dans un message flash.

| À garder (nom provisoire) | Contenu | Durée |
|---|---|---|
| **`FaitDeMonde`** *(posé en A)* | Un fait structuré : type, **jour** nullable, **lieu** nullable, acteur nullable, quantité nullable | Les **3 dernières quinzaines** pour la ville, **40** pour le monde |
| **`InstantaneDeQuinzaine`** | **Une ligne par partie et par quinzaine** : un petit JSON — **qui est à quel poste**, quels chefs, quels Medjaÿ disponibles, effectifs par bâtiment, nombre de foyers, malades | Les **3 dernières**, purgées ensuite |

Le principe est celui du récapitulatif — **deux photographies avant/après** — étendu :
**une photographie au début** de la quinzaine, **les faits** pendant, et **l'état
d'après est l'état courant**. La reconstitution est donc **une fonction pure** de
ces trois choses : on **ne stocke aucune scène**.

Points de vigilance :

- **Taille** : quelques kilo-octets par ligne ; trois quinzaines par partie.
- **Les colonnes JSON portent un défaut** dans la migration (`'{}'`) — la règle
  de `tests.md`.
- **Rattaché à la partie, cascade `remove`** ; rien ne référence `User`,
  `SuppressionDeCompte` est inchangée.
- **Un `flush` par quinzaine**, avec le reste de `PassageDeCycle`.
- **Rejouer une quinzaine ancienne** (plus de trois) n'est **pas possible** :
  on le dit. La purge fait partie du contrat.

## 8. La chorégraphie : une fonction pure

```
choregraphier(instantané, faits, graine) → scénario
```

Le scénario est **une liste de pas par figure** : `jour, moment, lieu, geste,
fait lié`. Il **ne contient pas de texte** : des **clés** (`geste: 'fabrique'`,
`fait: 'production'`), que Twig rend. **La logique reste en PHP, l'affichage en
Twig** : règle du projet.

- **Pur** : aucun accès à la base, à l'horloge, ni au hasard global. Tout entre par
  les paramètres. C'est la discipline du moteur de la V1 de `simulation`
  (`avancer(monde, durée, données)`), appliquée à un rendu.
- **Déterministe** : même entrée, même scénario.
- **Les nommés d'abord** : ils sont placés **avant** la foule, qui remplit **ce
  qui reste** du plafond.
- **Les chemins** : le plus court entre deux portes, **calculé côté serveur**.
  Le navigateur **reçoit** des trajets, il ne les cherche pas.
- **Les visiteurs** des paliers B et C se **branchent ici** : leurs intentions
  (`decider()`) produisent leurs scènes, avec **leurs raisons** — *« Panehsy est au
  Marché : Byblos manque de lin »*.

## 9. L'écran

Une **fenêtre au-dessus de la carte**, la même que « Le monde » (A3) : deux
**onglets**, **La ville** et **Le monde**. Le premier regarde **ici**, le second
**ailleurs**.

**Composition** :

1. **Le plan** de la ville (le visuel existant) et, par-dessus, **le calque des
   figures**.
2. **La frise** des 15 jours (5 en fin d'année), les **fêtes marquées**, un
   **curseur**.
3. **Les commandes** : lecture, pause, **vitesses**, **recommencer**.
4. **La fiche** de la personne cliquée : portrait, métier, bâtiment, foyer, **ses
   quinze jours**, journal par jour, **le lien vers le fait** (le convoi, le
   chantier) qu'une scène illustre.
5. **Des filtres** : par métier, par bâtiment.

**Les vitesses** : « normale » (une journée ≈ 4 s), **« rapide »** (une quinzaine
en ≈ 30 s), « très rapide » (≈ 10 s). **« Suivre »** garde une personne
sous les yeux.

**Principes d'interface**, hérités de `interface.md` :

- **Le texte équivalent existe toujours.** La fiche liste les gestes jour par jour :
  **le visuel est un plus, jamais la seule voie**. C'est ce qui fait que l'écran
  reste lisible aux lecteurs d'écran et sans animation.
- **`prefers-reduced-motion`** : aucune animation ; on **avance jour par jour** au
  clic, comme la V1 de `simulation` coupait les siennes.
- **Cibles d'au moins 44 px à 390 px** : sur téléphone, les figures cliquables
  sont **agrandies** et **la fiche s'ouvre en feuille basse**, pas en panneau
  latéral.
- **Aucun état par la couleur seule** : un malade est **couché** et **porte un
  pictogramme**, pas seulement une teinte.
- **La lecture se met en pause** quand l'onglet n'est plus visible, et **à
  l'ouverture d'une fiche**.
- **Un plafond de figures animées** (§ 12) : au-delà, l'écran saccade.

**La technique, sans JavaScript applicatif** :

- **PHP décide**, tout : lieux, chemins, jours, gestes. Le serveur rend **le
  scénario en données** (un JSON compact dans un attribut).
- **Un petit contrôleur Stimulus** (`ville_vivante_controller.js`) **joue** : il lit
  le scénario et **anime** (API Web Animations ou variables CSS). **Il ne calcule
  ni lieu ni geste.** C'est la frontière de `vente_controller.js` et
  `curseur_controller.js` : *décoratifs*, la fenêtre fonctionne **sans eux**.
- **Poids** : une quarantaine de figures × quinze jours × quelques pas — **quelques
  dizaines de kilo-octets**, à **mesurer**.
- **Aucune ressource externe** : ni police, ni bibliothèque d'animation.

**L'art des figures.** Des **silhouettes de profil, à l'égyptienne** — la
représentation même du Nouvel Empire — s'y prêtent bien : **deux orientations
seulement** (gauche et droite, l'une est le miroir de l'autre), **peu de
gestes**, et **un petit répertoire** (paysan, artisan, scribe, prêtre, Medjaÿ,
marchand, femme, enfant, ancien, chef de famille). Deux voies, **à trancher** :

- **des pictogrammes SVG** dessinés à la main, **légers, sans coût de
  génération**, cohérents avec la planche de pictogrammes de l'interface ;
- **des sprites** produits par prompts comme ceux de la ville, **plus riches, plus
  coûteux**, et dont la cohérence avec les bâtiments est un risque (le piège
  des échelles déjà payé).

Dans les deux cas, **à confronter à la direction artistique** (doc 15 du Drive) et à
**consigner dans `docs/prompts-images-ville.md`** si l'on passe par des sprites.

## 10. Liens avec les paliers A, B et C

| Palier | Ce que le panneau lui doit | Ce qu'il lui apporte |
|---|---|---|
| **A** | La **graine de partie**, `FaitDeMonde`, la fenêtre **« Le monde »** | La **foule** montre la conjoncture : plus de caravanes quand le commerce est bon, une file au Grenier quand les vivres manquent |
| **B** | Les **intentions** et leurs **raisons** | Un rival, un chef revenu, un courtisan **ont un lieu** : on les **croise**. Le « pourquoi » se **voit** |
| **C** | Les cités autonomes | Une **caravane** d'une cité **arrive au Port** ; un envoyé traverse la ville |

**Aucun des trois n'est un prérequis**. Les lots V1 à V3 ne demandent **que ce qui
existe** : les chefs, les Medjaÿ, les maisonnées. C'est la raison de l'ordre du
§ 12 : **ce plan peut commencer avant A**.

## 11. Comment on le vérifie

| Contrôle | Ce qu'il prouve |
|---|---|
| **Déterminisme** | Même instantané, mêmes faits, même graine → **même scénario**, pas après pas |
| **Chaque fait a sa scène** | Pour chaque fait de la quinzaine, **au moins une scène** ; **aucune scène sans fait** hors routine étiquetée |
| **Ordre du cycle** | La paie précède la production ; un départ de convoi précède son retour ; un chantier achevé se voit à son **dernier** jour |
| **Compte des gens** | Les figures **au travail** d'un bâtiment valent ses effectifs **dans la limite du plafond** ; le total des foyers vaut `Population::foyersPour()` — **l'invariant de `Maisonnees`** |
| **La santé se voit** | Une ville en famine montre **moins** de silhouettes et **une file** ; en épidémie, **des couchées** : un test par état |
| **Chemins** | Aucun trajet ne **traverse un bâtiment** ; deux portes quelconques sont **reliées** |
| **Quinze jours, ou cinq** | Le 25ᵉ cycle de l'année dure **5 jours** et la frise le dit |
| **Rejeu identique** | Deux lectures successives donnent **le même film** |
| **Mouvement réduit, texte seul** | Sans animation, **toute l'information** reste accessible |
| **Navigateur** | Fenêtre, frise, fiche, rejeu ; **à 390 px et en desktop** (skill `verification-ui-navigateur`) : débordement, cibles de 44 px, figures perdues, balisage orphelin, lignes de debug. **Mesurer la fluidité** avec le plafond de figures |
| **Poids** | Le scénario d'une quinzaine **tient sous un seuil** posé à l'avance |

Ce qu'aucun test ne dira : **si la reconstitution se sent vivante ou factice**. C'est
la question du playtest de V3.

## 12. Découpage

| Lot | Contenu | Sortie | Verrou |
|---|---|---|---|
| **V0** | Ce document, les décisions du § 14 | Cadrage validé | — |
| **V1** | **« La ville habitée »** : le calque de figures **à l'instant**, chacun **à sa place** (nommés, foyers, foule), **sans temps ni trace**. Les points de passage ne sont **pas** nécessaires. Fiche d'un nommé et d'un foyer | **Du visuel, tout de suite**, avec **les seules données qui existent** | **Playtest : regarde-t-on ?** |
| **V2** | **La trace** (`InstantaneDeQuinzaine`, `FaitDeMonde` de ville) et la **chorégraphie pure** ; **frise** de 15 jours, curseur, **journal jour par jour** dans la fiche. **Les figures sautent**, elles ne marchent pas | Le **jour par jour**, en texte et en lieux | Déterminisme, ordre du cycle, compte des gens |
| **V3** | **Les chemins** (graphe des deux visuels, outil de contrôle), **le rejeu animé**, vitesses, suivi, `prefers-reduced-motion` | **Le film de la quinzaine** | **Playtest : vivant ou factice ?** Fluidité à 390 px |
| **V4** | **Le zoom « quartier »** : une rue, des maisons, des portes | « Dans leur maison » | Un visuel de plus à produire |
| **V5** | Les **visiteurs** des paliers B et C ; **les raisons** à l'écran | Le monde entre en ville | **A et B livrés** |
| **V6** | Passe **artistique** (sprites ou pictogrammes finis), calibrage, valeurs au § 6 du plan de bataille | Finition | — |

**V1 est le lot qui répond le plus vite à l'attente** : *« autre chose que seulement du
texte »*. Il n'a **besoin ni de trace, ni de chemins, ni de palier A** — seulement
de ce que `Maisonnees`, `Employee` et `Medjay` savent déjà. **On commence par lui**,
et le playtest dit si le reste vaut d'être bâti. **V3 est le lot cher** : le graphe
de rues, l'animation, la mesure de fluidité. On ne l'engage pas avant d'avoir vu
V1 regardé.

## 13. Valeurs inventées — à calibrer

| Valeur | Amorce | Ce qu'elle infléchit |
|---|---|---|
| Plafond de figures animées | 40 | La fluidité à 390 px |
| Part de foule dans ce plafond | 50 % | L'équilibre entre nommés et ambiance |
| Moments par jour | 4 | La finesse de la reconstitution |
| Vitesse « normale » | 4 s par jour | Le confort de lecture |
| Vitesse « rapide » | ≈ 30 s la quinzaine | Le plaisir du film |
| Quinzaines gardées pour le rejeu | 3 | Le poids de la trace |
| Quinzaines gardées pour le monde | 40 | Idem |
| Points de passage par visuel | ≈ 30 | Le réalisme des trajets |
| Poids maximal d'un scénario | à poser | Le temps de chargement |

## 14. Questions ouvertes

| Question | Penchant |
|---|---|
| **Par quel lot commence-t-on ?** | **V1**, sans trace ni chemins |
| **Pictogrammes SVG ou sprites ?** | SVG au début, de profil ; sprites plus tard si la direction artistique le veut |
| **Les décès ordinaires sont-ils un jour mis en scène ?** | Non : un chiffre dans la fiche |
| **Les nommés ont-ils un portrait ?** | Oui, **déduit** du métier et du trait, sans image par personne |
| **Rejouer plus de trois quinzaines ?** | Non : le contrat dit que la trace se purge |
| **Une ville sans Port (variante sans eau) montre-t-elle un autre visuel ?** | Oui, comme aujourd'hui : un graphe par visuel |
| **Les enfants et les anciens sont-ils cliquables ?** | Par **foyer**, pas par individu |
| **Quel mot pour l'écran ?** | « La ville » (onglet), « Le film de la quinzaine » (rejeu) |
| **Le son ?** | Non : aucune ressource audio, et l'accessibilité d'abord |
| **Et sur la carte isométrique**, des silhouettes aux cases ? | Non : la carte reste la carte ; la ville est dans sa fenêtre |
