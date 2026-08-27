<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\FamilleDeMateriau;
use App\Game\MissionCatalogue;
use App\Game\Ressource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FamilleDeMateriau::class)]
final class FamilleDeMateriauTest extends TestCase
{
    /**
     * L'argile relève de la maçonnerie : le doc 01 bâtit la quasi-totalité de
     * la ville en brique crue, faite du limon du fleuve. Sans cette règle, le
     * Delta n'aurait aucune pierre pour son premier grenier.
     */
    public function testLArgileBatitCommeLaPierre(): void
    {
        self::assertTrue(FamilleDeMateriau::Pierre->contient(Ressource::Argile));
    }

    /**
     * Hors Levant, aucune région d'Égypte n'a de bois d'œuvre. Le doc 01
     * décrit lui-même les toitures en nattes — donc en roseau.
     */
    public function testLesRoseauxTiennentLieuDeBois(): void
    {
        self::assertTrue(FamilleDeMateriau::Bois->contient(Ressource::Roseaux));
    }

    public function testUneRessourceNAppartientQuAUneSeuleFamille(): void
    {
        foreach (Ressource::cases() as $ressource) {
            $familles = array_filter(
                FamilleDeMateriau::cases(),
                static fn (FamilleDeMateriau $famille): bool => $famille->contient($ressource),
            );

            self::assertLessThanOrEqual(
                1,
                \count($familles),
                \sprintf('« %s » relève de deux familles à la fois.', $ressource->libelle()),
            );
        }
    }

    public function testLOrNeBatitPas(): void
    {
        // L'or paie, il ne maçonne pas : il s'exprime au nom dans les coûts.
        self::assertNull(Ressource::Or->familleDeMateriau());
    }

    public function testLaNourritureNeBatitPas(): void
    {
        foreach (Ressource::cases() as $ressource) {
            if ($ressource->estNourriture()) {
                self::assertNull($ressource->familleDeMateriau(), $ressource->libelle());
            }
        }
    }

    /**
     * L'invariant qui rend la première mission jouable : le Delta se suffit à
     * lui-même. Il porte des roseaux pour couvrir et de l'argile pour monter
     * les murs, donc de quoi bâtir sans rien importer.
     */
    public function testLeDeltaSeSuffitAuxDeuxFamilles(): void
    {
        self::assertSame(
            [FamilleDeMateriau::Pierre, FamilleDeMateriau::Bois],
            self::famillesDeLaRegion(1),
            'Sans les deux familles, la mission d\'apprentissage serait imbâtissable.',
        );
    }

    /**
     * Constat à porter au game design, pas un défaut de code : **le Delta est
     * la seule région autosuffisante**. Cinq régions ne portent que de la
     * pierre, une seule que du bois, et trois — Haute-Nubie, mer Rouge, Sinaï —
     * ni l'un ni l'autre.
     *
     * La dotation royale comble le départ (voir DotationRoyaleTest), mais elle
     * ne finance pas une mission entière : à partir de la région 2, le commerce
     * de la Phase 5 devient une condition de jouabilité, pas un confort.
     *
     * Ce test échouera le jour où une région gagnera un matériau — ce sera le
     * signal qu'il faut relire cette conclusion.
     */
    #[DataProvider('regionsSansBoisLocal')]
    public function testHorsDeltaEtLevantAucuneRegionNaDeBois(int $numero): void
    {
        self::assertNotContains(
            FamilleDeMateriau::Bois,
            self::famillesDeLaRegion($numero),
            'Cette région a gagné du bois : la conclusion sur le commerce est à relire.',
        );
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function regionsSansBoisLocal(): iterable
    {
        foreach ([2, 3, 5, 6, 7, 8, 9, 10] as $numero) {
            yield \sprintf('région %d', $numero) => [$numero];
        }
    }

    /**
     * @return list<FamilleDeMateriau>
     */
    private static function famillesDeLaRegion(int $numero): array
    {
        $familles = [];

        foreach ((new MissionCatalogue())->get($numero)->geographie->ressourcesDeZone as $ressource) {
            $famille = $ressource->familleDeMateriau();

            if (null !== $famille && !\in_array($famille, $familles, true)) {
                $familles[] = $famille;
            }
        }

        return $familles;
    }
}
