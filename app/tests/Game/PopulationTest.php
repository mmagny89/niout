<?php

declare(strict_types=1);

namespace App\Tests\Game;

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

    public function testDesHabitantsSeLogentParMaisonneesEntamees(): void
    {
        self::assertSame(0, Population::foyersPour(0));
        self::assertSame(1, Population::foyersPour(1), 'Un habitant seul occupe déjà un logement.');
        self::assertSame(1, Population::foyersPour(5));
        self::assertSame(2, Population::foyersPour(6));
        self::assertSame(3, Population::foyersPour(11));
    }

    /**
     * Le convoi du pharaon : assez de bras pour tenir quelques exploitations
     * **et** ses bâtiments, et assez de monde pour que loger devienne le
     * premier geste.
     */
    public function testLesVolontairesDuPharaonSontUneVilleCredible(): void
    {
        $habitants = Population::ACTIFS_AU_DEPART + Population::ENFANTS_AU_DEPART + Population::ANCIENS_AU_DEPART;

        self::assertSame(17, $habitants);
        self::assertSame(
            4,
            Population::foyersPour($habitants),
            'Quatre maisonnées, quand la Résidence seule n\'en loge qu\'une : bâtir des maisons est le premier geste.',
        );
        self::assertGreaterThanOrEqual(
            8,
            Population::ACTIFS_AU_DEPART,
            'Assez de bras pour que le territoire en reçoive après les bâtiments.',
        );
    }
}
