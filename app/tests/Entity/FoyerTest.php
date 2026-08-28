<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\City;
use App\Entity\Foyer;
use App\Game\GenerateurDeFoyer;
use App\Game\Population;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

#[CoversClass(Foyer::class)]
#[CoversClass(GenerateurDeFoyer::class)]
final class FoyerTest extends TestCase
{
    public function testUnFoyerCompteSesPersonnesEtSesBras(): void
    {
        $foyer = new Foyer($this->ville(), 2, [10, 20, 30]);

        self::assertSame(5, $foyer->personnes());
        self::assertSame(2, $foyer->getAdultes(), 'Seuls les adultes travaillent.');
        self::assertSame(3, $foyer->getEnfants());
    }

    /**
     * Un adulte vaut deux demi-rations, un enfant une seule. Compter en
     * demi-rations est ce qui permet de ne jamais manipuler de 0,5.
     */
    public function testUnEnfantMangeLaMoitieDUnAdulte(): void
    {
        $sansEnfant = new Foyer($this->ville(), 2);
        $avecTrois = new Foyer($this->ville(), 2, [10, 20, 30]);

        self::assertSame(4, $sansEnfant->demiRations());
        self::assertSame(7, $avecTrois->demiRations());
    }

    /**
     * Le cœur du lot : un enfant qui atteint l'âge de travailler devient un
     * bras, et cesse d'être une demi-ration pour en devenir deux.
     */
    public function testUnEnfantQuiAtteintDouzeAnsDevientUnBras(): void
    {
        $foyer = new Foyer($this->ville(), 2, [Population::AGE_ADULTE_EN_QUINZAINES - 1]);

        $majeurs = $foyer->vieillirDUneQuinzaine();

        self::assertSame(1, $majeurs);
        self::assertSame(3, $foyer->getAdultes());
        self::assertSame(0, $foyer->getEnfants());
        self::assertSame(3, $foyer->personnes(), 'Personne n\'a disparu : un enfant a grandi.');
        // Il mangeait une demi-ration, il en mange deux : grandir a un coût.
        self::assertSame(6, $foyer->demiRations());
    }

    public function testUnEnfantEncoreJeuneNeChangeRien(): void
    {
        $foyer = new Foyer($this->ville(), 2, [0]);

        self::assertSame(0, $foyer->vieillirDUneQuinzaine());
        self::assertSame(2, $foyer->getAdultes());
        self::assertSame(1, $foyer->getEnfants());
    }

    /**
     * Aucune compression du temps : douze ans de jeu, soit près de trois cents
     * quinzaines. C'est ce qui fait qu'une nichée en bas âge est un
     * investissement de plusieurs règnes, quand un aîné donne un bras dans
     * l'année.
     */
    public function testUnNourrissonMetDouzeAnsAGrandir(): void
    {
        $foyer = new Foyer($this->ville(), 2, [0]);

        for ($quinzaine = 1; $quinzaine < Population::AGE_ADULTE_EN_QUINZAINES; ++$quinzaine) {
            self::assertSame(0, $foyer->vieillirDUneQuinzaine(), \sprintf('Quinzaine %d.', $quinzaine));
        }

        self::assertSame(1, $foyer->vieillirDUneQuinzaine(), 'À la trois-centième, l\'enfant entre dans la vie active.');
    }

    public function testLesAgesSeLisentDuPlusGrandAuPlusJeune(): void
    {
        $foyer = new Foyer($this->ville(), 2, [25, 275, 125]);

        self::assertSame([11, 5, 1], $foyer->agesDesEnfantsEnAnnees());
    }

    /**
     * La génération est aléatoire : son test porte sur des invariants, jamais
     * sur un foyer attendu.
     */
    public function testUnFoyerTireResteDansLesBornesDeLaConception(): void
    {
        for ($graine = 1; $graine <= 60; ++$graine) {
            $foyer = (new GenerateurDeFoyer(new Randomizer(new Mt19937($graine))))->pour($this->ville());

            self::assertSame(Population::ADULTES_PAR_FOYER, $foyer->getAdultes());
            self::assertLessThanOrEqual(Population::ENFANTS_MAX_PAR_FOYER, $foyer->getEnfants());
            self::assertGreaterThanOrEqual(2, $foyer->personnes());
            self::assertLessThanOrEqual(8, $foyer->personnes());

            foreach ($foyer->agesDesEnfantsEnAnnees() as $age) {
                self::assertGreaterThanOrEqual(0, $age);
                self::assertLessThan(
                    Population::AGE_ADULTE_EN_ANNEES,
                    $age,
                    'Un enfant de douze ans serait déjà un adulte, pas un enfant.',
                );
            }
        }
    }

    /**
     * Les foyers doivent couvrir toute l'enfance, pas seulement une tranche :
     * c'est ce qui fait de l'âge un critère d'embauche.
     */
    public function testLesFoyersTiresCouvrentToutesLesTranchesDAge(): void
    {
        $ages = [];

        for ($graine = 1; $graine <= 60; ++$graine) {
            $foyer = (new GenerateurDeFoyer(new Randomizer(new Mt19937($graine))))->pour($this->ville());
            $ages = [...$ages, ...$foyer->agesDesEnfantsEnAnnees()];
        }

        self::assertNotEmpty(array_filter($ages, static fn (int $age): bool => $age <= 2), 'Aucun enfant en bas âge.');
        self::assertNotEmpty(array_filter($ages, static fn (int $age): bool => $age >= 10), 'Aucun aîné proche de la majorité.');
    }

    private function ville(): City
    {
        return new City('Avaris', 0, 3);
    }
}
