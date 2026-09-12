## Ce que ça change

<!-- Une phrase : ce que la personne qui joue voit de différent. -->

## Pourquoi

<!-- La raison d'être. Si ça corrige un défaut, dire lequel et comment il se reproduisait. -->

## Vérifications

- [ ] `php-cs-fixer fix --dry-run --diff` passe
- [ ] `phpstan analyse` passe, niveau 8, zéro erreur, sans baseline nouvelle
- [ ] `composer audit` passe
- [ ] `bin/phpunit` passe
- [ ] La documentation (`docs/`, `CLAUDE.md`) suit le changement, s'il touche une règle
- [ ] Le `CHANGELOG.md` porte une ligne sous « Non publié », si le changement se voit
