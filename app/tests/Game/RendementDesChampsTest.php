<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DateDeJeu;
use App\Game\QualiteDeCrue;
use App\Game\RendementDesChamps;
use App\Game\Saison;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(RendementDesChamps::class)]
final class RendementDesChampsTest extends TestCase
{
    /**
     * Le cœur de la mécanique agricole : pendant la crue, les champs sont sous
     * l'eau. C'est le prix de la fertilité, et il se paie tous les ans.
     */
    #[DataProvider('toutesLesQuinzainesDUneSaison')]
    public function testUnChampNeDonneRienPendantLaCrue(int $rang): void
    {
        self::assertSame(
            0,
            RendementDesChamps::pourUneQuinzaine(Saison::Akhet, $rang, QualiteDeCrue::Forte),
            'Même une crue forte ne fait pas pousser un champ noyé.',
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function toutesLesQuinzainesDUneSaison(): iterable
    {
        for ($rang = 1; $rang <= DateDeJeu::CYCLES_PAR_SAISON; ++$rang) {
            yield \sprintf('quinzaine %d', $rang) => [$rang];
        }
    }

    public function testLeRendementDePeretMonteAuFilDeLaSaison(): void
    {
        $precedent = -1;

        for ($rang = 1; $rang <= DateDeJeu::CYCLES_PAR_SAISON; ++$rang) {
            $rendement = RendementDesChamps::pourUneQuinzaine(Saison::Peret, $rang, QualiteDeCrue::Normale);

            self::assertGreaterThanOrEqual($precedent, $rendement, 'Une culture ne régresse pas.');
            $precedent = $rendement;
        }

        self::assertSame(
            RendementDesChamps::RECOLTE_DE_REFERENCE,
            $precedent,
            'La dernière quinzaine de Perèt atteint le plein rendement.',
        );
    }

    public function testLaCroissanceDePeretIgnoreLaQualiteDeLaCrue(): void
    {
        // Le doc 05 ne module que le pic de Chémou : la crue fertilise ce qu'on
        // moissonne, elle n'accélère pas la pousse.
        self::assertSame(
            RendementDesChamps::pourUneQuinzaine(Saison::Peret, 4, QualiteDeCrue::Faible),
            RendementDesChamps::pourUneQuinzaine(Saison::Peret, 4, QualiteDeCrue::Forte),
        );
    }

    #[DataProvider('moissons')]
    public function testLaMoissonSuitLaQualiteDeLaCrue(QualiteDeCrue $crue, int $attendu): void
    {
        self::assertSame($attendu, RendementDesChamps::pourUneQuinzaine(Saison::Chemou, 1, $crue));
    }

    /**
     * @return iterable<string, array{QualiteDeCrue, int}>
     */
    public static function moissons(): iterable
    {
        // Récolte de référence 10, modulée par ×0,7 / ×1,0 / ×1,3 (doc 05).
        yield 'crue faible' => [QualiteDeCrue::Faible, 7];
        yield 'crue normale' => [QualiteDeCrue::Normale, 10];
        yield 'crue forte' => [QualiteDeCrue::Forte, 13];
    }

    public function testUneMoissonDeChemouNeDependPasDuRangDansLaSaison(): void
    {
        // Contrairement à Perèt, Chémou est un pic constant : la moisson tombe
        // à chaque quinzaine de la saison, pas seulement à la dernière.
        self::assertSame(
            RendementDesChamps::pourUneQuinzaine(Saison::Chemou, 1, QualiteDeCrue::Normale),
            RendementDesChamps::pourUneQuinzaine(Saison::Chemou, 8, QualiteDeCrue::Normale),
        );
    }

    public function testLesJoursEpagomenesNeDonnentRien(): void
    {
        self::assertSame(0, RendementDesChamps::pourUneQuinzaine(null, null, QualiteDeCrue::Forte));
    }
}
