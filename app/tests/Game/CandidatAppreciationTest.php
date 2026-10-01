<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Candidat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Candidat::class)]
final class CandidatAppreciationTest extends TestCase
{
    public function testLAppreciationEstQualitativeEtJamaisChiffree(): void
    {
        foreach ([20, 45, 60, 80, 100] as $competence) {
            $candidat = new Candidat($competence, 5, 20, [], null, 2, 0);

            self::assertNotSame('', $candidat->appreciation());
            self::assertDoesNotMatchRegularExpression(
                '/\d/',
                $candidat->appreciation(),
                'Le doc 03 refuse de rendre la compétence au joueur en chiffres.',
            );
        }
    }

    public function testDeuxCandidatsDeRangDifferentNontPasLaMemeAppreciation(): void
    {
        $faible = new Candidat(20, 5, 20, [], null, 2, 0);
        $maitre = new Candidat(100, 5, 20, [], null, 2, 0);

        self::assertNotSame($faible->appreciation(), $maitre->appreciation());
    }
}
