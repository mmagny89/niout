<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une fouille ou une déduction ne peut pas aboutir. Le message est destiné au
 * joueur.
 */
final class EnqueteImpossible extends \RuntimeException
{
}
