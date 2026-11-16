# Niout — Plan : des personnages non joueurs qui vivent leur vie

**Statut : cadrage. Rien n'est codé, rien n'est tranché.** Ce document pose
l'intention, ce qu'on réutilise de la V1 du projet `simulation`, ce que le jeu
sait déjà faire, un découpage en lots et les questions qui attendent une
décision. Il se lit avant de toucher à `src/Game/Rivaux.php`,
`CataloguePartenaires.php`, `Bandits.php` ou aux candidats de `JobOffer`.

## 1. L'intention

Rendre Niout plus attractif en lui donnant **un monde qui bouge sans le
joueur** : à chaque quinzaine, des personnages font des choses — commercent,
fondent, guerroient, se disputent une route — et le joueur **regarde** dans un
écran dédié, puis réagit s'il le veut.

**Ce n'est pas un jeu qui se joue seul.** Niout est un jeu : le joueur décide,
les PNJ **peuplent** la partie. Une cité entièrement autonome (un « mode
spectateur » de la ville du joueur) est écartée pour l'instant — elle ne
donnerait rien à faire.

**Le temps ne change pas.** Une quinzaine se déclenche quand le joueur le
décide (`PassageDeCycle`) ; il n'y a **aucune attente en temps réel** et les PNJ
n'en introduisent pas. « Regarder faire les PNJ » veut dire : **lire ce qu'ils
ont fait pendant la quinzaine qui vient de passer**. La V1 de `simulation`
tournait en continu et rattrapait l'absence ; ici, le battement est déjà celui
du joueur, et tout le mécanisme de rattrapage est inutile.

Ce que le joueur doit y gagner, par ordre d'importance :

1. **Une raison d'avancer d'une quinzaine** : « que fait Byblos ? », « le rival
   a-t-il trouvé le filon ? ». Le cycle devient aussi une page de chronique.
2. **Un monde qui répond** : les prix, les routes, les rencontres dépendent de
   quelqu'un, pas d'un tirage anonyme.
3. **Des histoires émergentes** que les documents de conception n'ont pas
   écrites, sans que personne ait eu à les écrire.
4. **Une longue partie Aventure vivante** : Memphis « sans fin scriptée »
   (doc 14) manque précisément de ce qui change d'un règne à l'autre.

## 2. Principes (les garde-fous)

Chacun vient d'une règle déjà payée dans `regles-du-jeu.md` ; les supprimer
sans lire la raison, c'est repayer.

- **Un PNJ rogne, il ne détruit rien.** Reprise de la règle des rivaux : un
  effet sur le joueur est **borné, lisible, et a une réponse** — y compris
  « ne rien faire », qui reste une décision. Jamais de bâtiment perdu, de
  stock vidé ni de travailleur tué par un PNJ.
- **Des malades, jamais des morts** (ligne du projet, `Epidemies`). Un PNJ ne
  « meurt » pas à l'écran : son héritier prend la suite, comme la famille du
  joueur (`SuccessionFamiliale`).
- **Un seul fil d'adversité à la fois sur le joueur.** La règle « une seule
  rivalité à la fois » tient : plusieurs PNJ peuvent se disputer entre eux, un
  seul pèse sur le joueur, sans quoi deux malus brouillent l'enquête qui en
  démonte un.
- **Ce que l'on ne peut pas savoir ne s'affiche pas** (règle des hiéroglyphes,
  étendue) : un PNJ n'a que des noms, lieux et faits **attestés ou
  clairement donnés comme inventés**. Les prénoms viennent de
  `PrenomEgyptien`. Le vocabulaire (Medjaÿ, Akhèt, Perèt, Chémou, quinzaine,
  Niout) reste tel quel.
- **Pas de triche cachée.** Un PNJ **n'a pas la ville du joueur** : il a un
  modèle abstrait, plus simple, et on le dit. Lui donner des bonus invisibles
  pour qu'il « tienne » est exactement ce qui rend un monde injuste.
- **Un état qui se déduit ne se stocke pas.** Les journaux stockent des **faits
  structurés** (type, acteur, lieu, quantité), pas du texte : le texte est
  rendu par Twig. Un texte stocké diverge du fait qu'il raconte, comme le
  « récapitulatif de la quinzaine » l'a déjà montré.
- **Logique côté PHP, affichage côté Twig.** Aucun calcul de décision dans un
  gabarit.
- **Pas de JavaScript applicatif.** L'écran du monde est une fenêtre Turbo
  comme les autres.

## 3. Ce qu'on reprend de la V1 de `simulation`

La V1 est un moteur JavaScript pour des poissons. **Rien du code ne se réutilise**
(Niout n'a pas de JS applicatif et calcule tout côté serveur), mais le
**patron** se transpose presque terme à terme :

| `simulation` V1 | Dans Niout |
|---|---|
| Besoins (faim, repos, sécurité…) | **Aspirations** d'un PNJ : prospérité, sécurité, prestige, piété, vengeance. Quelques-unes seulement, lisibles |
| Choix par **score d'utilité** | Même idée : chaque action possible reçoit un score pondéré par les aspirations et l'état du monde ; on joue la meilleure, ou une parmi les meilleures par tirage |
| **Inertie** (ne pas changer d'avis à chaque tick) | **Projet en cours** : un PNJ qui entreprend une expédition la poursuit plusieurs quinzaines, sans quoi il papillonne et l'écran devient du bruit |
| **Urgences** qui court-circuitent le reste | Famine, siège, mort du chef : l'urgence l'emporte sur l'ambition |
| **Graine** stockée, déterminisme | **Graine dérivée de (partie, quinzaine, PNJ)** : on rejoue n'importe quelle quinzaine à l'identique, donc on la teste et on la débogue. Rien à conserver entre deux cycles |
| **Variabilité individuelle** | **Traits** (`TraitDeCandidat` existe déjà pour les chefs) : un PNJ prudent, ambitieux, pieux |
| **Rythme circadien** | **Saisons** (Akhèt, Perèt, Chémou) et crue : un PNJ ne commerce pas pareil à la crue |
| **Explication chiffrée des choix** | **Le « pourquoi »** affiché : « Ipou prépare une caravane vers Pount : ses greniers sont pleins et il veut du prestige ». C'est ce qui rend un PNJ lisible, donc attachant |
| Jalons, **scénarios d'acceptation**, tests `node --test` | Un **banc d'essai en console** qui fait tourner 200 quinzaines sans joueur et vérifie que le monde ne s'effondre ni ne s'emballe (voir § 7) |
| Rattrapage hors-ligne, temps accéléré, mode vacances | **Sans objet** : le cycle est déjà à la main du joueur |
| Mort, cadavres, reproduction | **Écarté** (voir § 2) : héritiers et successions, pas de morts à l'écran |

Le cahier des charges de `simulation` annonçait déjà ce passage (« moteur
réutilisable plus tard pour Niout ») ; c'est cette transposition, pas un portage.

## 4. Ce que Niout a déjà — et qui attend un visage

Le monde extérieur existe, mais **anonyme et tiré au sort** :

| Aujourd'hui | Devient |
|---|---|
| `CataloguePartenaires` : Byblos, Pount, Babylone… sont des **débouchés fixes**, hors grille | Des **cours et des marchés avec un état** : ils manquent de grain, s'enrichissent, entrent en guerre, et leurs prix suivent |
| `Rivaux` : un marchand apparaît selon la renommée, rogne 10 à 20 % d'une route, **s'en va de lui-même** | Un **marchand nommé et persistant**, avec un but, qui peut revenir, grandir, ou s'allier. La règle du rognage et de la sortie reste |
| `Bandits` : un **attribut de case**, fixe | Des **bandes** qui ont un chef et un butin ; leur sort suit ce que le joueur en fait (le butin décroissant noté au plan de bataille trouve là sa cause) |
| `JobOffer` : des **candidats tirés** à chaque annonce | Des **personnes** : un chef a une histoire, peut être débauché ailleurs, change de cité. Les candidats deviennent des habitants du monde |
| `QueteDeChantier` : le pharaon demande, tout seul | Une **cour** qui a des humeurs, des favoris, des campagnes ; la succession des règnes (`SuccessionDesRegnes`) devient un événement de ce monde, pas un décor |
| `CarnetDeContacts` : un contact par mission, **statique** | Un **réseau** qui évolue : on retrouve les mêmes personnes de mission en mission |
| `Lignee` : l'acquis du joueur | Peut porter des **relations** (un rival revu, une dette) — question ouverte, § 8 |

Les **préconditions** d'un geste (`ChantierImpossible`, `CommerceImpossible`,
`OffrandeImpossible`…) sont déjà écrites : quand un PNJ agit **sur** le joueur
(une offre, un prix, un convoi), il passe par les mêmes services et obéit aux
mêmes règles.

## 5. Le modèle

### 5.1 Trois paliers, du moins cher au plus cher

| Palier | Contenu | Coût | Intérêt |
|---|---|---|---|
| **A. Le monde** | Les partenaires commerciaux ont un état (stocks abstraits, humeur, relation) | Faible : peu d'acteurs, déjà dans le catalogue | Des prix et des routes qui racontent quelque chose |
| **B. Les personnes** | Rivaux persistants, chefs voyageurs, chefs de bande, courtisans | Moyen | Des visages, des arcs courts, des rencontres |
| **C. Les cités autonomes** | D'autres familles qui fondent et gèrent une cité, en modèle abstrait | Élevé | Le vrai « monde vivant » ; un enjeu de comparaison et de compétition |

**Le palier A est cadré en détail dans [`plan-pnj-palier-a.md`](plan-pnj-palier-a.md)**
(choisi le 2026-11-16). On y découvre que A n'a pas besoin du score d'utilité :
c'est un monde qui subit et réagit, les agents viennent avec B.

**On commence par A, on prouve que le joueur regarde, puis on monte.** C'est la
seule marche qui ressemble à une « civilisation autonome » ; la bâtir avant de
savoir si l'écran du monde est lu serait payer le plus cher pour le moins
vérifié.

### 5.2 Un PNJ, concrètement

Un PNJ est un **état léger**, jamais une `City` : un nom, un rôle, des traits,
une position (une case ou un débouché), quelques **compteurs abstraits** (une
richesse, une influence, une humeur envers la famille), un **projet en cours** et
une graine dérivée. Pas de bâtiments, pas de carte à lui, pas de population
détaillée. Raison d'être : une `City` complète par PNJ multiplierait les
écritures par quinzaine par le nombre de PNJ, pour un détail que personne ne
verra.

### 5.3 Une quinzaine de PNJ

Dans `PassageDeCycle`, **à un seul endroit**, après les résolutions de la ville
du joueur et avant le récapitulatif :

1. pour chaque PNJ actif, **une urgence** éventuelle ;
2. sinon la poursuite de son **projet** ;
3. sinon le **score des actions possibles**, et le choix ;
4. l'effet est appliqué à l'état du PNJ — et, **seulement si la règle le permet**,
   au joueur par les services existants ;
5. un **fait structuré** est écrit pour la chronique ;
6. **un seul flush**, comme le reste du cycle.

L'ordre compte et se documente : un PNJ qui décide avant que la ville ait
produit voit un monde périmé.

### 5.4 Où vit le code (à confirmer à l'ouverture du chantier)

- `src/Entity/` — l'état persisté d'un PNJ et ses faits de chronique,
  **rattachés à `GameSave` avec cascade `remove`**, comme tout le reste. Nom de
  classe à trancher (§ 8).
- `src/Game/` — les **règles et le contenu** : archétypes, aspirations, poids,
  catalogue des PNJ de départ par mission, fonction de décision **pure**
  (état + graine → décision). Rien de ceci n'est persisté.
- `src/Fenetre/` — l'écran du monde, **un fournisseur de données** qui ne
  calcule que ce qu'il montre.
- Toute entité qui référence `User` s'ajoute à `Security\SuppressionDeCompte` ;
  c'est ici sans objet tant que les PNJ appartiennent à la partie.

## 6. L'écran : regarder, puis réagir

L'écran est une **fenêtre au-dessus de la carte** (`plan-fenetres.md`) : la carte
ne disparaît pas. Trois niveaux de lecture, du plus rapide au plus profond :

1. **« Au loin » dans le récapitulatif de la quinzaine** — une nouvelle
   catégorie d'événement (`CategorieDEvenement`), trois ou quatre lignes au plus.
   Si le joueur ne lit que cela, il a déjà vu son monde bouger.
2. **La chronique** — le fil des quinzaines passées, filtrable par personnage et
   par région, chaque ligne avec son **pourquoi**.
3. **La fiche d'un personnage** — son portrait, ce qu'il veut, sa relation avec
   la famille, ce qu'on peut faire avec lui.

**L'information se mérite.** Le joueur ne voit pas tout : ce qu'il apprend
dépend de ce qu'il a **bâti et exploré** — rumeurs à l'Auberge, nouvelles des
ports au Port, relevés à la Maison des scribes, ce que ramène un émissaire. Une
famille sans Auberge ne sait rien du Delta. Raisons : cela donne **un emploi
neuf à des bâtiments existants**, évite un écran omniscient qui noie, et fait
de « regarder » un geste de jeu plutôt qu'une contemplation.

**Réagir** : sans rien à faire, l'écran est un économiseur d'écran. Chaque
fiche offre donc au moins une prise — commercer, offrir, avertir, embaucher,
enquêter, ignorer. **Ignorer reste une réponse valide**, comme pour le rival.

Le détail visuel (maquette, mobile, accessibilité à 390 px) se décidera avec
`interface.md` ; il suit les règles de la barre de jeu et des fenêtres.

## 7. Comment on saura que ça marche

Trois contrôles, dont deux sans navigateur :

- **Déterminisme** : même partie, même quinzaine, même résultat. Un test rejoue
  une quinzaine deux fois.
- **Banc d'essai de monde** : une commande console qui fait tourner **200
  quinzaines sans joueur** sur plusieurs graines et rapporte la dispersion
  (richesses, nombre d'actions par type, PNJ immobiles, emballements). C'est
  l'équivalent des « scénarios d'acceptation » de la V1. **Les seuils se
  calibrent sur ces mesures**, pas sur des valeurs recopiées — même leçon que les
  objectifs de mission.
- **Chaque mesure qui doit bouger bouge**, vérifiée une par une (piège
  d'`ajusterRenommee()`) : aucun compteur de PNJ « déclaré » sans test qui le
  fait varier.

Ce qu'aucun test ne dira : **si l'écran est lu et s'il donne envie d'avancer**.
Cela ne se tranche qu'en jouant ; prévoir un playtest après le premier lot, avant
d'engager le suivant. Aucune télémétrie n'est envisagée (RGPD).

## 8. Questions ouvertes

| Question | Enjeu | Penchant |
|---|---|---|
| **Quel palier d'abord ?** | A seul est peu cher et prouve l'idée | A, puis B ; C seulement si le playtest le réclame |
| **Les PNJ survivent-ils aux parties ?** (sur `Lignee`) | Retrouver un rival de mission en mission est très fort, mais c'est une entité qui référence le joueur : **aucune cascade ne l'emporte**, il faut l'ajouter à `SuppressionDeCompte` | Non au départ ; relations simples sur `Lignee` plus tard |
| **PNJ en Campagne, en Aventure, ou les deux ?** | Aventure (Memphis, règnes successifs) en profite le plus ; en Campagne, les dix missions ont leur propre fiction | Les deux, avec un contenu par mission en Campagne |
| **Les PNJ se disputent-ils des cases de la carte du joueur ?** | Casserait « rien ne se perd » ; la carte est propre à la partie | Non : ils agissent hors grille, sur les routes et les débouchés |
| **Les Medjaÿ répondent-ils aux rivaux ?** (déjà ouverte au plan de bataille) | Un monde qui bouge réclame plus d'issues | À rouvrir avec ce chantier |
| **Nom de l'entité** | `Npc` (anglais, clair, comme `User`) ou un mot de l'univers | `Npc`, vocabulaire de l'univers pour les rôles |
| **Quels PNJ historiques nomme-t-on ?** | Attesté ou inventé : la règle exige de le dire | Archétypes inventés (« un marchand de Byblos »), personnages attestés seulement quand l'enquête les établit |
| **Qui peut agir sur le prix du marché ?** | Un PNJ qui bouge les prix ouvre un arbitrage | Plafond commun, comme `AvantageDeNegoce` |

## 9. Découpage proposé en lots

| Lot | Contenu | Sortie |
|---|---|---|
| **0** | Ce document et les décisions du § 8 | Cadrage validé |
| **1** | Socle : entité, graine dérivée, décision pure, chronique structurée, tests de déterminisme. **Un** type de PNJ : les partenaires commerciaux | Un monde qui change les prix, invisible mais mesuré |
| **2** | « Au loin » dans le récapitulatif, fenêtre du monde, rumeurs par l'Auberge et le Port | **Le premier playtest** : le joueur regarde-t-il ? |
| **3** | Rivaux persistants et chefs voyageurs | Des visages |
| **4** | Cours et succession comme événements de monde | Le long terme en Aventure |
| **5** | Banc d'essai de monde étoffé ; calibrage | Équilibre |
| **6** (conditionnel) | Cités autonomes en modèle abstrait | La « civilisation » |

Chaque lot se livre seul et laisse le jeu jouable ; le suivant ne démarre pas
sans le résultat du playtest précédent.

## 10. Risques

- **Un écran de chronique que personne ne lit.** D'où le niveau 1 dans le
  récapitulatif, l'information qui se mérite, et le playtest avant le lot 3.
- **Un monde qui s'effondre ou s'emballe** sans joueur : le banc d'essai du
  lot 1 le voit avant le joueur.
- **Un PNJ qui devient un moyen de contourner l'économie** (arbitrage sur ses
  prix) : plafond commun, valeurs inventées listées au § 6 du plan de bataille.
- **Du contenu à écrire en quantité** : l'attractivité se joue sur la variété des
  « pourquoi ». Un petit nombre d'archétypes bien écrits vaut mieux que beaucoup
  de gabarits creux.
- **Faux historique** : tout ce qui est inventé est dit inventé (§ 2).
