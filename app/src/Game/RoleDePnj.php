<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'une personne fait dans le monde (`docs/plan-pnj-palier-b.md` § 5). **Un rôle, une vie** : une
 * personne ne change pas de rôle. Les chefs voyageurs, les chefs de bande et les courtisans viendront
 * avec leurs lots ; pour l'instant, le seul rôle écrit est celui du rival.
 */
enum RoleDePnj: string
{
    case Rival = 'rival';

    public function libelle(): string
    {
        return match ($this) {
            self::Rival => 'Marchand rival',
        };
    }
}
