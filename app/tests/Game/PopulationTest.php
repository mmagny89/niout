<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Game\Population;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Population::class)]
final class PopulationTest extends TestCase
{
    public function testUneVilleSansQuartierPorteLaFamilleFondatrice(): void
    {
        $ville = new City('Avaris', 0, 3);

        self::assertSame(Population::HABITANTS_DE_BASE, Population::pour($ville));
    }

    public function testLeQuartierDHabitationAugmenteLaPopulation(): void
    {
        $ville = new City('Avaris', 0, 3);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, niveau: 2));

        self::assertGreaterThan(Population::pour(new City('Autre', 0, 3)), Population::pour($ville));
    }

    public function testLaConsommationSuitLaPopulation(): void
    {
        $ville = new City('Avaris', 0, 3);

        self::assertSame(
            Population::pour($ville) * Population::RATION_PAR_HABITANT,
            Population::consommationParQuinzaine($ville),
        );
    }
}
