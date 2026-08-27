<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Family;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Family::class)]
final class FamilyTest extends TestCase
{
    public function testUneFamilleDemarreInconnue(): void
    {
        $famille = new Family(Family::NOM_PAR_DEFAUT);

        self::assertSame(Family::RENOMMEE_MIN, $famille->getRenommee());
        self::assertSame('Inconnue', $famille->palierDeRenommee());
    }

    public function testLaRenommeeNeDescendJamaisSousZero(): void
    {
        $famille = new Family('Nakht');

        $famille->ajusterRenommee(-50);

        self::assertSame(Family::RENOMMEE_MIN, $famille->getRenommee());
    }

    public function testLaRenommeeNeDepasseJamaisCent(): void
    {
        $famille = new Family('Nakht');

        $famille->ajusterRenommee(500);

        self::assertSame(Family::RENOMMEE_MAX, $famille->getRenommee());
    }

    #[DataProvider('paliersDeRenommee')]
    public function testLesPaliersSuiventLEchelleDuDocument(int $renommee, string $palierAttendu): void
    {
        $famille = new Family('Nakht');

        $famille->ajusterRenommee($renommee);

        self::assertSame($palierAttendu, $famille->palierDeRenommee());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function paliersDeRenommee(): iterable
    {
        yield 'plancher inconnue' => [0, 'Inconnue'];
        yield 'haut de inconnue' => [19, 'Inconnue'];
        yield 'bas de modeste' => [20, 'Modeste'];
        yield 'bas de reconnue' => [40, 'Reconnue'];
        yield 'bas de respectée' => [60, 'Respectée'];
        yield 'bas de illustre' => [80, 'Illustre'];
        yield 'plafond illustre' => [100, 'Illustre'];
    }
}
