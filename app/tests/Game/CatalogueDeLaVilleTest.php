<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Game\CatalogueDeLaVille;
use App\Game\OffreDeConstruction;
use App\Game\TypeDeBatiment;
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
        // Entrepôt : 20 bois, 10 pierre, 15 or — exactement la dotation royale.
        $offre = $this->offrePour($this->villeDotee(), TypeDeBatiment::Entrepot);

        self::assertTrue($offre->estRealisable());
        self::assertNull($offre->empechement);
    }

    public function testUnBatimentTropCherEstEmpecheAvecLeDetailDuManque(): void
    {
        // Caserne : 20 bois, 30 pierre, 40 or. La dotation n'en couvre que 10
        // de pierre — il en manque 20.
        $offre = $this->offrePour($this->villeDotee(), TypeDeBatiment::Caserne);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('20 pierre', $offre->empechement);
    }

    public function testLePortEstEmpecheFauteDeCarte(): void
    {
        $offre = $this->offrePour($this->villeRiche(), TypeDeBatiment::Port);

        self::assertFalse($offre->estRealisable());
        self::assertNotNull($offre->empechement);
        self::assertStringContainsString('point d\'eau', $offre->empechement);
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
        return (new City('Avaris', 0, 3))->crediter(or: 50, bois: 20, pierre: 10);
    }

    private function villeRiche(): City
    {
        return (new City('Avaris', 0, 3))->crediter(or: 9999, bois: 9999, pierre: 9999);
    }
}
