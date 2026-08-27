<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Expedition;
use App\Game\Saison;
use App\Game\TypeDeTerrain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Expedition::class)]
final class ExpeditionTest extends TestCase
{
    public function testUnTrajetDureUnCycleParCase(): void
    {
        self::assertSame(3, Expedition::dureeDuTrajet(3, TypeDeTerrain::Fertile, Saison::Peret));
    }

    public function testUnTrajetNeDescendJamaisSousUnCycle(): void
    {
        self::assertSame(1, Expedition::dureeDuTrajet(0, TypeDeTerrain::Fertile, Saison::Peret));
    }

    #[DataProvider('trajetsHorsDuFleuve')]
    public function testLaSaisonNeChangeRienHorsDuFleuve(?Saison $saison): void
    {
        self::assertSame(
            4,
            Expedition::dureeDuTrajet(4, TypeDeTerrain::Desert, $saison),
            'Seuls les trajets fluviaux profitent ou pâtissent de la saison.',
        );
    }

    /**
     * @return iterable<string, array{?Saison}>
     */
    public static function trajetsHorsDuFleuve(): iterable
    {
        yield 'crue' => [Saison::Akhet];
        yield 'émergence' => [Saison::Peret];
        yield 'récolte' => [Saison::Chemou];
        yield 'jours épagomènes' => [null];
    }

    public function testLaCrueRaccourcitUnTrajetFluvial(): void
    {
        // Le Nil gonflé porte les barques : -30 % (doc 04, doc 05).
        self::assertSame(3, Expedition::dureeDuTrajet(4, TypeDeTerrain::Nil, Saison::Akhet));
    }

    public function testLEtiageAllongeUnTrajetFluvial(): void
    {
        // En Chémou, le fleuve est au plus bas : +30 %.
        self::assertSame(6, Expedition::dureeDuTrajet(4, TypeDeTerrain::Nil, Saison::Chemou));
    }

    public function testPeretLaisseLeTrajetFluvialInchange(): void
    {
        self::assertSame(4, Expedition::dureeDuTrajet(4, TypeDeTerrain::Nil, Saison::Peret));
    }

    public function testMemeAcceleréUnTrajetGardeAuMoinsUnCycle(): void
    {
        self::assertSame(1, Expedition::dureeDuTrajet(1, TypeDeTerrain::Nil, Saison::Akhet));
    }
}
