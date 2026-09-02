<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Candidat;
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

    public function testLaMaisonneeAnnonceCeQuElleCouteraANourrir(): void
    {
        $candidat = $this->candidat(inactifs: 2);

        self::assertSame(4, $candidat->personnesAmenees());
        // Deux actifs à deux demi-rations, deux inactifs à une : six.
        self::assertSame(6, $candidat->demiRationsAmenees());
    }

    /**
     * Deux candidats au même salaire ne coûtent pas la même chose : celui qui
     * arrive avec six bouches à charge pèse deux fois plus sur le grenier.
     */
    public function testDeuxCandidatsAuMemeSalaireNontPasLeMemeCout(): void
    {
        $seul = $this->candidat(inactifs: 0);
        $charge = $this->candidat(inactifs: 6);

        self::assertSame(4, $seul->demiRationsAmenees());
        self::assertSame(10, $charge->demiRationsAmenees());
        self::assertSame($seul->salaire, $charge->salaire);
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
    public function testPlusAucunTraitNeDort(): void
    {
        $dormants = array_values(array_filter(
            TraitDeCandidat::cases(),
            static fn (TraitDeCandidat $trait): bool => $trait->dortEnAttendantSaPhase(),
        ));

        // « Pieux » s'est réveillé au lot 6.7, « Bagarreur » au 10.7 : la
        // Caserne lui a donné son emploi, et le poste civil son malus. Le
        // garde-fou reste pour le trait qu'on ajouterait demain.
        self::assertSame([], $dormants);
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
     * **Ce qui dort et ce qui agit doit rester exact.** Le lot 5.9 a réveillé
     * les sept spécialités d'atelier, plus le Négociateur et le Logisticien de
     * l'Entrepôt. Restent inertes celles dont le système n'existe pas encore :
     * le Dévot attend la faveur divine, les Scribes les énigmes, les
     * Instructeurs le combat.
     *
     * L'Acheteur du Marché en fait toujours partie : le Marché est une vente
     * locale, l'achat passant par les caravanes et non par lui.
     */
    public function testSeulesLesSpecialitesDunSystemeExistantAgissent(): void
    {
        $dormantes = array_values(array_filter(
            SpecialiteDeChef::cases(),
            static fn (SpecialiteDeChef $specialite): bool => !$specialite->agitDeja(),
        ));

        // Les deux Instructeurs agissent depuis le lot 10.7 : ils aiguisent la
        // spécialisation qu'ils enseignent. Restent l'Acheteur du Marché — que
        // le Marché, purement local, n'accueillera jamais — et le Commerçant
        // naval, qui attend un commerce naval avancé.
        self::assertSame([
            SpecialiteDeChef::MarcheAcheteur,
            SpecialiteDeChef::PortCommercantNaval,
        ], $dormantes);
    }

    /**
     * Une spécialité d'atelier ne vaut que sur son propre ouvrage : un
     * Brasseur ne fait pas de meilleurs papyrus.
     */
    public function testUneSpecialiteDAtelierNeFavoriseQueSonPropreOuvrage(): void
    {
        self::assertTrue(SpecialiteDeChef::AtelierBierePain->favorise(\App\Game\Recette::Biere));
        self::assertTrue(SpecialiteDeChef::AtelierBierePain->favorise(\App\Game\Recette::Pain));
        self::assertFalse(SpecialiteDeChef::AtelierBierePain->favorise(\App\Game\Recette::Papyrus));

        // Le doc 03 nomme la vannerie et les sandales ensemble.
        self::assertTrue(SpecialiteDeChef::AtelierVannerie->favorise(\App\Game\Recette::Vannerie));
        self::assertTrue(SpecialiteDeChef::AtelierVannerie->favorise(\App\Game\Recette::Sandales));
    }

    private function candidat(int $competence = 50, int $anciennete = 20, int $inactifs = 0): Candidat
    {
        return new Candidat(
            competence: $competence,
            salaire: 8,
            ancienneteProbable: $anciennete,
            traits: [],
            specialite: null,
            actifsAmenes: 2,
            inactifsAmenes: $inactifs,
        );
    }
}
