<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\Building;
use App\Entity\City;

final class OrdreDesBatiments
{
    /**
     * Bâtiments dressés, dans un ordre stable et lisible plutôt que celui,
     * arbitraire, de leur insertion en base.
     *
     * @return list<Building>
     */
    public static function parLibelle(City $ville): array
    {
        $batiments = array_values($ville->getBatiments()->toArray());
        usort(
            $batiments,
            static fn (Building $a, Building $b): int => $a->getType()->libelle() <=> $b->getType()->libelle(),
        );

        return $batiments;
    }
}
