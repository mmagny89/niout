<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Zone;
use App\Game\CatalogueDeLaVille;
use App\Game\DotationRoyale;
use App\Game\OffreDeConstruction;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogueDeLaVille::class)]
#[CoversClass(OffreDeConstruction::class)]
final class CatalogueDeLaVilleTest extends TestCase
{
    public function testLeCatalogueCouvreTousLesBatimentsConstructibles(): void
    {
        $offres = (new CatalogueDeLaVille())->pour($this->villeDotee());

        self::assertCount(\count(TypeDeBatiment::constructibles()), $offres);
    }

    public function testUnBatimentFinancableEstRealisable(): void
    {
        // Entrepôt : 20 roseaux, 10 argile, 15 deben — exactement la dotation royale.
        $offre = $this->offrePour($this->villeDotee(), TypeDeBatiment::Entrepot);

        self::assertTrue($offre->estRealisable());
        self::assertNull($offre->empechement);
    }

    public function testUnBatimentTropCherEstEmpecheAvecLeDetailDuManque(): void
    {
        // Caserne : 20 roseaux, 30 argile, 40 deben (doc 01). Cette ville-ci n'a que
        // 10 d'argile : il lui en manque 20, et le message doit le dire.
        $pauvre = (new City('Avaris', 0, 3))->crediterRessources([
            Ressource::Deben->value => 999,
            Ressource::Roseaux->value => 999,
            Ressource::Argile->value => 10,
        ]);

        $offre = $this->offrePour($pauvre, TypeDeBatiment::Caserne);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('20 argile', $offre->empechement);
    }

    public function testLePortEstEmpecheFauteDeCarte(): void
    {
        $offre = $this->offrePour($this->villeRiche(), TypeDeBatiment::Port);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('point d\'eau', $offre->empechement);
    }

    /**
     * Seule la géographie peut désormais empêcher le Port : la pêche qu'il
     * débloque existe depuis le lot 3.6, il n'y a plus de raison de le retenir
     * sur une ville qui borde bien l'eau et qui en a les moyens.
     */
    public function testLePortEstConstructibleQuandLaVilleBordeLEau(): void
    {
        $offre = $this->offrePour($this->villeAuBordDuNil(), TypeDeBatiment::Port);

        self::assertTrue($offre->estRealisable(), $offre->empechement ?? '');
        self::assertNull($offre->empechement);
    }

    public function testLeTempleEstEmpecheFauteDeLin(): void
    {
        $offre = $this->offrePour($this->villeRiche(), TypeDeBatiment::Temple);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('lin', $offre->empechement);
    }

    public function testUnBatimentDejaDressePropoSeUneAmelioration(): void
    {
        $ville = $this->villeRiche();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));

        $offre = $this->offrePour($ville, TypeDeBatiment::Grenier);

        self::assertTrue($offre->estDejaDressé());
        self::assertSame('Améliorer', $offre->libelleDeLAction());
    }

    public function testUnBatimentAuPlafondNePropoSePlusRien(): void
    {
        $ville = $this->villeRiche();
        // Difficulté 0 : plafond régional 5, que le Grenier atteint ici.
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, niveau: 5));

        $offre = $this->offrePour($ville, TypeDeBatiment::Grenier);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('Niveau maximal', $offre->empechement);
    }

    private function offrePour(City $ville, TypeDeBatiment $type): OffreDeConstruction
    {
        foreach ((new CatalogueDeLaVille())->pour($ville) as $offre) {
            if ($offre->type === $type) {
                return $offre;
            }
        }

        self::fail(\sprintf('Aucune offre pour le %s.', $type->libelle()));
    }

    /**
     * Ville au départ d'une campagne : la dotation royale, rien de plus.
     */
    private function villeDotee(): City
    {
        return (new City('Avaris', 0, 3))->crediterRessources(DotationRoyale::pour(0)->enRessources());
    }

    /**
     * Une ville riche dont la case jouxte le fleuve — la seule condition que
     * le Port pose encore.
     */
    private function villeAuBordDuNil(): City
    {
        $ville = $this->villeRiche();

        $centre = new Zone($ville, 1, 1, TypeDeTerrain::Fertile);
        $centre->yPlacerLaVille();

        $ville->ajouterZone($centre);
        $ville->ajouterZone(new Zone($ville, 1, 0, TypeDeTerrain::Nil));

        return $ville;
    }

    private function villeRiche(): City
    {
        return (new City('Avaris', 0, 3))->crediterRessources([
            Ressource::Deben->value => 9999,
            Ressource::Roseaux->value => 9999,
            // Les deux maçonneries : la brique crue pour presque tout, la
            // pierre de taille pour le Temple et le Port.
            Ressource::Argile->value => 9999,
            Ressource::Calcaire->value => 9999,
        ]);
    }
}
