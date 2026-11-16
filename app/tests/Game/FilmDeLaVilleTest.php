<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Fenetre\FilmDeLaVille;
use App\Fenetre\GensDeLaVille;
use App\Game\Choregraphie;
use App\Game\EmplacementsDeLaVille;
use App\Game\ReseauDeRues;
use App\Game\TypeDeFait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Le film de la quinzaine : des points datés, que le navigateur n'a qu'à
 * interpoler. Le serveur décide de tout — lieu, chemin, instant —, donc c'est ici que
 * ça se garde (`docs/plan-pnj-ville-vivante.md` § 8).
 */
#[CoversClass(FilmDeLaVille::class)]
final class FilmDeLaVilleTest extends TestCase
{
    public function testLeMemeFilmALIdentique(): void
    {
        self::assertEquals($this->film(), $this->film());
    }

    /**
     * La forme d'un film : du premier au dernier moment, des instants qui ne
     * reculent jamais, et des positions qui restent dans le visuel.
     */
    public function testLesPointsSontDatesDansLOrdreEtDansLeVisuel(): void
    {
        $film = $this->film($this->faits());

        self::assertSame(60, $film['moments']);
        self::assertLessThanOrEqual(GensDeLaVille::PLAFOND_DE_FIGURES, \count($film['figures']));

        foreach ($film['figures'] as $figure) {
            $points = $figure['points'];

            self::assertSame(0.0, $points[0][0], $figure['cle'].' commence à zéro');
            self::assertSame(60.0, $points[array_key_last($points)][0], $figure['cle'].' finit au dernier moment');

            for ($k = 0; $k < \count($points); ++$k) {
                if ($k > 0) {
                    self::assertGreaterThanOrEqual($points[$k - 1][0], $points[$k][0], $figure['cle']);
                }

                self::assertGreaterThanOrEqual(0.0, $points[$k][1]);
                self::assertLessThanOrEqual(100.0, $points[$k][1]);
                self::assertGreaterThanOrEqual(0.0, $points[$k][2]);
                self::assertLessThanOrEqual(100.0, $points[$k][2]);
                self::assertContains($points[$k][3], [0, 1]);
                self::assertContains($points[$k][4], [0, 1]);
                self::assertContains($points[$k][5], [0, 1]);
            }
        }
    }

    /**
     * Un chef qui passe de chez lui à son poste **marche** : des points intermédiaires,
     * pendant le début du moment, et il arrive à l'instant dit.
     */
    public function testUnChefQuiChangeDeLieuMarchePendantLeDebutDuMoment(): void
    {
        $figure = $this->figure($this->film(), 'chef-1');
        // Aube du jour 1 → matin du jour 1 : du moment 0 au moment 1.
        $marche = array_values(array_filter($figure['points'], static fn (array $p): bool => $p[0] >= 1.0 && $p[0] <= 1.0 + FilmDeLaVille::MARCHE));

        self::assertGreaterThanOrEqual(3, \count($marche), 'Un départ, au moins un carrefour, une arrivée.');
        self::assertSame(1.0, $marche[0][0]);
        self::assertSame(round(1.0 + FilmDeLaVille::MARCHE, 3), $marche[array_key_last($marche)][0]);

        // Les points d'entre-deux sont des carrefours du réseau.
        $carrefours = array_map(
            static fn (array $noeud): array => [
                round(($noeud[0] - GensDeLaVille::LARGEUR_DEBOUT / 2) / EmplacementsDeLaVille::LARGEUR * 100, 2),
                round(($noeud[1] - GensDeLaVille::HAUTEUR_DEBOUT) / EmplacementsDeLaVille::HAUTEUR * 100, 2),
            ],
            array_values(ReseauDeRues::NOEUDS),
        );

        foreach (\array_slice($marche, 1, -1) as $point) {
            self::assertContains([$point[1], $point[2]], $carrefours, 'On marche par les rues.');
        }
    }

    /**
     * Un chef que la quinzaine emporte disparaît, et ne revient pas.
     */
    public function testUnChefPartiDisparaitEtNeRevientPas(): void
    {
        $faits = [['type' => TypeDeFait::ChefParti, 'lieu' => null, 'acteur' => 'chef-1', 'quantite' => null, 'details' => ['libelle' => 'Nebnefer']]];
        $points = $this->figure($this->film($faits), 'chef-1')['points'];
        $disparu = false;

        foreach ($points as $point) {
            if (0 === $point[3]) {
                $disparu = true;
            }

            self::assertFalse($disparu && 1 === $point[3], 'Il ne revient pas.');
        }

        self::assertTrue($disparu);
    }

    /**
     * Un Medjaÿ blessé se couche : un point le dit, au moment où cela arrive.
     */
    public function testUnMedjayBlesseSeCouche(): void
    {
        $faits = [['type' => TypeDeFait::MedjayBlesse, 'lieu' => null, 'acteur' => 'medjay-1', 'quantite' => null, 'details' => ['libelle' => 'Hori']]];
        $points = $this->figure($this->film($faits), 'medjay-1')['points'];

        self::assertNotEmpty(array_filter($points, static fn (array $p): bool => 1 === $p[4]));
        self::assertSame(0, $points[0][4], 'Debout au départ.');
    }

    /**
     * Sans faits, que des gestes de routine : personne ne disparaît, personne ne se
     * couche, et chaque silhouette reste visible tout du long.
     */
    public function testSansFaitPersonneNeDisparaitNiNeSeCouche(): void
    {
        foreach ($this->film()['figures'] as $figure) {
            foreach ($figure['points'] as $point) {
                if (!str_starts_with($figure['cle'], 'rival')) {
                    self::assertSame(1, $point[3], $figure['cle']);
                }

                self::assertSame(0, $point[4], $figure['cle']);
            }
        }
    }

    /**
     * Les silhouettes d'un même lot ne se superposent pas : chacune a sa place.
     */
    public function testDeuxSilhouettesDUnMemeLotNontPasLaMemePlace(): void
    {
        $film = $this->film();
        $parLieu = [];

        // Au matin du premier jour (t = 1,4 : arrivés), trois silhouettes sont au Grenier.
        foreach ($film['figures'] as $figure) {
            if (\in_array($figure['cle'], ['chef-1', 'poste-grenier'], true)) {
                foreach ($figure['points'] as $point) {
                    if (1.4 === $point[0]) {
                        $parLieu[] = $point[1].'/'.$point[2];
                    }
                }
            }
        }

        self::assertCount(3, $parLieu);
        self::assertCount(\count(array_unique($parLieu)), $parLieu);
    }

    /**
     * @param list<array{type: TypeDeFait, lieu: ?string, acteur: ?string, quantite: ?int, details: array<string, mixed>}> $faits
     *
     * @return array<string, mixed>
     */
    private function film(array $faits = []): array
    {
        $photo = [
            'cycle' => 3, 'jours' => 15, 'saison' => 'peret', 'fete' => null,
            'candidats' => [
                ['cle' => 'famille', 'role' => 'chef_de_famille', 'libelle' => 'Famille', 'lieu' => 'residence_familiale', 'couchee' => false],
                ['cle' => 'chef-1', 'role' => 'chef', 'libelle' => 'Chef 1', 'lieu' => 'grenier', 'couchee' => false],
                ['cle' => 'chef-2', 'role' => 'chef', 'libelle' => 'Chef 2', 'lieu' => 'entrepot', 'couchee' => false],
                ['cle' => 'medjay-1', 'role' => 'medjay', 'libelle' => 'Medjaÿ', 'lieu' => 'caserne', 'couchee' => false],
                ['cle' => 'poste-grenier', 'role' => 'travailleur', 'libelle' => 'Travailleur', 'lieu' => 'grenier', 'couchee' => false],
                ['cle' => 'poste-grenier', 'role' => 'travailleur', 'libelle' => 'Travailleur', 'lieu' => 'grenier', 'couchee' => false],
            ],
            'chantiers' => [], 'fabrications' => [], 'convois' => [], 'expeditions' => [],
            'epidemie' => false, 'malades' => 0, 'maisons' => 0, 'chefs' => [], 'medjays' => [], 'rival' => null,
        ];
        $film = Choregraphie::pour($photo, $faits, 7);

        $lots = [];

        foreach (EmplacementsDeLaVille::tous() as $lot) {
            if (null !== $lot['type']) {
                $lots[$lot['type']->value] = $lot;
            }
        }

        return FilmDeLaVille::construire($photo['candidats'], $film['pas'], 15, $lots, 7);
    }

    /**
     * @return list<array{type: TypeDeFait, lieu: ?string, acteur: ?string, quantite: ?int, details: array<string, mixed>}>
     */
    private function faits(): array
    {
        return [
            ['type' => TypeDeFait::ChantierAcheve, 'lieu' => 'grenier', 'acteur' => 'chantier-1', 'quantite' => null, 'details' => []],
            ['type' => TypeDeFait::MedjayBlesse, 'lieu' => null, 'acteur' => 'medjay-1', 'quantite' => null, 'details' => []],
            ['type' => TypeDeFait::ChefParti, 'lieu' => null, 'acteur' => 'chef-2', 'quantite' => null, 'details' => ['libelle' => 'X']],
        ];
    }

    /**
     * @param array<string, mixed> $film
     *
     * @return array{i: int, cle: string, libelle: string, role: string, symbole: string, points: list<array{float, float, float, int, int, int}>}
     */
    private function figure(array $film, string $cle): array
    {
        foreach ($film['figures'] as $figure) {
            if ($figure['cle'] === $cle) {
                return $figure;
            }
        }

        self::fail('Pas de figure '.$cle);
    }
}
