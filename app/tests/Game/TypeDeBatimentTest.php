<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\CoutDeConstruction;
use App\Game\Ressource;
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
        // Grenier : 15 argile, 10 roseaux, 8 bois local à la fondation (doc 01).
        $base = TypeDeBatiment::Grenier->coutDeBase();
        self::assertSame(10, $base->quantiteDe(Ressource::Roseaux));

        // Les matériaux : coutFondation × (1 + (N - 1) × 0,4).
        self::assertSame(14, $base->pourNiveau(2)->quantiteDe(Ressource::Roseaux), '10 × 1,4');
        self::assertSame(18, $base->pourNiveau(3)->quantiteDe(Ressource::Roseaux), '10 × 1,8');
    }

    /**
     * **La fondation ne coûte pas de deben, l'amélioration si** (doc 01
     * révisé). La brique crue d'un premier niveau relevait de matériaux locaux
     * et d'une main-d'œuvre familiale ; le deben ne rétribuait qu'un
     * savoir-faire spécialisé, donc ce qui s'ajoute en montant de niveau.
     */
    public function testLaFondationNeCoutePasDeDeben(): void
    {
        foreach (TypeDeBatiment::constructibles() as $type) {
            if (\in_array($type, [TypeDeBatiment::Temple, TypeDeBatiment::Port], true)) {
                continue;
            }

            self::assertSame(
                0,
                $type->coutDeBase()->quantiteDe(Ressource::Deben),
                \sprintf('%s ne doit rien coûter en deben à la fondation.', $type->libelle()),
            );
        }
    }

    /**
     * Les deux exceptions du document : le Temple pour son rituel de dédicace,
     * le Port pour l'assemblage de ses pontons par des spécialistes.
     */
    public function testSeulsLeTempleEtLePortPaientDesLaFondation(): void
    {
        $payants = array_values(array_filter(
            TypeDeBatiment::constructibles(),
            static fn (TypeDeBatiment $t): bool => $t->coutDeBase()->quantiteDe(Ressource::Deben) > 0,
        ));

        self::assertSame([TypeDeBatiment::Temple, TypeDeBatiment::Port], $payants);
    }

    /**
     * Le deben suit sa propre loi — `debenFondation + debenParNiveau × (N-1)` —
     * et non celle des matériaux. Les deux ne croissent pas ensemble : les
     * matériaux avec la taille du bâtiment, le deben avec le savoir-faire
     * qu'on achète pour le raffiner.
     */
    public function testLeDebenCroitLineairementAvecLeNiveau(): void
    {
        $grenier = TypeDeBatiment::Grenier->coutDeBase();

        self::assertSame(0, $grenier->pourNiveau(1)->quantiteDe(Ressource::Deben));
        self::assertSame(8, $grenier->pourNiveau(2)->quantiteDe(Ressource::Deben));
        self::assertSame(16, $grenier->pourNiveau(3)->quantiteDe(Ressource::Deben));
    }

    /**
     * Le bois local remplace le « bois » générique du document : tous les
     * bâtiments en réclament, aucun ne demande de cèdre — celui-ci s'importe
     * du Levant et se réserve au prestige.
     */
    public function testChaqueBatimentReclameDuBoisLocalEtJamaisDuCedre(): void
    {
        foreach (TypeDeBatiment::constructibles() as $type) {
            self::assertGreaterThan(
                0,
                $type->coutDeBase()->quantiteDe(Ressource::BoisLocal),
                \sprintf('%s se charpente bien avec quelque chose.', $type->libelle()),
            );
            self::assertSame(0, $type->coutDeBase()->quantiteDe(Ressource::BoisDeCedre));
        }
    }

    public function testLeCoutDuPremierNiveauEstLeCoutDeBase(): void
    {
        foreach (TypeDeBatiment::constructibles() as $type) {
            $base = $type->coutDeBase();

            self::assertSame(
                $base->enRessources(),
                $base->pourNiveau(1)->enRessources(),
                $type->libelle(),
            );
        }
    }

    public function testSeulLeTempleReclameDuLin(): void
    {
        $avecLin = array_filter(
            TypeDeBatiment::cases(),
            static fn (TypeDeBatiment $t): bool => $t->coutDeBase()->quantiteDe(Ressource::Lin) > 0,
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

        self::assertArrayHasKey(Ressource::Roseaux->libelle(), $detail);
        self::assertArrayNotHasKey(Ressource::Lin->libelle(), $detail, 'Le Grenier ne réclame pas de lin.');
    }
}
