<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\DateDeJeu;
use App\Game\DotationRoyale;
use App\Game\Population;
use App\Game\Ressource;
use App\Game\Salaires;
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
        // Ce que réclament les quatre bâtiments d'ouverture, plus l'année de
        // salaires que le pharaon avance (lot 4.6), plus 10 par niveau de
        // difficulté — une mission plus rude appelle un soutien plus consistant.
        $ouverture = DotationRoyale::coutDesBatimentsDouverture()[Ressource::Deben->value];
        $salaires = Population::ACTIFS_AU_DEPART * Salaires::SALAIRE_DUN_TRAVAILLEUR * DateDeJeu::CYCLES_PAR_ANNEE;
        $base = $ouverture + $salaires;

        yield 'Delta, la plus clémente' => [0, $base];
        yield 'difficulté moyenne' => [5, $base + 50];
        yield 'Sinaï, la plus rude' => [9, $base + 90];
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
     * Chacun des quatre bâtiments d'ouverture doit être à portée dès la
     * première quinzaine (décision de la joueuse).
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
        yield 'Quartier d\'habitation' => [TypeDeBatiment::QuartierDHabitation];
        yield 'Grenier' => [TypeDeBatiment::Grenier];
        yield 'Marché' => [TypeDeBatiment::Marche];
        yield 'Entrepôt' => [TypeDeBatiment::Entrepot];
    }

    /**
     * **L'invariant du lot** (décision de la joueuse) : les quatre bâtiments
     * d'ouverture doivent pouvoir être dressés **ensemble**, pas seulement
     * chacun de son côté. C'est ce qui fait rouler la ville dès la première
     * quinzaine — elle loge ses volontaires, conserve sa moisson, vend son
     * surplus et pourra commercer.
     *
     * Les vérifier un par un ne dirait rien : quatre bâtiments à portée
     * séparément peuvent parfaitement être hors d'atteinte cumulés.
     */
    public function testLesQuatreBatimentsDOuvertureSeDressentEnsemble(): void
    {
        $recu = DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources();

        foreach (DotationRoyale::coutDesBatimentsDouverture() as $valeur => $quantite) {
            self::assertGreaterThanOrEqual(
                $quantite,
                $recu[$valeur] ?? 0,
                \sprintf('%s pour les quatre bâtiments réunis.', Ressource::from($valeur)->libelle()),
            );
        }
    }

    /**
     * La dotation ne laisse **aucune marge en matériaux** : elle couvre les
     * quatre bâtiments, et rien de plus. Une largesse ferait de la carrière un
     * ornement pendant les premières quinzaines, alors qu'elle est censée être
     * la première chose qu'on ouvre.
     */
    public function testLaDotationNeDonneRienAuDelaDesQuatreBatiments(): void
    {
        $recu = DotationRoyale::pour(0, self::CONSOMMATION_DE_REFERENCE)->enRessources();
        $ouverture = DotationRoyale::coutDesBatimentsDouverture();

        foreach ($ouverture as $valeur => $quantite) {
            if (Ressource::Deben->value === $valeur) {
                continue;
            }

            self::assertSame($quantite, $recu[$valeur] ?? 0, Ressource::from($valeur)->libelle());
        }
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
