# Niout — Plan de bataille

Document de cadrage technique. Traduit les 16 documents de conception du jeu
(dossier Google Drive `Niout`, fichiers `00` à `15`) en un plan de développement
Symfony.

- **Stack** : Symfony 8.1 · Twig · Tailwind CSS 4.3 · PostgreSQL · FrankenPHP/Docker
- **Contrainte** : rendu serveur, zéro React, zéro headless
- **Source de conception** : Google Drive `Niout/`, docs 00–15 + sous-dossier `Sprites/` (18 planches)

---

## 1. Le jeu en un coup d'œil

Jeu de gestion / aventure / RPG léger, Égypte antique du Nouvel Empire
(~1550-1070 av. J.-C.), jouable au navigateur, **sans aucune attente en temps
réel** : le temps avance uniquement quand le joueur déclenche un cycle (une
« quinzaine » = 2 semaines de temps de jeu).

Le joueur incarne une famille chargée par un pharaon de fonder, restaurer ou
sécuriser une ville. Cinq boucles de jeu s'auto-alimentent : exploration,
artisanat/commerce, construction, faveur divine, fil rouge narratif.

| Mode | Principe |
|---|---|
| **Campagne** | 10 missions, chacune liée à un pharaon réel et une ville attestée (Avaris, Megiddo, Malkata…), difficulté croissante par région |
| **Aventure** | Une seule ville (Memphis), jouable sur la durée à travers une succession de règnes, sans fin scriptée |

**Rejouabilité** : génération semi-aléatoire des cartes, héritage familial entre
parties, renommée persistante.

Tous les systèmes sont déjà spécifiés en détail dans les documents Drive. Le
travail de cadrage restant est *technique*, pas du game design.

---

## 2. Choix techniques retenus

| Domaine | Choix |
|---|---|
| Framework | **Symfony 8.1** (dernière version stable, mai 2026), architecture MVC classique — pas d'API séparée, pas de front headless |
| Rendu | Twig côté serveur. Interactivité ponctuelle (déclencher un cycle, popup de case, formulaires) via **Symfony UX Turbo + Stimulus**. Aucun React, aucun front SPA |
| CSS | **Tailwind CSS 4.3** via `symfonycasts/tailwind-bundle` — intégration native AssetMapper, binaire autonome, **pas de Node.js requis** |
| Données | Doctrine ORM, migrations versionnées, **PostgreSQL** |
| Auth | `symfony/security-bundle`, entité `User`, hash argon2, `symfony/form` + validation. Vérification d'email et réinitialisation de mot de passe via `symfony/mailer` |
| Assets | AssetMapper — même mécanisme que Tailwind, un seul système d'assets pour tout le projet |
| Infra | Docker / FrankenPHP (mode worker) + Caddy intégré, Ember pour l'observabilité. Généré par `.claude/scripts/setup-symfony.sh` selon `.claude/rules/stack-conventions.md` |
| Tests | PHPUnit : unitaire, intégration (`KernelTestCase`) et fonctionnel (`WebTestCase`), avec `dama/doctrine-test-bundle` pour l'isolation. Behat reste envisagé pour les scénarios de jeu, où la lisibilité Gherkin profite à la relecture fonctionnelle |

**Pourquoi ce choix.** Le jeu n'a pas besoin de temps réel ni d'un état client
complexe : chaque action se résout côté serveur, le joueur rafraîchit une vue.
Turbo évite le rechargement complet de page sans la complexité d'un SPA, et
AssetMapper + Tailwind évitent toute chaîne de build Node.js.

---

## 3. Compte, famille, partie

La fiction du jeu fournit la bonne hiérarchie de données : un **compte** peut
incarner plusieurs **familles** jouées dans le temps, chaque partie étant une
**run** indépendante et rejouable (cf. doc 00 : « chaque partie est une run
complète, pas une sauvegarde infinie »).

**Tranché** : le nom de famille se choisit **au lancement d'une partie**, pas à
l'inscription. Un compte peut mener **jusqu'à 5 parties en cours simultanément**.

| Entité | État | Rôle | Doc source |
|---|---|---|---|
| `User` | ✅ | Compte joueur — email, mot de passe, statut de vérification, rôles | — |
| `GameSave` | ✅ | Une run : mode, mission en cours, cycle. Jusqu'à 5 actifs par `User`, supprimés avec lui | 00, 14 |
| `Family` | ✅ | Nom choisi au lancement (1 par `GameSave`) et renommée. Héritage et contacts commerciaux en Phase 9 | 13 |
| `City` | ✅ | Nom, difficulté régionale, taille de grille, stock, carte, chantiers | 01, 02, 11 |
| `Building` | ✅ | Un bâtiment dressé : son type et son niveau | 01 |
| `Chantier` | ✅ | Travaux en cours : niveau visé, durée, avancement | 01, 05 |
| … (Phase 3+) | — | Carte, Medjaÿ, faveur divine, énigmes | 02–12 |

`Family` et `City` sont détenues par leur `GameSave` : l'abandon d'une partie,
comme la purge d'un compte, les emporte en cascade.

**Couche de domaine.** Ce qui relève des règles du jeu plutôt que de la
persistance vit dans `src/Game/` : catalogue des missions, dotation royale,
lancement de partie. Ces classes ne sont jamais persistées — elles décrivent le
contenu et les règles, pas l'état d'une partie.

---

## 4. Phase 0 — Fondations techniques  ✅

- [x] Dépôt git initialisé (branche `main`)
- [x] Plan de bataille sauvegardé dans le projet (`docs/plan-de-bataille.md`)
- [x] Stack Docker + squelette Symfony via `.claude/scripts/setup-symfony.sh --dedicated-server --run`
- [x] `CLAUDE.md` du projet (conventions, commandes, pièges d'infrastructure)
- [x] Thème Tailwind : palette et typographies de la direction artistique (doc 15) — ocre/sable/terre cuite, accents lapis-lazuli/or
- [x] Gabarit Twig de base (`base.html.twig`)
- [x] Configuration `symfony/mailer` — `null://null` en dev, emails lisibles dans le profiler
- [x] Pipeline CI GitHub Actions : style, analyse statique, audit dépendances, tests
- [x] `symfony/security-bundle` + entité `User`

**Phase 0 terminée.**

### Outillage qualité en place

| Outil | Configuration | Commande |
|---|---|---|
| php-cs-fixer | `@Symfony` + `declare_strict_types` | `vendor/bin/php-cs-fixer fix` |
| PHPStan | **niveau 8** (cible Symfony), extensions Symfony + Doctrine | `vendor/bin/phpstan analyse` |
| PHPUnit | Tests unitaires, d'intégration et fonctionnels | `php bin/phpunit` |
| composer audit | Aucun avis de sécurité | `composer audit` |

### Polices — décision

Les polices (**Marcellus** en titrage, **Alegreya Sans** en texte) sont
**self-hébergées** dans `app/assets/fonts/`, sous-ensembles latin et latin-ext
uniquement. Pas d'appel runtime à Google Fonts : le jeu vise un public français,
et un tel appel transmettrait l'IP du visiteur à un tiers.

### Versions effectivement installées (vérifiées le 2026-08-27)

| Composant | Version |
|---|---|
| Symfony | 8.1.5 |
| PHP | 8.4 |
| FrankenPHP | 1.12.7 |
| Tailwind CSS (CLI standalone) | 4.3.3 (`symfonycasts/tailwind-bundle` v1.0.0) |
| PostgreSQL | 18 |
| Ember (observabilité) | 1.6.0 |

Le stack répond sur `https://localhost` (certificat auto-signé Caddy).

---

## 5. Phase 1 — Comptes et page d'accueil  ✅

Trois pages, un compte fonctionnel.

### Page de présentation (accueil)

- [x] Pitch du jeu (doc 00 : gestion/aventure/RPG léger, Nouvel Empire, sans attente réelle)
- [x] Mise en avant des deux modes (Campagne 10 missions / Aventure — Memphis)
- [ ] Aperçu visuel à partir des planches déjà générées (dossier `Sprites/`) —
      **non fait** : la page reste typographique. Reporté au découpage des sprites
      (§7), pour ne pas servir un JPEG de 2 Mo en attendant
- [x] Appels à l'action : « Créer un compte » / « Se connecter »
- [x] Route publique, aucune authentification requise

### Inscription

- [x] Formulaire Symfony Form : email, mot de passe (+ confirmation), CSRF activé
- [x] Validation serveur (email unique, robustesse du mot de passe)
- [x] Hash argon2 via le hasher natif Symfony
- [x] Connexion automatique après inscription — **le compte est utilisable tout de suite, sans blocage**
- [x] Email de vérification envoyé à l'inscription (lien signé, `symfony/mailer`)
- [x] Délai de grâce de **7 jours** pour valider ; passé ce délai, une commande
  planifiée (`app:users:purge-unverified`, cron) **supprime définitivement** les
  comptes non vérifiés
- [x] Bandeau de rappel discret tant que le compte n'est pas vérifié, avec lien pour
  renvoyer l'email

### Connexion & compte

- [x] Formulaire de connexion Symfony Security (email + mot de passe)
- [x] Gestion de session, déconnexion
- [x] **Mot de passe oublié** (inclus en Phase 1) : demande par email, lien de
  réinitialisation signé et à expiration, formulaire de nouveau mot de passe
- [x] Page compte minimale (email, statut de vérification, déconnexion) — prête à
  lister les parties en cours une fois la Phase 2 posée

### État : livrée

Les trois pages fonctionnent, couvertes par 21 tests (5 unitaires, 16 fonctionnels).

Écarts assumés par rapport au plan initial :

- **Tests fonctionnels en `WebTestCase` plutôt qu'en Behat.** Pour de la
  plomberie d'authentification, l'outil natif Symfony couvre les mêmes parcours
  sans machinerie supplémentaire. Behat garde son intérêt pour les scénarios de
  jeu, où la lisibilité Gherkin profite à la relecture fonctionnelle.
- **URL en français** (`/inscription`, `/connexion`, `/mot-de-passe-oublie`),
  décidées tant qu'elles n'étaient pas publiques. Les *noms* de routes restent en
  anglais (`app_register`…), car `security.yaml` les référence.
- **Emails envoyés en synchrone.** La recette Symfony les route vers Messenger en
  asynchrone, mais le stack ne fait tourner aucun worker : les messages
  seraient restés en file sans qu'aucune erreur ne le signale. À rebasculer le
  jour où un service worker est ajouté.
- **Mot de passe durci à l'inscription** : le maker acceptait 6 caractères sans
  contrôle de robustesse, alors que la réinitialisation en exigeait 12 avec
  `PasswordStrength` et `NotCompromisedPassword`. Les deux sont désormais alignés.
- **Case « conditions d'utilisation » retirée** du formulaire : elle renvoyait
  vers des CGU inexistantes.

Point de sécurité laissé ouvert : le formulaire d'inscription révèle qu'une
adresse possède déjà un compte (énumération). Comportement par défaut de Symfony
et de la plupart des sites ; le masquer dégraderait l'inscription. Le flux de
réinitialisation, lui, ne fuit rien.

Dette identifiée pour la Phase 2 : la commande de purge retire les demandes de
réinitialisation liées à un compte avant de le supprimer. **Quand `GameSave`
arrivera, il faudra étendre ce nettoyage**, sinon la purge échouera sur les
comptes ayant lancé une partie.

### Définition de « fini »

Un visiteur découvre le jeu sur l'accueil, crée un compte (utilisable
immédiatement), reçoit un email de vérification, se connecte, réinitialise son
mot de passe si besoin, voit sa page compte. Parcours couverts de bout en bout
par des tests fonctionnels `WebTestCase` (inscription, vérification, purge à
7 jours, connexion, mot de passe oublié), formulaires accessibles (labels, focus
visible), revue sécurité (CSRF, hashing, liens signés à expiration) passée avant
merge.

---

## 6. Feuille de route

Chaque phase correspond à un ou plusieurs documents déjà entièrement spécifiés —
le travail y est surtout de la traduction en entités Doctrine, contrôleurs et
vues, pas de la conception.

- [x] **Phase 0** — Fondations techniques · §4
- [x] **Phase 1** — Comptes et page d'accueil · §5
- [x] **Phase 2** — Lancer une partie et bâtir · §6.1 · `01`, `05`, `13`
- [ ] **Phase 3** — Carte, exploration et ressources · §6.2 · `02`, `04`, `06`, `08`
- [ ] **Phase 4** — Population : recrutement, chefs et travailleurs · `01`, `03`
- [ ] **Phase 5** — Artisanat et commerce · `08`, `12`
- [ ] **Phase 6** — Faveur divine et événements · `07`
- [ ] **Phase 7** — Énigmes, enquêtes et fil rouge · `10`
- [ ] **Phase 8** — Campagne : les 10 missions et leurs objectifs · `09`, `11`
- [ ] **Phase 9** — Renommée, héritage et succession familiale · `13`
- [ ] **Phase 10** — Medjaÿ et combat automatique · `03`
- [ ] **Phase 11** — Mode Aventure : Memphis et succession des règnes · `14`
- [ ] **Phase 12** — Découpage et intégration des sprites · `15` · §7 — **hors
      planche « tuiles »**, découpée dès la Phase 3 pour l'écran de carte

Le document 15 (interface & direction artistique) est **transverse** : chaque
phase l'utilise au fur et à mesure, la phase 12 ne concerne que l'intégration
des images elles-mêmes.

**Réordonnancement par rapport à la première version.** Les cycles (doc 05)
remontent en Phase 2 : ils ne sont pas un système parmi d'autres mais le
battement du jeu, et un chantier qui ne progresse pas n'est pas démontrable.
Symétriquement, le combat (doc 03) redescend en Phase 10 : il est optionnel dans
les boucles de jeu et n'a de sens qu'une fois les zones dangereuses posées.

---

### 6.1 Phase 2 — Lancer une partie et bâtir  ✅

**Intention.** Livrer la plus petite tranche réellement *jouable* plutôt que
tout le document 01 sans écoulement du temps : créer une partie, voir sa ville,
lancer un chantier, déclencher un cycle, voir le chantier avancer puis s'achever.
C'est ce qui valide l'architecture d'état par partie et la résolution de cycle —
la fondation de tout le reste.

À la fin de la phase, on doit pouvoir raconter : *« je crée une famille, le
pharaon me dote, je lance la construction d'un grenier, j'avance de deux
quinzaines, le grenier est debout. »*

#### 2.1 — Modèle de partie  ✅

- [x] `GameSave` : mode (campagne/aventure), mission ou règne courant, numéro de
      cycle, date de création et de dernière ouverture
- [x] `Family` : nom choisi par le joueur (défaut proposé : **Nakht**) et renommée,
      avec ses cinq paliers (doc 13). **La trésorerie n'y est pas** : l'or est un
      objectif de mission, donc lié à la ville — il rejoint le stock au lot 2.6
- [x] `City` : nom de la ville, difficulté de la région
- [x] Un compte porte **plusieurs `GameSave` actifs, plafonnés à 5** (décision §9)
- [x] **Étendre `app:users:purge-unverified`** pour supprimer les parties d'un
      compte purgé — dette identifiée en Phase 1, la clé étrangère bloquerait
      sinon la suppression

#### 2.2 — Parcours « nouvelle partie »  ✅

- [x] Choix du mode : Campagne (démarre toujours à la mission 1, Avaris — l'ordre
      des missions est imposé) ou Aventure (Memphis)
- [x] Saisie du nom de famille, avec **Nakht** proposé par défaut (doc 09)
- [x] En mode Aventure uniquement : choix de la difficulté (0 à 9) et de la taille
      de grille (doc 14)
- [x] Texte d'introduction citant le pharaon commanditaire et le contexte
      historique (doc 09 — format texte simple, pas de cinématique)
- [x] **Dotation royale** créditée au départ : `50 + 10 × difficulté` en or, plus
      de quoi couvrir un premier bâtiment (doc 13)

#### 2.3 — Liste et gestion des parties  ✅

- [x] La page de compte liste les parties en cours (ville, mode, cycle atteint)
- [x] Reprendre une partie, via un **récapitulatif d'état** avant de rendre la
      main sur la ville. Il porte pour l'instant le cycle et le stock ; la saison
      et les chantiers s'y ajouteront aux lots 2.4 et 2.5, quand ils existeront
- [x] Abandonner une partie : **suppression définitive**, derrière une confirmation
      explicite — action irréversible
- [x] Refus de création au-delà de **5 parties**, avec un message qui invite à en
      abandonner une

#### 2.4 — Vue de la ville et bâtiments  ✅

- [x] Vue en **liste/vignettes**, jamais de placement libre sur une grille (doc 15)
- [x] Les 12 bâtiments du doc 01, avec leur condition de disponibilité
      (le Port exige un point d'eau adjacent — donc indisponible tant que la carte
      n'existe pas, ce qui est cohérent avec la Phase 3)
- [x] Coûts de construction et de montée de niveau :
      `coutBase × (1 + (N-1) × 0,4)`
- [x] Plafonds de niveau : `min(niveauMaxBatiment, 5 + difficulté)`
- [x] Fiche de bâtiment : niveau, effet courant, coût du niveau suivant
- [x] La Résidence familiale est présente d'emblée, offerte avec la ville (doc 01)
- [x] Chaque empêchement porte son motif, plutôt qu'un bâtiment grisé sans
      explication

**Deux bâtiments restent inaccessibles**, pour des dépendances assumées : le
**Port** exige un point d'eau adjacent, qui n'existera qu'avec la carte (Phase 3),
et le **Temple** réclame 5 lin en offrande, ressource agricole de la même phase.
L'interface le dit explicitement au lieu de les masquer.

#### 2.5 — Cycles et chantiers  ✅

- [x] Bouton « Cycle suivant » — **la seule chose qui fait avancer le temps**
- [x] Calendrier pharaonique : nom du mois affiché, saison courante (doc 05)
- [x] Durée de chantier : `dureeBase + niveau`, `dureeBase` propre au bâtiment
- [x] **Étapes de chantier nommées** avec leur info-bulle pédagogique — séchage
      des briques, élévation des murs… (doc 01)
- [x] Accélération ×1,5 pendant Akhèt (main-d'œuvre libérée par la crue)
- [x] Le joueur reste libre d'agir entre deux cycles : aucun blocage

#### 2.6 — Ressources minimales  ✅

Strictement ce qu'exige la construction, le reste attend la Phase 3 :

- [x] Stock de la ville : or, bois, pierre — **remonté au lot 2.2** : la dotation
      royale n'avait nulle part où atterrir sans lui. Colonne `stock_or` et non
      `or`, mot réservé du SQL
- [x] Débit à la mise en chantier, refus si le stock est insuffisant — rien
      n'est débité sur un refus
- [x] Affichage permanent des compteurs (barre supérieure, doc 15) : or, bois,
      pierre, date pharaonique et passage de cycle, sur **tous** les écrans de
      partie — sauf l'écran d'abandon, où avancer le temps n'a pas de sens

#### Hors périmètre, explicitement

Carte et exploration, production de ressources, recrutement de chefs et de
travailleurs, effets fonctionnels des bâtiments (un grenier construit ne stocke
encore rien), craft, commerce, faveur divine, énigmes, objectifs de mission.

Conséquence assumée : en fin de Phase 2, on **construit** sans encore
**produire**. La dotation royale finance les premiers bâtiments, ce qui suffit à
valider la boucle. La production arrive en Phase 3 avec la carte, qui en est la
source.

#### Définition de « fini »

Parcours complet couvert de bout en bout par des tests fonctionnels : créer une
partie → dotation créditée → lancer un chantier → déclencher les cycles → le
bâtiment est opérationnel. Plus des tests unitaires sur les formules (coût,
durée, plafonds, bonus d'Akhèt), qui sont le cœur calculatoire et l'endroit où
une régression passerait le plus facilement inaperçue.

Les quatre portes qualité au vert, revue de sécurité sur les nouvelles routes
(une partie ne doit être lisible et modifiable que par son propriétaire — un
**Voter** plutôt qu'un simple contrôle de rôle).

#### Phase 2 — bilan

**Livrée.** La boucle annoncée en intention est démontrable : créer une famille,
recevoir la dotation, engager un chantier, déclencher des quinzaines, voir le
bâtiment se dresser. Vérifiée en HTTP réel, pas seulement en tests.

Écarts assumés par rapport au découpage initial :

- Le **stock** est remonté du lot 2.6 au 2.2 : la dotation royale n'avait nulle
  part où atterrir sans lui.
- Le lot 2.4 livre le **catalogue** des bâtiments sans l'action de construire,
  qui appartient au 2.5 avec les chantiers. Un bouton qui n'aurait rien fait
  aurait été pire qu'une page sans bouton.
- Le **récapitulatif de reprise** ne porte que le cycle et le stock. La saison
  et les chantiers l'ont rejoint au lot 2.5.

Trois pièges rencontrés, corrigés et documentés :

- `or` est un **mot réservé du SQL**. Le `CREATE TABLE` passait, les `SELECT`
  générés ensuite non. Colonne `stock_or`.
- La signature des **Voters a changé en Symfony 8** : un paramètre `Vote` a été
  ajouté à `voteOnAttribute()`, avec une erreur fatale opaque à la clé.
- L'avancement des chantiers se compte en **dixièmes de cycle**, pas en
  flottants : le facteur ×1,5 d'Akhèt aurait fini par laisser un chantier bloqué
  à un cheveu de son terme.

Deux bâtiments restent hors d'atteinte, en attendant la Phase 3 : le **Port**
(point d'eau) et le **Temple** (lin). L'interface le dit au lieu de les masquer.

---

#### Points tranchés

| Question | Décision |
|---|---|
| Abandonner une partie | **Suppression définitive**, derrière une confirmation explicite |
| Parties simultanées | **Plafonnées à 5** par compte |
| Ordre des missions | **Imposé** : la campagne se joue de la mission 1 à la 10, sans choix de région |
| Reprise de partie | **Récapitulatif** avant de rendre la main sur la ville |

Deux conséquences à retenir :

- L'ordre imposé des missions **simplifie le modèle** : un `GameSave` de campagne
  porte un simple numéro de mission qui s'incrémente. Aucun écran de sélection
  de région, aucune notion de région débloquée à gérer.
- Le récapitulatif n'est **pas un journal d'événements**. Le jeu n'ayant aucun
  temps réel, rien ne se produit pendant l'absence du joueur : un « depuis votre
  dernière visite » serait toujours vide. Le récapitulatif porte donc sur
  **l'état où la partie a été laissée** — cycle et saison en cours, chantiers
  engagés avec les cycles restants, stock, et ce qui s'est résolu au dernier
  cycle déclenché. C'est une reprise de contexte, pas une notification.

---

### 6.2 Phase 3 — Carte, exploration et ressources  *(5 lots sur 6)*

**Intention.** Faire basculer la ville de la dépense à la production. Aujourd'hui
elle consomme une dotation qui ne se renouvelle pas ; à la fin de cette phase,
elle tire ses matériaux de son territoire et sa nourriture de ses champs.

À la fin de la phase, on doit pouvoir raconter : *« j'envoie un éclaireur sur une
case voisine, il y trouve de l'argile, je l'exploite, et cette argile alimente
mes chantiers ; j'établis un champ, je bâtis le grenier, et la moisson tombe en
Chémou. »*

C'est aussi la phase qui débloque les deux bâtiments aujourd'hui hors d'atteinte :
le **Port** (point d'eau adjacent) et le **Temple** (lin en offrande).

#### 3.1 — Généralisation du stock  *(prérequis)*  ✅

Fait en premier, avant que les ressources n'arrivent : la migration coûte peu
aujourd'hui, beaucoup plus une fois des parties en cours.

- [x] Remplacer les trois colonnes `stock_or`, `bois`, `pierre` par une table
      `ressource → quantité`
- [x] Énumération des ressources du doc 08 : minérales, agricoles, importées
- [x] Migration des parties existantes sans perte
- [x] `crediterRessources()` / `debiterRessources()` prennent une carte
      ressource → quantité. **Leur signature a dû changer**, contrairement à ce
      que ce plan annonçait : un contrat nommant `or`, `bois` et `pierre` ne
      peut pas rester générique. Les appelants ont suivi, les chantiers n'ont
      pas changé de comportement
- [x] Un débit hors de moyens ne retire **rien**, pas même ce qui suffisait

#### 3.2 — Génération de la carte  ✅

- [x] `Zone` : position sur la grille, type de terrain, contenu, état de découverte
- [x] Géographie cohérente avec l'Égypte réelle (doc 02) : Méditerranée en ligne
      du haut, mer Rouge en colonne de droite, Nil en colonne sur un bord libre,
      désert sur un bord libre ou dispersé, oasis à l'intérieur du désert
- [x] Placement contraint de la ville : adjacente à un point d'eau s'il en existe
      un, sinon sur une zone fertile — jamais en plein désert
- [x] Tirage pondéré du contenu par difficulté (ressource / champ éligible /
      événement / vide), selon le tableau du doc 02
- [x] **Génération à la création de la partie** (décision) : une partie sans
      territoire n'aurait pas de sens, et ça évite un état à moitié initialisé
- [x] Instanciation de la première carte, le Delta du Nord en 3×3 (doc 06)
- [x] Géographie des dix régions renseignée d'après les docs 02, 08 et 11

**Un écart aux documents, tranché.** Le doc 02 ne pose le désert que sur **un
bord**. Appliqué tel quel au Ouadi Hammamat ou au Sinaï, il produisait des cartes
majoritairement fertiles — un camp minier entouré de champs, contraire à la
description « désert dominant » du doc 11. Un attribut `desertDominant` ensable
donc tout ce qui ne borde pas l'eau, pour les régions 2, 9 et 10. Une bande
fertile survit le long du fleuve ou de la mer, sans quoi la ville n'aurait nulle
part où s'installer.

**Un invariant renforcé.** Le doc 02 exige que la ville touche l'eau et
n'apparaisse « jamais en plein désert ». Les deux conditions se contredisaient
sur une case de sable bordant le Nil : elle est désormais écartée. C'est un test
d'invariant, rejoué sur vingt graines, qui l'a révélé.

#### 3.3 — Découpage des tuiles et écran de carte  ✅

Ce lot **remonte de la Phase 12** : la carte se dessine avec les vraies tuiles,
décision prise pour cette phase.

- [x] Découper la planche « tuiles » du Drive : 8 tuiles isométriques en losange,
      1408 × 768 pixels, soit des cellules de 352 × 384
- [x] Détourer le fond sombre pour rendre les losanges transparents — sans quoi
      ils ne peuvent pas se juxtaposer
- [x] Servir les PNG via AssetMapper, comme les polices
- [x] Grille **isométrique** : les tuiles se posent en losange, pas en carré
- [x] Tuile de brouillard sur toute case non reconnue, marqueur de ville sur la
      sienne
- [x] Détail au clic sur une case reconnue — rendu **côté serveur** plutôt qu'en
      JavaScript : le jeu se joue sans, et le lien reste partageable
- [x] Commande `app:parties:generer-cartes-manquantes` : les parties lancées
      avant le lot 3.2 n'avaient pas de territoire et seraient restées vides

**Trois obstacles rencontrés au découpage.** La tuile de brouillard, presque
aussi sombre que le fond de la planche, se faisait dévorer par le détourage : son
masque est repris de la tuile de désert, prisme de même forme. Les cellules sont
conservées **entières** plutôt que recadrées, sans quoi les roseaux et les
palmiers qui dépassent du losange décalaient la grille. Et la planche pèse un
mégaoctet en pleine résolution : les tuiles sont ramenées à 176 px de losange,
soit 344 Ko pour les huit.

**Géométrie retenue** : losange de 174 × 140 dans une cellule de 189 × 206, posé
tous les 87 px en x et 70 px en y — la moitié de chaque dimension du losange. Les
cases sont peintes par somme x+y croissante, sinon les roseaux d'une case passent
derrière celle qu'ils devraient masquer.

**Deux couches, pas une.** Les tuiles se recouvrent : si chacune portait son
propre lien, celles du premier plan captureraient les clics des cases situées
derrière, ne laissant cliquable que le sommet de chaque losange. Les images
forment donc une couche inerte, et une seconde couche de liens découpés en
losange (`clip-path`) reçoit les clics. Les losanges pavant le plan sans se
chevaucher, chaque point de la carte appartient à une seule case.

**La carte devient l'écran principal d'une partie** (décision prise en cours de
lot). On y arrive en reprenant une partie, la barre de jeu y ramène, et c'est en
**cliquant la tuile de la ville** qu'on ouvre la liste de ses bâtiments — non
l'inverse. Avancer le temps renvoie le joueur là où il se trouvait, la route de
retour étant validée contre une liste blanche : une valeur soumise ne doit pas
devenir un nom de route arbitraire.

#### 3.4 — Reconnaissance

- [x] **Éclaireur** : reconnaissance de toute case inconnue, coût modeste
- [x] Coût en **or et cycles** pour l'instant (décision) ; la part en provisions
      s'ajoutera au lot 3.5, quand la nourriture existera
- [x] **Plusieurs expéditions simultanées** (décision), une par case — la
      contrainte vient naturellement du coût, pas d'une limite arbitraire
- [x] L'expédition part et progresse au fil des cycles, sans bloquer le joueur —
      même mécanique que les chantiers, déjà éprouvée au lot 2.5
- [x] Bonus d'Akhèt sur les trajets empruntant le Nil, malus symétrique en Chémou

Le passage d'une quinzaine était jusqu'ici piloté par `Chantiers`. Les
expéditions avançant au même rythme, la responsabilité est remontée dans
`PassageDeCycle` : chaque service fait progresser ce qui le concerne sans rien
persister, et tout ce qui se dénoue dans la même quinzaine tient en une seule
écriture.

Le doc 04 accorde le bonus de crue aux trajets « empruntant le Nil » sans dire à
quoi on les reconnaît. Interprétation retenue, à rediscuter si elle déçoit à
l'usage : **une expédition emprunte le Nil quand sa destination est une case du
fleuve**. Une case sous brouillard s'ouvre désormais au clic — il faut bien
pouvoir y envoyer un éclaireur — mais son panneau ne livre ni terrain ni
gisement, ce qu'un test verrouille.

#### 3.5 — Ressources de zone, champs et cycle agricole

- [x] Ressources brutes du doc 08, cohérentes avec la géologie réelle : argile et
      roseaux dans le Delta, calcaire, grès, granite, cuivre, turquoise ailleurs
- [x] Exploitation d'une case reconnue : la ressource alimente le stock, et les
      chantiers y puisent
- [x] Champ établi sur une zone fertile ou une zone du Nil inondable (doc 02)
- [x] **Sans Grenier construit, un champ ne produit rien d'exploitable** (doc 01) :
      la dépendance est le cœur de la mécanique, pas un détail
- [x] Rendement suivant les saisons (doc 05) : nul en Akhèt, champs sous l'eau ;
      croissant en Perèt ; pic de moisson en Chémou
- [x] Qualité de la crue tirée en début d'année (faible ×0,7 / normale / forte
      ×1,3), annoncée au joueur
- [x] Blé, orge et lin — le lin **débloque le Temple**
- [x] Provisions désormais payées en nourriture par les expéditions

##### Bois et pierre : des familles, pas des ressources

La question laissée ouverte depuis le lot 3.1 est tranchée. Le doc 01 chiffre
tous ses bâtiments en `bois` et `pierre` ; le doc 08 ne connaît ni l'un ni
l'autre, seulement des matériaux nommés. Prise au pied de la lettre, la
contradiction rendait la **première mission injouable** : le Delta ne porte
qu'argile, roseaux et calcaire, donc ni « bois » ni « pierre ».

Décision : **un coût se paie avec n'importe quel matériau de la famille
demandée**. Chaque région fournit le sien, ce qui est aussi la réalité
historique — on bâtissait avec la pierre qu'on avait sous la main. Les coûts du
doc 01 restent intacts, et les pierres nommées du doc 08 cessent d'être
décoratives. Deux rattachements en découlent, tous deux appuyés sur le doc 01 :

- **L'argile relève de la maçonnerie** (décision de la joueuse) : le doc 01
  précise que la quasi-totalité des bâtiments sont en brique crue, faite du
  limon du fleuve. Un grenier du Delta se bâtit en brique, un temple en
  calcaire, et tous deux paient la même ligne.
- **Les roseaux tiennent lieu de bois** : hors Levant, aucune région d'Égypte
  n'a de bois d'œuvre, et le doc 01 décrit lui-même les toitures en troncs de
  palmier et en **nattes** — donc en roseau.

Un prélèvement puise **du plus abondant au plus rare**, sans quoi un grenier de
brique crue pourrait engloutir le granite réservé au temple.

##### À porter au game design : seul le Delta est autosuffisant

Constat établi en écrivant ce lot, et verrouillé par un test qui échouera si une
région change : sur les dix régions, **seul le Delta porte les deux familles**.
Cinq n'ont que de la pierre, le Levant que du bois, et trois — Haute-Nubie, mer
Rouge, Sinaï — ni l'une ni l'autre.

La dotation royale comble le départ, en envoyant du cèdre et du calcaire là où
la région ne produit rien. Mais elle ne finance pas une mission entière : **à
partir de la région 2, le commerce de la Phase 5 devient une condition de
jouabilité, pas un confort.** À trancher avant de livrer la mission 2 — soit en
avançant la Phase 5, soit en dotant ces régions d'un matériau local.

##### Valeurs inventées, à calibrer en playtest

Aucun document ne les chiffre. Elles sont signalées comme telles dans le code :

| Valeur | Retenue | Où |
|---|---|---|
| Récolte d'un champ par quinzaine, au pic | 10 | `RendementDesChamps` |
| Extraction d'un gisement par quinzaine | 5, avant rareté régionale | `Recoltes` |
| Provisions d'un éclaireur | 5 vivres | `RoleDExploration` |
| Provisions de la dotation royale | 40 blé | `DotationRoyale` |

Les provisions de départ ne sont pas un confort : sans elles, le joueur ne
pourrait pas envoyer son premier éclaireur, donc jamais trouver la terre où
semer. La boucle se refermait sur elle-même.

##### Les quatre étapes d'un chantier sont toutes affichées

Défaut relevé à l'usage : un Grenier de niveau 1 dure deux quinzaines pour
quatre étapes, et l'écran n'en montrait qu'une à la fois — déduite du
pourcentage d'avancement. Le joueur ne voyait donc jamais que les étapes 1 et 3.
Le séchage des briques, qui porte l'explication de pourquoi aucun chantier ne
dure moins d'une quinzaine, passait à la trappe.

Les quatre étapes sont désormais rendues en permanence, marquées *terminée*, *en
cours* ou *à venir*. Celles que la quinzaine va traverser portent leur
explication ; les autres, leur seul intitulé. C'est ce que le doc 01 décrit par
« les cycles sont répartis proportionnellement entre ces étapes ».

Subtilité découverte en vérifiant en navigateur : la fenêtre « en cours » doit
suivre la **vitesse réelle** du cycle. En Akhèt, la corvée fait avancer d'1,5
cycle, donc la quinzaine franchit une étape de plus qu'annoncé — et cette
étape-là n'apparaissait jamais. Un test parcourt maintenant chaque chantier de
bout en bout, dans les trois saisons, et vérifie qu'aucune étape n'est escamotée.

##### Deux ajustements d'ouverture de partie (décisions de la joueuse)

- **La dotation royale couvre le Grenier**, pas seulement l'Entrepôt : sa part
  de pierre passe de 10 à 15. Le Grenier coûte 15 bois, 15 pierre, 15 or au
  niveau 1 (doc 01) et conditionne toute l'agriculture ; le laisser hors de
  portée jusqu'à la première carrière rendait les champs sans destination.
- **Reconnaître les abords immédiats de la ville ne coûte pas d'or** : les huit
  cases adjacentes, en orthogonal comme en diagonale, se reconnaissent sans
  bourse délier — on voit ses propres abords depuis les murs. Les vivres restent
  dus, l'éclaireur mangeant même à une heure de marche. Effet de bord voulu :
  une partie ne peut plus se retrouver bloquée sans issue faute d'or, ce qu'un
  test verrouille.

#### 3.6 — Points d'eau, Port et pêche

- [ ] Le Port devient constructible dès qu'un point d'eau jouxte la ville
- [ ] Pêche sur les cases d'eau reconnues, une fois le Port dressé
- [ ] Les cases d'eau cessent d'être un décor : elles portent du contenu comme
      les autres (doc 02)

#### Hors périmètre, explicitement

**Les rôles d'exploration autres que l'éclaireur.** L'émissaire suppose des PNJ,
le chef d'expédition des zones lourdes, l'escorte des Medjaÿ — qui n'arrivent
qu'en Phase 10. La première mission se joue en difficulté 0, **sans aucune zone
à bandits** : l'éclaireur seul y suffit, et c'est ce que la phase livre.

Également hors périmètre : l'épuisement des gisements et la re-exploration, qui
ne concernent que les régions de difficulté 4 et plus ; le commerce et le craft
(Phase 5) ; les événements de zone et les énigmes (Phase 7).

#### Définition de « fini »

Parcours couvert de bout en bout : carte générée à la création de la partie →
éclaireur envoyé → cycles déclenchés → case révélée → ressource exploitée →
chantier financé par cette ressource. Plus un champ établi, un grenier bâti, et
une moisson qui tombe en Chémou et pas en Akhèt.

Tests unitaires sur les points où une régression passerait inaperçue : les règles
de placement géographique, le tirage pondéré, le rendement saisonnier. La
génération étant semi-aléatoire, ses tests doivent porter sur des **invariants**
(la ville touche toujours l'eau si l'eau existe, la grille fait toujours la
bonne taille) plutôt que sur une carte attendue.

Les quatre portes qualité au vert, et une revue de sécurité sur les nouvelles
routes — exploiter une case ou envoyer un éclaireur modifie l'état d'une partie
et doit passer par le `PartieVoter`.

#### Décisions actées pour cette phase

| Question | Décision |
|---|---|
| Stock générique | **Oui, en premier** (lot 3.1), avant l'arrivée des ressources |
| Génération de la carte | **À la création de la partie** |
| Expéditions simultanées | **Plusieurs**, une par case ; le coût fait la contrainte |
| Coût de l'éclaireur | **Or et cycles** au lot 3.4 ; provisions ajoutées au 3.5 |
| Écran de carte | **Grille isométrique**, avec les tuiles du Drive |

#### Le point à surveiller : les tuiles sont isométriques

La planche livrée contient **huit losanges isométriques** — Nil bordé de
papyrus, mer, désert, champs irrigués, oasis, forêt de cèdres, brouillard gravé
de hiéroglyphes, et un marqueur de ville avec son embarcadère.

C'est fidèle à la direction artistique du doc 15 (« vue isométrique légère »),
mais **le prompt de la planche 13 demandait des tuiles vues de dessus**. Le
générateur a suivi la direction artistique plutôt que le prompt. Deux
conséquences concrètes :

- La carte ne peut pas être une simple grille CSS : les losanges se posent en
  quinconce, avec un décalage d'une demi-tuile une ligne sur deux.
- Les tuiles arrivent sur **fond sombre opaque**, alors que le prompt demandait
  un fond transparent. Il faut les détourer avant de pouvoir les juxtaposer.

Ni l'un ni l'autre n'est bloquant, mais les deux sont du travail réel, chiffré
dans le lot 3.3 plutôt que découvert en cours de route. Le doc 15 mériterait
d'être corrigé sur ce point — son prompt de planche 13 contredit sa propre
direction artistique.

---

## 7. Pipeline des assets graphiques

Les **18 planches** décrites dans le document 15 sont **déjà générées** et
présentes dans le sous-dossier `Sprites/` du Drive (bâtiments ×12, carte,
ressources brutes, ressources importées, objets fabriqués, icônes d'interface,
divinités). Aucune génération d'image à refaire — il reste un travail de
découpage et d'intégration.

1. Récupérer les 18 JPEG depuis `Sprites/` et les découper en sprites individuels
   (grille 2×2 pour les bâtiments, 4×2/5×3/4×4 selon la planche)
2. Exporter chaque sprite en PNG avec fond transparent, nommage cohérent
   (`batiment_grenier_palier1.png`…)
3. Ranger dans `app/assets/images/sprites/`, servis via AssetMapper
4. Vérifier la lisibilité des planches denses (ressources brutes 15 items, icônes
   interface 16 items) — le doc 15 anticipe déjà un re-découpage en 2 si besoin

La planche « tuiles » est **découpée dès la Phase 3** (lot 3.3) : la carte se
dessine avec elle. Le reste peut attendre.

**Attention au format.** La planche « tuiles » livrée contient des losanges
**isométriques** sur fond sombre opaque, alors que son prompt du doc 15 demandait
des tuiles carrées vues de dessus et à fond transparent. C'est la direction
artistique générale qui l'a emporté sur le prompt — cohérent, mais le prompt de
la planche 13 mériterait d'être corrigé dans le document.

---

## 8. Qualité et outillage

Skills à mobiliser au fil du développement, pas en revue finale :

| Skill | Quand |
|---|---|
| `symfony-coding-standards` | À chaque fichier `.php`/`.twig` écrit ou modifié |
| `phpstan-analysis` | En continu, typage strict dès la Phase 0 |
| `phpunit-testing-standards` | Tests unitaires/intégration pour chaque service |
| `functional-e2e-testing` | Si Behat est introduit pour les scénarios de jeu (voir §2) |
| `web-security-checklist` | Revue dédiée avant merge de l'inscription/connexion (OWASP) |
| `web-accessibility-a11y` | Formulaires et navigation dès la Phase 1 (WCAG 2.2 AA) |
| `git-commit-conventions` | Tout au long, dès le premier commit |
| `ci-pipeline-standards` | Mise en place du pipeline en Phase 0 |
| `code-review` / `code-audit-orchestrator` | Revue de chaque lot livré |

L'infrastructure Docker suit `.claude/rules/stack-conventions.md`, qui fait
autorité sur toute question d'arborescence, nommage, ports et `.env`.

---

## 9. Décisions actées

| Question | Décision |
|---|---|
| Nom de famille | Choisi **au lancement d'une partie**, pas à l'inscription |
| Vérification d'email | **Oui**, mais non bloquante. 7 jours de grâce, puis suppression définitive du compte non vérifié |
| Mot de passe oublié | **Inclus dès la Phase 1** |
| Base de données | **PostgreSQL** |
| Hébergement | Pas de choix figé — **VPS envisagé** pour la production ultérieure. Plan indépendant de l'hébergeur |
| Parties simultanées | **Oui**, jusqu'à **5** `GameSave` actifs par compte |
| CSS | **Tailwind CSS 4.3** via `symfonycasts/tailwind-bundle`, pas de Node.js |
| Serveur staging/prod | **Dédié** (`--dedicated-server`), pas de Traefik partagé |
| Stock des ressources | **Générique** (table ressource → quantité), migré dès le lot 3.1 |
| Génération de la carte | **À la création de la partie** |
| Écran de carte | **Grille isométrique**, avec les tuiles du Drive |
| Abandon d'une partie | **Suppression définitive**, derrière confirmation |
| Ordre des missions | **Imposé**, de la mission 1 à la 10 |
| Reprise de partie | **Récapitulatif d'état** avant de rendre la main |
