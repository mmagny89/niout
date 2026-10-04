<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\TypeDeBatiment;

/**
 * Le panneau du Temple : le panthéon, ses paliers, ce qu'il est possible
 * d'offrir, et les énigmes qu'on y entend.
 */
final readonly class PanneauDuTemple implements FournisseurDePanneau
{
    public function __construct(
        private PantheonDuTemple $pantheon,
        private EnigmesDuLieu $enigmes,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Temple->value;
    }

    public function donnees(GameSave $partie): array
    {
        return [
            'enigmesDuTemple' => $this->enigmes->pour($partie, TypeDeBatiment::Temple),
        ] + $this->pantheon->donnees($partie) + $this->direction->donnees($partie);
    }
}
