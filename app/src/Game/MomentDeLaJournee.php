<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les quatre moments d'une journée du rejeu. La nuit se passe à la maison, sans
 * scène : on ne la compte pas (`docs/plan-pnj-ville-vivante.md` § 6.1).
 */
enum MomentDeLaJournee: int
{
    case Aube = 0;
    case Matin = 1;
    case ApresMidi = 2;
    case Soir = 3;

    public function libelle(): string
    {
        return match ($this) {
            self::Aube => 'À l\'aube',
            self::Matin => 'Le matin',
            self::ApresMidi => 'L\'après-midi',
            self::Soir => 'Le soir',
        };
    }

    /**
     * La forme courte, pour une liste : « matin, après-midi ».
     */
    public function court(): string
    {
        return match ($this) {
            self::Aube => 'aube',
            self::Matin => 'matin',
            self::ApresMidi => 'après-midi',
            self::Soir => 'soir',
        };
    }
}
