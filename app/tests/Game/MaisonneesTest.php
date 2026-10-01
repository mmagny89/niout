<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\City;
use App\Game\Maisonnees;
use App\Game\Population;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Maisonnees::class)]
final class MaisonneesTest extends TestCase
{
    public function testLaRepartitionRespecteLesInvariantsDuJeu(): void
    {
        $ville = $this->ville(actifs: 11, enfants: 6, anciens: 3);

        $maisons = Maisonnees::repartir($ville);

        self::assertCount(Population::foyersPour(20), $maisons);

        foreach ($maisons as $maison) {
            self::assertLessThanOrEqual(Population::PERSONNES_PAR_FOYER, \count($maison));
            self::assertNotSame([], $maison, 'Une maison occupée abrite quelqu\'un.');
        }

        $tous = array_merge(...$maisons);
        self::assertCount(20, $tous);
        self::assertSame(11, \count(array_filter($tous, static fn (string $h): bool => Maisonnees::ACTIF === $h)));
        self::assertSame(6, \count(array_filter($tous, static fn (string $h): bool => Maisonnees::ENFANT === $h)));
        self::assertSame(3, \count(array_filter($tous, static fn (string $h): bool => Maisonnees::ANCIEN === $h)));
    }

    public function testLesAgesSeMelentEtLesTaillesRestentEquilibrees(): void
    {
        $maisons = Maisonnees::repartir($this->ville(actifs: 8, enfants: 4, anciens: 4));

        $tailles = array_map('count', $maisons);

        self::assertGreaterThan(0, \count($tailles));
        self::assertLessThanOrEqual(1, max([...$tailles, 0]) - min([...$tailles, PHP_INT_MAX]));
        self::assertGreaterThan(1, \count(array_unique(array_merge(...$maisons))), 'Les âges se mêlent.');
    }

    public function testLaRepartitionNeChangePasEntreDeuxAppels(): void
    {
        $ville = $this->ville(actifs: 9, enfants: 7, anciens: 2);

        self::assertSame(Maisonnees::repartir($ville), Maisonnees::repartir($ville));
    }

    public function testUneVilleVideNAPasDeMaison(): void
    {
        self::assertSame([], Maisonnees::repartir($this->ville(actifs: 0, enfants: 0, anciens: 0)));
    }

    public function testUneMaisonSeDecritEnToutesLettres(): void
    {
        self::assertSame(
            '2 adultes qui travaillent, 1 enfant, 1 ancien',
            Maisonnees::decrire([Maisonnees::ACTIF, Maisonnees::ACTIF, Maisonnees::ENFANT, Maisonnees::ANCIEN]),
        );
    }

    private function ville(int $actifs, int $enfants, int $anciens): City
    {
        $ville = $this->createStub(City::class);
        $ville->method('getActifs')->willReturn($actifs);
        $ville->method('getEnfants')->willReturn($enfants);
        $ville->method('getAnciens')->willReturn($anciens);
        $ville->method('population')->willReturn($actifs + $enfants + $anciens);
        $ville->method('malades')->willReturn(0);

        return $ville;
    }
}
