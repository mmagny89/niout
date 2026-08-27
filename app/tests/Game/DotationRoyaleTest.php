<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DotationRoyale;
use App\Game\FamilleDeMateriau;
use App\Game\GeographieDeRegion;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotationRoyale::class)]
final class DotationRoyaleTest extends TestCase
{
    #[DataProvider('dotationsAttendues')]
    public function testLOrSuitLaFormuleDuDocument(int $difficulte, int $orAttendu): void
    {
        $dotation = DotationRoyale::pour($difficulte, self::delta());

        self::assertSame($orAttendu, $dotation->or);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function dotationsAttendues(): iterable
    {
        // 50 + 10 × difficulté (doc 13).
        yield 'Delta, la plus clémente' => [0, 50];
        yield 'difficulté moyenne' => [5, 100];
        yield 'Sinaï, la plus rude' => [9, 140];
    }

    public function testLesMateriauxNeDependentPasDeLaDifficulte(): void
    {
        // Seul l'or suit la difficulté (doc 13) : les matériaux, eux, sont
        // calibrés sur le premier bâtiment, le même partout.
        $clemente = self::sansLOr(DotationRoyale::pour(0, self::delta())->enRessources());
        $rude = self::sansLOr(DotationRoyale::pour(9, self::delta())->enRessources());

        self::assertSame($clemente, $rude);
    }

    /**
     * La dotation doit couvrir les deux bâtiments qui ouvrent réellement une
     * partie (doc 01) — et surtout le Grenier : sans lui, les champs du lot 3.5
     * ne mènent nulle part, et le joueur ne peut pas le savoir avant d'avoir
     * semé.
     *
     * Tous trois sont en brique crue : c'est l'argile qui les paie, jamais le
     * calcaire.
     */
    #[DataProvider('batimentsDOuverture')]
    public function testLaDotationCouvreLesBatimentsDOuverture(TypeDeBatiment $type): void
    {
        $recu = DotationRoyale::pour(0, self::delta())->enRessources();
        $cout = $type->coutDeBase()->pourNiveau(1);

        self::assertSame(FamilleDeMateriau::BriqueCrue, $cout->maconnerie, $type->libelle());
        self::assertGreaterThanOrEqual($cout->bois, self::total($recu, FamilleDeMateriau::Bois), $type->libelle());
        self::assertGreaterThanOrEqual($cout->pierre, self::total($recu, FamilleDeMateriau::BriqueCrue), $type->libelle());
        self::assertGreaterThanOrEqual($cout->or, $recu[Ressource::Or->value], $type->libelle());
    }

    /**
     * @return iterable<string, array{TypeDeBatiment}>
     */
    public static function batimentsDOuverture(): iterable
    {
        yield 'Grenier' => [TypeDeBatiment::Grenier];
        yield 'Entrepôt' => [TypeDeBatiment::Entrepot];
        yield 'Marché' => [TypeDeBatiment::Marche];
    }

    /**
     * Le Marché est la seule source d'or : une partie qui ne l'atteindrait pas
     * serait sans issue. La dotation doit donc le couvrir **en plus** du
     * Grenier, même si le joueur a d'abord dépensé ailleurs.
     */
    public function testLaDotationPermetLeGrenierPuisLeMarche(): void
    {
        $dotation = DotationRoyale::pour(0, self::delta());
        $grenier = TypeDeBatiment::Grenier->coutDeBase()->pourNiveau(1);
        $marche = TypeDeBatiment::Marche->coutDeBase()->pourNiveau(1);

        self::assertGreaterThanOrEqual(
            $grenier->or + $marche->or,
            $dotation->or,
            'Sans Marché atteignable, l\'or ne peut plus jamais rentrer.',
        );
    }

    /**
     * Le pharaon envoie ce que la région travaille : des roseaux et de l'argile
     * dans le Delta, pas un cèdre venu de Byblos.
     */
    public function testLaDotationPuiseDansLesMateriauxDeLaRegion(): void
    {
        $recu = DotationRoyale::pour(0, self::delta())->enRessources();

        self::assertArrayHasKey(Ressource::Roseaux->value, $recu, 'Le bois du Delta, ce sont ses roseaux.');
        self::assertArrayHasKey(Ressource::Argile->value, $recu);
        self::assertArrayNotHasKey(Ressource::BoisDeCedre->value, $recu);
    }

    /**
     * La Basse-Nubie ne porte que du granite : rien pour couvrir un toit. La
     * couronne complète, sans quoi la région serait imbâtissable.
     */
    public function testLaCouronneComplèteCeQueLaRegionNaPas(): void
    {
        $recu = DotationRoyale::pour(6, new GeographieDeRegion(nil: true, ressourcesDeZone: [Ressource::Granite]))->enRessources();

        self::assertArrayHasKey(Ressource::Argile->value, $recu, 'La brique crue est envoyée partout.');
        self::assertArrayHasKey(Ressource::BoisDeCedre->value, $recu, 'Le bois, lui, doit venir de loin.');
    }

    /**
     * Sans vivres, aucune expédition ne peut partir (doc 04) — et sans
     * expédition, le joueur ne trouverait jamais la terre où semer.
     */
    public function testLaDotationPermetDePartirEnReconnaissance(): void
    {
        $recu = DotationRoyale::pour(0, self::delta())->enRessources();

        self::assertGreaterThan(0, $recu[Ressource::Ble->value]);
    }

    private static function delta(): GeographieDeRegion
    {
        return new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );
    }

    /**
     * @param array<string, int> $recu
     *
     * @return array<string, int>
     */
    private static function sansLOr(array $recu): array
    {
        unset($recu[Ressource::Or->value]);

        return $recu;
    }

    /**
     * @param array<string, int> $recu
     */
    private static function total(array $recu, FamilleDeMateriau $famille): int
    {
        $total = 0;

        foreach ($famille->ressources() as $ressource) {
            $total += $recu[$ressource->value] ?? 0;
        }

        return $total;
    }
}
