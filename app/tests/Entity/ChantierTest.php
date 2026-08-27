<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chantier;
use App\Entity\City;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Chantier::class)]
final class ChantierTest extends TestCase
{
    public function testLaDureeSuitLaFormuleDuDocument(): void
    {
        // dureeBase + niveau visé (doc 01). Grenier : base 1, niveau 1 → 2 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertSame(2, $chantier->getDureeEnCycles());
    }

    public function testUnBatimentDePierreDemandePlusLongtemps(): void
    {
        // Temple : base 3, niveau 1 → 4 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Temple, niveauVise: 1);

        self::assertSame(4, $chantier->getDureeEnCycles());
    }

    public function testUnChantierNeufNEstPasAcheve(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertFalse($chantier->estAcheve());
        self::assertSame(2, $chantier->cyclesRestants());
    }

    public function testDeuxCyclesOrdinairesAchèventUnChantierDeDeuxCycles(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertFalse($chantier->estAcheve());

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertTrue($chantier->estAcheve());
    }

    public function testLaCrueFaitGagnerUnCycleSurUnChantierDeTrois(): void
    {
        // Entrepôt niveau 2 : base 1 + 2 = 3 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Entrepot, niveauVise: 2);
        self::assertSame(3, $chantier->getDureeEnCycles());

        // Deux quinzaines de crue valent trois quinzaines ordinaires.
        $chantier->avancerDUnCycle(Saison::Akhet);
        $chantier->avancerDUnCycle(Saison::Akhet);

        self::assertTrue($chantier->estAcheve(), 'La corvée d\'Akhèt doit faire gagner un cycle.');
    }

    public function testLesJoursEpagomenesNAccelerentRien(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        // Aucune saison : les cinq jours hors année n'ont pas de corvée.
        $chantier->avancerDUnCycle(null);

        self::assertFalse($chantier->estAcheve());
        self::assertSame(1, $chantier->cyclesRestants());
    }

    public function testLAvancementSExprimeEnPourcentage(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);
        self::assertSame(0, $chantier->pourcentageDAvancement());

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertSame(50, $chantier->pourcentageDAvancement());
    }

    public function testLePourcentageNeDepasseJamaisCent(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        for ($i = 0; $i < 10; ++$i) {
            $chantier->avancerDUnCycle(Saison::Akhet);
        }

        self::assertSame(100, $chantier->pourcentageDAvancement());
        self::assertSame(0, $chantier->cyclesRestants());
    }

    public function testLeChantierTraverseSesQuatreEtapesNommees(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertSame(4, $chantier->nombreDEtapes());
        self::assertSame(1, $chantier->numeroDEtape());
        self::assertSame('Préparation du terrain', $chantier->etapeEnCours()->nom);

        $chantier->avancerDUnCycle(Saison::Peret);

        self::assertSame(3, $chantier->numeroDEtape());
        self::assertNotSame('', $chantier->etapeEnCours()->explication);
    }

    public function testUnChantierDePierreASesProprresEtapes(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Temple, niveauVise: 1);

        self::assertSame('Extraction et transport de la pierre', $chantier->etapeEnCours()->nom);
    }

    public function testUnNiveauVisePlusGrandQueUnEstUneAmelioration(): void
    {
        $neuf = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);
        $amelioration = new Chantier($this->ville(), TypeDeBatiment::Marche, niveauVise: 2);

        self::assertFalse($neuf->estUneAmelioration());
        self::assertTrue($amelioration->estUneAmelioration());
    }

    private function ville(): City
    {
        return new City('Avaris', 0, 3);
    }
}
