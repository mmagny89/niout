<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\GenerateurDeCandidat;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * La génération est aléatoire : ses tests portent sur des **invariants** et des
 * **distributions**, jamais sur un candidat attendu. Un test qui figerait un
 * profil précis casserait au moindre ajustement de barème et finirait désactivé.
 */
#[CoversClass(GenerateurDeCandidat::class)]
final class GenerateurDeCandidatTest extends TestCase
{
    private const int TIRAGES = 400;

    public function testLaCompetenceResteDansSonEchelle(): void
    {
        foreach ($this->candidats() as $candidat) {
            self::assertGreaterThanOrEqual(GenerateurDeCandidat::COMPETENCE_MIN, $candidat->competence);
            self::assertLessThanOrEqual(GenerateurDeCandidat::COMPETENCE_MAX, $candidat->competence);
        }
    }

    /**
     * L'écart assumé au doc 03 : 4 à 14 deben au lieu de 11 à 35, un chef
     * n'ayant sinon jamais été rentable. L'échelle change, pas l'arbitrage.
     */
    public function testLeSalaireResteDansLaFourchetteCalibree(): void
    {
        $minimum = PHP_INT_MAX;
        $maximum = 0;

        foreach ($this->candidats() as $candidat) {
            self::assertGreaterThan(0, $candidat->salaire, 'Personne ne travaille gratuitement.');
            $minimum = min($minimum, $candidat->salaire);
            $maximum = max($maximum, $candidat->salaire);
        }

        self::assertGreaterThanOrEqual(3, $minimum, 'Un salaire dérisoire viderait l\'arbitrage de son sens.');
        self::assertLessThanOrEqual(18, $maximum, 'Au-delà, un seul chef mangerait tout le revenu de la ville.');
    }

    public function testUnMeilleurCandidatCouteToujoursPlusCher(): void
    {
        // À traits égaux — ici aucun —, le salaire suit la compétence.
        $barème = [];

        foreach ($this->candidats() as $candidat) {
            if ([] === $candidat->traits) {
                $barème[$candidat->competence] = $candidat->salaire;
            }
        }

        ksort($barème);
        $precedent = 0;

        foreach ($barème as $salaire) {
            self::assertGreaterThanOrEqual($precedent, $salaire, 'Le salaire ne décroît jamais avec la compétence.');
            $precedent = $salaire;
        }
    }

    public function testLAncienneteResteCredible(): void
    {
        foreach ($this->candidats() as $candidat) {
            self::assertGreaterThan(0, $candidat->ancienneteProbable);
            // 20 quinzaines de base, ±30 % au plus par les traits.
            self::assertLessThanOrEqual(30, $candidat->ancienneteProbable);
        }
    }

    /**
     * L'invariant du doc 03 que rien ne doit pouvoir violer : deux traits aux
     * effets opposés ne sortent jamais ensemble.
     */
    public function testDeuxTraitsIncompatiblesNeSortentJamaisEnsemble(): void
    {
        foreach ($this->candidats() as $candidat) {
            self::assertLessThanOrEqual(2, \count($candidat->traits));

            foreach ($candidat->traits as $trait) {
                foreach ($candidat->traits as $autre) {
                    if ($trait !== $autre) {
                        self::assertFalse(
                            $trait->estIncompatibleAvec($autre),
                            \sprintf('%s et %s ne peuvent pas coexister.', $trait->value, $autre->value),
                        );
                    }
                }
            }

            self::assertSame(
                array_unique(array_map(static fn (TraitDeCandidat $t): string => $t->value, $candidat->traits)),
                array_map(static fn (TraitDeCandidat $t): string => $t->value, $candidat->traits),
                'Un trait ne se tire jamais deux fois.',
            );
        }
    }

    /**
     * 45 % aucun, 40 % un, 15 % deux (doc 03). La tolérance est large : c'est
     * la forme de la distribution qu'on vérifie, pas le générateur de PHP.
     */
    public function testLesTraitsSuiventLesTauxDuDocument(): void
    {
        $combien = [0 => 0, 1 => 0, 2 => 0];

        foreach ($this->candidats() as $candidat) {
            ++$combien[\count($candidat->traits)];
        }

        self::assertGreaterThan($combien[1], $combien[0], 'Le cas le plus fréquent reste l\'absence de trait.');
        self::assertGreaterThan($combien[2], $combien[1], 'Un trait est plus courant que deux.');
        self::assertGreaterThan(0, $combien[2], 'Deux traits doivent rester possibles.');
    }

    public function testLesHuitTraitsFinissentTousParSortir(): void
    {
        $vus = [];

        foreach ($this->candidats() as $candidat) {
            foreach ($candidat->traits as $trait) {
                $vus[$trait->value] = true;
            }
        }

        self::assertCount(\count(TraitDeCandidat::cases()), $vus, 'Aucun trait ne doit être injoignable.');
    }

    /**
     * La spécialité dépend du poste, et d'aucune autre variable.
     */
    public function testLaSpecialiteAppartientToujoursAuBatimentBrigue(): void
    {
        foreach (TypeDeBatiment::cases() as $batiment) {
            $possibles = SpecialiteDeChef::pour($batiment);

            $generateur = $this->generateur();

            for ($essai = 1; $essai <= 40; ++$essai) {
                $candidat = $generateur->pour($batiment);

                if ([] === $possibles) {
                    self::assertNull($candidat->specialite, \sprintf('%s n\'a pas de spécialité.', $batiment->value));
                    continue;
                }

                self::assertNotNull($candidat->specialite);
                self::assertContains($candidat->specialite, $possibles);
            }
        }
    }

    public function testUneOffreProposeDeuxOuTroisCandidats(): void
    {
        $tailles = [];
        $generateur = $this->generateur();

        for ($essai = 1; $essai <= 60; ++$essai) {
            $offre = $generateur->pourUneOffre(TypeDeBatiment::Grenier);

            self::assertGreaterThanOrEqual(2, \count($offre));
            self::assertLessThanOrEqual(3, \count($offre));
            $tailles[\count($offre)] = true;
        }

        self::assertCount(2, $tailles, 'Les deux tailles d\'offre doivent apparaître.');
    }

    public function testChaqueCandidatAmeneUneMaisonneeCredible(): void
    {
        $tailles = [];

        foreach ($this->candidats() as $candidat) {
            self::assertSame(GenerateurDeCandidat::ACTIFS_AMENES, $candidat->actifsAmenes);
            self::assertGreaterThanOrEqual(2, $candidat->personnesAmenees());
            self::assertLessThanOrEqual(8, $candidat->personnesAmenees());
            $tailles[$candidat->personnesAmenees()] = true;
        }

        self::assertCount(7, $tailles, 'Toutes les tailles de deux à huit doivent sortir.');
    }

    /**
     * Un seul générateur qui tire longuement, plutôt qu'une graine par
     * candidat : deux Mt19937 semés par des entiers consécutifs produisent des
     * premiers tirages corrélés, ce qui fausserait toute mesure de
     * distribution.
     *
     * @return list<\App\Game\Candidat>
     */
    private function candidats(): array
    {
        $generateur = $this->generateur();
        $candidats = [];

        for ($i = 0; $i < self::TIRAGES; ++$i) {
            $candidats[] = $generateur->pour(TypeDeBatiment::Grenier);
        }

        return $candidats;
    }

    private function generateur(int $graine = 2026): GenerateurDeCandidat
    {
        return new GenerateurDeCandidat(new Randomizer(new Mt19937($graine)));
    }
}
