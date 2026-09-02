# Lot 1 — L'alphabet des scribes

Conception du système demandé par le **doc 10, section « L'alphabet des
scribes »** : les vingt-quatre signes unilitères, ceux qui notent un son.

Ce document précède le code. Une fois le lot livré, ce qu'il fixe rejoint
[`regles-du-jeu.md`](regles-du-jeu.md) et disparaît d'ici.

---

## 0. Le rendu des glyphes — vérifié, tranché

Le doc 10 laissait la question ouverte : « vérifier l'affichage correct des
glyphes hiéroglyphiques Unicode selon la police retenue — à défaut, prévoir une
planche de sprites dédiée aux 24 signes ».

**Vérifié au navigateur, sur les vingt-quatre signes.** Résultat :

- **Les vingt-quatre s'affichent**, y compris `Q3`, qui ressemble à un carré
  vide mais est bien la natte de roseau de Gardiner — un rectangle à trait fin.
  Comparé côte à côte avec un caractère non assigné, l'écart est net : le tofu
  est un rectangle à trait épais, plus grand.
- **Aucune police hiéroglyphique n'est installée sur la machine de
  développement**, et le rendu vient donc d'un repli du navigateur. Ce repli
  fonctionne ici ; rien ne dit qu'il fonctionne ailleurs, et c'est précisément
  ce qu'on ne peut pas vérifier machine par machine.
- Avec **Noto Sans Egyptian Hieroglyphs** chargée depuis Google Fonts, six
  signes changent de forme — `D36`, `N29`, `N37`, `S29`, `V13`, `D46` — et
  prennent leur dessin canonique. Le repli n'était donc pas seulement fragile :
  il était par endroits **moins juste**.

**Décision : du texte Unicode, et la police embarquée. Pas de planche de
sprites.**

Trois raisons de préférer le texte :

1. le signe reste **sélectionnable et lisible par un lecteur d'écran** — c'est
   déjà l'argument retenu pour la clé de lecture ;
2. une planche de sprites figerait la taille, alors que l'alphabet doit
   s'afficher aussi bien en table qu'en gros dans un cartouche ;
3. vingt-quatre images à découper, c'est le lot 12 par la petite porte.

Et une raison d'embarquer la police plutôt que de s'en remettre au système :
**un joueur sous Windows ou Android n'a rien qui couvre le bloc égyptien**, et
verrait vingt-quatre carrés. La police pèse ~120 Ko, se charge une fois, et
`font-display: swap` évite qu'elle bloque le rendu.

> **À faire dans ce lot** : ajouter `--font-hieroglyphes` au `@theme` de
> `app.css`, et l'appliquer partout où un glyphe s'affiche — y compris sur la
> **clé de lecture existante**, qui souffre du même aléa depuis le lot 7.1 sans
> que personne l'ait vu.

---

## 1. Ce que le document demande, et ce qu'on en retient

> « Distinct de la clé de lecture ci-dessus […] cette fonctionnalité enseigne le
> véritable alphabet phonétique unilitère égyptien. »

**Deux pistes séparées sur le même bâtiment**, et c'est le point structurant.
Elles ne se mélangent jamais :

| | Clé de lecture (existant) | Alphabet des scribes (ce lot) |
|---|---|---|
| Nature des signes | **Logogrammes** — un signe, une chose | **Phonogrammes** — un signe, un son |
| À quoi ça sert | Lire les inscriptions du fil rouge | Écrire un mot, lire un cartouche |
| Combien | 20 | 24 |
| Ouverture | `4 + 2 × niveau` | `3 × niveau` (voir § 3) |
| Enrichie par | Le niveau, une énigme réussie, le Déchiffreur, Thot | Le niveau seul |

**Pourquoi ne pas fusionner**, alors que six signes sont communs (`M17`, `N35`,
`D21`, `X1`, `V31`, `D36`) ? Parce qu'un même dessin **n'y veut pas dire la
même chose** : `N35` est « l'eau » dans la clé, et le son *n* dans l'alphabet ;
`X1` est « le pain », et le son *t*. Les confondre enseignerait le contraire de
ce que le document veut faire comprendre — que l'écriture égyptienne est
**mixte**, et qu'un signe s'y lit tantôt comme une chose, tantôt comme un son.

C'est aussi ce qui donne sa formule à l'écran : le même glyphe apparaît dans
les deux tables, avec deux lectures. **Ne pas dédupliquer.**

---

## 2. Le contenu — `SigneAlphabetique`

Un enum de vingt-quatre cas, dans `src/Game/`, du même moule que
`SymboleHieroglyphique` : du contenu, jamais de l'état.

```php
enum SigneAlphabetique: string
{
    case VautourPercnoptere = 'vautour_percnoptere';   // G1  Ȝ
    case RoseauFleuri       = 'roseau_fleuri';         // M17 i
    // … 22 autres, dans l'ordre du document
}
```

Chaque cas porte quatre choses, toutes tirées du document :

| Méthode | Contenu | Exemple |
|---|---|---|
| `codeDeGardiner()` | Le vrai code | `G1` |
| `signe()` | Le glyphe Unicode | `𓄿` |
| `objet()` | Ce que le dessin représente | « Vautour percnoptère » |
| `translitteration()` | La convention égyptologique | `Ȝ` |
| `son()` | Le son approché, en français | « Coup de glotte » |

**Une précision du document à ne pas perdre** : l'objet de `Aa1` est **débattu**
parmi les égyptologues — « parfois identifié comme un tamis ». Le document le
signale explicitement plutôt que de trancher, et l'écran doit faire de même. Un
booléen `objetIncertain()` suffit, et c'est la même discipline que
`Enigme::sourceAttestee()`.

**L'ordre est celui du document**, qui est l'ordre conventionnel des grammaires
— pas un ordre de difficulté inventé. C'est celui que le joueur retrouvera dans
n'importe quel manuel.

---

## 3. Le déblocage — `AlphabetDesScribes`

Le document donne « ex. 3 signes/niveau — valeur à calibrer ». La Maison des
scribes plafonne au niveau 8 : `3 × 8 = 24`. **La formule tombe juste, on la
garde telle quelle.**

```php
final readonly class AlphabetDesScribes
{
    public const int SIGNES_PAR_NIVEAU = 3;

    /** @return list<SigneAlphabetique> */
    public static function pour(City $ville): array;
}
```

Quatre points qui découlent de ce qui existe déjà :

- **Rien à persister.** La clé de lecture stocke ce qu'une énigme apprend ;
  l'alphabet ne s'ouvre que par le niveau, donc il se **calcule**. Une colonne
  de plus serait un état redondant avec le bâtiment.
- **Sans Maison des scribes, aucun signe** — contrairement à la clé, qui en
  connaît quatre d'emblée pour que la première énigme soit tentable avant
  d'avoir rien bâti. L'alphabet n'a pas cette contrainte : il n'ouvre aucune
  porte, il enseigne.
- **Le mode d'essai les ouvre tous**, comme pour la clé, et pour la même
  raison — éprouver l'écran sans jouer les heures qui y mènent.
- **Ni le Déchiffreur ni Thot n'y touchent.** Leur effet est écrit pour la clé
  de lecture, et l'étendre ici doublerait un bonus que rien ne demande. À
  reconsidérer si le playtest trouve la progression trop lente.

---

## 4. Les quatre usages, du plus petit au plus lourd

### 4.1 Écrire « Niout » — l'énigme fondatrice  *(à faire en premier)*

> « La toute première énigme du jeu (Acte 1, mission 1) enseigne directement le
> nom du jeu lui-même — apprendre à écrire **Niout** (niwt, "la ville") avec les
> signes n + i + w + t, geste fondateur qui ancre l'apprentissage dès la
> première quinzaine. »

C'est le cœur du lot, et c'est aussi ce qui tient dans le moins de code.

**Forme retenue** : le **glisser-déposer** de `dechiffrage_controller.js`, déjà
écrit et déjà éprouvé — on donne au joueur les quatre signes mêlés, il les
remet dans l'ordre `𓈖 𓇋 𓅱 𓏏`. Le contrôleur existe, la règle « une
interaction se construit au clavier, puis se décore à la souris » vaut telle
quelle, et les jetons sont déjà mélangés au rendu.

**Trois écueils à éviter :**

1. **Ce n'est pas une inscription du fil rouge.** `FilRouge::acte()` se déduit
   de l'inscription d'ouverture lue ; y greffer l'alphabet mêlerait les deux
   pistes. L'énigme fondatrice vit **à côté**, sans rien décider de la mission.
2. **Elle ne punit pas** — la règle des énigmes vaut ici : se tromper ne coûte
   ni ressource ni cycle, et l'explication tombe dans les deux cas.
3. **Elle se retente**, contrairement aux énigmes à choix multiple. Le motif de
   la règle « on ne répond qu'une fois » est qu'avec quatre propositions on
   essaie tout ; remettre quatre signes dans l'ordre a vingt-quatre arrangements
   possibles, et c'est un **exercice**, pas une devinette. On apprend en
   recommençant.

Ce qu'elle apprend, elle le dit : que l'égyptien ne note pas les voyelles, et
que « Niout » se lit *niwt* — la ville.

### 4.2 La table de l'alphabet, à la Maison des scribes

Un second bloc dans le panneau des scribes, sous la clé de lecture : les signes
ouverts, chacun avec son glyphe, son code, l'objet représenté, sa
translittération et son son. Même gabarit que la clé — c'est la même grille,
avec une colonne de plus.

Et la même phrase qui manque à la clé aujourd'hui : **ce qui reste à ouvrir**,
« monter la Maison des scribes ouvrirait trois signes de plus ».

### 4.3 Transcrire le nom de famille

> « Le jeu propose une transcription phonétique approximative en hiéroglyphes
> réels […] exactement la démarche employée aujourd'hui dans les musées. »

**Où l'afficher** : le document dit « au moment où le joueur choisit le nom ».
Le formulaire de lancement est rendu côté serveur, sans JavaScript applicatif —
une transcription **en direct** pendant la frappe demanderait un contrôleur
Stimulus et la table des 24 signes en JSON. Ce n'est pas exclu, mais ça déborde
du lot.

**Retenu** : la transcription s'affiche **une fois la partie lancée**, dans le
panneau de la Résidence familiale, à côté de la renommée — c'est là que vit
déjà tout ce qui touche à la lignée. Le nom est alors fixé, la transcription
est stable, et elle se calcule à l'affichage.

**La règle de transcription, et ses limites**, à écrire honnêtement :
consonnes seules, voyelles ignorées, et une correspondance approchée des
lettres latines vers les 24 signes. Ce n'est **pas** de l'égyptologie : c'est la
convention des musées, et l'écran doit le dire en une phrase plutôt que de
laisser croire à une traduction.

Un cas à traiter : une lettre sans équivalent — `v`, `x`, `z`, `c`. Le plus
honnête est de **l'omettre en le signalant**, comme le font les cartouches
touristiques, plutôt que de forcer un signe faux.

### 4.4 Les cartouches royaux

> « Chaque pharaon commanditaire est présenté avec son cartouche réel en
> hiéroglyphes lors de l'introduction de sa mission. »

**C'est du sourcing, pas du code.** Neuf cartouches réels à établir — Ahmôsis
Ier, Thoutmôsis Ier, Hatchepsout, Thoutmôsis III, Amenhotep III, Akhenaton,
Séthi Ier, Ramsès III, Ramsès IV — chacun avec sa graphie attestée, et
l'avertissement qui va avec : **un cartouche royal ne s'écrit pas avec le seul
alphabet**. Il mêle unilitères, bilitères et logogrammes, et le nom de trône
diffère du nom de naissance.

Le dire est même le meilleur enseignement du lot : c'est l'occasion de montrer
que l'alphabet est une porte d'entrée, pas la langue entière.

**Écran** : `templates/partie/commande.html.twig`, la mise en scène de la
commande du pharaon, qui existe déjà et n'attend que ça.

**Ce sous-lot se fait en dernier**, et seulement une fois les cartouches
vérifiés un par un. Un cartouche faux affiché comme réel est exactement ce que
la règle « les hiéroglyphes du jeu sont vrais » interdit.

---

## 5. Découpage proposé

| | Contenu | Pourquoi cet ordre |
|---|---|---|
| **1.a** | La police embarquée, appliquée aussi à la clé existante | Corrige un aléa déjà présent, et conditionne tout le reste |
| **1.b** | `SigneAlphabetique` + `AlphabetDesScribes` + la table à l'écran | Le socle ; visible immédiatement |
| **1.c** | L'énigme fondatrice « Niout » | Le geste que le document met en avant |
| **1.d** | La transcription du nom de famille | Autonome, se greffe sur la Résidence |
| **1.e** | Les neuf cartouches royaux | Sourcing lourd, à ne pas bâcler |

`1.a` à `1.c` forment un tout défendable et livrable d'un coup : la police, la
table, et l'énigme qui écrit le nom du jeu.

## 6. Ce que les tests devront tenir

- **Les vingt-quatre signes portent un code de Gardiner et un glyphe non
  vides**, et aucun doublon de code — même garde que `SymboleHieroglyphique`.
- **Les deux pistes ne se confondent pas** : un signe commun aux deux tables y
  porte deux sens différents, et c'est vérifié plutôt que subi.
- **`3 × niveau` atteint exactement 24 au niveau 8**, et jamais davantage.
- **Sans Maison des scribes, l'alphabet est vide** ; en mode d'essai, complet.
- **L'énigme fondatrice n'entre pas dans le fil rouge** : la résoudre ne fait
  pas avancer `FilRouge::acte()`.
- **La transcription n'invente jamais un signe** pour une lettre sans
  équivalent.

## 7. Ce qui reste à trancher avec la joueuse

1. **La transcription en direct** dans le formulaire de lancement, ou à la
   Résidence une fois la partie lancée ? Le document dit « au moment du choix » ;
   ce lot propose la seconde, plus simple et sans JavaScript applicatif.
2. **`3 signes par niveau`** convient-il ? La formule tombe juste sur 24 au
   niveau 8, mais le niveau 8 de la Maison des scribes est tardif : l'alphabet
   complet n'arrive alors qu'en fin de mission.
3. **Les cartouches** : les neuf d'un coup, ou seulement ceux des missions
   jouées le plus souvent ? Chacun demande une vérification sérieuse.
