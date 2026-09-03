<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche une succession familiale : aucune n'est ouverte, ou l'héritier
 * désigné ne se présente pas.
 */
final class SuccessionImpossible extends \RuntimeException
{
}
