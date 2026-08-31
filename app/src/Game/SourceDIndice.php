<?php

declare(strict_types=1);

namespace App\Game;

/**
 * D'où vient un indice (doc 04, doc 10) : le terrain qu'un éclaireur fouille,
 * ou la parole qu'un émissaire recueille.
 */
enum SourceDIndice: string
{
    case Terrain = 'terrain';
    case Temoignage = 'temoignage';

    public function libelle(): string
    {
        return match ($this) {
            self::Terrain => 'Trouvé sur le terrain',
            self::Temoignage => 'Recueilli de vive voix',
        };
    }
}
