# Politique de sécurité

Niout est un projet personnel, développé et hébergé par une seule personne.
Il n'y a ni équipe d'astreinte ni engagement de délai contractuel — mais toute
faille signalée est prise au sérieux et traitée en priorité sur le reste.

## Versions suivies

Seule la version déployée en production, correspondant au dernier commit de la
branche `main`, reçoit des correctifs. Le projet n'ayant pas encore atteint la
version 1.0, aucune version antérieure n'est maintenue.

## Signaler une faille

**Ne pas ouvrir d'issue publique.** Une issue est visible de tous, y compris de
qui voudrait exploiter la faille avant qu'elle ne soit corrigée.

Utiliser le signalement privé de GitHub :
[Security → Report a vulnerability](https://github.com/mmagny89/niout/security/advisories/new).

À défaut, écrire à mylene.magny@gmail.com avec `[Niout] sécurité` en objet.

Un signalement utile porte : ce qui est vulnérable (URL, formulaire, commande),
ce qu'on obtient en l'exploitant, et les étapes pour le reproduire.

## Ce à quoi s'attendre

| Étape | Délai visé |
|---|---|
| Accusé de réception | 72 heures |
| Première évaluation (confirmée / écartée, gravité) | 7 jours |
| Correctif en production pour une faille confirmée | 30 jours |

Les personnes qui signalent une faille valide sont créditées dans le
[CHANGELOG](CHANGELOG.md), sauf si elles préfèrent rester anonymes. Le projet
n'offre aucune récompense financière.

## Périmètre

Entrent dans le périmètre : le code de ce dépôt, l'instance en production et sa
configuration Docker.

N'y entrent pas : les vulnérabilités des dépendances tierces déjà publiées (elles
sont suivies par `composer audit` en intégration continue et par Dependabot), les
rapports issus d'un scanner automatique sans preuve d'exploitabilité, et le déni
de service par volume de requêtes.

## Ce que le projet fait déjà

- `composer audit` est une porte bloquante de l'intégration continue : aucune
  dépendance portant un avis de sécurité connu ne peut atteindre `main`.
- Dependabot ouvre des demandes de fusion pour les dépendances Composer et les
  actions GitHub.
- Aucun secret réel n'entre dans l'historique git — voir la section « secrets »
  du [CLAUDE.md](CLAUDE.md). Un `APP_SECRET` de développement s'y est glissé le
  2026-08-27 ; il a été retiré et révoqué le jour même, et une règle explicite
  empêche désormais sa réapparition.
