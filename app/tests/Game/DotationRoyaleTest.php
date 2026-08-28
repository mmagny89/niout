<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DateDeJeu;
use App\Game\DotationRoyale;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DotationRoyale::class)]
final class DotationRoyaleTest extends TestCase
{
    /**
     * Ce que mange une famille fondatrice moyenne — deux adultes et trois
     * enfants, soit sept demi-rations arrondies à quatre vivres. La dotation
     * s'y adosse depuis le lot 4.1 : le pharaon dote la famille qu'il envoie,
     * pas une population théorique.
     */
    private const int CONSOMMATION_DE_REFERENCE = 4;

    #[DataProvider('dotationsAttendues')]
    public function testLaMonnaieSuitLaFormuleDuDocument(int $difficulte, int $debenAttendu): void
    {
        $dotation = DotationRoyale::pour($difficulte, self::CONSOMMATION_DE_REFERENCE);

        self::assertSame($debenAttendu, $dotation->deben);
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
        // Seule la monnaie suit la difficulté (doc 13) : les matériaux, eux, sont
        // calibrés sur les bâtiments d'ouverture, les mêmes partout.
        $clemente = self::sansLaMonnaie(DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources());
        $rude = self::sansLaMonnaie(DotationRoyale::pour(9, self::CONSOMMATION_DE_REFERENCE)->enRessources());

        self::assertSame($clemente, $rude);
    }

    /**
     * La dotation doit couvrir le Grenier ET le Marché (doc 01) — le Marché
     * est la seule source d'or du jeu, et une partie qui ne l'atteindrait pas
     * serait sans issue.
     */
    #[DataProvider('batimentsDOuverture')]
    public function testLaDotationCouvreLesBatimentsDOuverture(TypeDeBatiment $type): void
    {
        $recu = DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources();
        $cout = $type->coutDeBase()->pourNiveau(1);

        foreach ($cout->ressources() as $ressource) {
            self::assertGreaterThanOrEqual(
                $cout->quantiteDe($ressource),
                $recu[$ressource->value] ?? 0,
                \sprintf('%s pour le %s.', $ressource->libelle(), $type->libelle()),
            );
        }
    }

    /**
     * @return iterable<string, array{TypeDeBatiment}>
     */
    public static function batimentsDOuverture(): iterable
    {
        yield 'Grenier' => [TypeDeBatiment::Grenier];
        yield 'Marché' => [TypeDeBatiment::Marche];
    }

    /**
     * Les deux matériaux dont rien ne tient lieu : la brique crue et le roseau
     * qui couvre les toits (doc 01).
     */
    public function testLaDotationDonneDesRoseauxEtDeLArgile(): void
    {
        $recu = DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources();

        self::assertGreaterThan(0, $recu[Ressource::Roseaux->value] ?? 0);
        self::assertGreaterThan(0, $recu[Ressource::Argile->value] ?? 0);
    }

    /**
     * Sans vivres, aucune expédition ne peut partir (doc 04) — et sans
     * expédition, le joueur ne trouverait jamais la terre où semer.
     */
    public function testLaDotationPermetDePartirEnReconnaissance(): void
    {
        $recu = DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources();

        self::assertGreaterThan(0, $recu[Ressource::Ble->value]);
    }

    /**
     * Le pharaon dote la famille qu'il envoie, pas une population théorique :
     * une maisonnée qui mange le double part avec le double de grain.
     */
    public function testLesVivresSuiventLaFamilleEnvoyee(): void
    {
        $petite = DotationRoyale::pour(0, 2)->provisions;
        $grande = DotationRoyale::pour(0, 4)->provisions;

        self::assertSame(2 * $petite, $grande);
        // Une année complète de rations, ni plus ni moins.
        self::assertSame(4 * DateDeJeu::CYCLES_PAR_ANNEE, $grande);
    }

    /**
     * @param array<string, int> $recu
     *
     * @return array<string, int>
     */
    private static function sansLaMonnaie(array $recu): array
    {
        unset($recu[Ressource::Deben->value]);

        return $recu;
    }
}
