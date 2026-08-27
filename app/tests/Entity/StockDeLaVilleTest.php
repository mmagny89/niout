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
        $ville->crediterRessources([Ressource::Bois->value => 20, Ressource::Pierre->value => 10]);

        $succes = $ville->debiterRessources([Ressource::Bois->value => 15]);

        self::assertTrue($succes);
        self::assertSame(5, $ville->quantite(Ressource::Bois));
        self::assertSame(10, $ville->quantite(Ressource::Pierre));
    }

    public function testUnDebitHorsDeMoyensNeRetireRien(): void
    {
        $ville = $this->ville();
        $ville->crediterRessources([Ressource::Bois->value => 20, Ressource::Pierre->value => 10]);

        // Le bois suffit, la pierre non : rien ne doit bouger, même le bois.
        $succes = $ville->debiterRessources([
            Ressource::Bois->value => 20,
            Ressource::Pierre->value => 30,
        ]);

        self::assertFalse($succes);
        self::assertSame(20, $ville->quantite(Ressource::Bois), 'Un débit partiel serait pire qu\'un refus.');
        self::assertSame(10, $ville->quantite(Ressource::Pierre));
    }

    public function testDebiterUneRessourceJamaisDetenueEchoue(): void
    {
        $ville = $this->ville();

        self::assertFalse($ville->debiterRessources([Ressource::LapisLazuli->value => 1]));
        self::assertCount(0, $ville->getStock(), 'Un refus ne doit pas créer de ligne à zéro.');
    }

    public function testLesRaccourcisDeLaBarreLisentBienLeStock(): void
    {
        $ville = $this->ville();

        $ville->crediterRessources([
            Ressource::Or->value => 50,
            Ressource::Bois->value => 20,
            Ressource::Pierre->value => 10,
        ]);

        self::assertSame(50, $ville->getOr());
        self::assertSame(20, $ville->getBois());
        self::assertSame(10, $ville->getPierre());
    }

    public function testLeStockAccueilleNimporteQuelleRessourceDuDocument(): void
    {
        $ville = $this->ville();

        foreach (Ressource::cases() as $ressource) {
            $ville->crediterRessources([$ressource->value => 1]);
        }

        self::assertCount(\count(Ressource::cases()), $ville->getStock());
    }

    private function ville(): City
    {
        return new City('Avaris', 0, 3);
    }
}
