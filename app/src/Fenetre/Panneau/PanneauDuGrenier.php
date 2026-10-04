<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\TypeDeBatiment;
use App\Game\VueDeLaReserve;

/**
 * Le panneau du Grenier : les vivres, et ce qui les produit.
 */
final readonly class PanneauDuGrenier implements FournisseurDePanneau
{
    public function __construct(private DirectionDesBatiments $direction)
    {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Grenier->value;
    }

    public function donnees(GameSave $partie): array
    {
        return [
            'reserveDesVivres' => VueDeLaReserve::pour($partie->getVille(), vivres: true),
            'exploitations' => ExploitationsParGouvernant::pour($partie),
        ] + $this->direction->donnees($partie);
    }
}
