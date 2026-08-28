<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Effectifs;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Effectifs::class)]
final class EffectifsTest extends TestCase
{
    /**
     * `travailleursDeBase + arrondiInférieur((niveau - 1) / 3)` (doc 01).
     */
    #[DataProvider('encadrementsAttendus')]
    public function testLEncadrementSuitLaFormuleDuDocument(int $niveau, int $attendu): void
    {
        self::assertSame($attendu, Effectifs::travailleursParChef(TypeDeBatiment::Grenier, $niveau));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function encadrementsAttendus(): iterable
    {
        // Le Grenier a un travailleur de base.
        yield 'niveau 1' => [1, 1];
        yield 'niveau 3' => [3, 1];
        yield 'niveau 4' => [4, 2];
        yield 'niveau 7' => [7, 3];
    }

    /**
     * Décision de la joueuse, contre les trois du doc 01 : un chef et un homme
     * suffisent à tenir un quai. À trois, l'équipage mangeait tout ce que la
     * pêche rapportait.
     */
    public function testLePortNeDemandeQuUnSeulTravailleurDeBase(): void
    {
        self::assertSame(1, TypeDeBatiment::Port->travailleursDeBase());
        self::assertSame(1, Effectifs::travailleursParChef(TypeDeBatiment::Port, 1));
    }

    /**
     * L'invariant qui rend la phase jouable : **rien ne s'éteint faute
     * d'employés**. Le doc 01 ne parlait que de « capacité réduite ».
     */
    #[DataProvider('rendementsAttendus')]
    public function testLeRendementNeDescendJamaisSousLaMoitie(int $affectes, int $requis, int $attendu): void
    {
        self::assertSame($attendu, Effectifs::rendementEnCentiemes($affectes, $requis));
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function rendementsAttendus(): iterable
    {
        yield 'sans chef, donc sans besoin' => [0, 0, 50];
        yield 'personne sur deux postes' => [0, 2, 50];
        yield 'la moitié des postes' => [1, 2, 75];
        yield 'au complet' => [2, 2, 100];
        yield 'plus que nécessaire ne dépasse pas le plein' => [9, 2, 100];
    }

    public function testLeRendementCroitAvecLesBras(): void
    {
        $precedent = 0;

        for ($affectes = 0; $affectes <= 4; ++$affectes) {
            $rendement = Effectifs::rendementEnCentiemes($affectes, 4);

            self::assertGreaterThan($precedent, $rendement);
            $precedent = $rendement;
        }

        self::assertSame(Effectifs::RENDEMENT_PLEIN, $precedent);
    }
}
