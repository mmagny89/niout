<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Levée quand une expédition ne peut pas partir : case déjà reconnue, déjà
 * visée par une autre, ou moyens insuffisants.
 *
 * Le message est destiné au joueur, il doit rester lisible.
 */
final class ExplorationImpossible extends \RuntimeException
{
}
