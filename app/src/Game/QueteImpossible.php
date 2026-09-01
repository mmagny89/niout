<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une quête de chantier ne peut pas être honorée. Le message est destiné au
 * joueur.
 */
final class QueteImpossible extends \RuntimeException
{
}
