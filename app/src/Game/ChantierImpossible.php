<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Levée quand un chantier ne peut pas démarrer : moyens insuffisants, plafond
 * atteint, dépendance manquante, ou travaux déjà en cours sur ce bâtiment.
 *
 * Le message est destiné au joueur, il doit rester lisible.
 */
final class ChantierImpossible extends \RuntimeException
{
}
