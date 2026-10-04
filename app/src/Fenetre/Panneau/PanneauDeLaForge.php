<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

/**
 * Le panneau de la Forge.
 */
final readonly class PanneauDeLaForge extends PanneauDeFabrication
{
    public static function cle(): string
    {
        return 'forge';
    }
}
