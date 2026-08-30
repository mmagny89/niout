<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche de lancer un ordre de fabrication. Le message est destiné au
 * joueur : il dit ce qui bloque et ce qui le débloquerait.
 */
final class FabricationImpossible extends \RuntimeException
{
}
