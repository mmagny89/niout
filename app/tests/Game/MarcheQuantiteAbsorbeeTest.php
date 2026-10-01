<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Effectifs;
use App\Game\Marche;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Marche::class)]
final class MarcheQuantiteAbsorbeeTest extends TestCase
{
    #[DataProvider('cas')]
    public function testLaQuantiteProposeeTientDansLeDeboucheEtEstLaPlusGrande(int $prix, int $enReserve, int $reste, int $coefficient): void
    {
        $quantite = Marche::quantiteQueLaPlaceAbsorbe($prix, $enReserve, $reste, $coefficient);

        self::assertLessThanOrEqual($enReserve, $quantite);

        if ($quantite > 0) {
            self::assertLessThanOrEqual($reste, intdiv($prix * $quantite * $coefficient, Effectifs::RENDEMENT_PLEIN));
        }

        // La suivante ne tiendrait plus — sauf si c'est la réserve qui borne.
        if ($quantite < $enReserve) {
            self::assertGreaterThan($reste, intdiv($prix * ($quantite + 1) * $coefficient, Effectifs::RENDEMENT_PLEIN));
        }
    }

    /**
     * @return iterable<string, array{int, int, int, int}>
     */
    public static function cas(): iterable
    {
        yield 'plein tarif' => [5, 100, 40, 100];
        yield 'prix élevé' => [19, 50, 40, 100];
        yield 'coefficient supérieur' => [8, 100, 40, 130];
        yield 'coefficient réduit' => [8, 100, 40, 60];
        yield 'la réserve borne' => [5, 3, 400, 100];
        yield 'un seul exemplaire passe' => [40, 10, 40, 100];
    }

    public function testRienNeSeVendQuandLaPlaceEstPleine(): void
    {
        self::assertSame(0, Marche::quantiteQueLaPlaceAbsorbe(5, 100, 0, 100));
    }
}
