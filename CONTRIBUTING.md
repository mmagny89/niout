# Contribuer à Niout

Niout est un projet personnel, publié pour être lu autant que pour être joué.
Les contributions extérieures ne sont pas attendues — mais si vous ouvrez ce
fichier, voici ce que le projet demande.

## Avant d'écrire du code

Ouvrir une issue d'abord. Le projet suit une feuille de route détaillée
([`docs/plan-de-bataille.md`](docs/plan-de-bataille.md)) et des règles de jeu
qui portent chacune leur raison d'être
([`docs/regles-du-jeu.md`](docs/regles-du-jeu.md)) : un changement qui les
ignore sera refusé, quelle qu'en soit la qualité technique.

[`CLAUDE.md`](CLAUDE.md) est le point d'entrée : stack, commandes, architecture.

## Les quatre portes qualité

Elles tournent en intégration continue et doivent toutes passer. Les jouer en
local avant de proposer un changement évite un aller-retour :

```bash
docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff
docker compose exec php vendor/bin/phpstan analyse
docker compose exec php composer audit
docker compose exec php php bin/phpunit
```

PHPStan tourne au **niveau 8, zéro erreur attendue** : aucune baseline, aucune
exclusion nouvelle. Deux prérequis faciles à oublier : `cache:warmup` avant
PHPStan si le cache est vide, et `tailwind:build` avant les tests — sans la CSS
compilée, tout test qui rend une page échoue sur un message qui ne mentionne
jamais Tailwind.

## Conventions

- **Commits et documentation en français**, au format
  [Conventional Commits](https://www.conventionalcommits.org/fr/) :
  `feat(carte): …`, `fix(commerce): …`, `docs(plan): …`.
- **Nommage** : classes en anglais quand un terme clair existe (`User`,
  `GameSave`), propriétés et méthodes en français (`$joueur`,
  `marquerOuverte()`). Le vocabulaire de l'univers ne se traduit jamais —
  Medjaÿ, Akhèt, quinzaine, Niout.
- **Branches** : `feat/…` ou `fix/…`, fusionnées dans `main` par demande de
  fusion. `main` est toujours déployable : ce qui y entre part en production.
- **Un changement de règle du jeu se documente** dans `docs/`, avec sa raison
  d'être. Une règle sans son pourquoi se fait supprimer par le suivant, qui
  repaiera le défaut qu'elle évitait.

## Signaler une faille de sécurité

Pas par une issue publique — voir [SECURITY.md](SECURITY.md).
