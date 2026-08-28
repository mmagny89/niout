<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Candidat;
use App\Game\Population;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Candidat::class)]
#[CoversClass(TraitDeCandidat::class)]
#[CoversClass(SpecialiteDeChef::class)]
final class CandidatTest extends TestCase
{
    /**
     * Le barème du doc 03. C'est le seul chiffre de compétence que le joueur
     * doit jamais voir.
     */
    #[DataProvider('baremeDEtoiles')]
    public function testLaCompetenceSeLitEnEtoiles(int $competence, int $etoiles): void
    {
        self::assertSame($etoiles, $this->candidat(competence: $competence)->etoiles());
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function baremeDEtoiles(): iterable
    {
        yield 'le pire possible' => [20, 1];
        yield 'haut de la première étoile' => [36, 1];
        yield 'bas de la deuxième' => [37, 2];
        yield 'trois étoiles' => [60, 3];
        yield 'quatre étoiles' => [84, 4];
        yield 'bas des cinq étoiles' => [85, 5];
        yield 'le meilleur possible' => [100, 5];
    }

    public function testLesCinqNiveauxDEtoilesSontTousAtteignables(): void
    {
        $vus = [];

        for ($competence = 20; $competence <= 100; ++$competence) {
            $vus[$this->candidat(competence: $competence)->etoiles()] = true;
        }

        self::assertSame([1, 2, 3, 4, 5], array_keys($vus));
    }

    /**
     * L'ancienneté ne s'affiche jamais en quinzaines : le joueur doit sentir
     * s'il embauche pour longtemps sans lire un nombre (doc 03).
     */
    public function testLAncienneteSeLitEnLibelle(): void
    {
        self::assertStringContainsString('longtemps', $this->candidat(anciennete: 26)->esperanceDeService());
        self::assertStringContainsString('tenir', $this->candidat(anciennete: 20)->esperanceDeService());
        self::assertStringContainsString('partir', $this->candidat(anciennete: 10)->esperanceDeService());
    }

    public function testLeFoyerAnnonceCeQuIlCouteraANourrir(): void
    {
        $candidat = $this->candidat(agesDesEnfants: [25, 50]);

        self::assertSame(4, $candidat->personnesDuFoyer());
        // Deux adultes à deux demi-rations, deux enfants à une : six.
        self::assertSame(6, $candidat->demiRationsDuFoyer());
    }

    /**
     * Ce qui fait d'une famille nombreuse un investissement plutôt qu'une
     * charge : l'aîné devient un bras avant les autres, et le joueur doit
     * pouvoir le voir avant de choisir.
     */
    public function testLAineAnnonceQuandIlDeviendraUnBras(): void
    {
        $ainePresqueAdulte = Population::AGE_ADULTE_EN_QUINZAINES - 10;
        $candidat = $this->candidat(agesDesEnfants: [5, $ainePresqueAdulte, 100]);

        self::assertSame(10, $candidat->prochainBras());
        self::assertSame([11, 4, 0], $candidat->agesDesEnfantsEnAnnees(), 'De l\'aîné au plus jeune.');
    }

    public function testUnCandidatSansEnfantNAnnonceAucunBrasAVenir(): void
    {
        self::assertNull($this->candidat()->prochainBras());
    }

    /**
     * Les deux couples que le doc 03 interdit, parce que leurs effets se
     * contredisent.
     */
    public function testLesTraitsOpposesSeReconnaissentIncompatibles(): void
    {
        self::assertTrue(TraitDeCandidat::Ambitieux->estIncompatibleAvec(TraitDeCandidat::Fidele));
        self::assertTrue(TraitDeCandidat::Fidele->estIncompatibleAvec(TraitDeCandidat::Ambitieux));
        self::assertTrue(TraitDeCandidat::TravailleurAcharne->estIncompatibleAvec(TraitDeCandidat::Econome));
        self::assertTrue(TraitDeCandidat::Econome->estIncompatibleAvec(TraitDeCandidat::TravailleurAcharne));

        self::assertFalse(TraitDeCandidat::Fidele->estIncompatibleAvec(TraitDeCandidat::Experimente));
        self::assertFalse(TraitDeCandidat::Croyant->estIncompatibleAvec(TraitDeCandidat::Bagarreur));
    }

    /**
     * Deux traits n'ont encore aucun système d'accueil. L'interface doit le
     * dire : promettre un bonus qui ne s'applique nulle part tromperait le
     * joueur au moment même où il compare des candidats.
     */
    public function testDeuxTraitsDormentEnAttendantLeurPhase(): void
    {
        $dormants = array_values(array_filter(
            TraitDeCandidat::cases(),
            static fn (TraitDeCandidat $trait): bool => $trait->dortEnAttendantSaPhase(),
        ));

        self::assertSame([TraitDeCandidat::Croyant, TraitDeCandidat::Bagarreur], $dormants);
    }

    public function testChaqueTraitPorteUnLibelleEtUneDescription(): void
    {
        foreach (TraitDeCandidat::cases() as $trait) {
            self::assertNotSame('', $trait->libelle());
            self::assertNotSame('', $trait->description());
        }

        // Le document tient à ce que le défaut se présente comme une promesse.
        self::assertSame('Débutant prometteur', TraitDeCandidat::Novice->libelle());
    }

    /**
     * Le doc 03 liste des spécialités pour neuf bâtiments et pour eux seuls.
     */
    public function testSeulsLesBatimentsDuDocumentOntUneSpecialite(): void
    {
        $sans = [];

        foreach (TypeDeBatiment::cases() as $batiment) {
            if ([] === SpecialiteDeChef::pour($batiment)) {
                $sans[] = $batiment;
            }
        }

        self::assertSame(
            [TypeDeBatiment::ResidenceFamiliale, TypeDeBatiment::QuartierDHabitation, TypeDeBatiment::Auberge],
            $sans,
        );
    }

    public function testChaqueSpecialiteAppartientAUnSeulBatiment(): void
    {
        $vues = [];

        foreach (TypeDeBatiment::cases() as $batiment) {
            foreach (SpecialiteDeChef::pour($batiment) as $specialite) {
                self::assertArrayNotHasKey($specialite->value, $vues, 'Une spécialité ne sert qu\'un poste.');
                $vues[$specialite->value] = true;
            }
        }

        self::assertCount(\count(SpecialiteDeChef::cases()), $vues, 'Aucune spécialité orpheline.');
    }

    /**
     * Seules les spécialités des trois bâtiments qui produisent déjà quelque
     * chose ont un effet (lot 4.8) ; les autres sont tirées et affichées mais
     * dorment.
     */
    public function testSeulesLesSpecialitesDesBatimentsProductifsAgissent(): void
    {
        $agissantes = array_values(array_filter(
            SpecialiteDeChef::cases(),
            static fn (SpecialiteDeChef $specialite): bool => $specialite->agitDeja(),
        ));

        self::assertSame([
            SpecialiteDeChef::MarcheAcheteur,
            SpecialiteDeChef::MarcheVendeur,
            SpecialiteDeChef::GrenierGestionnaire,
            SpecialiteDeChef::PortPecheur,
        ], $agissantes);
    }

    /**
     * @param list<int> $agesDesEnfants
     */
    private function candidat(int $competence = 50, int $anciennete = 20, array $agesDesEnfants = []): Candidat
    {
        return new Candidat(
            competence: $competence,
            salaire: 8,
            ancienneteProbable: $anciennete,
            traits: [],
            specialite: null,
            adultes: Population::ADULTES_PAR_FOYER,
            agesDesEnfants: $agesDesEnfants,
        );
    }
}
