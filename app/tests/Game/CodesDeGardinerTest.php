<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\CartoucheRoyal;
use App\Game\SigneAlphabetique;
use App\Game\SymboleHieroglyphique;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Un code de Gardiner et un glyphe peuvent se contredire en silence.**.
 *
 * Défaut réel, payé : la clé de lecture portait `N35` — une seule ondulation,
 * le phonogramme *n* — tout en décrivant « trois ondulations », qui sont
 * l'eau, `N35A`. Rien ne l'a signalé, et le jeu a enseigné un signe faux dans
 * un domaine dont c'est justement l'objet d'enseigner les vrais.
 *
 * Unicode nomme chacun de ses caractères par son code de Gardiner :
 * `IntlChar::charName('𓈗')` rend `EGYPTIAN HIEROGLYPH N035A`. La vérification
 * est donc **exacte**, et ne repose sur aucune table recopiée — la seule qui
 * pourrait diverger.
 */
final class CodesDeGardinerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function signesDeclares(): iterable
    {
        foreach (SymboleHieroglyphique::cases() as $symbole) {
            yield 'clé de lecture — '.$symbole->libelle() => [
                $symbole->codeDeGardiner(), $symbole->signe(), $symbole->libelle(),
            ];
        }

        foreach (SigneAlphabetique::cases() as $signe) {
            yield 'alphabet — '.$signe->translitteration() => [
                $signe->codeDeGardiner(), $signe->signe(), $signe->objet(),
            ];
        }

        foreach (CartoucheRoyal::cases() as $cartouche) {
            $glyphes = preg_split('//u', $cartouche->signes(), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($cartouche->codesDeGardiner() as $rang => $code) {
                yield \sprintf('cartouche %s — signe %d', $cartouche->lecture(), $rang + 1) => [
                    $code, $glyphes[$rang] ?? '', $cartouche->lecture(),
                ];
            }
        }
    }

    #[DataProvider('signesDeclares')]
    public function testChaqueGlypheEstBienCeluiDeSonCode(string $code, string $glyphe, string $quoi): void
    {
        self::assertSame(1, mb_strlen($glyphe), \sprintf('%s : un signe, pas une suite.', $quoi));

        $nom = \IntlChar::charName($glyphe);

        self::assertSame(
            'EGYPTIAN HIEROGLYPH '.self::normaliser($code),
            $nom,
            \sprintf(
                '%s déclare le code %s, mais son glyphe %s est %s.',
                $quoi,
                $code,
                $glyphe,
                $nom,
            ),
        );
    }

    /**
     * Un cartouche déclare autant de codes que de signes : sans cela, la
     * correspondance rang à rang glisserait et le contrôle vérifierait autre
     * chose que ce qu'il croit.
     */
    public function testChaqueCartoucheDeclareAutantDeCodesQueDeSignes(): void
    {
        foreach (CartoucheRoyal::cases() as $cartouche) {
            self::assertSame(
                mb_strlen($cartouche->signes()),
                \count($cartouche->codesDeGardiner()),
                $cartouche->lecture(),
            );
        }
    }

    /**
     * Unicode écrit les codes sur trois chiffres — `N35A` s'y nomme `N035A`. La
     * lettre de variante, elle, se garde : c'est tout ce qui distingue l'eau du
     * phonogramme *n*.
     */
    private static function normaliser(string $code): string
    {
        if (1 !== preg_match('/^(Aa|[A-Z])(\d+)([A-Z]?)$/', $code, $morceaux)) {
            self::fail(\sprintf('« %s » n\'a pas la forme d\'un code de Gardiner.', $code));
        }

        return \sprintf('%s%03d%s', mb_strtoupper($morceaux[1]), (int) $morceaux[2], $morceaux[3]);
    }
}
