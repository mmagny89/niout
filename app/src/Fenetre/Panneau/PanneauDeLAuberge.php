<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\TypeDeBatiment;

/**
 * Le panneau de l'Auberge : sa direction et les énigmes qu'on y entend.
 */
final readonly class PanneauDeLAuberge implements FournisseurDePanneau
{
    public function __construct(
        private DirectionDesBatiments $direction,
        private EnigmesDuLieu $enigmes,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Auberge->value;
    }

    public function donnees(GameSave $partie): array
    {
        return [
            'enigmesDeLAuberge' => $this->enigmes->pour($partie, TypeDeBatiment::Auberge),
        ] + $this->direction->donnees($partie);
    }
}
