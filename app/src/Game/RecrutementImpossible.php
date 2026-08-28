<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche de poster une offre, d'embaucher ou de renvoyer. Le message
 * est destiné au joueur : il dit ce qui bloque et ce qui le débloquerait.
 */
final class RecrutementImpossible extends \RuntimeException
{
}
