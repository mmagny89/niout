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
 * Deux exceptions assumées : le **salaire**, déjà qualitatif par nature — un
 * prix se lit tel quel —, et la **maisonnée** qu'il amène, qui décide de ce
 * qu'il coûtera à nourrir et des bras qu'il apporte.
 */
final readonly class Candidat
{
    /**
     * @param list<TraitDeCandidat> $traits un ou deux, parfois aucun
     */
    public function __construct(
        public int $competence,
        public int $salaire,
        public int $ancienneteProbable,
        public array $traits,
        public ?SpecialiteDeChef $specialite,
        public int $actifsAmenes,
        public int $inactifsAmenes,
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

    /**
     * Les habitants que ce candidat installerait — lui, les siens, ceux qui
     * travaillent comme ceux qui sont à charge.
     */
    public function personnesAmenees(): int
    {
        return $this->actifsAmenes + $this->inactifsAmenes;
    }

    /**
     * Ce que sa maisonnée mangera, en demi-rations : deux par actif, une par
     * inactif. C'est ce qui permet au joueur de comparer le coût réel de deux
     * candidats au même salaire.
     */
    public function demiRationsAmenees(): int
    {
        return $this->actifsAmenes * 2 + $this->inactifsAmenes;
    }
}
