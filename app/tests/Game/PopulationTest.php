<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DateDeJeu;
use App\Game\Population;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Population::class)]
final class PopulationTest extends TestCase
{
    /**
     * `20 - 1,5 × difficulté` (doc 02) : une région difficile n'est pas
     * seulement plus pauvre, elle est aussi moins peuplée.
     */
    #[DataProvider('viviersAttendus')]
    public function testLeVivierRegionalSuitLaFormuleDuDocument(int $difficulte, int $attendu): void
    {
        self::assertSame($attendu, Population::famillesDisponibles($difficulte));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function viviersAttendus(): iterable
    {
        yield 'Delta, la plus clémente' => [0, 20];
        yield 'difficulté moyenne' => [5, 13];
        yield 'Sinaï, la plus rude' => [9, 7];
    }

    public function testLeVivierNeSEffondreJamaisEnDessousDeQuelquesFamilles(): void
    {
        for ($difficulte = 0; $difficulte <= 9; ++$difficulte) {
            self::assertGreaterThan(
                0,
                Population::famillesDisponibles($difficulte),
                \sprintf('Une région sans main-d\'œuvre serait injouable (difficulté %d).', $difficulte),
            );
        }
    }

    /**
     * On ne compare aucune valeur de jeu en flottants : la consommation se
     * compte en demi-rations et ne se convertit qu'une fois, à l'échelle de la
     * ville.
     */
    #[DataProvider('conversionsDeRations')]
    public function testLesDemiRationsSArrondissentAuSuperieur(int $demiRations, int $vivresAttendus): void
    {
        self::assertSame($vivresAttendus, Population::vivresPourDemiRations($demiRations));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function conversionsDeRations(): iterable
    {
        yield 'ville déserte' => [0, 0];
        yield 'un seul enfant, qui ne mange pas gratuitement' => [1, 1];
        yield 'un adulte' => [2, 1];
        yield 'un adulte et un enfant' => [3, 2];
        yield 'deux adultes et trois enfants' => [7, 4];
        yield 'deux adultes et six enfants' => [10, 5];
    }

    public function testUnAgeSeLitEnAnneesRevolues(): void
    {
        self::assertSame(0, Population::enAnnees(DateDeJeu::CYCLES_PAR_ANNEE - 1));
        self::assertSame(1, Population::enAnnees(DateDeJeu::CYCLES_PAR_ANNEE));
        self::assertSame(11, Population::enAnnees(Population::AGE_ADULTE_EN_QUINZAINES - 1));
    }

    /**
     * L'ordre de grandeur qui rend la Phase 4 jouable : une famille moyenne
     * pèse cinq personnes et fournit deux bras.
     */
    public function testUneFamilleVaDeDeuxAHuitPersonnes(): void
    {
        $minimum = Population::ADULTES_PAR_FOYER;
        $maximum = Population::ADULTES_PAR_FOYER + Population::ENFANTS_MAX_PAR_FOYER;

        self::assertSame(2, $minimum);
        self::assertSame(8, $maximum);
        self::assertSame(5, intdiv($minimum + $maximum, 2), 'La moyenne visée, d\'après Kahun et Deir el-Médineh.');
    }
}
