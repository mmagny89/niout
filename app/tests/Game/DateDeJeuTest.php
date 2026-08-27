<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DateDeJeu;
use App\Game\Saison;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateDeJeu::class)]
#[CoversClass(Saison::class)]
final class DateDeJeuTest extends TestCase
{
    public function testLaPartieDemarreAuPremierMoisDeLaCrue(): void
    {
        $date = DateDeJeu::pourCycle(1);

        self::assertSame(1, $date->annee);
        self::assertSame('Tekhi', $date->nomDeMois);
        self::assertSame(Saison::Akhet, $date->saison);
    }

    public function testUnMoisDureExactementDeuxCycles(): void
    {
        // Une quinzaine par cycle, trente jours par mois pharaonique.
        self::assertSame('Tekhi', DateDeJeu::pourCycle(1)->nomDeMois);
        self::assertSame('Tekhi', DateDeJeu::pourCycle(2)->nomDeMois);
        self::assertSame('Menhèt', DateDeJeu::pourCycle(3)->nomDeMois);
    }

    #[DataProvider('saisonsAttendues')]
    public function testLesSaisonsCouvrentQuatreMoisChacune(int $cycle, Saison $saisonAttendue): void
    {
        self::assertSame($saisonAttendue, DateDeJeu::pourCycle($cycle)->saison);
    }

    /**
     * @return iterable<string, array{int, Saison}>
     */
    public static function saisonsAttendues(): iterable
    {
        yield 'mois 1, début d\'Akhèt' => [1, Saison::Akhet];
        yield 'mois 4, fin d\'Akhèt' => [8, Saison::Akhet];
        yield 'mois 5, début de Perèt' => [9, Saison::Peret];
        yield 'mois 8, fin de Perèt' => [16, Saison::Peret];
        yield 'mois 9, début de Chémou' => [17, Saison::Chemou];
        yield 'mois 12, fin de Chémou' => [24, Saison::Chemou];
    }

    public function testLeVingtCinquiemeCycleEstCeluiDesJoursEpagomenes(): void
    {
        $date = DateDeJeu::pourCycle(25);

        self::assertTrue($date->estJoursEpagomenes());
        self::assertNull($date->saison, 'Ces cinq jours n\'appartiennent à aucune saison.');
        self::assertSame('Hériou-renpèt', $date->nomDeMois);
    }

    public function testLAnneeSuivanteRepartAuPremierMois(): void
    {
        $date = DateDeJeu::pourCycle(26);

        self::assertSame(2, $date->annee);
        self::assertSame('Tekhi', $date->nomDeMois);
        self::assertSame(Saison::Akhet, $date->saison);
    }

    public function testUneAnneeCompteVingtCinqCycles(): void
    {
        self::assertSame(25, DateDeJeu::CYCLES_PAR_ANNEE);
        self::assertSame(1, DateDeJeu::pourCycle(25)->annee);
        self::assertSame(2, DateDeJeu::pourCycle(50)->annee);
        self::assertSame(3, DateDeJeu::pourCycle(51)->annee);
    }

    public function testLeLibelleReuniLeMoisLAnneeEtLaSaison(): void
    {
        self::assertSame('Tekhi, an 1 — Akhèt', DateDeJeu::pourCycle(1)->libelle());
        self::assertSame('Hériou-renpèt, an 1', DateDeJeu::pourCycle(25)->libelle());
    }

    public function testSeuleLaCrueAccelereLesChantiers(): void
    {
        self::assertSame(1.5, Saison::Akhet->facteurDAvancementDesChantiers());
        self::assertSame(1.0, Saison::Peret->facteurDAvancementDesChantiers());
        self::assertSame(1.0, Saison::Chemou->facteurDAvancementDesChantiers());
    }
}
