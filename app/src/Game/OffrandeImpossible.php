<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une offrande ne peut pas être portée au Temple. Le message est destiné au
 * joueur.
 */
final class OffrandeImpossible extends \RuntimeException
{
}
