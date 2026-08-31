<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une énigme ne peut pas être tentée. Le message est destiné au joueur.
 */
final class EnigmeImpossible extends \RuntimeException
{
}
