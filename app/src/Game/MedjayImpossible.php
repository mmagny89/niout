<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche de lever un Medjaÿ : pas de Caserne, niveau insuffisant,
 * effectif complet, bourse vide.
 */
final class MedjayImpossible extends \RuntimeException
{
}
