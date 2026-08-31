<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Où en est une enquête.
 *
 * **Échouée n'est définitif que pour une secondaire** (décision de la
 * joueuse) : une principale porte le fil rouge d'une mission et se rejoue
 * jusqu'à être résolue.
 */
enum StatutDEnquete: string
{
    case EnCours = 'en_cours';
    case Resolue = 'resolue';
    case Echouee = 'echouee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Resolue => 'Résolue',
            self::Echouee => 'Abandonnée',
        };
    }
}
