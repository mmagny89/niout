<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une mission qu'on n'a pas encore ouverte. Le message est destiné au joueur.
 */
final class MissionFermee extends \RuntimeException
{
}
