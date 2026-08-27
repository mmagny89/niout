<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Chantier;
use App\Entity\City;
use App\Game\EtatDEtape;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Chantier::class)]
final class ChantierTest extends TestCase
{
    public function testLaDureeSuitLaFormuleDuDocument(): void
    {
        // dureeBase + niveau visé (doc 01). Grenier : base 1, niveau 1 → 2 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertSame(2, $chantier->getDureeEnCycles());
    }

    public function testUnBatimentDePierreDemandePlusLongtemps(): void
    {
        // Temple : base 3, niveau 1 → 4 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Temple, niveauVise: 1);

        self::assertSame(4, $chantier->getDureeEnCycles());
    }

    public function testUnChantierNeufNEstPasAcheve(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertFalse($chantier->estAcheve());
        self::assertSame(2, $chantier->cyclesRestants());
    }

    public function testDeuxCyclesOrdinairesAchèventUnChantierDeDeuxCycles(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertFalse($chantier->estAcheve());

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertTrue($chantier->estAcheve());
    }

    public function testLaCrueFaitGagnerUnCycleSurUnChantierDeTrois(): void
    {
        // Entrepôt niveau 2 : base 1 + 2 = 3 cycles.
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Entrepot, niveauVise: 2);
        self::assertSame(3, $chantier->getDureeEnCycles());

        // Deux quinzaines de crue valent trois quinzaines ordinaires.
        $chantier->avancerDUnCycle(Saison::Akhet);
        $chantier->avancerDUnCycle(Saison::Akhet);

        self::assertTrue($chantier->estAcheve(), 'La corvée d\'Akhèt doit faire gagner un cycle.');
    }

    public function testLesJoursEpagomenesNAccelerentRien(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        // Aucune saison : les cinq jours hors année n'ont pas de corvée.
        $chantier->avancerDUnCycle(null);

        self::assertFalse($chantier->estAcheve());
        self::assertSame(1, $chantier->cyclesRestants());
    }

    public function testLAvancementSExprimeEnPourcentage(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);
        self::assertSame(0, $chantier->pourcentageDAvancement());

        $chantier->avancerDUnCycle(Saison::Peret);
        self::assertSame(50, $chantier->pourcentageDAvancement());
    }

    public function testLePourcentageNeDepasseJamaisCent(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        for ($i = 0; $i < 10; ++$i) {
            $chantier->avancerDUnCycle(Saison::Akhet);
        }

        self::assertSame(100, $chantier->pourcentageDAvancement());
        self::assertSame(0, $chantier->cyclesRestants());
    }

    public function testUnChantierMontreSesQuatreEtapesDesLePremierJour(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertSame(4, $chantier->nombreDEtapes());
        self::assertCount(4, $chantier->etapes(), 'Les quatre étapes sont visibles en permanence.');
        self::assertSame('Préparation du terrain', $chantier->etapes()[0]['etape']->nom);
    }

    /**
     * Un Grenier de niveau 1 dure deux quinzaines pour quatre étapes : chacune
     * en traverse donc deux. C'est ce que le doc 01 décrit par « les cycles sont
     * répartis proportionnellement entre ces étapes ».
     */
    public function testUnChantierCourtTraverseDeuxEtapesParQuinzaine(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);

        self::assertSame(
            ['Préparation du terrain', 'Fabrication et séchage des briques'],
            array_map(static fn ($etape): string => $etape->nom, $chantier->etapesEnCours()),
        );

        $chantier->avancerDUnCycle(Saison::Peret);

        self::assertSame(
            ['Élévation des murs', 'Finitions'],
            array_map(static fn ($etape): string => $etape->nom, $chantier->etapesEnCours()),
        );
    }

    /**
     * L'invariant qui compte, et qui manquait : **aucune étape ne doit défiler
     * sans jamais s'afficher**. L'ancien affichage n'en montrait qu'une par
     * quinzaine, escamotant le séchage des briques — l'étape qui porte
     * justement l'explication du rythme du jeu.
     */
    #[DataProvider('chantiersDeToutesLongueurs')]
    public function testAucuneEtapeNEstJamaisEscamotee(TypeDeBatiment $type, int $niveauVise, Saison $saison): void
    {
        $chantier = new Chantier($this->ville(), $type, $niveauVise);
        $vues = [];

        while (!$chantier->estAcheve()) {
            foreach ($chantier->etapesEnCours($saison) as $etape) {
                $vues[$etape->nom] = true;
            }

            $chantier->avancerDUnCycle($saison);
        }

        self::assertCount(
            $chantier->nombreDEtapes(),
            $vues,
            \sprintf('Étapes réellement affichées : %s.', implode(', ', array_keys($vues))),
        );
    }

    /**
     * Akhèt est le cas piège : la corvée fait avancer d'1,5 cycle, donc une
     * quinzaine franchit une étape de plus que la vitesse nominale ne le
     * laisserait croire.
     *
     * @return iterable<string, array{TypeDeBatiment, int, Saison}>
     */
    public static function chantiersDeToutesLongueurs(): iterable
    {
        foreach ([Saison::Akhet, Saison::Peret, Saison::Chemou] as $saison) {
            // Le chantier court est l'autre piège : 2 quinzaines pour 4 étapes.
            yield \sprintf('Grenier niveau 1, %s', $saison->libelle()) => [TypeDeBatiment::Grenier, 1, $saison];
            yield \sprintf('Grenier niveau 5, %s', $saison->libelle()) => [TypeDeBatiment::Grenier, 5, $saison];
            yield \sprintf('Temple niveau 1, %s', $saison->libelle()) => [TypeDeBatiment::Temple, 1, $saison];
            yield \sprintf('Marché niveau 3, %s', $saison->libelle()) => [TypeDeBatiment::Marche, 3, $saison];
        }
    }

    public function testUnChantierEnCoursNaJamaisZeroEtapeAMontrer(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 5);

        while (!$chantier->estAcheve()) {
            self::assertNotEmpty($chantier->etapesEnCours(), 'Un chantier travaille forcément à quelque chose.');
            $chantier->avancerDUnCycle(Saison::Peret);
        }
    }

    public function testUnChantierDePierreASesProprresEtapes(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Temple, niveauVise: 1);

        self::assertSame('Extraction et transport de la pierre', $chantier->etapes()[0]['etape']->nom);
    }

    public function testUneEtapeFranchieEstMarqueeTerminee(): void
    {
        $chantier = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);
        $chantier->avancerDUnCycle(Saison::Peret);

        $etats = array_map(static fn (array $rang): EtatDEtape => $rang['etat'], $chantier->etapes());

        self::assertSame(
            [EtatDEtape::Terminee, EtatDEtape::Terminee, EtatDEtape::EnCours, EtatDEtape::EnCours],
            $etats,
        );
    }

    public function testUnNiveauVisePlusGrandQueUnEstUneAmelioration(): void
    {
        $neuf = new Chantier($this->ville(), TypeDeBatiment::Grenier, niveauVise: 1);
        $amelioration = new Chantier($this->ville(), TypeDeBatiment::Marche, niveauVise: 2);

        self::assertFalse($neuf->estUneAmelioration());
        self::assertTrue($amelioration->estUneAmelioration());
    }

    private function ville(): City
    {
        return new City('Avaris', 0, 3);
    }
}
