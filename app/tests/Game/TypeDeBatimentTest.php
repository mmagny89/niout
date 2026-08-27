<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\CoutDeConstruction;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeDeBatiment::class)]
#[CoversClass(CoutDeConstruction::class)]
final class TypeDeBatimentTest extends TestCase
{
    public function testLaVilleCompteDouzeBatiments(): void
    {
        self::assertCount(12, TypeDeBatiment::cases());
    }

    public function testLaResidenceFamilialeEstOfferteEtHorsCatalogue(): void
    {
        self::assertTrue(TypeDeBatiment::ResidenceFamiliale->coutDeBase()->estGratuit());
        self::assertNotContains(TypeDeBatiment::ResidenceFamiliale, TypeDeBatiment::constructibles());
        self::assertCount(11, TypeDeBatiment::constructibles());
    }

    public function testSeulLePortDependDeLaGeographie(): void
    {
        $dependants = array_filter(
            TypeDeBatiment::cases(),
            static fn (TypeDeBatiment $t): bool => $t->exigeUnPointDEau(),
        );

        self::assertSame([TypeDeBatiment::Port], array_values($dependants));
    }

    public function testChaqueBatimentConstructibleACoute(): void
    {
        foreach (TypeDeBatiment::constructibles() as $type) {
            self::assertFalse(
                $type->coutDeBase()->estGratuit(),
                \sprintf('Le %s devrait avoir un coût.', $type->libelle()),
            );
        }
    }

    public function testLeCoutSuitLaProgressionDuDocument(): void
    {
        // Grenier : 15 bois, 15 pierre, 15 or au niveau 1 (doc 01).
        $base = TypeDeBatiment::Grenier->coutDeBase();
        self::assertSame(15, $base->bois);

        // coutNiveau(N) = coutBase × (1 + (N - 1) × 0,4)
        // Niveau 2 : 15 × 1,4 = 21
        self::assertSame(21, $base->pourNiveau(2)->bois);
        // Niveau 3 : 15 × 1,8 = 27
        self::assertSame(27, $base->pourNiveau(3)->bois);
    }

    public function testLeCoutDuPremierNiveauEstLeCoutDeBase(): void
    {
        foreach (TypeDeBatiment::constructibles() as $type) {
            $base = $type->coutDeBase();
            self::assertEquals($base, $base->pourNiveau(1));
        }
    }

    public function testSeulLeTempleReclameDuLin(): void
    {
        $avecLin = array_filter(
            TypeDeBatiment::cases(),
            static fn (TypeDeBatiment $t): bool => $t->coutDeBase()->lin > 0,
        );

        self::assertSame([TypeDeBatiment::Temple], array_values($avecLin));
    }

    public function testLesPlafondsRestentDansLaFourchetteDuDocument(): void
    {
        foreach (TypeDeBatiment::cases() as $type) {
            self::assertGreaterThanOrEqual(5, $type->niveauMax());
            self::assertLessThanOrEqual(10, $type->niveauMax());
        }
    }

    public function testLesBatimentsDePierreOntLesChantiersLesPlusLongs(): void
    {
        // Le Temple et le Port sont les seuls à 3 cycles de base : pierre et
        // infrastructure lourde, contre une quinzaine de séchage pour la brique.
        self::assertSame(3, TypeDeBatiment::Temple->dureeDeBase());
        self::assertSame(3, TypeDeBatiment::Port->dureeDeBase());
        self::assertSame(1, TypeDeBatiment::Grenier->dureeDeBase());
    }

    public function testLeDetailDuCoutOmetLesRessourcesNulles(): void
    {
        $detail = TypeDeBatiment::Grenier->coutDeBase()->detail();

        self::assertArrayHasKey('bois', $detail);
        self::assertArrayNotHasKey('lin', $detail, 'Le Grenier ne réclame pas de lin.');
    }
}
