<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une inscription ne peut pas être soumise. Le message est destiné au joueur.
 */
final class DechiffrageImpossible extends \RuntimeException
{
}
