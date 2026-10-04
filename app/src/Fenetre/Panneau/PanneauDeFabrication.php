<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;

/**
 * Le panneau d'un bâtiment qui fabrique : l'Atelier et la Forge partagent tout,
 * un seul gabarit les rend, une seule préparation les sert.
 */
abstract readonly class PanneauDeFabrication implements FournisseurDePanneau
{
    public function __construct(
        private AteliersDeLaVille $ateliers,
        private DirectionDesBatiments $direction,
    ) {
    }

    public function donnees(GameSave $partie): array
    {
        return [
            'ateliers' => $this->ateliers->pour($partie),
        ] + $this->direction->donnees($partie);
    }
}
