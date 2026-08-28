<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\Foyer;
use Random\Randomizer;

/**
 * Tire une maisonnée au sort (doc 02, calibrage de la joueuse).
 *
 * Comme pour la carte, le hasard passe par un `Randomizer` injecté : semé en
 * test, il rend la génération reproductible sans rien changer au code de
 * production.
 */
final readonly class GenerateurDeFoyer
{
    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Deux adultes, et de zéro à six enfants d'âges quelconques.
     *
     * Les âges sont tirés en quinzaines sur toute l'enfance, et non par années
     * entières : un enfant peut donc arriver à quelques quinzaines de sa
     * majorité comme au berceau. C'est ce qui fait de l'âge un critère
     * d'embauche — une famille de grands enfants donne des bras dans l'année,
     * une nichée en bas âge est un investissement de plusieurs règnes.
     */
    public function pour(City $ville): Foyer
    {
        ['adultes' => $adultes, 'agesDesEnfants' => $ages] = $this->composer();

        return new Foyer($ville, $adultes, $ages);
    }

    /**
     * La composition seule, sans ville à laquelle la rattacher : un candidat
     * annonce le foyer qu'il amènerait, mais celui-ci ne s'installe qu'à
     * l'embauche (lot 4.3).
     *
     * @return array{adultes: int, agesDesEnfants: list<int>}
     */
    public function composer(): array
    {
        $enfants = $this->hasard->getInt(0, Population::ENFANTS_MAX_PAR_FOYER);
        $ages = [];

        for ($i = 0; $i < $enfants; ++$i) {
            $ages[] = $this->hasard->getInt(0, Population::AGE_ADULTE_EN_QUINZAINES - 1);
        }

        return ['adultes' => Population::ADULTES_PAR_FOYER, 'agesDesEnfants' => $ages];
    }
}
