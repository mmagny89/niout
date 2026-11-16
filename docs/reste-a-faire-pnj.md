# Niout — PNJ : ce qui reste à faire

**État au 2026-11-16.** Palier A et palier B sont livrés et mergés dans `develop` (1395 tests, PHPStan niveau 8, style et audit
propres). Ce document dit ce qui **n'est pas fait**, dans l'ordre où l'on peut le reprendre. Le détail de chaque lot est dans son
plan : [`plan-pnj-palier-a.md`](plan-pnj-palier-a.md), [`plan-pnj-palier-b.md`](plan-pnj-palier-b.md),
[`plan-pnj-palier-c.md`](plan-pnj-palier-c.md), [`plan-pnj-ville-vivante.md`](plan-pnj-ville-vivante.md).

## 1. Ce qui bloque tout le reste : rien n'a été vu par quelqu'un qui joue

Les tests prouvent les règles et les bornes. Ils ne prouvent ni qu'un écran est lisible, ni qu'on s'attache à une personne.

| Dette | Pourquoi elle compte | Qui la solde |
|---|---|---|
| **Contrôle au navigateur de tout ce qui s'affiche depuis A2** : l'étal enrichi, la fenêtre « Le monde » (cités, personnes, chronique), le bouton « Le monde » sur la ville, le risque de pillage et le droit de passage dans l'Entrepôt et le Port, la ligne de raisons du rival. À 390 px **et** en desktop (skill `verification-ui-navigateur`) | Un défaut de mise en page ne se voit pas aux tests. Rien de ce qui précède n'a été regardé | Une session avec Claude in Chrome, sur une partie qui a une route ouverte, un rival et une bande |
| **Playtest d'A2** : le joueur lit-il l'étal ? | Verrou prévu avant A3 | La joueuse |
| **Playtest de B2** : s'attache-t-on à un rival ? | Verrou prévu avant B3, B4, B5 et **avant tout le palier C** (« si non, C ne se justifie pas ») | La joueuse |
| **Fluidité à 390 px** du film de la ville (40 silhouettes) | Verrou de V3, jamais mesuré sur un téléphone | La joueuse, sur un téléphone |
| Vérifier **à 390 px** les lots V2, V3 et V4a déjà livrés | Ils n'ont été vus qu'à 1 728 px | Une session navigateur |

## 2. Palier A — ce qui reste

Livré : conjoncture dérivée, avantage signé, saturation et relation, étal, « Au loin », fenêtre « Le monde », rumeurs, prévision du
Négociateur, sollicitations, banc d'essai (bornes, arbitrage, dispersion).

| Reste | Pourquoi c'est reporté | Taille |
|---|---|---|
| **Deuil de cour** (route ralentie, plancher 50 %) | Il touche la lecture au départ des convois | Moyen |
| **Troubles sur la route** (pillage en plus, plafonné à +10, affiché avant l'engagement) | Il se branche sur `ChefsDeBande` et `risqueDePillage`, maintenant écrits | Moyen |
| **Guerre et fermeture durable** (§ 4.4 : préavis, convoi épargné, jamais la dernière route, jamais un partenaire vital, fin garantie, adoucie par la relation ≥ 60) | C'est la **seule règle qui retire une capacité au joueur** : cadre strict, et un test « chaque mission garde un partenaire non fermable ou deux fermables » | Gros |
| **Sollicitation de paix** | Dépend de la guerre | Petit |
| **Occasion** (« une caravane part pour Pount, il reste de la place ») | Demande de lire la capacité d'un convoi | Petit |
| **Plancher de relation de 20 par mission accomplie**, et **relation affichée dans le carnet de contacts** | Reporté en A2 ; les tests du carnet ne doivent pas bouger | Petit |
| **Le panneau de l'Auberge** n'a pas changé : elle n'est que source de rumeurs | À confirmer avec `interface.md` | Petit |
| **Calibrage sur parties réelles** | A4 n'a mesuré que les bornes et la dispersion au banc : 17 % de quinzaines à événement, écart moyen 4,6 points. Si c'est trop ou trop peu, on ne le saura qu'en jouant | Dépend du playtest |

## 3. Palier B — ce qui reste

Livré : rival raisonné (décision pure, mémoire, retour), fiche et chronique, chefs voyageurs, bandes parlantes et droit de passage,
cour (titres, humeur), visages connus de la lignée, banc des rivaux.

| Reste | Pourquoi c'est reporté | Taille |
|---|---|---|
| **Noms historiques à la cour** (vizirs, généraux) | **Aucun nom n'est écrit sans vérification** (existence, règne, fonction). Titres seuls pour l'instant | À sourcer |
| **Logisticien parti** (raccourcit un trajet vers sa cité) | Touche `Commerce::trajetVers` et le plancher d'une quinzaine | Moyen |
| **Apparition du rival rejouable** | Seule son identité se déduit de la graine ; le tirage de renommée garde son `Randomizer` (les tests le forcent) | Petit, mais réécrit des tests |
| **Pool de candidats déduit** (« population stable de N profils par partie ») | Seuls les chefs que le joueur a employés reviennent | Moyen |
| **Retour du rival « plus riche »** | Un rival n'a pas de richesse | À décider |
| **Fréquence des requêtes selon l'humeur du courtisan** | Non calibrée : seuls le présent et le ton varient | Petit |
| **Rencontres dans « Au loin »** | Elles restent dans la catégorie « Rivaux » | Petit |
| **Bandes mobiles**, **changement de rôle** d'une personne | Écartés du palier : chantier de carte pour les premières | Gros |
| **Test HTTP de la route du droit de passage** | Le jeton CSRF sans état ne se simule pas ; la logique est testée par le service | Impossible en test, vérifier au navigateur |
| **Calibrage du palier B sur parties réelles** | Le banc ne couvre que la décision du rival (2 contre 1) | Dépend du playtest |

## 4. Palier C — les cités autonomes (conditionnel)

**Rien n'est codé.** Le plan lui-même dit de ne l'engager qu'après le playtest de B2. Idée : d'autres familles, chacune une cité
abstraite (cohortes, réserves, projet), qui croissent, commercent et déclinent sans le joueur ; la conjoncture d'A devient
**émergente** au lieu d'être déduite d'une graine.

| Lot | Contenu | Verrou |
|---|---|---|
| **C1** | Le moteur pur, **en mémoire, sans persistance ni écran** : cohortes, subsistance, production, démographie, décision. Banc à 500 quinzaines | Pas d'emballement, pas d'extinction, répartition acceptable |
| **C2** | Persistance (`CiteAutonome`), **une seule** cité à la place de la conjoncture dérivée pour **un** partenaire ; les bornes d'A tiennent sur de vrais stocks | Interface d'A inchangée |
| **C3** | Plusieurs cités, échanges entre elles, l'étal nourri par leurs stocks, onglet « Cités » | **Playtest : vivant, ou calculé ?** |
| **C4** | Relations, alliances et brouilles, guerres comme événements | — |
| **C5** | Aventure : générations, règnes, annales, frise | Décision sur la refondation |
| **C6** | Calibrage, valeurs au § 6 du plan de bataille | — |

**Risque principal** (§ 5 du plan C) : l'économie, où C peut tout casser. **Décision à prendre avant de commencer :** valider
qu'on veut ce palier, et lire les questions ouvertes du § 12 du plan C.

## 5. La ville vivante (V) — ce qui reste

Livré : prénoms (N), V1 la ville habitée, V2 la trace et la frise, V3 le film et les chemins, V3b journées variées, V4a dialogues
d'ambiance, nommer les chefs et Medjaÿ.

| Lot | Contenu | Verrou | Taille |
|---|---|---|---|
| **V4** (quartier) | **Le zoom « quartier »** : une rue, des maisons, des portes. « Dans leur maison » | **Un visuel de plus à produire** : sprites ou pictogrammes à créer, pas du code seul | Gros |
| **V5** | **Les visiteurs** des paliers B et C : le rival qui s'arrête au Marché, la caravane qui charge au Port, le chef de bande vu de loin ; **les raisons à l'écran** | A et B livrés — **c'est le cas**. Les visiteurs de C attendent C | Moyen |
| **V6** | Passe **artistique** : sprites ou pictogrammes finis (les portraits des personnes de « Le monde » y attendent), calibrage, valeurs au § 6 du plan de bataille | Décision artistique : SVG de profil, ou sprites ? | Gros |
| Dialogues-indices (I1 à I5) | Témoignages et aides à la lecture | Relire le **doc 10 du Drive** avant d'écrire une ligne ; livrés pour I1 à I5 d'après le journal, à confirmer | — |

## 6. Ordre que je recommande

1. **Contrôle navigateur** de tout ce qui précède (une session). C'est ce qui évite de bâtir V5 sur des gabarits jamais vus.
2. **Playtest** d'A2/A3, de B2, et de V3 à 390 px. Il décide de C et du calibrage des deux paliers.
3. **Événements d'A** (troubles, deuil, puis guerre en dernier) : ils réutilisent ce qui existe.
4. **V5**, qui donne un corps aux personnes de B dans la ville.
5. **V4 et V6** ensemble : ils partagent le travail de visuels.
6. **Palier C**, seulement si le playtest le réclame.
