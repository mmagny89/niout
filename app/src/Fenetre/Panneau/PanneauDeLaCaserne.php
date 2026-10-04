<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Medjays;
use App\Game\TypeDeBatiment;

/**
 * Le panneau de la Caserne : la troupe, ce qu'elle coûte, et ce qui empêche d'en
 * lever un de plus — dit avant la tentative (lot 10.2).
 */
final readonly class PanneauDeLaCaserne implements FournisseurDePanneau
{
    public function __construct(
        private Medjays $medjays,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Caserne->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            'medjays' => $ville->getMedjays(),
            'effectifMaximum' => $this->medjays->effectifMaximum($ville),
            'entretienDesMedjays' => $this->medjays->entretienParQuinzaine($ville),
            'offreDeLaCaserne' => $this->medjays->offreDeLaCaserne($partie),
        ] + $this->direction->donnees($partie);
    }
}
