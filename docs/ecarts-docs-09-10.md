# Écarts entre les documents 09 / 10 et le code

Relecture des documents **09 Lore et campagne** et **10 Énigmes et enquêtes**
(Drive, modifiés le 1er septembre 2026) confrontés au code livré.

Ce document est un **plan de travail temporaire** : il disparaît une fois ses
lots faits ou classés. Les décisions qui en sortiront rejoindront
[`plan-de-bataille.md`](plan-de-bataille.md) et
[`regles-du-jeu.md`](regles-du-jeu.md).

---

## Ce qui concorde déjà

Inutile d'y revenir : les dix missions, leurs pharaons, leurs villes et leurs
types correspondent au document ; les quêtes de chantier reprennent ses valeurs
au chiffre près (4 cycles, 20 à 50 unités, +5 renommée, +10 faveur, −2 au
refus) et ses neuf monuments réels ; la clé de lecture suit `4 + 2 × niveau`
jusqu'à vingt signes au niveau 8 ; la pénalité de déduction erronée vaut deux
cycles sans perte de ressource ; la réussite partielle, l'affichage des
objectifs dès le premier jour et le legs du pharaon sont tranchés dans le même
sens des deux côtés.

Les objectifs par mission suivent les pistes du document, à un détail près
(lot 4 ci-dessous).

---

## Lot 1 — L'alphabet des scribes  ✅ *(livré)*

**Doc 10, section « L'alphabet des scribes ».** Une piste pédagogique
**entièrement neuve**, et explicitement **distincte** de la clé de lecture : les
24 signes unilitères, ceux qui notent un son. Le document en donne la table
complète — code de Gardiner, objet représenté, translittération, son approché —
et signale que l'objet de `Aa1` est débattu.

Le code n'en a rien. `SymboleHieroglyphique` écrit même l'inverse en toutes
lettres : « on ne fait pas apprendre l'égyptien, on fait lire des rébus ». La
phrase reste vraie pour la clé de lecture ; elle devient fausse pour le jeu.

Quatre usages sont demandés, d'ampleur très inégale :

| Usage | Ce qu'il demande |
|---|---|
| **Écrire « Niout »** — n + i + w + t, énigme fondatrice de la mission 1 | Le plus petit, et le plus beau : une énigme, quatre signes, un geste inaugural |
| **Transcrire le nom de famille** au lancement | Une translittération approchée, avec la note que l'égyptien ne notait pas les voyelles |
| **Cartouches royaux** à l'introduction de chaque mission | Neuf cartouches réels à établir — travail de sourcing, pas de code |
| **Déblocage progressif**, ~3 signes par niveau de Maison des scribes | Une seconde piste à côté de `CleDeLecture`, sur le même bâtiment |

**Question du rendu Unicode : vérifiée et tranchée.** Les vingt-quatre signes
s'affichent, on reste sur du texte, et la police se charge avec la page plutôt
que de dépendre de la machine. Pas de planche de sprites.

**Livré**, sept cartouches sur neuf — les deux autres n'affichent rien plutôt
qu'une approximation. Conception :
[`lot-1-alphabet-des-scribes.md`](lot-1-alphabet-des-scribes.md) ; règles
retenues : [`regles-du-jeu.md`](regles-du-jeu.md) ; récit :
[`plan-de-bataille.md`](plan-de-bataille.md), Phase 8 ter.

---

## Lot 2 — Les stèles historiques par mission  *(nouveau)*

**Doc 09, section « Stèles historiques par mission », reprise par le doc 10 sous
« Contenu authentique ».** Chaque pharaon commanditaire a laissé une stèle
réelle, et le document en dresse la table avec, pour chacune, son contenu et un
**niveau de confiance** — quatre confirmées par source consultée, cinq « bien
établies » mais non vérifiées par citation directe.

Aujourd'hui, les inscriptions du fil rouge sont des **rébus inventés**
(`Inscription`), fidèles aux signes mais pas au contenu. Le document demande
qu'elles paraphrasent une stèle réelle, présentée avec son vrai nom et son lieu
de conservation.

Trois choses à trancher avant de coder :

1. **Toutes les missions, ou seulement celles qui s'y prêtent ?** Le document
   pose lui-même la question et cite Thoutmôsis III (stèle poétique, texte
   traduit consulté) et Akhenaton (stèles-frontières d'Amarna, qui parlent
   justement de la fondation d'Akhetaton) comme les deux cas où contenu
   pédagogique et fil rouge coïncident. Commencer par ces deux-là coûte peu et
   dit si la greffe prend.
2. **Vérifier les cinq stèles « bien établies »** avant de les nommer à
   l'écran — Tombos, Deir el-Bahari, Amarna, les stèles d'Amenhotep III. Le
   document le demande explicitement.
3. **Paraphrase, jamais citation**, y compris pour les traductions anciennes.
   C'est une contrainte de droits que le document répète deux fois.

Le mécanisme existant s'y prête : `Inscription::provenance()` dit déjà d'où
vient une pierre. Il suffirait qu'elle nomme une vraie stèle plutôt qu'un lieu
générique, et qu'un champ de plus porte le lieu de conservation.

---

## Lot 3 — La mission 9 est de type « Exploiter »  *(petit, mais à clarifier)*

**Doc 09.** Le tableau des missions donne à l'Ouadi Hammamat le type
**Exploiter**. Le code la range en `Developper`, et `TypeDeMission` ne connaît
que trois cas.

**Le document se contredit lui-même** : sa section « Les 3 types de mission »
n'en liste toujours que trois, sans Exploiter. Deux lectures possibles, et
c'est à toi de dire laquelle :

- **un quatrième type assumé** — un camp minier temporaire n'est ni une
  fondation ni un développement, et le nommer le dirait ;
- **une coquille du tableau**, auquel cas le code a raison et c'est le document
  qui se corrige.

Le coût est le même dans les deux sens : un cas d'enum, un libellé, une ligne
de catalogue.

---

## Lot 4 — Deux calibrages qui divergent  *(à classer, pas à corriger)*

Ce ne sont pas des oublis mais des **décisions prises contre le document**,
déjà consignées dans [`plan-de-bataille.md`](plan-de-bataille.md). Elles sont
rappelées ici pour que la relecture ne les redécouvre pas comme des défauts :

| Point | Document 09 | Code | Pourquoi |
|---|---|---|---|
| Richesse | `200 + 50 × d` **en or** | `250 + 75 × d` **en deben** | Le document compte encore en or comme si c'était la monnaie ; l'Égypte pharaonique n'en a pas |
| Population | `20 + 10 × d` travailleurs | `12 + 4 × d` habitants | Seuil mesuré sur deux cents parties : une ville à Quartier 1 monte à treize |
| Commerce, ressource | `500 + 100 × d`, `100 + 20 × d` | `400 + 120 × d`, `60 + 15 × d` | Recalibrés sur l'économie réelle des Phases 4 et 5 |

**Un seul vrai écart de contenu** : le document veut pour la mission 9
« grauwacke **et or** » ; le code demande grauwacke et une trésorerie. Le
Ouadi Hammamat portant bien de l'or dans sa géographie, aligner le code sur le
document est trivial — reste à savoir si deux objectifs de ressource pure sur
la même mission ne la rendent pas monotone.

---

## Lot 5 — Les énigmes secondaires ne sont pas par mission  *(divergence ancienne)*

**Doc 10, calibrage** : `nombreEnigmesSecondaires = 5 à 8 par mission`.

Le code en porte **onze au total**, valables partout, filtrées par le bâtiment
où on les entend (`Enigme::lieu()`) et non par la mission. Une partie qui
enchaîne les dix missions les épuise donc au bout de la première ou de la
deuxième.

Ce n'est pas nouveau, mais le document le chiffre, et le chiffre ne tient pas.
Deux issues : écrire un corpus par région — c'est du contenu, beaucoup —, ou
assumer un corpus commun et le dire dans le document. La seconde est
défendable : une devinette sur la brique crue vaut au Delta comme au Fayoum.

Deux formats du document restent par ailleurs à l'état de QCM là où il annonce
un mini-jeu : la **reconnaissance astronomique** (associer un décan à un mois)
et l'**association symbolique** (relier un animal à son dieu). Le fond est
juste, la forme est plus pauvre que ce qui est écrit.

---

## Ce qui reste

Le lot 1 est livré. Restent, par ordre de coût croissant :

1. **Lot 3** — dire ce qu'il en est d'« Exploiter » : une heure.
2. **Lot 4 et 5** — deux décisions, pas de code : garder l'écart et le
   consigner, ou aligner.
3. **Lot 2** — les stèles, en commençant par Thoutmôsis III et Akhenaton, les
   deux que le document désigne. Vérifier les cinq stèles « bien établies »
   avant de les nommer à l'écran.

Et, hors de ces lots : **les deux cartouches manquants**, Akhenaton et
Ramsès IV, qui demandent une source égyptologique plus sûre que celles
consultées.
