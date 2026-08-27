<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DotationRoyale;
use App\Game\FamilleDeMateriau;
use App\Game\GeographieDeRegion;
use App\Game\Ressource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotationRoyale::class)]
final class DotationRoyaleTest extends TestCase
{
    #[DataProvider('dotationsAttendues')]
    public function testLOrSuitLaFormuleDuDocument(int $difficulte, int $orAttendu): void
    {
        $dotation = DotationRoyale::pour($difficulte, self::delta());

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
        // Seul l'or suit la difficulté (doc 13) : les matériaux, eux, sont
        // calibrés sur le premier bâtiment, le même partout.
        $clemente = self::sansLOr(DotationRoyale::pour(0, self::delta())->enRessources());
        $rude = self::sansLOr(DotationRoyale::pour(9, self::delta())->enRessources());

        self::assertSame($clemente, $rude);
    }

    public function testLaDotationCouvreUnPremierBatiment(): void
    {
        $dotation = DotationRoyale::pour(0, self::delta());
        $recu = $dotation->enRessources();

        // L'Entrepôt coûte 20 bois, 10 pierre et 15 or au niveau 1 (doc 01) :
        // c'est exactement la dotation en matériaux proposée par le doc 13, qui
        // est donc calibrée sur ce bâtiment précis.
        self::assertGreaterThanOrEqual(15, $recu[Ressource::Or->value]);
        self::assertGreaterThanOrEqual(20, self::total($recu, FamilleDeMateriau::Bois));
        self::assertGreaterThanOrEqual(10, self::total($recu, FamilleDeMateriau::Pierre));
    }

    /**
     * Le pharaon envoie ce que la région travaille : des roseaux et de l'argile
     * dans le Delta, pas un cèdre venu de Byblos.
     */
    public function testLaDotationPuiseDansLesMateriauxDeLaRegion(): void
    {
        $recu = DotationRoyale::pour(0, self::delta())->enRessources();

        self::assertArrayHasKey(Ressource::Roseaux->value, $recu);
        self::assertArrayHasKey(Ressource::Argile->value, $recu);
        self::assertArrayNotHasKey(Ressource::BoisDeCedre->value, $recu);
    }

    /**
     * La Basse-Nubie ne porte que du granite : rien pour couvrir un toit. La
     * couronne complète, sans quoi la région serait imbâtissable.
     */
    public function testLaCouronneComplèteCeQueLaRegionNaPas(): void
    {
        $recu = DotationRoyale::pour(6, new GeographieDeRegion(nil: true, ressourcesDeZone: [Ressource::Granite]))->enRessources();

        self::assertArrayHasKey(Ressource::Granite->value, $recu, 'La pierre locale reste préférée.');
        self::assertArrayHasKey(Ressource::BoisDeCedre->value, $recu, 'Le bois, lui, doit venir de loin.');
    }

    /**
     * Sans vivres, aucune expédition ne peut partir (doc 04) — et sans
     * expédition, le joueur ne trouverait jamais la terre où semer.
     */
    public function testLaDotationPermetDePartirEnReconnaissance(): void
    {
        $recu = DotationRoyale::pour(0, self::delta())->enRessources();

        self::assertGreaterThan(0, $recu[Ressource::Ble->value]);
    }

    private static function delta(): GeographieDeRegion
    {
        return new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );
    }

    /**
     * @param array<string, int> $recu
     *
     * @return array<string, int>
     */
    private static function sansLOr(array $recu): array
    {
        unset($recu[Ressource::Or->value]);

        return $recu;
    }

    /**
     * @param array<string, int> $recu
     */
    private static function total(array $recu, FamilleDeMateriau $famille): int
    {
        $total = 0;

        foreach ($famille->ressources() as $ressource) {
            $total += $recu[$ressource->value] ?? 0;
        }

        return $total;
    }
}
