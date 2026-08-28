<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\City;
use App\Entity\Zone;
use App\Game\ContenuDeZone;
use App\Game\GenerateurDeCarte;
use App\Game\GeographieDeRegion;
use App\Game\MissionCatalogue;
use App\Game\Ressource;
use App\Game\TypeDeTerrain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * La génération est semi-aléatoire : ses tests portent sur des **invariants**,
 * jamais sur une carte attendue. Un test qui figerait une disposition précise
 * casserait au moindre ajustement de pondération et finirait désactivé.
 *
 * Les rares tests qui ont besoin de reproductibilité sèment le générateur.
 */
#[CoversClass(GenerateurDeCarte::class)]
#[CoversClass(GeographieDeRegion::class)]
final class GenerateurDeCarteTest extends TestCase
{
    public function testLaGrilleRemplitExactementLaTailleDeLaVille(): void
    {
        $ville = new City('Avaris', 0, 5);

        $this->generer($ville, new GeographieDeRegion(nil: true));

        self::assertCount(25, $ville->getZones());
    }

    public function testChaqueCaseEstOccupeeUneSeuleFois(): void
    {
        $ville = new City('Avaris', 0, 4);

        $this->generer($ville, new GeographieDeRegion(nil: true, mediterranee: true));

        $positions = [];
        foreach ($ville->getZones() as $zone) {
            $positions[] = $zone->getX().','.$zone->getY();
        }

        self::assertCount(16, array_unique($positions));
    }

    public function testLaMediterraneeBordeToujoursLeNord(): void
    {
        $ville = new City('Avaris', 0, 4);

        $this->generer($ville, new GeographieDeRegion(mediterranee: true));

        foreach ($this->zonesDeLaLigne($ville, 0) as $zone) {
            self::assertSame(TypeDeTerrain::Mediterranee, $zone->getTerrain());
        }
    }

    public function testLaMerRougeBordeToujoursLEst(): void
    {
        $ville = new City('Mersa Gaouasis', 2, 4);

        $this->generer($ville, new GeographieDeRegion(merRouge: true, desert: true));

        foreach ($ville->getZones() as $zone) {
            if (3 === $zone->getX()) {
                self::assertSame(TypeDeTerrain::MerRouge, $zone->getTerrain());
            }
        }
    }

    public function testSansGeographieParticuliereToutEstFertile(): void
    {
        $ville = new City('Nulle part', 0, 3);

        $this->generer($ville, new GeographieDeRegion());

        foreach ($ville->getZones() as $zone) {
            self::assertSame(TypeDeTerrain::Fertile, $zone->getTerrain());
        }
    }

    public function testUneOasisEstToujoursPoseeDansLeDesert(): void
    {
        $ville = new City('Shedet', 7, 5);

        $this->generer($ville, new GeographieDeRegion(desert: true, oasis: true));

        $oasis = array_filter(
            $ville->getZones()->toArray(),
            static fn (Zone $z): bool => TypeDeTerrain::Oasis === $z->getTerrain(),
        );

        self::assertNotEmpty($oasis, 'Une région à oasis doit en porter une.');
    }

    /**
     * L'invariant le plus important du doc 02 : une ville s'installe toujours
     * près de l'eau quand il y en a, jamais en plein désert.
     */
    #[DataProvider('geographiesAvecEau')]
    public function testLaVilleTouchetoujoursLEauQuandIlYEnA(GeographieDeRegion $geographie): void
    {
        // Répété : le placement est aléatoire, un seul tirage ne prouverait rien.
        for ($graine = 1; $graine <= 20; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, $geographie, $graine);

            self::assertTrue(
                $ville->jouxteUnPointDEau(),
                \sprintf('Ville isolée de l\'eau avec la graine %d.', $graine),
            );
        }
    }

    /**
     * @return iterable<string, array{GeographieDeRegion}>
     */
    public static function geographiesAvecEau(): iterable
    {
        yield 'Nil seul' => [new GeographieDeRegion(nil: true)];
        yield 'Nil et Méditerranée' => [new GeographieDeRegion(nil: true, mediterranee: true)];
        yield 'mer Rouge et désert' => [new GeographieDeRegion(merRouge: true, desert: true)];
    }

    public function testLaVilleNEstJamaisPlaceeDansLEauNiDansLeDesert(): void
    {
        for ($graine = 1; $graine <= 20; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, new GeographieDeRegion(nil: true, desert: true), $graine);

            $centre = $ville->zoneDeLaVille();
            self::assertInstanceOf(Zone::class, $centre);
            self::assertFalse($centre->getTerrain()->estUnPointDEau());
            self::assertNotSame(TypeDeTerrain::Desert, $centre->getTerrain());
        }
    }

    /**
     * Le Nil a priorité sur la Méditerranée : quand une région porte les deux,
     * la ville doit border le fleuve, pas seulement « de l'eau » en général.
     */
    public function testLaVilleBordeLeNilEnPrioriteSurLaMediterranee(): void
    {
        for ($graine = 1; $graine <= 20; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, new GeographieDeRegion(nil: true, mediterranee: true), $graine);

            $centre = $ville->zoneDeLaVille();
            self::assertInstanceOf(Zone::class, $centre);

            $bordeLeNil = false;
            foreach ($ville->getZones() as $zone) {
                if (TypeDeTerrain::Nil === $zone->getTerrain() && $zone->estAdjacenteA($centre)) {
                    $bordeLeNil = true;
                    break;
                }
            }

            self::assertTrue($bordeLeNil, \sprintf('Ville pas au bord du Nil, graine %d.', $graine));
        }
    }

    public function testTouteCarteACompteExactementUneVille(): void
    {
        $ville = new City('Avaris', 0, 4);

        $this->generer($ville, new GeographieDeRegion(nil: true, mediterranee: true));

        $villes = array_filter(
            $ville->getZones()->toArray(),
            static fn (Zone $z): bool => $z->porteLaVille(),
        );

        self::assertCount(1, $villes);
    }

    public function testLaCaseDeLaVilleEstDecouverteEtVide(): void
    {
        $ville = new City('Avaris', 0, 3);

        $this->generer($ville, new GeographieDeRegion(nil: true));
        $centre = $ville->zoneDeLaVille();

        self::assertInstanceOf(Zone::class, $centre);
        self::assertTrue($centre->estDecouverte(), 'On est déjà sur place.');
        self::assertSame(ContenuDeZone::Rien, $centre->getContenu());
    }

    public function testToutesLesAutresCasesSontSousLeBrouillard(): void
    {
        $ville = new City('Avaris', 0, 4);

        $this->generer($ville, new GeographieDeRegion(nil: true, mediterranee: true));

        foreach ($ville->getZones() as $zone) {
            if (!$zone->porteLaVille()) {
                self::assertFalse($zone->estDecouverte());
            }
        }
    }

    public function testLesGisementsRespectentLaGeologieDeLaRegion(): void
    {
        $geographie = new GeographieDeRegion(
            nil: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux],
        );
        $ville = new City('Avaris', 0, 5);

        $this->generer($ville, $geographie);

        foreach ($ville->getZones() as $zone) {
            // Le poisson est la seule ressource des cases d'eau (doc 02).
            $attendues = $zone->getTerrain()->estUnPointDEau()
                ? [Ressource::Poisson]
                : [Ressource::Argile, Ressource::Roseaux];

            foreach ($zone->getGisements() as $gisement) {
                self::assertContains($gisement->getRessource(), $attendues);
            }
        }
    }

    public function testUnChampNEstEligibleQueSurUnTerrainQuiLAccepte(): void
    {
        $ville = new City('Avaris', 0, 5);

        $this->generer($ville, new GeographieDeRegion(nil: true, desert: true, ressourcesDeZone: [Ressource::Argile]));

        foreach ($ville->getZones() as $zone) {
            if (ContenuDeZone::ChampEligible === $zone->getContenu()) {
                self::assertTrue(
                    $zone->getTerrain()->accepteUnChamp(),
                    \sprintf('Champ éligible sur un terrain qui le refuse : %s.', $zone->getTerrain()->value),
                );
            }
        }
    }

    /**
     * Une case qui aurait pu porter un champ mais n'en tire pas un doit rester
     * identifiable comme telle (« terre fertile, non cultivable ») plutôt que
     * de se fondre dans le « rien » générique du désert ou de la mer.
     */
    public function testUneTerreFertileSansChampEstMarqueeNonCultivable(): void
    {
        $trouvee = false;

        for ($graine = 1; $graine <= 30 && !$trouvee; ++$graine) {
            $ville = new City('Avaris', 0, 5);
            $this->generer($ville, new GeographieDeRegion(nil: true, ressourcesDeZone: [Ressource::Argile]), $graine);

            foreach ($ville->getZones() as $zone) {
                if (ContenuDeZone::TerreNonCultivable === $zone->getContenu()) {
                    self::assertTrue($zone->getTerrain()->accepteUnChamp());
                    $trouvee = true;
                    break;
                }
            }
        }

        self::assertTrue($trouvee, 'Aucune terre non cultivable rencontrée en 30 graines.');
    }

    /**
     * Un matériau non alimentaire garanti près de la ville (anneau des 8
     * cases) ne s'y trouve qu'en un seul exemplaire : sur une carte assez
     * grande pour laisser le choix, rien ne doit forcer un doublon local
     * (décision de la joueuse — « éviter d'avoir directement tout »).
     */
    public function testLAnneauProcheNePorteJamaisDeuxFoisLeMemeMateriauSurUneGrandeCarte(): void
    {
        $region = new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );

        for ($graine = 1; $graine <= 30; ++$graine) {
            $ville = new City('Thèbes', 0, 8);
            $this->generer($ville, $region, $graine);

            $centre = $ville->zoneDeLaVille();
            self::assertInstanceOf(Zone::class, $centre);

            $vus = [];
            foreach ($ville->getZones() as $zone) {
                if ($zone->porteLaVille() || !$zone->estAdjacenteA($centre)) {
                    continue;
                }

                foreach ($zone->getGisements() as $gisement) {
                    if ($gisement->getRessource()->estNourriture()) {
                        continue;
                    }

                    $valeur = $gisement->getRessource()->value;
                    self::assertArrayNotHasKey($valeur, $vus, \sprintf('%s en double près de la ville, graine %d.', $valeur, $graine));
                    $vus[$valeur] = true;
                }
            }
        }
    }

    public function testUneMemeGraineProduitLaMemeCarte(): void
    {
        $premiere = new City('Avaris', 0, 4);
        $seconde = new City('Avaris', 0, 4);
        $geographie = new GeographieDeRegion(nil: true, mediterranee: true, ressourcesDeZone: [Ressource::Argile]);

        $this->generer($premiere, $geographie, graine: 42);
        $this->generer($seconde, $geographie, graine: 42);

        self::assertSame($this->empreinte($premiere), $this->empreinte($seconde));
    }

    public function testDeuxGrainesDifferentesDonnentDesCartesDifferentes(): void
    {
        $premiere = new City('Avaris', 0, 5);
        $seconde = new City('Avaris', 0, 5);
        $geographie = new GeographieDeRegion(nil: true, mediterranee: true, ressourcesDeZone: [Ressource::Argile]);

        $this->generer($premiere, $geographie, graine: 1);
        $this->generer($seconde, $geographie, graine: 2);

        self::assertNotSame(
            $this->empreinte($premiere),
            $this->empreinte($seconde),
            'Deux parties dans la même région ne doivent pas se ressembler.',
        );
    }

    public function testLesDixMissionsSeGenerentSansExploser(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            $ville = new City($mission->ville, $mission->difficulte, $mission->tailleDeGrille());

            $this->generer($ville, $mission->geographie);

            $attendu = $mission->tailleDeGrille() ** 2;
            self::assertCount($attendu, $ville->getZones(), \sprintf('Mission %d.', $mission->numero));
            self::assertNotNull($ville->zoneDeLaVille(), \sprintf('Mission %d sans ville.', $mission->numero));
        }
    }

    /**
     * L'invariant qui rend une ville bâtissable : presque tous les bâtiments
     * sont en brique crue, et **rien ne se substitue à l'argile**. Un tirage
     * qui n'en poserait aucune condamnerait la partie dès le deuxième
     * bâtiment — c'est exactement ce qui est arrivé en jeu.
     */
    #[DataProvider('materiauxIndispensables')]
    public function testUneRegionGarantitToujoursSesMateriauxVitaux(Ressource $materiau): void
    {
        $delta = new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );

        // Répété : le contenu est tiré au sort, un seul tirage ne prouverait rien.
        for ($graine = 1; $graine <= 30; ++$graine) {
            $ville = new City('Avaris', 0, 3);
            $this->generer($ville, $delta, $graine);

            self::assertNotEmpty(
                $this->gisementsDe($ville, $materiau),
                \sprintf('Aucun gisement de %s avec la graine %d : la ville serait imbâtissable.', $materiau->libelle(), $graine),
            );
        }
    }

    /**
     * Les deux matériaux dont rien ne tient lieu : la brique crue et le roseau
     * qui couvre les toits. Sans l'un ou l'autre, la partie se fige au deuxième
     * bâtiment.
     *
     * @return iterable<string, array{Ressource}>
     */
    public static function materiauxIndispensables(): iterable
    {
        yield 'argile' => [Ressource::Argile];
        yield 'roseaux' => [Ressource::Roseaux];
    }

    /**
     * Le limon se dépose sur les berges à chaque crue (doc 08) : l'argile
     * garantie doit donc être au bord de l'eau, pas au fond du désert.
     */
    #[DataProvider('materiauxIndispensables')]
    public function testLesMateriauxGarantisBordentLEau(Ressource $materiau): void
    {
        $delta = new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );

        for ($graine = 1; $graine <= 30; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, $delta, $graine);

            $riveraine = false;
            foreach ($this->gisementsDe($ville, $materiau) as $gisement) {
                foreach ($ville->getZones() as $voisine) {
                    if ($voisine->getTerrain()->estUnPointDEau() && $voisine->estAdjacenteA($gisement)) {
                        $riveraine = true;
                        break 2;
                    }
                }
            }

            self::assertTrue($riveraine, \sprintf('%s loin de toute eau, graine %d.', $materiau->libelle(), $graine));
        }
    }

    /**
     * On ne trouve jamais tout au même endroit : une case porte au plus deux
     * gisements, sans quoi explorer cesserait d'avoir un intérêt.
     */
    public function testUneCaseNePorteJamaisPlusDeDeuxGisements(): void
    {
        $riche = new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire, Ressource::Natron],
        );

        for ($graine = 1; $graine <= 30; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, $riche, $graine);

            foreach ($ville->getZones() as $zone) {
                self::assertLessThanOrEqual(
                    Zone::GISEMENTS_MAX,
                    $zone->getGisements()->count(),
                    \sprintf('Case (%d, %d) trop riche, graine %d.', $zone->getX(), $zone->getY(), $graine),
                );
            }
        }
    }

    /**
     * Une case ne porte jamais deux fois le même matériau : ce serait un filon
     * en double, pas une case plus riche.
     */
    public function testUneCaseNePorteJamaisDeuxFoisLeMemeMateriau(): void
    {
        for ($graine = 1; $graine <= 20; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, new GeographieDeRegion(
                nil: true,
                ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux],
            ), $graine);

            foreach ($ville->getZones() as $zone) {
                $vues = [];
                foreach ($zone->getGisements() as $gisement) {
                    $vues[] = $gisement->getRessource()->value;
                }

                self::assertSame(array_unique($vues), $vues, \sprintf('Doublon en (%d, %d).', $zone->getX(), $zone->getY()));
            }
        }
    }

    /**
     * @return list<Zone>
     */
    private function gisementsDe(City $ville, Ressource $ressource): array
    {
        return array_values(array_filter(
            $ville->getZones()->toArray(),
            static fn (Zone $z): bool => null !== $z->gisementDe($ressource),
        ));
    }

    /**
     * Toute carte porte un minimum de champs et de poisson, en fonction de la
     * région : sans terre à semer ni eau à pêcher, le Grenier et le Port
     * resteraient des bâtiments sans objet.
     */
    public function testUneRegionAvecEauEtTerreGarantitChampsEtPoisson(): void
    {
        $delta = new GeographieDeRegion(
            nil: true,
            mediterranee: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Calcaire],
        );

        for ($graine = 1; $graine <= 20; ++$graine) {
            $ville = new City('Avaris', 0, 4);
            $this->generer($ville, $delta, $graine);

            $champs = 0;
            $poissonneuses = 0;

            foreach ($ville->getZones() as $zone) {
                if (ContenuDeZone::ChampEligible === $zone->getContenu()) {
                    ++$champs;
                }

                if (null !== $zone->gisementDe(Ressource::Poisson)) {
                    ++$poissonneuses;
                }
            }

            self::assertGreaterThanOrEqual(1, $champs, \sprintf('Aucun champ, graine %d.', $graine));
            self::assertGreaterThanOrEqual(1, $poissonneuses, \sprintf('Aucun poisson, graine %d.', $graine));
        }
    }

    /**
     * Une région sans eau ne peut évidemment porter aucun poisson : la garantie
     * ne doit rien inventer qui n'ait pas de sens géographique.
     */
    public function testUneRegionSansEauNePorteJamaisDePoisson(): void
    {
        $sansEau = new GeographieDeRegion(desert: true, oasis: true, ressourcesDeZone: [Ressource::Argile]);

        for ($graine = 1; $graine <= 10; ++$graine) {
            $ville = new City('Test', 5, 4);
            $this->generer($ville, $sansEau, $graine);

            foreach ($ville->getZones() as $zone) {
                self::assertNull($zone->gisementDe(Ressource::Poisson), \sprintf('Graine %d.', $graine));
            }
        }
    }

    /**
     * Chaque ressource de la région apparaît au moins une fois sur la carte —
     * pas seulement les deux matériaux vitaux.
     */
    public function testChaqueRessourceDeLaRegionApparaitAuMoinsUneFois(): void
    {
        $region = new GeographieDeRegion(
            nil: true,
            desert: true,
            ressourcesDeZone: [Ressource::Argile, Ressource::Roseaux, Ressource::Gres, Ressource::Calcaire, Ressource::Granite],
        );

        for ($graine = 1; $graine <= 15; ++$graine) {
            $ville = new City('Thèbes', 4, 5);
            $this->generer($ville, $region, $graine);

            foreach ($region->ressourcesDeZone as $materiau) {
                $trouve = false;

                foreach ($ville->getZones() as $zone) {
                    if (null !== $zone->gisementDe($materiau)) {
                        $trouve = true;
                        break;
                    }
                }

                self::assertTrue($trouve, \sprintf('%s absent, graine %d.', $materiau->libelle(), $graine));
            }
        }
    }

    /**
     * Régression : sur le Delta (3×3, la plus petite carte du jeu, mission 1),
     * matériaux, poisson et champs se disputaient les mêmes cases riveraines.
     * Une carte est déjà sortie sans un seul champ. Ce test rejoue exactement
     * la géographie de la mission 1, sur un grand nombre de graines.
     */
    public function testLaMission1NeSortJamaisSansChampNiPoisson(): void
    {
        $delta = (new MissionCatalogue())->get(1)->geographie;

        for ($graine = 1; $graine <= 50; ++$graine) {
            $ville = new City('Avaris', 0, 3);
            $this->generer($ville, $delta, $graine);

            $champs = 0;
            $poisson = 0;

            foreach ($ville->getZones() as $zone) {
                if (ContenuDeZone::ChampEligible === $zone->getContenu()) {
                    ++$champs;
                }

                if (null !== $zone->gisementDe(Ressource::Poisson)) {
                    ++$poisson;
                }
            }

            self::assertGreaterThanOrEqual(1, $champs, \sprintf('Aucun champ sur le Delta, graine %d.', $graine));
            self::assertGreaterThanOrEqual(1, $poisson, \sprintf('Aucun poisson sur le Delta, graine %d.', $graine));
        }
    }

    private function generer(City $ville, GeographieDeRegion $geographie, ?int $graine = null): void
    {
        $hasard = null === $graine ? new Randomizer() : new Randomizer(new Mt19937($graine));

        (new GenerateurDeCarte($hasard))->peupler($ville, $geographie);
    }

    /**
     * @return list<Zone>
     */
    private function zonesDeLaLigne(City $ville, int $y): array
    {
        return array_values(array_filter(
            $ville->getZones()->toArray(),
            static fn (Zone $z): bool => $z->getY() === $y,
        ));
    }

    private function empreinte(City $ville): string
    {
        $lignes = [];
        foreach ($ville->getZones() as $zone) {
            $lignes[] = \sprintf(
                '%d,%d,%s,%s,%s,%d',
                $zone->getX(),
                $zone->getY(),
                $zone->getTerrain()->value,
                $zone->getContenu()->value,
                implode('+', array_map(
                    static fn ($g): string => $g->getRessource()->value,
                    $zone->getGisements()->toArray(),
                )) ?: '-',
                $zone->porteLaVille() ? 1 : 0,
            );
        }

        return implode('|', $lignes);
    }
}
