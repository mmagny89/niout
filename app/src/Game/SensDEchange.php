<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le sens d'un ordre commercial, du point de vue de la ville.
 */
enum SensDEchange: string
{
    case Vendre = 'vendre';
    case Acheter = 'acheter';

    public function libelle(): string
    {
        return match ($this) {
            self::Vendre => 'Vendre',
            self::Acheter => 'Acheter',
        };
    }
}
