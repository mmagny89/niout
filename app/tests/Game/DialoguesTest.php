<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Dialogues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Le catalogue des répliques : ce qui rend un dialogue d'ambiance sans danger pour le
 * joueur — il dit un fait vrai ou se déclare ambiance, ne donne aucun chiffre, et ne
 * s'use pas (`docs/plan-pnj-ville-vivante.md`, lot V4a).
 */
#[CoversClass(Dialogues::class)]
final class DialoguesTest extends TestCase
{
    /**
     * Au moins quatre variantes pour celui qui parle et quatre pour celui qui répond : sans
     * quoi la quinzaine entière répète la même phrase.
     */
    public function testChaqueSituationAAssezDeVariantes(): void
    {
        foreach (Dialogues::catalogue() as $situation => $lignes) {
            self::assertGreaterThanOrEqual(4, \count($lignes['a']), $situation.' : celui qui parle');
            self::assertGreaterThanOrEqual(4, \count($lignes['b']), $situation.' : celui qui répond');
            self::assertSame(\count($lignes['a']), \count(array_unique($lignes['a'])), $situation.' : pas de doublon');
            self::assertSame(\count($lignes['b']), \count(array_unique($lignes['b'])), $situation.' : pas de doublon');
        }
    }

    /**
     * Une situation est un fait vrai, ou une ambiance : rien d'autre.
     */
    public function testChaqueSituationEstUnFaitOuUneAmbiance(): void
    {
        foreach (Dialogues::catalogue() as $situation => $lignes) {
            self::assertContains($lignes['nature'], [Dialogues::FAIT, Dialogues::AMBIANCE], $situation);
        }

        self::assertSame(Dialogues::AMBIANCE, Dialogues::nature('ambiance'));
        self::assertSame(Dialogues::AMBIANCE, Dialogues::nature('ambiance_soir'));
        self::assertSame(Dialogues::FAIT, Dialogues::nature('fievre'));
        self::assertSame(Dialogues::AMBIANCE, Dialogues::nature('situation-inconnue'), 'Dans le doute, une ambiance : elle ne promet rien.');
    }

    /**
     * **Aucun chiffre dans une réplique** : un nombre dirait quelque chose du jeu qu'on ne
     * saurait tenir vrai — et une réplique-indice ne doit porter que ce que le jeu sait déjà.
     * Courte aussi : une bulle se lit d'un coup d'œil.
     */
    public function testAucuneRepliqueNeDonneUnChiffreEtToutesSontCourtes(): void
    {
        foreach (Dialogues::catalogue() as $situation => $lignes) {
            foreach ([...$lignes['a'], ...$lignes['b']] as $texte) {
                self::assertSame(0, preg_match('/\d/', $texte), \sprintf('%s : « %s » contient un chiffre.', $situation, $texte));
                self::assertLessThanOrEqual(80, mb_strlen($texte), \sprintf('%s : « %s » est trop long pour une bulle.', $situation, $texte));
            }
        }
    }

    public function testLesVariablesSeRemplacentEtRienNeReste(): void
    {
        $variables = ['fete' => 'Opet', 'partenaire' => 'Byblos', 'rival' => 'Panehsy le marchand'];

        foreach (Dialogues::catalogue() as $situation => $lignes) {
            foreach (['a', 'b'] as $qui) {
                foreach (array_keys($lignes[$qui]) as $variante) {
                    self::assertStringNotContainsString('{', Dialogues::replique($situation, $qui, $variante, $variables), $situation);
                }
            }
        }

        self::assertStringContainsString('Byblos', Dialogues::replique('convoi', 'a', 1, $variables));
        self::assertStringContainsString('Opet', Dialogues::replique('fete', 'a', 0, $variables));
    }

    public function testUneVarianteTourneEtUneSituationInconnueNeDitRien(): void
    {
        $n = \count(Dialogues::catalogue()['ambiance']['a']);

        self::assertSame(Dialogues::replique('ambiance', 'a', 1), Dialogues::replique('ambiance', 'a', 1 + $n));
        self::assertNotSame(Dialogues::replique('ambiance', 'a', 0), Dialogues::replique('ambiance', 'a', 1));
        self::assertSame('', Dialogues::replique('nulle-part', 'a', 0));
    }
}
