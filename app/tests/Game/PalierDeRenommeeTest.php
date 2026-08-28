<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\PalierDeRenommee;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PalierDeRenommee::class)]
final class PalierDeRenommeeTest extends TestCase
{
    #[DataProvider('paliersDuDocument')]
    public function testLesPlagesSuiventLeDocument(int $renommee, PalierDeRenommee $attendu): void
    {
        self::assertSame($attendu, PalierDeRenommee::pour($renommee));
    }

    /**
     * @return iterable<string, array{int, PalierDeRenommee}>
     */
    public static function paliersDuDocument(): iterable
    {
        yield 'au départ' => [0, PalierDeRenommee::Inconnue];
        yield 'juste avant Modeste' => [19, PalierDeRenommee::Inconnue];
        yield 'Modeste' => [20, PalierDeRenommee::Modeste];
        yield 'Reconnue' => [40, PalierDeRenommee::Reconnue];
        yield 'Respectée' => [60, PalierDeRenommee::Respectee];
        yield 'Illustre' => [80, PalierDeRenommee::Illustre];
        yield 'au plafond' => [100, PalierDeRenommee::Illustre];
    }

    /**
     * L'invariant qui donne son sens à la renommée : se faire un nom doit
     * toujours rendre l'accueil moins cher, jamais l'inverse.
     */
    public function testLAppelCouteDeMoinsEnMoinsCher(): void
    {
        $precedent = \PHP_INT_MAX;

        foreach (PalierDeRenommee::cases() as $palier) {
            self::assertLessThan($precedent, $palier->coutDAppel());
            $precedent = $palier->coutDAppel();
        }
    }

    /**
     * Le doc 13 réserve la migration spontanée aux deux derniers paliers, et
     * la veut « abondante » au dernier.
     */
    public function testLaMigrationSpontaneeNeCommenceQuAPartirDeRespectee(): void
    {
        self::assertSame(0, PalierDeRenommee::Inconnue->chanceDeMigrationSpontanee());
        self::assertSame(0, PalierDeRenommee::Modeste->chanceDeMigrationSpontanee());
        self::assertSame(0, PalierDeRenommee::Reconnue->chanceDeMigrationSpontanee());

        self::assertGreaterThan(0, PalierDeRenommee::Respectee->chanceDeMigrationSpontanee());
        self::assertGreaterThan(
            PalierDeRenommee::Respectee->chanceDeMigrationSpontanee(),
            PalierDeRenommee::Illustre->chanceDeMigrationSpontanee(),
        );
    }
}
