<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Indice;
use App\Game\SourceDIndice;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La voix des témoins (`docs/plan-pnj-dialogues-indices.md`, I3) : un témoignage rejoué en paroles
 * est le même indice dit par quelqu'un — jamais un indice de plus, et jamais d'une voix qui trahisse
 * ce qu'il vaut (doc 10 : « le joueur ne sait jamais lequel »).
 */
#[CoversClass(Indice::class)]
final class IndiceTest extends TestCase
{
    /**
     * Qui a une voix : les témoignages, tous, et eux seuls.
     */
    public function testSeulsLesTemoignagesParlent(): void
    {
        $temoignages = 0;

        foreach (Indice::cases() as $indice) {
            if (SourceDIndice::Temoignage === $indice->source()) {
                ++$temoignages;
                self::assertNotNull($indice->temoin(), $indice->value.' : qui parle ?');
                self::assertNotNull($indice->parole(), $indice->value.' : que dit-il ?');
            } else {
                self::assertNull($indice->temoin(), $indice->value.' est un indice de terrain : il se fouille, il ne parle pas.');
                self::assertNull($indice->parole(), $indice->value);
            }
        }

        self::assertSame(16, $temoignages);
    }

    /**
     * **Le même ton pour tous** : aucune parole ne nomme la nature d'un indice, ni ne porte un
     * jugement de valeur qui la laisserait deviner. Courte, sans chiffre, et qui se termine.
     */
    public function testAucuneParoleNeTrahitLaNatureDeLIndice(): void
    {
        $interdits = ['concordant', 'trompeur', 'optionnel', 'fausse piste', 'important', 'essentiel', 'décisif', 'mensonge', 'suspect', 'douteux', 'sûrement faux', 'c\'est la clé'];

        foreach (Indice::cases() as $indice) {
            $parole = $indice->parole();

            if (null === $parole) {
                continue;
            }

            self::assertSame(0, preg_match('/\d/', $parole), $indice->value.' : un chiffre.');
            self::assertLessThanOrEqual(120, mb_strlen($parole), $indice->value.' : trop long pour une bulle.');
            self::assertMatchesRegularExpression('/[.!?»]$/u', $parole, $indice->value.' : la phrase ne se termine pas.');

            foreach ($interdits as $mot) {
                self::assertStringNotContainsStringIgnoringCase($mot, $parole, $indice->value);
            }
        }
    }

    /**
     * **Rien de plus que l'indice** : la parole reprend l'essentiel de `texte()`, pas une autre
     * information. On le garde par le vocabulaire — au moins trois mots de quatre lettres en commun.
     */
    public function testLaParoleNeDitQueCeQueLIndiceDisaitDeja(): void
    {
        $mots = static function (string $texte): array {
            preg_match_all('/\p{L}{4,}/u', mb_strtolower($texte), $trouves);

            return array_unique($trouves[0]);
        };

        foreach (Indice::cases() as $indice) {
            if (null === $indice->parole()) {
                continue;
            }

            $communs = array_intersect($mots($indice->parole()), $mots($indice->texte()));

            self::assertGreaterThanOrEqual(3, \count($communs), \sprintf('%s : la parole s\'écarte du texte de l\'indice (%s).', $indice->value, implode(', ', $communs)));
        }
    }
}
