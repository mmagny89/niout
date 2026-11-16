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
| Les **chefs** sont suivis un par un : compétence (affichée en étoiles), salaire, spécialité, traits, **foyer amené** ; les **Medjaÿ** aussi : spécialisation, expérience, équipement, **mort permanente** au combat. **Aucun des deux n'a de nom aujourd'hui** (décision de la joueuse, **levée le 2026-11-16** : voir § 16) | `Employee`, `Medjay` | La **strate des désignés** : déjà là ; **le prénom est à créer** (lot N) |
| Les **effectifs** par bâtiment se **dérivent** (nombre de travailleurs requis, rendement) | `Effectifs` | Combien de **silhouettes** au travail dans chaque bâtiment, sans rien inventer |
| Le **calendrier** compte **15 jours par quinzaine**, 25 cycles par an — **le dernier ne dure que 5 jours** (épagomènes) — et trois **fêtes attestées** : Opet, mystères d'Osiris, Belle Fête de la Vallée | `DateDeJeu`, `FeteCalendaire` | **La frise du rejeu** : 15 jours, **5 en fin d'année** ; les fêtes en sont des **jours marqués** |
| **La corvée d'Akhèt** mobilise les paysans que la crue désœuvre ; sans Nil, pas de corvée | `PassageDeCycle`, `GeographieDeLaPartie` | Une scène qui **n'est pas décorative** : on voit les paysans aux chantiers |
| Le **récapitulatif de la quinzaine** est fait de **deux photographies** de l'état (avant, après) et voyage en **message flash** : **il ne persiste pas** | `RecapitulatifDeQuinzaine` | **Le rejeu a besoin de ce qui n'est pas gardé** : une trace (§ 6) |
| Les **fenêtres** sont des `<dialog>` + Turbo Frame ; l'interactivité passe par **Stimulus**, jamais par du JS applicatif fait main ; **aucun CDN** | `plan-fenetres.md`, `assets/controllers/` | Le panneau est **une fenêtre** ; le JavaScript **joue**, il ne décide pas |
| Les sprites se fabriquent par **prompts**, puis `outils/decouper-*.py` ; `sources-sprites/` est ignoré par git | `docs/prompts-images-ville.md` | Le **coût** des figures est un vrai sujet (§ 9) |

## 4. Qui l'on voit : trois strates

| Strate | Qui | Ce que c'est | Cliquable |
|---|---|---|---|
| **Désignés** | Chefs en poste, Medjaÿ, **chef de famille** (le seul qui ait un prénom, en Aventure) et le **rival** installé ; plus tard, les **visiteurs** des paliers B et C | **De vraies personnes** : leurs données existent dans le jeu. **Un prénom chacun** (lot N, § 16) : *« Nakhtmin, chef du Grenier »* | Oui — fiche |
| **Foyers** | Les maisonnées de `Maisonnees` | **Représentatifs** : une **maison numérotée**, ses membres (actif, enfant, ancien, malade). Un nom déduit d'une graine (`PrenomEgyptien`) reste possible, **mais le jeu n'en donne à personne d'autre** : question ouverte (§ 14) | Oui — fiche de foyer, dite *représentative* |
| **Foule** | Les silhouettes qui animent les rues | **Décor** : un échantillon, jamais le compte | Non — mais **la foule montre l'état** (§ 6.2) |

**On ne dessine pas toute la ville.** Une ville a des dizaines d'habitants ;
afficher chaque personne noierait le panneau et coûterait en images. **Un plafond
de figures animées** (valeur inventée, § 12) s'applique, **les désignés d'abord**.

**La foule dit la santé de la ville** et c'est ce qui la rend utile, pas seulement
belle : clairsemée en famine, des silhouettes **couchées** quand l'épidémie
(`Epidemies`) frappe, **plus dense** au Marché les jours de fête. Chaque variation
suit **une donnée réelle** — jamais un effet d'ambiance qui mentirait.

**Ce que le panneau ne montre pas** : les **décès ordinaires** et les
**naissances** (`Demographie`) restent des **nombres** dans la fiche du jour ; **la mort d'un Medjaÿ au combat** (mort permanente, doc 03) reste un fait du journal, pas une scène. Pas
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
- **Les désignés d'abord** : ils sont placés **avant** la foule, qui remplit **ce
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
| **N** | **Un prénom pour chaque chef et chaque Medjaÿ** (§ 16) : colonne, attribution à la création, rattrapage des existants, affichage. **Livrable seul**, avant V1 | Les gens ont un nom, **dans tout le jeu** | Migration jouée sur la base de test, suite complète |
| **V1** | **« La ville habitée »** : le calque de figures **à l'instant**, chacun **à sa place** (désignés, foyers, foule), **sans temps ni trace**. Les points de passage ne sont **pas** nécessaires. Fiche d'un désigné et d'un foyer | **Du visuel, tout de suite**, avec **les seules données qui existent** | **Playtest : regarde-t-on ?** |
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
| Part de foule dans ce plafond | 50 % | L'équilibre entre désignés et ambiance |
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
| **Les chefs et les Medjaÿ reçoivent-ils un nom ?** | **Décidé le 2026-11-16 : oui**, stocké, attribué à la création (§ 16, lot N) |
| **Les désignés ont-ils un portrait ?** | Oui, **déduit** du métier et du trait, sans image par personne |
| **Rejouer plus de trois quinzaines ?** | Non : le contrat dit que la trace se purge |
| **Une ville sans Port (variante sans eau) montre-t-elle un autre visuel ?** | Oui, comme aujourd'hui : un graphe par visuel |
| **Les enfants et les anciens sont-ils cliquables ?** | Par **foyer**, pas par individu |
| **Quel mot pour l'écran ?** | « La ville » (onglet), « Le film de la quinzaine » (rejeu) |
| **Le son ?** | Non : aucune ressource audio, et l'accessibilité d'abord |
| **Et sur la carte isométrique**, des silhouettes aux cases ? | Non : la carte reste la carte ; la ville est dans sa fenêtre |

## 15. Lot V1 — « La ville habitée » : la spécification

**Retenu le 2026-11-16 comme premier lot, après le lot N (§ 16) : les désignés y portent un prénom.** Ce qui suit est ce qu'on écrira, fichier
par fichier, **quand le code sera autorisé** — rien n'est codé à ce jour.

### 15.1 Ce que V1 est, et ce qu'il n'est pas

**V1 pose des silhouettes sur la ville vue d'en haut, à l'instant présent**, d'après
ce que le jeu sait déjà, et ouvre une fiche au clic. **Il n'ajoute ni table, ni
migration, ni contrôleur Stimulus, ni animation.** Tout est calculé à la volée, par
une fonction déterministe, comme `Maisonnees`.

| V1 fait | V1 ne fait pas |
|---|---|
| Des silhouettes **à leur poste, au quartier, à la caserne** | Aucun **temps** : pas de jours, pas de frise, pas de rejeu |
| Les **désignés** : chefs en poste, Medjaÿ, chef de famille, rival | Aucune **trace** d'une quinzaine passée |
| Des **foyers représentatifs** au Quartier, les malades **couchés** | Aucun **chemin** : personne ne marche |
| Les **bras sans poste** vus au Marché ; les paysans « aux champs » en pastille | Pas de visiteurs des paliers A, B, C |
| Une **fenêtre « Les gens de la ville »** : la liste équivalente et les fiches | Pas de zoom sur le quartier |
| La mention **« reconstitution »** | Pas de sprites : des silhouettes SVG |

### 15.2 Ce qui existe, et suffit (vérifié)

| Besoin | Source dans le code |
|---|---|
| Où se dresse chaque bâtiment (centre du lot, classe de lot) | `EmplacementsDeLaVille::tous()`, en pixels de 1376 × 768 |
| Quel bâtiment est dressé | `City::batimentDeType()`, `VueDeLaVille` |
| **Le prénom** d'un chef ou d'un Medjaÿ | **N'existe pas encore** : lot N (§ 16) |
| Les chefs en poste, leur bâtiment, leur spécialité, leurs traits | `City::getEmployes()`, `Employee::estEnPoste($cycle)`, `getType()`, `getSpecialite()` |
| Les Medjaÿ, **disponibles ou blessés** | `City::getMedjays()`, `Medjay::estDisponible($cycle)`, `getRetabliAuCycle()` |
| Combien de travailleurs **à chaque poste** | `Effectifs::repartir($ville, $cycle)` → `affectes`, `requis` |
| Les bras **oisifs** et les postes **vides** | `Effectifs::bilan()` → `oisifs`, `manquants` |
| Les paysans **aux champs** | `Effectifs::brasPourLesChamps()` |
| Les maisons, leurs habitants, leurs malades | `Maisonnees::repartir()`, `Maisonnees::decrire()` |
| Le chef de famille | `Family::getChefDeFamille()` (nul hors Aventure) |
| Le rival installé | `City::getRival()` — **il a déjà un nom** (`Panehsy le marchand`) |
| L'ouverture d'une fenêtre | `data-turbo-frame="fenetre"`, `fenetre#ouvrir`, `app_partie_ville` |
| Le droit de voir une partie | `#[IsGranted(PartieVoter::VOIR, subject: 'partie')]` |

### 15.3 Les fichiers

| Fichier | Rôle |
|---|---|
| `src/Fenetre/GensDeLaVille.php` *(neuf)* | **Le service.** `pour(GameSave): array` rend les figures et les listes. **Pur, déterministe, sans écriture.** Aucune règle de jeu nouvelle : il **lit** |
| `src/Fenetre/RoleDeFigure.php` *(neuf, enum)* | Chef, Medjaÿ, ChefDeFamille, Rival, Travailleur, Foyer, Malade, SansPoste. Porte le **symbole** et le **libellé** |
| `src/Controller/PartieController.php` | Une route `app_partie_gens` : `GET /partie/{id}/ville/gens`, `?cle=…` pour une fiche. **Même contrôle d'accès** que `app_partie_ville` ; une `cle` inconnue → 404 |
| `templates/partie/_vue_de_la_ville.html.twig` | Un calque de figures **après** les sprites, un bouton « Les gens de la ville », la mention de reconstitution |
| `templates/partie/_figures_de_la_ville.html.twig` *(neuf)* | Le calque : une boucle, aucune logique |
| `templates/partie/_symboles_de_figures.html.twig` *(neuf)* | Les `<symbol>` SVG, **inline, une seule fois** |
| `templates/fenetre/gens.html.twig` *(neuf)* | La liste « qui est où » et la fiche |
| `tests/…` | § 15.7 |
| `CHANGELOG.md`, `docs/interface.md`, `docs/regles-du-jeu.md` | **Au moment du commit**, pas après (§ 15.8) |

### 15.4 Le service

**Une figure** porte : une **clé stable** (`chef-<id>`, `medjay-<id>`, `famille`,
`rival`, `maison-<n>`, `poste-<bâtiment>-<i>`), un **rôle**, un **libellé**, un
**lieu** (le bâtiment), une **position** en pourcentages du visuel, une
**orientation** (gauche ou droite), une **profondeur**, un **état** (debout, couché),
un **lien** (la fiche) ou rien, et un **libellé pour lecteur d'écran**.

**Règles de composition**, par priorité décroissante, sous **un plafond de 40
figures** (valeur inventée, § 13) :

1. **Le chef de famille**, à la Résidence. Son prénom s'il existe (Aventure), sinon
   « le chef de la famille ».
2. **Les chefs en poste**, **un par chef**, à leur bâtiment — *« Nakhtmin, chef du Grenier »*,
   par son **prénom** (lot N).
3. **Les Medjaÿ**, à la Caserne s'ils sont disponibles ; **couchés au Quartier**
   s'ils sont blessés.
4. **Le rival**, au Marché, s'il est installé.
5. **Des travailleurs représentatifs** : par bâtiment, **`min(affectés, 3)`**
   silhouettes — **le compte exact est dans la fiche**, pas dans le nombre de
   silhouettes. Un bâtiment **à l'arrêt** (aucun affecté) **n'en a aucune** : c'est
   une information vraie, visible d'un coup d'œil.
6. **Les bras sans poste** : **`min(oisifs, 3)`** au Marché.
7. **Des foyers** : jusqu'à **6 maisons représentatives** au Quartier ; les
   **malades** y sont **couchés**.
8. **Une pastille « aux champs : N »** en marge — les paysans sont **hors scène**.

**Une figure par désigné d'abord, la foule ensuite** : si le plafond est atteint, on
coupe la foule, **jamais** un chef.

**Le placement** : un **décalage par lot**, d'après un **petit tableau d'ancrages**
(devant la porte, en éventail, selon la classe du lot — grand, moyen, petit), converti
en pourcentages **dans le même repère** que les sprites ; **borné à l'intérieur du
visuel**. **L'orientation et le léger décalage** se tirent d'une graine **dérivée de
l'identifiant de la partie** (`Randomizer` à moteur semé, jamais le hasard global) :
**stables d'une visite à l'autre**, comme les héritiers.

**Le service ne dessine rien** : il rend des données ; le gabarit les affiche.

### 15.5 L'affichage

- **Le calque** : à l'intérieur du conteneur du visuel, **après** les sprites ;
  chaque figure est **en `absolute`, en pourcentages**, `z-index` = profondeur du lot
  **+ 1** (devant son propre bâtiment, derrière un bâtiment plus proche). Le conteneur
  a déjà `isolation: isolate`.
- **La géométrie est posée en ligne** — position, taille, retournement
  (`transform: scaleX(-1)`) — **règle du projet** : une classe Tailwind oubliée par la
  compilation ferait écraser toutes les figures sur une ligne, comme les sprites une
  fois l'ont fait.
- **Les silhouettes** : un petit jeu de `<symbol>` SVG **de profil, à l'égyptienne**,
  coloré par les **jetons de couleur du thème** — jamais une valeur codée en dur. **Une
  teinte par rôle ne suffit pas** : un malade est **couché** et porte **un
  pictogramme**.
- **Cliquables à partir de `md`** : un lien vers `app_partie_gens?cle=…`, avec
  `data-turbo-frame="fenetre"` et `data-action="click->fenetre#ouvrir"`, **comme un
  sprite**. Sous `md`, les figures sont **décoratives** (`aria-hidden`, pas de clic) :
  elles seraient **trop petites pour 44 px** et recouvriraient les bâtiments.
- **Le bouton « Les gens de la ville »**, **toujours là**, d'**au moins 44 px**, à
  côté du titre : **c'est la voie de téléphone et de lecteur d'écran**.
- **La mention** : *« Image reconstituée — le jeu ne suit pas chaque habitant. »*,
  sous le bouton.
- **Aucune ressource externe** ; **aucun JavaScript neuf**.

### 15.6 La fenêtre « Les gens de la ville »

**La liste équivalente** — une section par lieu : *Résidence*, *Grenier — chef,
gestionnaire ; 3 travailleurs en poste (2 requis)*, *Quartier — 12 maisons, 3
malades*, *Caserne — 2 Medjaÿ disponibles, 1 blessé jusqu'au cycle N*, *Marché — un
rival, 2 bras sans poste*, *Aux champs — 14*. **Tout ce que le panneau visuel montre
s'y lit**, sans image.

**La fiche** (`?cle=…`) :

| Cible | Contenu |
|---|---|
| **Chef** | **Prénom**, bâtiment (**lien vers sa fenêtre**), spécialité, **compétence en étoiles** (jamais en nombre : *« chiffré en interne, qualitatif à l'affichage »*), traits, foyer amené |
| **Medjaÿ** | **Prénom**, spécialisation, expérience (qualitative), équipement, **disponible ou blessé jusqu'à** |
| **Chef de famille** | Prénom ou titre, génération, trait de lignée |
| **Rival** | Nom, route disputée, ce qu'il prend, **les trois issues** (lien vers l'écran du commerce) |
| **Maison** | `Maisonnees::decrire()`, **dite représentative** |
| **Travailleurs d'un bâtiment** | Affectés, requis, rendement ; lien vers le bâtiment |

**La mention de reconstitution y figure** pour les maisons et les travailleurs ;
**pas pour les chefs ni les Medjaÿ**, qui sont réels.

### 15.7 Les tests

| Test | Ce qu'il prouve |
|---|---|
| **Déterminisme** | Deux appels sur la même partie rendent **exactement** les mêmes figures |
| **Plafond et priorité** | Jamais plus de 40 figures ; **si on coupe, c'est la foule**, jamais un chef |
| **Fidélité** | Autant de figures « chef » que de chefs **en poste** ; Medjaÿ **disponibles** à la Caserne, **blessés** couchés ; figures « malade » ≤ `City::malades()` ; « sans poste » ≤ `oisifs` ; maisons ≤ `Population::foyersPour()` |
| **Pas de fantôme** | Aucune figure à un bâtiment **non dressé** ; aucun travailleur à un bâtiment **à l'arrêt** |
| **Bornes** | Toute position dans `[0, 100]` % ; clés **uniques** |
| **Route des gens** | **200** pour la propriétaire ; **403/404** pour une autre joueuse (IDOR) ; `cle` inconnue → 404 ; une clé qui vise **une autre partie** → 404 |
| **Vue de la ville** (`VueDeLaVilleTest`, étendu) | Le calque, le bouton et la mention s'affichent ; chaque lien porte un `aria-label` |
| **Gabarit** | Aucun calcul dans Twig ; aucune couleur codée en dur |

**Contrôle au navigateur** (skill `verification-ui-navigateur`, Claude in Chrome sur
`http://localhost`), **à 390 px et en desktop** : défilement horizontal
(`scrollWidth` contre `clientWidth`), **figures qui sortent du visuel**, **figures
qui recouvrent un lien de bâtiment ou « Retour au territoire »**, cibles **sous
44 px**, balisage orphelin, lignes de debug. **Avant, vider `app/public/assets`** si
le build compilé existe, et lancer `tailwind:build` : une classe neuve n'existe
qu'après. Ce qu'on regarde n'est pas ce que les tests voient.

**Les quatre portes** (style, PHPStan niveau 8, audit des dépendances, tests) passent
**une fois**, avant de committer ; pendant le développement, **seulement les tests
concernés**.

### 15.8 La documentation à écrire avec le code

- **`CHANGELOG.md`**, sous « Non publié », **dans le commit** : *« La ville vue d'en
  haut est habitée : chefs, Medjaÿ, foyers et rival s'y voient à leur place, et une
  fenêtre liste « qui est où » »*.
- **`docs/interface.md`** : une section sur le calque, la règle des 44 px, la liste
  équivalente, la mention de reconstitution.
- **`docs/regles-du-jeu.md`** : *une représentation, pas un état* ; **ce que chaque
  figure lit** ; **un bâtiment à l'arrêt n'a aucun travailleur à l'écran**.
- **`docs/phases-livrees.md`** : l'entrée du lot, ses pièges.

### 15.9 Les scénarios d'acceptation

1. **Une ville neuve** : le chef de famille à la Résidence, quelques maisons au
   Quartier, **aucun chef, aucun travailleur** — une ville calme, et c'est vrai.
2. **Un chef au Grenier** : une silhouette au Grenier, étiquetée *« Nakhtmin, chef du Grenier »* ; le clic ouvre sa fiche ; la fiche **renvoie** à la fenêtre du
   Grenier.
3. **Une épidémie** : des silhouettes **couchées** au Quartier, **moins** de
   travailleurs aux postes ; la liste le dit.
4. **Un rival installé** : un marchand **au Marché**, par son nom.
5. **Un Medjaÿ blessé** : couché ; **debout à la Caserne** une fois rétabli.
6. **Des bras sans poste** : des silhouettes oisives au Marché, et la liste dit
   *« 2 bras sans poste »*.
7. **Un bâtiment à l'arrêt** : **aucun** travailleur, et la fiche en dit la raison.
8. **À 390 px** : aucun débordement ; le bouton atteint 44 px ; **la liste suffit** ;
   les figures ne gênent aucun lien.
9. **Sans JavaScript, sans animation** : tout fonctionne.

### 15.10 Les décisions à prendre **avant** de coder

| Question | Penchant |
|---|---|
| ~~Les chefs et les Medjaÿ restent-ils sans nom ?~~ | **Tranché le 2026-11-16 : ils reçoivent un prénom.** V1 **dépend du lot N** (§ 16) |
| **Les maisons ont-elles un nom** ? | **Numérotées** (« maison 7 ») : le jeu ne nomme personne d'autre |
| **Silhouettes en SVG faits main, ou sprites générés ?** | SVG : léger, sans pipeline, cohérent avec les pictogrammes ; les sprites viendraient avec V6 |
| **Le rival et les bras sans poste dans V1 ?** | **Oui** : ce sont des **données vraies** qui rendent le panneau utile, et non un décor |
| **Figures cliquables sur desktop seulement ?** | **Oui** ; le bouton et la liste font le reste |
| **Un plafond de 40 est-il juste ?** | **À mesurer** au navigateur, à 390 px surtout |

## 16. Lot N — un prénom pour chaque chef et chaque Medjaÿ

**Décidé le 2026-11-16.** Jusqu'ici, `Employee` et `Medjay` n'avaient pas de nom,
par décision de la joueuse : *« on le désigne par son bâtiment et sa spécialité »*.
Cette décision est **levée**, parce que le panneau visuel est précisément le lieu où
une silhouette sans nom ne se laisse pas attacher. Elle ne se contourne pas : elle
se **remplace**, et ses traces — docblocks, règles, gabarits — se réécrivent.

### 16.1 Stocké, pas déduit

On aurait pu **déduire** le prénom d'une graine, comme les héritiers. **Ce n'est pas
retenu**, et la raison est précise : un prénom déduit **du rang** d'un chef dans la
ville change dès qu'un chef plus ancien part (`DepartsNaturels`) — tous les suivants
se décalent. Déduit **de l'identifiant**, il ne garantit pas l'unicité : trente
prénoms, des collisions certaines. **Un nom qui change sous les yeux du joueur est
pire que pas de nom.** Il est donc **gardé**, comme l'est déjà le prénom du chef de
famille (`Family::chefDeFamille`).

**Statut : livré et mergé dans `develop`** (branche `feat/prenoms-des-gens`, migration `Version20261116132813`, `Game\Prenoms`, `PrenomsTest`). Écarts avec le plan ci-dessous : les prénoms se donnent à l'annonce par `Prenoms::nommerLesCandidats()` (et non dans `GenerateurDeCandidat`, qui reste sans notion de ville) ; **le fonds des gens exclut dès maintenant** les dix hauts fonctionnaires de `PrenomEgyptien`, faute de les avoir relus un par un (`ordinaires()`), sans toucher à `tous()` que les héritiers indexent ; un message de récapitulatif — départ, embauche, renvoi, levée — porte le prénom. **Non vérifié au navigateur** : le rendu est gardé par un test de gabarit, pas encore regardé à 390 px.

### 16.2 Ce qui change

| Où | Quoi |
|---|---|
| `Employee`, `Medjay` | Une colonne **`prenom`**, **nullable**, `VARCHAR(40)` |
| `Candidat`, `JobOffer` | Le **candidat porte déjà son prénom à l'annonce** : on embauche *quelqu'un*, on ne découvre pas son nom après. La sérialisation des candidatures accepte l'**absence** de la clé (offres déjà affichées) |
| `GenerateurDeCandidat` | Tire le prénom **avec** le reste du candidat, par le `Randomizer` injecté |
| `Medjays::lever()` | Tire le prénom de la recrue |
| `PrenomEgyptien` | Sépare **masculins** et **féminins** selon les blocs déjà écrits ; ajoute les accès |
| Un service d'attribution (nom provisoire `Prenoms`) | **Une seule** règle : choisir un prénom **libre** dans la ville |
| `PassageDeCycle` | Une étape qui **donne un prénom à qui n'en a pas** (§ 16.4) |
| Gabarits et messages | Le prénom **précède** la désignation : *« Nakhtmin — chef du Grenier, gestionnaire »* |

### 16.3 L'attribution

- **Un prénom libre** : parmi ceux que ne portent ni un chef en poste, ni un Medjaÿ,
  ni le chef de famille, ni un candidat d'une annonce ouverte, **ni le rival** installé.
- **Les Medjaÿ tirent dans le fonds masculin**, les chefs dans l'ensemble.
- **Quand les trente prénoms sont pris** — une grande ville, tard —, on ajoute **la
  filiation** : *« Hori, fils de Sennedjem »*. C'est la **façon dont les Égyptiens se
  distinguaient** ; ce n'est pas une invention. Au-delà d'un cas absurde, un numéro
  d'ordre plutôt qu'une boucle infinie.
- **Le rival** garde son propre fonds (`Rivaux::unNom()`, « *X le marchand* ») ; la
  règle d'unicité **le lit**, pour qu'on ne rencontre pas deux « Hori ».
- **Déterminisme** : le tirage passe par le `Randomizer` injecté, **semé en test**.

### 16.4 Les gens déjà là

Les parties en cours ont des chefs **sans prénom**. Pas de migration qui les remplisse
(elle ne saurait pas appeler la règle d'unicité), pas d'écriture pendant une
consultation. **Une étape idempotente de `PassageDeCycle`** donne un prénom, **dans
l'ordre des identifiants**, à qui n'en a pas, et passe au même `flush` que le reste.
**Entre-temps**, le gabarit retombe sur la désignation actuelle : *« Chef du
Grenier »*. Aucune partie ne montre un trou.

### 16.5 Ce qu'il faut vérifier avant d'écrire

- **Les prénoms de `PrenomEgyptien`** viennent de deux sources : des gens ordinaires
  de Deir el-Médineh, et **des hauts fonctionnaires identifiés** de la nécropole
  thébaine (le fonds cite par exemple Rekhmirê, Ineni, Sennefer). Les donner à un
  chef de grenier **laisse entendre** que c'est *lui*. **À relire, nom par nom**, avant
  de les étendre aux chefs ; en cas de doute, **on retire** plutôt qu'on affiche une
  approximation — la règle du projet.
- **Les Medjaÿ étaient à l'origine un peuple de Nubie**, devenu un corps de
  police. Un fonds de noms **propre à eux** serait plus juste ; il demande des
  sources. **Question ouverte** (§ 16.8) : en attendant, le fonds masculin commun.
- **Le sexe d'un prénom** : le fonds répartit déjà ses blocs (les épouses et les
  filles sont à part). On s'y tient, sans rien affirmer de plus.

### 16.6 Les tests

| Test | Ce qu'il prouve |
|---|---|
| **Unicité** | Dans une ville, **aucun prénom n'est porté deux fois** — chefs, Medjaÿ, chef de famille, rival, candidats ouverts |
| **Stabilité** | Un chef **garde** son prénom quand un autre part ou arrive |
| **Filiation** | Quand le fonds est épuisé, on **n'échoue pas** et le nom reste unique |
| **Medjaÿ** | Un Medjaÿ **ne reçoit jamais** un prénom du fonds féminin |
| **Rattrapage** | L'étape donne un prénom à qui n'en a pas, **une seule fois** : l'appeler deux fois ne change rien |
| **Candidat** | Le prénom **vu à l'annonce** est **celui du chef embauché** |
| **Rétrocompatibilité** | Une annonce **sans** clé `prenom` se lit encore |
| **Gabarits** | Aucun gabarit ne montre un prénom **vide** ; la désignation de repli s'affiche |
| **Migration** | Jouée sur la base **de développement et de test** (`--env=test`), puis **la suite complète** avant de merger : c'est l'un des cas où elle se justifie |

Les tests existants qui **comparent les désignations** (`Chef du …`) se mettent à jour
**avec** le lot, pas après.

### 16.7 La documentation à réécrire avec le code

- Les **docblocks** de `Employee` et de `Medjay`, qui affirment *« il n'a pas de nom »*.
- `docs/regles-du-jeu.md` : la règle des chefs et des Medjaÿ ; **ajouter la raison de
  ce choix** (un nom qui s'attache, un nom qui ne bouge pas).
- `docs/interface.md` : l'ordre *prénom, puis désignation*.
- `CHANGELOG.md`, sous « Non publié », **dans le commit** : *« Chaque chef et chaque
  Medjaÿ porte désormais un prénom »*.
- **Le palier B** : *« notre Négociateur »* peut redevenir *« Ouserhat, notre
  Négociateur »* ; la question du prénom d'un chef voyageur y est **tranchée**.

### 16.8 Ce qui reste ouvert

| Question | Penchant |
|---|---|
| **Un fonds de prénoms propre aux Medjaÿ** (origine nubienne) ? | Le fonds commun d'abord ; un fonds propre quand on aura **les sources** |
| **Les prénoms de hauts fonctionnaires identifiés** dans le fonds ? | **Les retirer du fonds des chefs** : ils désignent des personnes réelles |
| **Un chef renvoyé ou parti garde-t-il son prénom** s'il revient comme candidat (palier B) ? | **Oui** : c'est tout l'intérêt de B |
| **Les travailleurs et les foyers ont-ils un prénom ?** | **Non** : le jeu ne les suit pas ; ils restent représentatifs et numérotés |
| **Le prénom entre-t-il dans les messages du récapitulatif** (« X s'en va ») ? | **Oui**, c'est là qu'il se fait sentir ; la désignation suit |
