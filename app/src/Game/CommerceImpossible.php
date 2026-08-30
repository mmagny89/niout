<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche d'ouvrir une route. Le message est destiné au joueur : il dit
 * ce qui bloque et ce qui le débloquerait.
 */
final class CommerceImpossible extends \RuntimeException
{
}
