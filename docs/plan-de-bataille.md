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
| Tests | PHPUnit (unitaire/intégration) + Behat (parcours joueur en Gherkin) dès la Phase 1 |

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
l'inscription. Un compte peut avoir **plusieurs parties en cours simultanément**.

| Entité (esquisse) | Rôle | Doc source |
|---|---|---|
| `User` | Compte joueur — email, mot de passe, statut de vérification, rôles | — |
| `GameSave` | Une run : mode (Campagne/Aventure), mission ou règne en cours, cycle courant. Plusieurs `GameSave` actifs par `User` | 00, 14 |
| `Family` | Nom de famille choisi au lancement (1 par `GameSave`), renommée, héritage, contacts commerciaux | 13 |
| … (Phase 2+) | Ville, bâtiments, ressources, carte, Medjaÿ, faveur divine, énigmes | 01–12 |

Pour la Phase 1, seul `User` est nécessaire (avec son statut de vérification).

---

## 4. Phase 0 — Fondations techniques

- [x] Dépôt git initialisé (branche `main`)
- [x] Plan de bataille sauvegardé dans le projet (`docs/plan-de-bataille.md`)
- [x] Stack Docker + squelette Symfony via `.claude/scripts/setup-symfony.sh --dedicated-server --run`
- [x] `CLAUDE.md` du projet (conventions, commandes, pièges d'infrastructure)
- [ ] Thème Tailwind : palette et typographies de la direction artistique (doc 15) — ocre/sable/terre cuite, accents lapis-lazuli/or
- [ ] Gabarit Twig de base (`base.html.twig`)
- [ ] Configuration `symfony/mailer` (transport de dev) — nécessaire dès la Phase 1
- [ ] Pipeline CI minimal : lint, PHPStan, PHPUnit
- [ ] `symfony/security-bundle` + entité `User`

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

## 5. Phase 1 — Périmètre immédiat

Trois pages, un compte fonctionnel.

### Page de présentation (accueil)

- Pitch du jeu (doc 00 : gestion/aventure/RPG léger, Nouvel Empire, sans attente réelle)
- Mise en avant des deux modes (Campagne 10 missions / Aventure — Memphis)
- Aperçu visuel à partir des planches déjà générées (dossier `Sprites/`)
- Appels à l'action : « Créer un compte » / « Se connecter »
- Route publique, aucune authentification requise

### Inscription

- Formulaire Symfony Form : email, mot de passe (+ confirmation), CSRF activé
- Validation serveur (email unique, robustesse du mot de passe)
- Hash argon2 via le hasher natif Symfony
- Connexion automatique après inscription — **le compte est utilisable tout de suite, sans blocage**
- Email de vérification envoyé à l'inscription (lien signé, `symfony/mailer`)
- Délai de grâce de **7 jours** pour valider ; passé ce délai, une commande
  planifiée (`app:users:purge-unverified`, cron) **supprime définitivement** les
  comptes non vérifiés
- Bandeau de rappel discret tant que le compte n'est pas vérifié, avec lien pour
  renvoyer l'email

### Connexion & compte

- Formulaire de connexion Symfony Security (email + mot de passe)
- Gestion de session, déconnexion
- **Mot de passe oublié** (inclus en Phase 1) : demande par email, lien de
  réinitialisation signé et à expiration, formulaire de nouveau mot de passe
- Page compte minimale (email, statut de vérification, déconnexion) — prête à
  lister les parties en cours une fois la Phase 2 posée

### Définition de « fini »

Un visiteur découvre le jeu sur l'accueil, crée un compte (utilisable
immédiatement), reçoit un email de vérification, se connecte, réinitialise son
mot de passe si besoin, voit sa page compte. Parcours couverts par des tests
Behat de bout en bout (inscription, vérification, expiration à 7 jours, mot de
passe oublié), formulaires accessibles (labels, focus clavier), revue sécurité
(CSRF, hashing, liens signés à expiration) passée avant merge.

---

## 6. Feuille de route (phases suivantes)

Chaque phase correspond à un ou plusieurs documents déjà entièrement spécifiés —
le travail y est surtout de la traduction en entités Doctrine, contrôleurs et
vues, pas de la conception.

| # | Phase | Documents source |
|---|---|---|
| 2 | Ville & bâtiments — vue de la ville, construction/amélioration, chefs/travailleurs, chantiers non instantanés | `01_batiments` |
| 3 | Carte & première région — génération de scénario, grille, brouillard de guerre, Delta du Nord | `02_carte_generation_scenario`, `06_premiere_carte` |
| 4 | Cycles & calendrier — bouton « cycle suivant », saisons, crue du Nil | `05_cycles` |
| 5 | Exploration — éclaireur, action complémentaire, coûts et risques | `04_exploration` |
| 6 | Recrutement & combat — offres d'emploi, Medjaÿ, combat automatique | `03_recrutement_combat` |
| 7 | Ressources, artisanat & commerce — Marché, Entrepôt, recettes, rivaux | `08_ressources` |
| 8 | Faveur divine — panthéon, offrandes, épidémies | `07_faveur_divine` |
| 9 | Campagne, lore & énigmes — 10 missions, fil rouge en 3 actes, déchiffrage | `09_lore_campagne`, `10_enigmes_enquetes` |
| 10 | Régions & routes commerciales — 9 régions restantes, imports/exports, héritage | `11_regions_campagne`, `12_routes_commerciales` |
| 11 | Renommée & héritage familial — jauge, dotation royale, succession | `13_renommee_heritage` |
| 12 | Mode Aventure — Memphis, succession des règnes, paramètres | `14_mode_aventure` |

Le document 15 (interface & direction artistique) est **transverse** : chaque
phase l'utilise au fur et à mesure.

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
| `functional-e2e-testing` | Parcours Behat, dès la Phase 1 |
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
| Parties simultanées | **Oui**, plusieurs `GameSave` actifs par compte |
| CSS | **Tailwind CSS 4.3** via `symfonycasts/tailwind-bundle`, pas de Node.js |
| Serveur staging/prod | **Dédié** (`--dedicated-server`), pas de Traefik partagé |
