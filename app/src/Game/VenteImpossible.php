<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une vente au Marché ne peut pas aboutir. Le message est destiné au joueur.
 */
final class VenteImpossible extends \RuntimeException
{
}
