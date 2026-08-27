<?php

declare(strict_types=1);

namespace App\Game;

use Random\Randomizer;

/**
 * Retire la crue d'une année (doc 05) : faible 20 %, normale 60 %, forte 20 %.
 *
 * Le hasard passe par un `Randomizer` injecté, comme pour la génération de
 * carte : semé en test, il rend le tirage reproductible sans que le code de
 * production ait à connaître la différence.
 */
final readonly class TirageDeLaCrue
{
    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    public function tirer(): QualiteDeCrue
    {
        $poids = QualiteDeCrue::poids();
        $tirage = $this->hasard->getInt(1, array_sum($poids));

        foreach ($poids as $valeur => $part) {
            $tirage -= $part;

            if ($tirage <= 0) {
                return QualiteDeCrue::from($valeur);
            }
        }

        return QualiteDeCrue::Normale;
    }
}
