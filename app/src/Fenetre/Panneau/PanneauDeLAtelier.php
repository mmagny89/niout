<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

/**
 * Le panneau de l'Atelier.
 */
final readonly class PanneauDeLAtelier extends PanneauDeFabrication
{
    public static function cle(): string
    {
        return 'atelier';
    }
}
