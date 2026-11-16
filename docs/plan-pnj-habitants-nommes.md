# Niout — Nommer les habitants : réflexion

**Statut : réflexion, rien n'est codé.** Suite de la question laissée ouverte en fin de lot « nommer »
(`plan-pnj-ville-vivante.md` § 14 : *« un nom déduit d'une graine reste possible, mais le jeu n'en donne à personne d'autre »*).
On peut aujourd'hui nommer un **chef** ou un **Medjaÿ** sans prénom ; on ne peut pas nommer un habitant de maison, un
travailleur, un enfant, un ancien. Ce document dit **pourquoi**, ce que coûterait chaque manière d'y arriver, et ce que je
recommande. À lire avec `plan-pnj-ville-vivante.md` (§ 14, § 16) et `plan-pnj-autonomes.md` (§ 8, palier A).

## 1. Pourquoi on ne peut pas, aujourd'hui

Nommer quelqu'un, c'est **retrouver la même personne demain**. Or, pour les gens anonymes, rien ne tient :

| Fait | Conséquence pour un nom |
|---|---|
| **Le jeu compte des nombres** : `City::actifs`, `enfants`, `anciens` (+ `malades`). Il n'y a pas de ligne « Hori » en base | Aucun endroit où poser un nom qui survive |
| **Les maisons se recomposent à chaque lecture** (`Maisonnees::repartir()` : une répartition déterministe des comptes) | « Maison 3 » désigne une autre composition dès qu'un enfant naît ou qu'un actif part : le **rang** n'est pas une identité |
| **Plusieurs silhouettes partagent une clé** (`poste-grenier` ×3, `enfant-2` ×2, `macon-forge` ×3) et **une seule trace** de pas | On ne peut pas distinguer *lequel* des trois travailleurs on a cliqué, donc *lequel* on nomme |
| **La démographie est annuelle et aléatoire** (`Demographie` : naissances, vieillissement, morts, migration spontanée) | Les gens changent d'état en bloc, par tirage sur des comptes — personne ne « devient ancien », le compte des anciens monte |
| **La photographie n'est qu'une représentation** (`PhotoDeLaVille` : au plus 40 silhouettes, `MAISONS_MONTREES = 6`) | Ce qu'on voit est un échantillon de la ville, pas la ville |

Nommer sans identité, c'est donc **un nom qui flotte** : il changerait de tête au gré des recompositions. C'est pourquoi la
décision de V1 a été de ne nommer que les **désignés** (ceux dont les données existent un par un).

## 2. Quatre manières d'y répondre

### A. Un nom déduit d'une graine, jamais stocké

`PrenomEgyptien` + un hachage de `partie|clé|rang`. **Gratuit** : aucune base, aucun test de démographie.
**Défaut décisif** : les noms se **mélangent** à la moindre recomposition de maison — « Meryt » est une enfant, puis un ancien,
puis personne. Et rien de *saisi par le joueur* ne peut survivre. **Rejeté** : il ne répond ni à « nommer », ni à « custom ».

### B. Des foyers persistants — **recommandé**

On range ce que `Maisonnees` recompose : une entité **`Foyer`** (id stable, `ville`, compte d'actifs / enfants / anciens /
malades), qui **appartient à sa ville** (cascade `remove`, comme `Family` et `City`). Les maisons cessent d'être un
calcul ; elles deviennent des lignes, que la démographie **fait vivre** : une naissance entre dans un foyer, un départ en sort.

- **Invariant à tenir par un test** : la somme des foyers = les comptes de `City` (actifs, enfants, anciens). Tant qu'il tient, le
  reste du jeu (rations, salaires, logements, `Population`) ne bouge pas d'une ligne.
- **L'identité devient celle du foyer** : `foyer-17` ne change plus de sens. On le **nomme** : un **chef de foyer** a un prénom
  (tiré dans `PrenomEgyptien`, ou saisi), et la maison se lit « La maison de Hori ».
- **Les membres se nomment à la demande** : un enfant ou un ancien cliqué reçoit un prénom *au clic* (liste ou texte), rangé dans
  le foyer (une liste de prénoms par âge, plafonnée à son compte).

**Ce que ça coûte** : la **démographie doit écrire dans les foyers** — et c'est là qu'est tout le risque. Les endroits qui
modifient aujourd'hui les comptes sont peu nombreux (à l'écriture de ce document : `City::accueillir()` et `laisserPartir()`,
appelés par `Demographie`, `DepartsNaturels`, `Recrutements`, `AppelDHabitants`, `LanceurDePartie`) — mais **chacun doit
dire dans quel foyer** il agit. Une migration doit répartir les comptes existants **comme `Maisonnees` le fait déjà**, pour que
rien ne saute aux yeux le jour du déploiement.

### C. Des habitants individuels

Une ligne par personne (prénom, âge, foyer, métier, traits). C'est le palier le plus riche — **et le plus cher** : `Population`
cesserait d'être un tirage sur des comptes pour devenir des individus qui vieillissent (60 à 300 écritures par quinzaine,
l'objection déjà faite dans `plan-pnj-autonomes.md`), les comptes de `City` deviendraient dérivés, et il faudrait réécrire
`Subsistance`, `Epidemies`, `Salaires`, `Effectifs`. C'est **le palier A du plan PNJ autonomes**, pas un lot de nommage.
**Hors de portée de cette demande** ; B n'en ferme pas la porte (un foyer persistant est exactement ce qu'un habitant
individuel viendrait habiter).

### D. Nommer seulement les désignés, mieux

C'est l'état actuel, à quoi s'ajouteraient : renommer (aujourd'hui interdit après la première fois), nommer le **chef de famille** et le **rival**. **Peu cher, sans risque**, mais n'adresse pas les enfants, les anciens, les travailleurs.

## 3. Ce que je recommande

**B en trois lots, dans cet ordre, chacun livrable seul :**

| Lot | Contenu | Risque | Verrou |
|---|---|---|---|
| **H1** | L'entité `Foyer`, la migration (répartition identique à `Maisonnees`), la démographie qui y écrit. **Aucun changement visible** | **Élevé** : c'est tout le travail | Invariant « somme des foyers = comptes de la ville », rejoué après chaque événement (naissance, mort, départ, famine, épidémie, appel) ; base de test migrée ; suite complète |
| **H2** | Un prénom de chef de foyer, attribué à la création et rattrapé (comme le lot N pour les chefs), affiché : « La maison de Hori » ; clés `foyer-17` stables dans la ville, la liste et la fiche | Moyen | `Prenoms::pris()` compte aussi les foyers ; deux foyers ne partagent pas un prénom |
| **H3** | Nommer **au clic** : un enfant, un ancien, un membre du foyer reçoit un prénom (liste ou texte) ; une clé de silhouette **unique par personne** (`enfant-17-1`), ce qui oblige à donner à chacun son propre pas dans le film | Moyen : le film partage aujourd'hui une trace par clé | Le film d'une quinzaine garde ses invariants (fluidité, pas de superposition) ; les noms survivent à une quinzaine |

**Pourquoi pas tout de suite C** : nommer ne justifie pas de réécrire la population. B donne un nom qui **dure** à qui est cliqué, sans toucher aux règles.

## 4. Pièges déjà vus qu'il faudra regarder

- **Une migration qui retire un défaut de colonne** n'échoue qu'en CI (`'[]'` sur du JSON) : conserver les défauts, rejouer la base de test (`--env=test`), puis la suite complète — règle de `tests.md`.
- **`SuppressionDeCompte`** : un foyer appartient à sa ville, donc à sa partie : la cascade suffit, **mais** si un nom devait survivre aux parties (via `Lignee`), ce serait une autre décision (§ 5).
- **Les saves existantes** : des foyers vides ne doivent jamais rendre les comptes faux ; le test de migration part d'une ville ancienne.
- **Le plafond de 40 silhouettes** reste ce qui borne le film : persister les foyers ne change pas ce qu'on **montre**.
- **La confidentialité** : un prénom saisi par le joueur est une donnée libre ; le doc `rgpd-conformite` n'y voit rien (jeu fictif), mais **le champ est borné** (lettres, 2 à 24 signes) comme pour les chefs.

## 5. Questions ouvertes

| Question | Penchant |
|---|---|
| **Un nom donné se reprend-il ?** (aujourd'hui : non, pour les chefs et Medjaÿ) | **Non** pour les désignés ; pour un foyer, **oui une fois** (« renommer la maison ») — un nom qu'on n'aime pas ne doit pas rester |
| **Les membres d'un foyer ont-ils des liens (époux, parent, enfant) ?** | **Non en H3** : *« Hori, Meryt, 2 enfants »*, sans inventer de parenté que le jeu ne tient pas |
| **Un enfant qui devient actif garde-t-il son prénom ?** | **Oui, en C seulement.** En B, les prénoms sont par âge : au passage de l'enfant à l'actif, un prénom d'enfant **s'efface** — c'est dit, et c'est la limite assumée de B |
| **Les noms survivent-ils à la partie (via `Lignee`) ?** | **Non** : un foyer meurt avec sa ville ; seule la relation avec le monde survit (décision du 2026-11-16) |
| **Nomme-t-on les travailleurs d'un poste ?** | **Non, en B** : ils n'ont pas de foyer propre — ils viennent des foyers ; le poste dit « 3 travailleurs », la fiche d'une silhouette de poste reste représentative |
| **Le joueur peut-il tout nommer, ou le jeu nomme-t-il d'office ?** | **Le jeu nomme les chefs de foyer d'office** (H2), **le joueur nomme les membres à la demande** (H3) : on ne demande pas au joueur de saisir soixante prénoms |
