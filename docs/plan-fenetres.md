# Niout — Plan : tout en fenêtres au-dessus de la carte

**Statut : à valider.** Rien de ce document n'est livré. Il prépare le gros
chantier — ne plus avoir de pages dans le jeu, mais **la carte en permanence et
tout le reste en fenêtre par-dessus** — et pose les décisions à prendre avant
d'écrire une ligne.

## 1. Ce qu'on a, ce qu'on veut

Aujourd'hui, une partie est une suite de pages : la carte, la ville (onglets),
la cité, la commande, la reprise. Chaque action est un formulaire qui renvoie
une page entière, et le joueur « quitte » la carte pour gérer un bâtiment.

On veut **une seule page de jeu, la carte**, et des fenêtres : un clic sur la
ville ouvre la cité, un clic sur un bâtiment ouvre son panneau, on agit, on ferme,
la carte n'a jamais disparu. La cité en `<dialog>` (livrée) en est le premier pas.

## 2. Le principe technique : Turbo Frames, pas du JavaScript maison

Le projet interdit le JS applicatif fait main ; Turbo (8.0.23) est déjà là. Le
mécanisme :

- la page carte porte **un `<dialog>` natif** (focus piégé, Échap, inertie du fond
  — déjà acquis) qui contient **un `<turbo-frame id="fenetre">`** ;
- un lien `data-turbo-frame="fenetre"` charge sa cible *dans* la fenêtre, sans
  quitter la carte ; un **formulaire** posté depuis l'intérieur se soumet dans le
  cadre, le serveur **redirige comme aujourd'hui**, et Turbo n'affiche que le
  cadre de la page d'arrivée ;
- **les gabarits de bâtiment ne changent pas** : `batiments/_grenier.html.twig`
  & co. deviennent le contenu du cadre tels quels.

Conséquence heureuse : les ~40 actions qui font `retourALaVille()` continuent de
marcher sans réécriture — leur redirection tombe simplement dans le cadre.

## 3. Les cinq difficultés, et ce qu'on fait de chacune

| Difficulté | Décision proposée |
|---|---|
| **L'URL doit rester vraie** : recharger la page, le bouton retour, un lien partagé | L'état est dans l'URL : `carte?ouvre=/partie/12/ville?onglet=grenier`. Le serveur rend la carte **avec la fenêtre ouverte et le cadre déjà rempli** (rendu serveur, pas de chargement différé). Un `history.replaceState` met l'URL à jour à chaque navigation dans la fenêtre. |
| **Les anciens liens et les tests** (89 références à `/ville` dans 21 fichiers) | `GET /partie/{id}/ville` **continue de répondre** : sans en-tête `Turbo-Frame`, elle rend la carte avec la fenêtre ouverte ; avec l'en-tête, seulement le cadre. Les tests qui lisent la page voient toujours le contenu (il est dans le document). Il faudra seulement adapter les contrôles de structure globaux. |
| **La barre de jeu est hors de la fenêtre** : le deben change, la barre ment | La barre devient un cadre à elle (`<turbo-frame id="barre">`, route dédiée). Un événement `turbo:submit-end` dans la fenêtre la recharge. À la fermeture, si quelque chose a changé, **un rafraîchissement unique de la carte** (cases, signaux, expéditions). |
| **Les messages (flashes)** vivent dans la coque, pas dans le cadre | Le gabarit du cadre les affiche et les consomme lui-même ; la coque les affiche si la page n'est pas une réponse de cadre. |
| **Avancer d'une quinzaine depuis un panneau** | Le bouton est **répété dans l'en-tête de la fenêtre** ; il fait un rechargement complet de la carte avec `ouvre=` conservé : la fenêtre se rouvre au même endroit, le monde a avancé. |

## 4. L'architecture de la fenêtre

Une **fenêtre unique**, pas des fenêtres empilées : à gauche un **rail de
carrés** (la cité : Résidence, Grenier, Marché…), à droite le **panneau du
bâtiment choisi** (ses sous-onglets, comme aujourd'hui). Cliquer un carré change
le contenu du cadre ; la fenêtre ne bouge pas. Les carrés de la fenêtre de la
cité deviennent ce rail — la page « cité » disparaît, l'écran « ville » aussi.

Sur téléphone, la fenêtre est une **feuille plein écran**, le rail devient une
rangée défilante en haut.

## 5. Ce qui devient fenêtre, ce qui reste page

| Écran | Devenir |
|---|---|
| Ville, onglets de bâtiment, Résidence, essai | **Fenêtre** (rail + cadre) |
| Cité (page) | **Supprimée** — le rail la remplace |
| Commande du pharaon, reprise de partie | **Fenêtre**, ouverte d'office à l'entrée (`ouvre=commande`) |
| Détail d'une case, expéditions, signaux | **À décider** (voir § 7) |
| Accueil, connexion, inscription, compte, administration | **Pages** — hors jeu |
| Mes parties, nouvelle partie, abandon | **Pages** — on n'est pas encore dans une carte |

## 6. Les phases

Chaque phase se livre seule, tests verts, et laisse le jeu jouable.

1. **Fondations.** Extraire la barre de jeu en cadre (`barre`) ; poser le
   `<dialog>` + `<turbo-frame id="fenetre">` dans la carte ; paramètre
   `ouvre=` validé (chemin interne à la partie, rien d'autre) ; rendu serveur
   cadre-seul / carte-ouverte ; `replaceState`. *Aucun écran ne change encore.*
2. **La ville dans la fenêtre.** Le rail de carrés et le cadre ; les liens de la
   cité et de la carte ouvrent la fenêtre ; flashes dans le cadre ; barre
   rechargée après chaque action ; bouton de quinzaine dans l'en-tête. Les
   gabarits de bâtiment passent tels quels.
3. **Fermer proprement.** Rafraîchissement de la carte à la fermeture si une
   action a eu lieu ; gestion d'Échap / clic sur le fond / retour arrière ;
   suppression de la page cité.
4. **Commande et reprise.** Ouverture d'office en fenêtre ; la route « reprendre »
   atterrit sur la carte.
5. **Case, expéditions, signaux** — selon la décision du § 7.
6. **Assainissement.** `PartieController` fait 2 532 lignes et `ville()` en prend
   250 : elle calcule les données de **tous** les panneaux alors qu'on n'en ouvre
   qu'un. Une fois dans des cadres, chaque panneau ne doit calculer que le sien
   (un fournisseur de données par bâtiment) — c'est ce qui rend la fenêtre
   rapide, et le contrôleur lisible.
7. **Mobile et accessibilité.** Feuille plein écran, rail défilant, focus rendu à
   la tuile qui a ouvert la fenêtre, annonce du titre à l'ouverture, contrôle au
   lecteur d'écran.

## 7. Décisions à prendre

1. **Périmètre de « tout »** : seulement le jeu (ville, cité, commande, reprise),
   ou aussi la carte elle-même (case, expéditions, signaux) ?
2. **Le panneau de droite de la carte** (détail d'une case, signaux, expéditions) :
   le garder, ou libérer toute la carte et passer ces éléments en fenêtre /
   surimpression ?
3. **La forme de la fenêtre** : une fenêtre unique avec rail de carrés (proposé),
   ou des fenêtres distinctes par bâtiment ?
4. **Quinzaine suivante** : seulement dans la barre, ou aussi dans la fenêtre
   (proposé, car on la passe souvent plusieurs fois de suite depuis un panneau) ?

## 8. Risques

- **Les tests de structure globaux** (`SousOngletsTest`, `ErgonomieTest`)
  comptent les barres d'onglets de toute la page : la fenêtre pré-remplie en
  ajoute. À scoper, pas à supprimer.
- **Turbo et le JavaScript des panneaux** : les contrôleurs Stimulus
  (`onglets`, `dechiffrage`) se rebranchent d'eux-mêmes sur un cadre chargé ;
  à vérifier au navigateur, pas aux tests (le client de test n'exécute pas de JS).
- **Le jeton CSRF sans état** : tout formulaire écrit à la main doit garder
  `data-controller="csrf-protection"` sur son champ caché — c'est déjà vrai, mais
  un formulaire chargé dans un cadre doit le rester.
- **La taille du `PartieController`** : y ajouter des routes de cadre avant la
  phase 6 aggraverait ce qu'on veut réparer ; on les pose dans un contrôleur à
  part dès la phase 1.
