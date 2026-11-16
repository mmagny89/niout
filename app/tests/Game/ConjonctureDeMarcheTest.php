<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\AvantageDeNegoce;
use App\Game\BancDEssaiDuMonde;
use App\Game\CataloguePartenaires;
use App\Game\ConjonctureDeMarche;
use App\Game\EvenementDeMarche;
use App\Game\QualiteDeCrue;
use App\Game\Ressource;
use PHPUnit\Framework\TestCase;

/**
 * La conjoncture des marchés : pure, déterministe, bornée (`docs/plan-pnj-palier-a.md` § 10).
 */
final class ConjonctureDeMarcheTest extends TestCase
{
    private ConjonctureDeMarche $conjoncture;
    private CataloguePartenaires $catalogue;

    protected function setUp(): void
    {
        $this->conjoncture = new ConjonctureDeMarche();
        $this->catalogue = new CataloguePartenaires();
    }

    public function testMemeGraineMemeQuinzaineMemeResultat(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];

        for ($cycle = 0; $cycle < 60; ++$cycle) {
            self::assertEquals(
                $this->conjoncture->calculer(42, QualiteDeCrue::Normale, $delta, Ressource::Ble, $cycle),
                $this->conjoncture->calculer(42, QualiteDeCrue::Normale, $delta, Ressource::Ble, $cycle),
            );
        }
    }

    public function testDeuxGrainesFontDeuxMondes(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];
        $differences = 0;

        for ($cycle = 0; $cycle < 60; ++$cycle) {
            if ($this->conjoncture->calculer(1, QualiteDeCrue::Normale, $delta, Ressource::Lin, $cycle)->points
                !== $this->conjoncture->calculer(2, QualiteDeCrue::Normale, $delta, Ressource::Lin, $cycle)->points) {
                ++$differences;
            }
        }

        self::assertGreaterThan(10, $differences);
    }

    public function testUneMarchandiseQueLaCiteNeTraitePasNeBougePas(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];

        for ($cycle = 0; $cycle < 30; ++$cycle) {
            self::assertSame(0, $this->conjoncture->calculer(7, QualiteDeCrue::Forte, $delta, Ressource::Encens, $cycle)->points);
        }
    }

    public function testLaMeteoNeSautePasD(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];

        for ($cycle = 0; $cycle < 100; ++$cycle) {
            $a = $this->conjoncture->calculer(9, QualiteDeCrue::Normale, $delta, Ressource::Lin, $cycle)->meteo;
            $b = $this->conjoncture->calculer(9, QualiteDeCrue::Normale, $delta, Ressource::Lin, $cycle + 1)->meteo;

            // Un maillage de quatre quinzaines, au plus vingt points d'écart : jamais plus de cinq d'un cran.
            self::assertLessThanOrEqual(5, abs($a - $b));
        }
    }

    public function testUneCrueForteGonfleLesVivresDuFleuve(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];
        self::assertTrue($delta->vendDe(Ressource::Ble));

        // Même quinzaine, même graine : seule la crue change.
        $forte = $this->conjoncture->calculer(5, QualiteDeCrue::Forte, $delta, Ressource::Ble, 3);
        $faible = $this->conjoncture->calculer(5, QualiteDeCrue::Faible, $delta, Ressource::Ble, 3);

        self::assertSame(2 * ConjonctureDeMarche::POIDS_DE_LA_CRUE, $forte->saisonEtCrue - $faible->saisonEtCrue);
    }

    public function testLesEvenementsExistentEtDurentDeuxASixQuinzaines(): void
    {
        $delta = $this->catalogue->pourMemphis(null)[0];
        $vus = [];

        foreach ([3, 11, 29, 53] as $graine) {
            $enCours = null;
            $longueur = 0;

            for ($cycle = 0; $cycle < 400; ++$cycle) {
                $evenement = $this->conjoncture->evenementEnCours($graine, $delta->cle, $cycle);

                if ($evenement === $enCours && null !== $evenement) {
                    ++$longueur;
                    continue;
                }

                if (null !== $enCours) {
                    self::assertGreaterThanOrEqual(ConjonctureDeMarche::DUREE_MIN, $longueur);
                    self::assertLessThanOrEqual(ConjonctureDeMarche::DUREE_MAX, $longueur);
                }

                $enCours = $evenement;
                $longueur = null === $evenement ? 0 : 1;

                if (null !== $evenement) {
                    $vus[$evenement->value] = true;
                }
            }
        }

        self::assertCount(\count(EvenementDeMarche::cases()), $vus, 'Chaque événement doit apparaître sur 4 graines et 400 quinzaines.');
    }

    public function testUneDisetteFavoriseCeQuonVendEtDesavantageCeQuIlVend(): void
    {
        $byblos = $this->catalogue->pourLaMission(1)[0];
        $achete = $byblos->achete[0];

        if (\App\Game\FamilleDeRessource::Vivres === $achete->famille()) {
            self::assertSame(EvenementDeMarche::POIDS, EvenementDeMarche::Disette->pointsSur($byblos, $achete));
        }

        // Sur ce qu'il vend en vivres, la disette joue contre nous.
        foreach ($byblos->vend as $ressource) {
            if (\App\Game\FamilleDeRessource::Vivres === $ressource->famille()) {
                self::assertSame(-EvenementDeMarche::POIDS, EvenementDeMarche::Disette->pointsSur($byblos, $ressource));
            }
        }
    }

    public function testLAvantageSigneRogneSansJamaisDepasserLesBornes(): void
    {
        // Renommée pleine (20) et monde à −20 : l'acquis est mangé, pas davantage.
        self::assertSame(0, AvantageDeNegoce::total(100, 0, -20));
        self::assertSame(-20, AvantageDeNegoce::total(0, 0, -20));
        self::assertSame(AvantageDeNegoce::PLAFOND_TOTAL, AvantageDeNegoce::total(100, 25, 20));
        // Un autre que le monde ne peut pas être négatif.
        self::assertSame(AvantageDeNegoce::deLaRenommee(50), AvantageDeNegoce::total(50, -5));
    }

    public function testLeBancDEssaiTientLesBornesSurToutLeCatalogue(): void
    {
        $banc = new BancDEssaiDuMonde($this->catalogue, $this->conjoncture);
        $rapport = $banc->mesurer([1, 7920, 15839], 200);

        self::assertSame([], $rapport['violations']);
        self::assertGreaterThanOrEqual(-ConjonctureDeMarche::AMPLITUDE, $rapport['minimum']);
        self::assertLessThanOrEqual(ConjonctureDeMarche::AMPLITUDE, $rapport['maximum']);
        self::assertLessThanOrEqual(AvantageDeNegoce::PLAFOND_TOTAL, $rapport['avantageMaximal']);
        // Le monde n'élargit jamais l'arbitrage : la marge reste sous celle du plafond sans monde.
        self::assertGreaterThan(0, $rapport['arbitrageDeReference']);
        self::assertLessThanOrEqual($rapport['arbitrageDeReference'], $rapport['arbitrageMaximal']);
        // Le monde bouge : sans cela, il ne serait que décor.
        self::assertGreaterThan(0, $rapport['nonNulles']);
        self::assertNotSame([], $rapport['evenements']);
    }
}
