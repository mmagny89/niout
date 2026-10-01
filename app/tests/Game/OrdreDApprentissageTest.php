<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\AlphabetDesScribes;
use App\Game\LeconDeNiout;
use App\Game\SigneAlphabetique;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SigneAlphabetique::class)]
#[CoversClass(AlphabetDesScribes::class)]
final class OrdreDApprentissageTest extends TestCase
{
    public function testLOrdreContientChaqueSigneUneSeuleFois(): void
    {
        $ordre = SigneAlphabetique::ordreDApprentissage();

        self::assertCount(\count(SigneAlphabetique::cases()), $ordre);
        self::assertEqualsCanonicalizing(SigneAlphabetique::cases(), $ordre);
        self::assertSame($ordre, array_values(array_unique($ordre, \SORT_REGULAR)));
    }

    /**
     * Les trois premiers niveaux ouvrent les sons dont on a le plus besoin pour
     * écrire un nom : le *a*, le *r* (et le *l*), le *m*, le *s*, le *d*, le *h*
     * et le *k*.
     */
    public function testLesPremiersSignesSontLesPlusUtilesPourEcrireUnNom(): void
    {
        $sons = array_map(
            static fn (SigneAlphabetique $s): string => $s->translitteration(),
            \array_slice(SigneAlphabetique::ordreDApprentissage(), 0, 7),
        );

        self::assertSame(['Ȝ', 'r', 'm', 's', 'd', 'h', 'k'], $sons);
    }

    /**
     * Les quatre signes de Niout, que la ville connaît déjà, ferment la liste :
     * les ouvrir en premier gaspillerait les premiers niveaux de la Maison des
     * scribes sur ce que le joueur sait.
     */
    public function testLesSignesDeNioutFermentLaListe(): void
    {
        $ordre = SigneAlphabetique::ordreDApprentissage();
        $fin = \array_slice($ordre, -\count(LeconDeNiout::SIGNES));

        self::assertEqualsCanonicalizing(LeconDeNiout::SIGNES, $fin);
    }
}
