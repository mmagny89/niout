<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Levée quand un joueur tente de lancer une partie de plus que le plafond
 * autorisé. Il doit d'abord en abandonner une — la suppression étant définitive.
 */
final class PlafondDePartiesAtteint extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(\sprintf(
            'Vous menez déjà %d parties. Abandonnez-en une pour en commencer une nouvelle.',
            GameSave::MAX_PAR_COMPTE,
        ));
    }
}
