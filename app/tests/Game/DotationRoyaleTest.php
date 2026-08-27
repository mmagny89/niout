<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DotationRoyale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotationRoyale::class)]
final class DotationRoyaleTest extends TestCase
{
    #[DataProvider('dotationsAttendues')]
    public function testLOrSuitLaFormuleDuDocument(int $difficulte, int $orAttendu): void
    {
        $dotation = DotationRoyale::pourDifficulte($difficulte);

        self::assertSame($orAttendu, $dotation->or);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function dotationsAttendues(): iterable
    {
        // 50 + 10 × difficulté (doc 13).
        yield 'Delta, la plus clémente' => [0, 50];
        yield 'difficulté moyenne' => [5, 100];
        yield 'Sinaï, la plus rude' => [9, 140];
    }

    public function testLesMateriauxNeDependentPasDeLaDifficulte(): void
    {
        $clemente = DotationRoyale::pourDifficulte(0);
        $rude = DotationRoyale::pourDifficulte(9);

        self::assertSame($clemente->bois, $rude->bois);
        self::assertSame($clemente->pierre, $rude->pierre);
    }

    public function testLaDotationCouvreUnPremierBatiment(): void
    {
        $dotation = DotationRoyale::pourDifficulte(0);

        // L'Entrepôt coûte 20 bois, 10 pierre et 15 or au niveau 1 (doc 01) :
        // c'est exactement la dotation en matériaux proposée par le doc 13, qui
        // est donc calibrée sur ce bâtiment précis.
        self::assertGreaterThanOrEqual(15, $dotation->or);
        self::assertGreaterThanOrEqual(20, $dotation->bois);
        self::assertGreaterThanOrEqual(10, $dotation->pierre);
    }
}
