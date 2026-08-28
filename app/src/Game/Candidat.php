<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un candidat qui se présente à une offre d'emploi (doc 03).
 *
 * Jamais persisté : une candidature n'existe que le temps du choix. Ce qui
 * survit, c'est le foyer qui s'installe et le poste qu'il occupe (lot 4.3).
 *
 * **Chiffré en interne, qualitatif à l'affichage.** Le moteur manipule une
 * compétence de 20 à 100 ; le joueur ne doit voir que des étoiles et des
 * libellés. `competence` reste accessible parce que les calculs de production
 * en ont besoin (lot 4.8) — mais **aucun gabarit ne doit l'imprimer**, sous
 * peine de rendre au joueur une précision que le document lui refuse
 * délibérément. Pour l'affichage : `etoiles()` et `esperanceDeService()`.
 *
 * Deux exceptions assumées, toutes deux dans le document : le **salaire**,
 * déjà qualitatif par nature — un prix se lit tel quel —, et la **composition
 * du foyer**, qui décide de ce que le candidat coûtera à nourrir et de quand
 * ses enfants deviendront des bras.
 */
final readonly class Candidat
{
    /**
     * @param list<TraitDeCandidat> $traits         un ou deux, parfois aucun
     * @param list<int>             $agesDesEnfants en quinzaines
     */
    public function __construct(
        public int $competence,
        public int $salaire,
        public int $ancienneteProbable,
        public array $traits,
        public ?SpecialiteDeChef $specialite,
        public int $adultes,
        public array $agesDesEnfants,
    ) {
    }

    /**
     * Le barème du doc 03 : 20-36 ★, 37-52 ★★, 53-68 ★★★, 69-84 ★★★★,
     * 85-100 ★★★★★.
     */
    public function etoiles(): int
    {
        return match (true) {
            $this->competence <= 36 => 1,
            $this->competence <= 52 => 2,
            $this->competence <= 68 => 3,
            $this->competence <= 84 => 4,
            default => 5,
        };
    }

    /**
     * L'ancienneté, traduite en libellé (doc 03) : le joueur doit sentir s'il
     * embauche pour longtemps, sans lire un nombre de quinzaines.
     */
    public function esperanceDeService(): string
    {
        return match (true) {
            $this->ancienneteProbable >= 24 => 'Devrait rester longtemps',
            $this->ancienneteProbable >= 17 => 'Devrait tenir son poste',
            default => 'Risque de partir bientôt',
        };
    }

    public function personnesDuFoyer(): int
    {
        return $this->adultes + \count($this->agesDesEnfants);
    }

    /**
     * Ce que ce foyer mangera, en demi-rations — deux par adulte, une par
     * enfant. Le joueur compare ainsi le coût réel de deux candidats à salaire
     * égal.
     */
    public function demiRationsDuFoyer(): int
    {
        return $this->adultes * 2 + \count($this->agesDesEnfants);
    }

    /**
     * Les âges des enfants en années révolues, de l'aîné au plus jeune —
     * l'ordre qui intéresse le joueur, puisque c'est l'aîné qui deviendra un
     * bras le premier.
     *
     * @return list<int>
     */
    public function agesDesEnfantsEnAnnees(): array
    {
        $annees = array_map(Population::enAnnees(...), $this->agesDesEnfants);
        rsort($annees);

        return $annees;
    }

    /**
     * Dans combien de quinzaines l'aîné entrera dans la vie active, ou null
     * s'il n'y a pas d'enfant. C'est le chiffre qui fait d'une famille
     * nombreuse un investissement plutôt qu'une charge.
     */
    public function prochainBras(): ?int
    {
        if ([] === $this->agesDesEnfants) {
            return null;
        }

        return Population::AGE_ADULTE_EN_QUINZAINES - max($this->agesDesEnfants);
    }
}
