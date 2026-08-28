<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Building;
use App\Entity\City;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Building::class)]
final class BuildingTest extends TestCase
{
    public function testUnBatimentNeufEstAuNiveauUn(): void
    {
        $batiment = new Building($this->ville(difficulte: 0), TypeDeBatiment::Grenier);

        self::assertSame(1, $batiment->getNiveau());
    }

    public function testLePlafondRegionalBrideUnBatimentAFortPotentiel(): void
    {
        // L'Entrepôt plafonne à 10, mais le Delta (difficulté 0) ne permet que 5.
        $batiment = new Building($this->ville(difficulte: 0), TypeDeBatiment::Entrepot);

        self::assertSame(5, $batiment->niveauMaxAtteignable());
    }

    public function testUneRegionDifficileLibereLePotentielDuBatiment(): void
    {
        // Difficulté 5 : plafond régional 10, l'Entrepôt peut enfin s'exprimer.
        $batiment = new Building($this->ville(difficulte: 5), TypeDeBatiment::Entrepot);

        self::assertSame(10, $batiment->niveauMaxAtteignable());
    }

    public function testLePlafondPropreAuBatimentPrimeQuandIlEstPlusBas(): void
    {
        // L'Auberge plafonne à 5, même dans la région la plus difficile.
        $batiment = new Building($this->ville(difficulte: 9), TypeDeBatiment::Auberge);

        self::assertSame(5, $batiment->niveauMaxAtteignable());
    }

    public function testUnBatimentAuMaximumNAPlusDeCoutDeMontee(): void
    {
        $batiment = new Building($this->ville(difficulte: 0), TypeDeBatiment::Grenier, niveau: 5);

        self::assertTrue($batiment->estAuMaximum());
        self::assertNull($batiment->coutDeLaMonteeDeNiveau());
    }

    public function testMonterUnBatimentDejaAuMaximumEstRefuse(): void
    {
        $batiment = new Building($this->ville(difficulte: 0), TypeDeBatiment::Grenier, niveau: 5);

        $this->expectException(\LogicException::class);

        $batiment->monterDUnNiveau();
    }

    public function testLeCoutDeMonteeEstCeluiDuNiveauVise(): void
    {
        $batiment = new Building($this->ville(difficulte: 0), TypeDeBatiment::Grenier, niveau: 1);

        $cout = $batiment->coutDeLaMonteeDeNiveau();

        self::assertNotNull($cout);
        // Roseaux niveau 2 : 15 × 1,4 = 21.
        self::assertSame(21, $cout->quantiteDe(Ressource::Roseaux));
    }

    #[DataProvider('paliersVisuels')]
    public function testLePalierVisuelSuitLeQuartDuPlafondPropre(int $niveau, int $palierAttendu): void
    {
        // Le Grenier plafonne à 6 : quatre paliers d'illustration (doc 15).
        $batiment = new Building($this->ville(difficulte: 9), TypeDeBatiment::Grenier, niveau: $niveau);

        self::assertSame($palierAttendu, $batiment->palierVisuel());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function paliersVisuels(): iterable
    {
        yield 'niveau 1 — modeste' => [1, 1];
        yield 'niveau 2 — développé' => [2, 2];
        yield 'niveau 4 — prospère' => [4, 3];
        yield 'niveau 6 — monumental' => [6, 4];
    }

    private function ville(int $difficulte): City
    {
        return new City('Avaris', $difficulte, 3);
    }
}
