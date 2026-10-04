<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Commerce;
use App\Game\TypeDeBatiment;

/**
 * Le panneau du Port : les routes ouvertes, leurs étals, et les pêcheries.
 */
final readonly class PanneauDuPort implements FournisseurDePanneau
{
    public function __construct(
        private Commerce $commerce,
        private EtalsDesRoutes $etals,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Port->value;
    }

    public function donnees(GameSave $partie): array
    {
        return [
            'routes' => $this->commerce->offrePour($partie),
            'etals' => $this->etals->pour($partie),
            'exploitations' => ExploitationsParGouvernant::pour($partie),
        ] + $this->direction->donnees($partie);
    }
}
