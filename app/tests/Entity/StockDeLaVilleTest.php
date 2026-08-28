<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\City;
use App\Game\Ressource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(City::class)]
final class StockDeLaVilleTest extends TestCase
{
    public function testUneVilleNeuveNAAucunStock(): void
    {
        $ville = $this->ville();

        self::assertSame(0, $ville->quantite(Ressource::Or));
        self::assertCount(0, $ville->getStock(), 'Aucune ligne tant que rien n\'a été détenu.');
    }

    public function testCrediterCreeLaLigneALaPremiereFois(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([Ressource::Argile->value => 12]);

        self::assertSame(12, $ville->quantite(Ressource::Argile));
        self::assertCount(1, $ville->getStock());
    }

    public function testDeuxCreditsSAdditionnentSurLaMemeLigne(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([Ressource::Argile->value => 10]);
        $ville->crediterRessources([Ressource::Argile->value => 5]);

        self::assertSame(15, $ville->quantite(Ressource::Argile));
        self::assertCount(1, $ville->getStock(), 'Une ressource n\'occupe jamais deux lignes.');
    }

    public function testUnCreditNulNeCreePasDeLigne(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([Ressource::Argile->value => 0]);

        self::assertCount(0, $ville->getStock());
    }

    public function testDebiterRetireLesQuantites(): void
    {
        $ville = $this->ville();
        $ville->crediterRessources([Ressource::Roseaux->value => 20, Ressource::Calcaire->value => 10]);

        $succes = $ville->debiterRessources([Ressource::Roseaux->value => 15]);

        self::assertTrue($succes);
        self::assertSame(5, $ville->quantite(Ressource::Roseaux));
        self::assertSame(10, $ville->quantite(Ressource::Calcaire));
    }

    public function testUnDebitHorsDeMoyensNeRetireRien(): void
    {
        $ville = $this->ville();
        $ville->crediterRessources([Ressource::Roseaux->value => 20, Ressource::Calcaire->value => 10]);

        // Les roseaux suffisent, le calcaire non : rien ne doit bouger, même les roseaux.
        $succes = $ville->debiterRessources([
            Ressource::Roseaux->value => 20,
            Ressource::Calcaire->value => 30,
        ]);

        self::assertFalse($succes);
        self::assertSame(20, $ville->quantite(Ressource::Roseaux), 'Un débit partiel serait pire qu\'un refus.');
        self::assertSame(10, $ville->quantite(Ressource::Calcaire));
    }

    public function testDebiterUneRessourceJamaisDetenueEchoue(): void
    {
        $ville = $this->ville();

        self::assertFalse($ville->debiterRessources([Ressource::LapisLazuli->value => 1]));
        self::assertCount(0, $ville->getStock(), 'Un refus ne doit pas créer de ligne à zéro.');
    }

    public function testLeRaccourciDeLaBarreLitBienLeStock(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([Ressource::Or->value => 50]);

        self::assertSame(50, $ville->getOr());
    }

    /**
     * Chaque ressource reste distincte : rien ne s'agrège sous un compteur
     * générique, sans quoi le joueur ne saurait plus ce qu'il possède vraiment.
     */
    public function testLeStockAffichableNAgregeRien(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([
            Ressource::Roseaux->value => 20,
            Ressource::Calcaire->value => 10,
        ]);

        /** @var array<string, int> $lignes */
        $lignes = [];
        foreach ($ville->stockAffichable() as $ligne) {
            $lignes[$ligne->getRessource()->value] = $ligne->getQuantite();
        }

        self::assertSame(20, $lignes[Ressource::Roseaux->value] ?? null);
        self::assertSame(10, $lignes[Ressource::Calcaire->value] ?? null);
    }

    public function testLeStockAccueilleNimporteQuelleRessourceDuDocument(): void
    {
        $ville = $this->ville();

        foreach (Ressource::cases() as $ressource) {
            $ville->crediterRessources([$ressource->value => 1]);
        }

        self::assertCount(\count(Ressource::cases()), $ville->getStock());
    }

    public function testLaPrepositionSElideDevantUneVoyelle(): void
    {
        self::assertSame("d'Avaris", (new City('Avaris', 0, 3))->avecPreposition());
        self::assertSame("d'Éléphantine", (new City('Éléphantine', 6, 6))->avecPreposition());
        self::assertSame("d'Ouadi Hammamat", (new City('Ouadi Hammamat', 8, 7))->avecPreposition());
    }

    public function testLaPrepositionResteEntiereDevantUneConsonne(): void
    {
        self::assertSame('de Memphis', (new City('Memphis', 0, 8))->avecPreposition());
        self::assertSame('de Megiddo', (new City('Megiddo', 3, 4))->avecPreposition());
        self::assertSame('de Saï', (new City('Saï', 1, 3))->avecPreposition());
    }

    private function ville(): City
    {
        return new City('Avaris', 0, 3);
    }
}
