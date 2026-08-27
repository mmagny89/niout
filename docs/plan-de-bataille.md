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

| Entité (esquisse) | Rôle | Doc source |
|---|---|---|
| `User` | Compte joueur — email, mot de passe, statut de vérification, rôles | — |
| `GameSave` | Une run : mode (Campagne/Aventure), mission ou règne en cours, cycle courant. Jusqu'à 5 `GameSave` actifs par `User` | 00, 14 |
| `Family` | Nom de famille choisi au lancement (1 par `GameSave`), renommée, héritage, contacts commerciaux | 13 |
| … (Phase 2+) | Ville, bâtiments, ressources, carte, Medjaÿ, faveur divine, énigmes | 01–12 |

Pour la Phase 1, seul `User` est nécessaire (avec son statut de vérification).

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
| PHPUnit | 5 tests sur `User` (vérification, purge, rôles) | `php bin/phpunit` |
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
Un `404 Welcome to Symfony` sur `/` est le comportement attendu tant qu'aucune
route n'est déclarée.

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
- [ ] **Phase 2** — Lancer une partie et bâtir · §6.1 · `01`, `05`, `13`
- [ ] **Phase 3** — Carte, exploration et ressources · `02`, `04`, `06`, `08`
- [ ] **Phase 4** — Population : recrutement, chefs et travailleurs · `01`, `03`
- [ ] **Phase 5** — Artisanat et commerce · `08`, `12`
- [ ] **Phase 6** — Faveur divine et événements · `07`
- [ ] **Phase 7** — Énigmes, enquêtes et fil rouge · `10`
- [ ] **Phase 8** — Campagne : les 10 missions et leurs objectifs · `09`, `11`
- [ ] **Phase 9** — Renommée, héritage et succession familiale · `13`
- [ ] **Phase 10** — Medjaÿ et combat automatique · `03`
- [ ] **Phase 11** — Mode Aventure : Memphis et succession des règnes · `14`
- [ ] **Phase 12** — Découpage et intégration des sprites · `15` · §7

Le document 15 (interface & direction artistique) est **transverse** : chaque
phase l'utilise au fur et à mesure, la phase 12 ne concerne que l'intégration
des images elles-mêmes.

**Réordonnancement par rapport à la première version.** Les cycles (doc 05)
remontent en Phase 2 : ils ne sont pas un système parmi d'autres mais le
battement du jeu, et un chantier qui ne progresse pas n'est pas démontrable.
Symétriquement, le combat (doc 03) redescend en Phase 10 : il est optionnel dans
les boucles de jeu et n'a de sens qu'une fois les zones dangereuses posées.

---

### 6.1 Phase 2 — Lancer une partie et bâtir  *(proposition détaillée)*

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

#### 2.3 — Liste et gestion des parties

- [ ] La page de compte liste les parties en cours (ville, mode, cycle atteint)
- [ ] Reprendre une partie, via un **récapitulatif d'état** (cycle et saison,
      chantiers en cours, stock) avant de rendre la main sur la ville
- [ ] Abandonner une partie : **suppression définitive**, derrière une confirmation
      explicite — action irréversible
- [ ] Refus de création au-delà de **5 parties**, avec un message qui invite à en
      abandonner une

#### 2.4 — Vue de la ville et bâtiments

- [ ] Vue en **liste/vignettes**, jamais de placement libre sur une grille (doc 15)
- [ ] Les 12 bâtiments du doc 01, avec leur condition de disponibilité
      (le Port exige un point d'eau adjacent — donc indisponible tant que la carte
      n'existe pas, ce qui est cohérent avec la Phase 3)
- [ ] Coûts de construction et de montée de niveau :
      `coutBase × (1 + (N-1) × 0,4)`
- [ ] Plafonds de niveau : `min(niveauMaxBatiment, 5 + difficulté)`
- [ ] Fiche de bâtiment : niveau, effet courant, coût du niveau suivant

#### 2.5 — Cycles et chantiers

- [ ] Bouton « Cycle suivant » — **la seule chose qui fait avancer le temps**
- [ ] Calendrier pharaonique : nom du mois affiché, saison courante (doc 05)
- [ ] Durée de chantier : `dureeBase + niveau`, `dureeBase` propre au bâtiment
- [ ] **Étapes de chantier nommées** avec leur info-bulle pédagogique — séchage
      des briques, élévation des murs… (doc 01)
- [ ] Accélération ×1,5 pendant Akhèt (main-d'œuvre libérée par la crue)
- [ ] Le joueur reste libre d'agir entre deux cycles : aucun blocage

#### 2.6 — Ressources minimales

Strictement ce qu'exige la construction, le reste attend la Phase 3 :

- [x] Stock de la ville : or, bois, pierre — **remonté au lot 2.2** : la dotation
      royale n'avait nulle part où atterrir sans lui. Colonne `stock_or` et non
      `or`, mot réservé du SQL
- [ ] Débit à la mise en chantier, refus si le stock est insuffisant
- [ ] Affichage permanent des compteurs (barre supérieure, doc 15)

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

Pour la Phase 1, seuls la palette et éventuellement un visuel (planche
« Divinités » ou « Carte ») pour l'accueil sont nécessaires. Le découpage complet
peut attendre la Phase 2.

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
| Abandon d'une partie | **Suppression définitive**, derrière confirmation |
| Ordre des missions | **Imposé**, de la mission 1 à la 10 |
| Reprise de partie | **Récapitulatif d'état** avant de rendre la main |
