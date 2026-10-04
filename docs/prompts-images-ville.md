# Niout — Prompts d'images pour la ville vue d'en haut

Ces prompts remplacent les planches de bâtiments et les deux visuels de ville
(`ville`, `ville_port`) de la première génération. Ils s'envoient à une IA de
génération d'image. **Ils sont en anglais** — c'est la langue que ces outils
suivent le mieux, et celle du préambule du document 15 — ; tout le reste est en
français.

## 1. Pourquoi la première série rend « bizarre »

Quatre causes, vues sur la capture du jeu :

| Défaut | Ce qu'on voit | Cause |
|---|---|---|
| **Échelles qui ne collent pas** | Un petit silo de palier 1 occupe autant de place que le temple monumental | Chaque sprite était mis à la largeur de son enclos : un bâtiment minuscule était gonflé. Les planches n'avaient aucune échelle commune |
| **Perspective incohérente** | Certains bâtiments paraissent vus plus d'en haut, ou tournés, par rapport au plan | Chaque cellule de planche a été dessinée avec son propre angle ; rien n'imposait la même caméra que le plan de ville |
| **Enclos peints dans le décor** | Les bâtiments se posent à côté, ou par-dessus, des murets du plan | Le plan contenait déjà des enclos dessinés ; le sprite ne les recouvre jamais exactement |
| **Fond de ville** | Un rectangle blanc cassé autour du terrain | Le visuel n'a pas de fond maîtrisé |

**Le remède tient en une règle** : *chaque sprite porte son propre terrain (le « lot ») et
tous les paliers d'un même bâtiment partagent exactement le même lot*. Le plan de
ville ne contient plus aucun enclos, seulement du terrain nu — le code pose les lots
par-dessus. L'échelle devient une constante de la ville, pas une affaire de sprite.

## 2. Cahier des charges commun

À respecter dans **tous** les prompts.

**Caméra.** Isométrie 2:1 (« dimetric », celle des jeux de gestion classiques) : un carré au
sol devient un **losange deux fois plus large que haut**. Les arêtes du sol vont
**de bas-gauche à haut-droite** et **de haut-gauche à bas-droite**, jamais à l'horizontale
ni à la verticale. Les murs d'un bâtiment sont parallèles à ces arêtes : on voit **deux
façades**, celle de gauche et celle de droite, plus le toit.

**Lumière.** Une seule source, **en haut à gauche** : toit le plus clair, façade de gauche
éclairée, façade de droite plus sombre. Ombre portée courte, **à l'intérieur du lot
seulement**.

**Entrées.** La porte principale est sur la **façade de gauche** (celle qui regarde en
bas à gauche), sauf le **Port**, dont le quai regarde **en bas à droite** — vers le
fleuve.

**Style.** Illustration 2D stylisée, légèrement peinte, Égypte du Nouvel Empire,
palette chaude — ocre, sable, terre cuite — avec lapis-lazuli et or pour le religieux
et le prestige. Pas de photoréalisme. Lisible à petite taille (le bâtiment sera
affiché à 150–250 px de large).

**Fond.** **Blanc pur uni (#FFFFFF)** autour de l'objet, sans dégradé, sans ombre
projetée sur le fond, sans texte, sans cadre. (Les générateurs ne font pas de vraie
transparence : un blanc uni se détoure proprement.)

**Le lot.** Plate-forme au sol en losange : terre battue, **mur de brique crue très bas**
sur tout le pourtour (environ 4 % de la largeur du lot). Elle est **identique** — même
taille, même forme, même position — dans les quatre paliers d'un bâtiment.

| Classe de lot | Largeur du losange, en % de la largeur de l'image | Unités du plan |
|---|---|---|
| **S** (petit) | 50 % | 2 × 2 |
| **M** (moyen) | 68 % | 3 × 3 |
| **L** (grand) | 88 % | 4 × 4 |

## 3. Le plan guide

Deux images sont dans `sources-sprites/` — **à joindre** au prompt des deux visuels de
ville pour que le générateur respecte le quadrillage :

- `guide_ville.png` — plan avec les noms des bâtiments ;
- `guide_ville_sans_texte.png` — le même, sans texte (à joindre en premier : certains
  générateurs recopient les mots).

Ville de **17 × 17 unités**. Cinq lots L (Temple, Résidence, Marché, Caserne, Port),
quatre M (Scribes, Quartier d'habitation, Entrepôt, Grenier), six S (Atelier, Forge,
Auberge, et trois lots de décor). Le **fleuve** longe le bord en bas à droite ; le
Port est le lot qui le touche.

## 4. Ordre de travail

1. **Visuel `ville`** (prompt A) — terrain, chemins, végétation, **sans aucun enclos**.
2. **Visuel `ville_port`** (prompt B) — *retouche* du premier : tout identique, plus le
   fleuve et le ponton. Ne pas regénérer de zéro : les deux plans doivent se
   superposer au pixel.
3. **Lots vides** (prompt C) — S, M, L : ce qu'on voit tant qu'un bâtiment n'est pas
   construit.
4. **Un bâtiment à la fois** (prompt D) — en joignant le résultat du prompt C et,
   dès que l'un est validé, **le premier bâtiment validé comme référence d'échelle et
   de style** pour les suivants.
5. Déposer les images dans `sources-sprites/v2/` (`ville.png`, `ville_port.png`,
   `lots.png`, `grenier.png`…) et me prévenir : je recale les coordonnées, retaille et
   découpe.

**Contrôle à l'œil à chaque image**, avant d'enchaîner :
- les arêtes du sol vont bien en diagonale, à 2:1 ;
- le lot occupe toujours **la même place** dans les quatre cellules ;
- le palier 1 est visiblement **plus petit** que le palier 4, sur le **même** lot ;
- aucune ombre, aucun décor ne sort du lot ;
- le fond est blanc uni.

## 5. Préambule commun (à coller en tête de chaque prompt)

```
Stylized 2D isometric game art, 2:1 dimetric projection: the ground square becomes a
diamond exactly twice as wide as it is tall; ground edges run diagonally, never
horizontal or vertical. Ancient Egyptian New Kingdom setting. Warm ochre, sandstone
and terracotta palette with lapis-lazuli and gold accents for religious or prestige
elements. Soft painterly shading, clean readable silhouettes, not photorealistic.
Single light source from the top-left: brightest roofs, lit left wall, darker right
wall; short soft shadow kept inside the object's own base. Flat pure white (#FFFFFF)
background, no gradient, no cast shadow on the background, no text, no watermark, no
border, no grid lines.
```

## 6. Prompt A — le visuel `ville`

*Joindre : `guide_ville_sans_texte.png`, puis `guide_ville.png`.*

```
[PRÉAMBULE COMMUN]

Create the top-down isometric terrain of a small ancient Egyptian town, seen as a
single diamond-shaped slab of earth floating on the white background, with a visible
thick edge of earth along its two front sides.

Use the attached blueprint EXACTLY for the layout. The slab is the large beige diamond;
the colored diamonds mark building clearings: 5 large (4x4 units), 4 medium (3x3), 6
small (2x2). Keep every clearing at the exact position and size shown, keep the
isometric 2:1 angle of the blueprint.

Paint only the GROUND, no buildings and no walls:
- each colored diamond becomes a flat clearing of bare packed earth, slightly smaller
  than its blueprint diamond (about 5% inset), with NO border, NO wall and NO fence;
  the 3 grey "decor" clearings stay the same bare earth;
- between the clearings, a network of narrow dirt paths one unit wide, forming clean
  straight streets that follow the diamond grid, with small slightly irregular edges;
- grass patches and low scrub in the gaps, a few date palms and acacias scattered along
  the slab's back and left edges and between paths, never inside a clearing;
- the slab edge at the front: a thick strip of layered earth, darker on the right side.

Do NOT draw the blue river from the blueprint: on this version the front-right edge is
simply dry land with a few reeds.
Do NOT copy any text from the blueprint.
Output 16:9, 1792x1024.
```

## 7. Prompt B — le visuel `ville_port`

*Joindre : le visuel `ville` validé. À envoyer comme une **retouche** (« edit »), pas
comme une nouvelle génération.*

```
Edit the attached image. Keep EVERYTHING identical — same slab, same clearings, same
paths, same vegetation, same camera, same 1792x1024 framing, same white background —
except along the front-right edge of the slab:

- add a calm blue-green Nile river running along the whole front-right edge, filling the
  lower-right part of the canvas, with a gently curved natural bank of reeds and wet
  sand;
- add a short wooden pier (about 2 units long) leaving the bank, aligned with the
  diamond grid, in front of the large bottom-right clearing;
- the river must stay in the isometric 2:1 projection: its banks follow the same
  diagonal as the slab edge.

Change nothing else.
```

## 8. Prompt C — les lots vides S, M, L

*Joindre : rien (c'est la référence de taille). Générer **une** image avec les trois.*

```
[PRÉAMBULE COMMUN]

Create a sprite sheet with exactly 3 items in one row, left to right: a SMALL, a MEDIUM
and a LARGE empty building lot. Each item sits in its own square cell, 3 equal cells
side by side, no overlap, wide white spacing.

A building lot is a flat diamond-shaped platform of packed earth with a very low
mud-brick border wall all around (about 4% of the lot width), a few small stones and
tufts on the ground, and nothing built on it. Perfect 2:1 isometric diamond.

Sizes, as a share of ONE cell's width: small lot 50%, medium lot 68%, large lot 88%.
All three lots are centered horizontally in their cell and share the same vertical
center. Keep the exact same style, color and thickness for the three.
```

## 9. Prompt D — un bâtiment, quatre paliers

*Joindre : l'image des lots vides (prompt C) + (dès qu'il existe) le premier bâtiment
validé. Un prompt par bâtiment ; remplacer `[BÂTIMENT]`, `[CLASSE]` et les quatre
paliers par la ligne du tableau.*

```
[PRÉAMBULE COMMUN]

Create a sprite sheet with exactly 4 items in a 2x2 grid, representing the same
building, [BÂTIMENT], at 4 increasing levels of development (left to right, top to
bottom = weakest to strongest). Each item sits in its own square cell, 4 equal cells,
no overlap, wide white spacing.

THE LOT IS THE SCALE. Every cell contains the SAME [CLASSE]-size building lot from the
attached reference image (flat diamond platform of packed earth with a very low
mud-brick border), drawn at the exact same size, shape and position in all four cells:
centered horizontally, same vertical center. Lot width = [50 % / 68 % / 88 %] of the
cell width. The building stands INSIDE the lot and never crosses its border.

Camera and orientation, identical to the reference: 2:1 isometric, two visible walls
(left wall lit, right wall darker), light from the top-left, MAIN ENTRANCE ON THE LEFT
WALL [Port: quay and boats on the right wall, toward the river].

How the four levels grow, always on the same lot:
- Level 1: a small, modest structure using about 40% of the lot, the rest bare earth.
- Level 2: larger, about 60% of the lot.
- Level 3: imposing, about 80% of the lot.
- Level 4: the most accomplished version, filling about 95% of the lot; may rise higher.
Building height never exceeds the lot's width.

The four levels are: [LES QUATRE PALIERS].
```

### Les douze bâtiments

La classe de lot suit le plan guide (§ 3). Les quatre paliers reprennent le
document 15, traduits.

| Bâtiment | Lot | Les quatre paliers (`[LES QUATRE PALIERS]`) |
|---|---|---|
| **Résidence familiale** | L | 1. single-door mud-brick house with a flat roof · 2. bigger house, painted door frame, small courtyard · 3. two-storey house, decorated façade, a palm tree · 4. villa with a columned entrance, garden, painted walls |
| **Temple** | L | 1. small mud-brick chapel with a niche · 2. small temple with a painted pylon · 3. temple with a columned courtyard and an obelisk · 4. grand complex with a double pylon and an avenue of sphinxes |
| **Marché** | L | 1. a few stalls under awnings · 2. small market square · 3. busy market, goods on display · 4. large square with covered arcades |
| **Caserne** | L | 1. guard post with a weapon rack · 2. barracks with a drill yard · 3. barracks with an archery range · 4. fortified barracks with a chariot yard (bows and shields, never spears) |
| **Port** | L | 1. wooden pier with one boat · 2. pier with two boats and fishing nets · 3. busy quay, several boats, cargo being loaded · 4. grand harbor with warehouses and ships. *Quay and boats on the right wall, toward the river* |
| **Maison des scribes** | M | 1. one small room, a seated scribe, scrolls · 2. house with shelves of scrolls · 3. scribe school, several scribes at work · 4. great House of Life with a library and a courtyard |
| **Quartier d'habitation** | M | 1. a group of three huts · 2. a block of six houses, narrow alleys · 3. dense block with shared courtyards, washing lines · 4. large quarter with a well and small chapels |
| **Entrepôt** | M | 1. rectangular storehouse · 2. storehouse with wooden doors, stacked amphorae · 3. large warehouse with a loading dock and donkeys · 4. complex with a caravan courtyard |
| **Grenier** | M | 1. one small round silo · 2. two beehive silos · 3. a row of four silos on a platform · 4. complex with a scribe's office |
| **Atelier** | S | 1. one-room workshop with a potter's wheel · 2. workshop with a kiln and a loom · 3. multi-room workshop, artisans visible · 4. large complex with specialised wings |
| **Forge** | S | 1. small forge with a simple furnace · 2. forge with an anvil and stacked copper ingots · 3. busy forge, several furnaces · 4. forge-armoury complex with a storage yard |
| **Auberge** | S | 1. small one-room inn · 2. inn with a courtyard and tethering ring · 3. lively inn, travellers, nearby stalls · 4. great caravanserai with stables |

## 10. Ce que je ferai à la réception

1. **Recaler le plan** : mesurer sur l'image reçue le centre et la taille réels de chaque
   lot (le générateur ne suit pas le guide au pixel), et mettre à jour
   `Game\EmplacementsDeLaVille`.
2. **Une échelle par ville, pas par sprite** : chaque sprite est posé à une échelle
   unique par classe de lot, calée sur la largeur du lot — fini les petits bâtiments
   gonflés.
3. **Découpe** : `outils/decouper-batiments.py` (fond blanc, détourage par remplissage
   depuis les bords).
4. **Lots vides** à la place des enclos dessinés : un enclos pas encore bâti montre le
   lot vide.
5. Mettre à jour le **document 15** du Drive : il tient le fond de ville à emplacements
   pour *abandonné* (« Ce qui a été abandonné… »), ce qui n'est plus vrai.

## 11. En cas d'échec

- **Le générateur ignore le plan guide** : lui décrire le plan en mots — « a 17×17 grid;
  large lots at columns…, rows… » — marche moins bien ; préférer régénérer, ou me
  confier le plan et composer le décor en deux temps (un sol nu, puis chemins).
- **Le lot change de taille d'un palier à l'autre** : ne pas retoucher à la main ;
  regénérer en rappelant « the lot is IDENTICAL in all four cells, same width and same
  position » et en joignant le lot vide.
- **Les paliers se ressemblent trop** : renforcer le pourcentage d'occupation du lot
  (40 / 60 / 80 / 95 %) plutôt que de demander « plus grand ».
- **Une IA qui n'accepte qu'une image** : regrouper le plan guide et le lot vide sur un
  même fichier.
