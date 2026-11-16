<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'une personne fait dans le monde (`docs/plan-pnj-palier-b.md` § 5). **Un rôle, une vie** : une
 * personne ne change pas de rôle. Les chefs voyageurs, les chefs de bande et les courtisans viendront
 * avec leurs lots ; pour l'instant, les seuls rôles écrits sont celui du rival et celui du chef qui a quitté la ville.
 */
enum RoleDePnj: string
{
    case Rival = 'rival';
    case ChefVoyageur = 'chef-voyageur';
    case ChefDeBande = 'chef-de-bande';
    case Courtisan = 'courtisan';

    public function libelle(): string
    {
        return match ($this) {
            self::Rival => 'Marchand rival',
            self::ChefVoyageur => 'Ancien chef de la ville',
            self::ChefDeBande => 'Chef de bande',
            self::Courtisan => 'Courtisan du palais',
        };
    }
}
