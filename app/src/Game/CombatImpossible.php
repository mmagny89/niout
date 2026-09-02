<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche d'engager une sortie : aucune case à prendre, aucun homme
 * valide, une case déjà libre.
 */
final class CombatImpossible extends \RuntimeException
{
}
