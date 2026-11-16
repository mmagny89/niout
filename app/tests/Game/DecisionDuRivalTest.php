<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DecisionDuRival;
use App\Game\TraitDeCandidat;
use PHPUnit\Framework\TestCase;

/**
 * Le choix d'un rival : pur, rejouable, et il dit pourquoi (`docs/plan-pnj-palier-b.md` § 4, § 9).
 */
final class DecisionDuRivalTest extends TestCase
{
    private const array ROUTES = [
        ['cle' => 'byblos', 'prosperite' => 90, 'prestige' => 10],
        ['cle' => 'canaan', 'prosperite' => 10, 'prestige' => 90],
        ['cle' => 'thebes', 'prosperite' => 0, 'prestige' => 0],
    ];

    public function testSansRouteIlNeChoisitRien(): void
    {
        self::assertNull(DecisionDuRival::choisir([], TraitDeCandidat::Econome, 7));
    }

    public function testMemeEntreeMemeDecisionEtMemesRaisons(): void
    {
        for ($identite = 0; $identite < 50; ++$identite) {
            self::assertSame(
                DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Ambitieux, $identite),
                DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Ambitieux, $identite),
            );
        }
    }

    public function testLeCaractereInfleshitLeChoix(): void
    {
        // Un économe pèse la prospérité double : il préfère Byblos (90/10) à Canaan (10/90).
        $econome = DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Econome, 1);
        // Un ambitieux pèse le prestige double : Canaan.
        $ambitieux = DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Ambitieux, 1);

        self::assertSame('byblos', $econome['cle'] ?? null);
        self::assertSame('canaan', $ambitieux['cle'] ?? null);
    }

    public function testIlNeChoisitJamaisHorsDesDeuxMeilleuresEtJamaisLaPire(): void
    {
        $vus = [];

        for ($identite = 0; $identite < 100; ++$identite) {
            $vus[DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Fidele, $identite)['cle'] ?? ''] = true;
        }

        self::assertArrayNotHasKey('thebes', $vus);
        self::assertCount(2, $vus, 'La variété vient du hasard parmi les deux meilleures.');

        // Deux contre un : le caractère se lit, la variété reste.
        $premiere = 0;
        for ($identite = 0; $identite < 300; ++$identite) {
            $premiere += 'byblos' === (DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Econome, $identite)['cle'] ?? '') ? 1 : 0;
        }
        self::assertSame(200, $premiere);
    }

    public function testLaDecisionPorteSesRaisonsEnClesJamaisEnPhrase(): void
    {
        $decision = DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Econome, 0);

        self::assertNotNull($decision);
        self::assertSame(['route', 'prosperite', 'prestige', 'caractere'], array_keys($decision['raisons']));
        self::assertSame('econome', $decision['raisons']['caractere']);
    }

    public function testLOrdreDArriveeNeChangeRien(): void
    {
        $inverse = array_reverse(self::ROUTES);

        self::assertSame(
            DecisionDuRival::choisir(self::ROUTES, TraitDeCandidat::Fidele, 3),
            DecisionDuRival::choisir($inverse, TraitDeCandidat::Fidele, 3),
        );
    }
}
