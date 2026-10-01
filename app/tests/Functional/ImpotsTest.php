<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\DateDeJeu;
use App\Game\Impots;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImpotsTest extends KernelTestCase
{
    /**
     * Le mois pharaonique dure deux quinzaines : l'impôt ne se perçoit que sur
     * la seconde, jamais à chaque cycle.
     */
    public function testLImpotNeSePercoitQuUneQuinzaineSurDeux(): void
    {
        self::bootKernel();
        $impots = static::getContainer()->get(Impots::class);
        $partie = $this->lancerPartie('impot-rythme@example.com');

        $clotures = [];
        for ($cycle = 1; $cycle <= DateDeJeu::CYCLES_PAR_ANNEE; ++$cycle) {
            $this->placerAuCycle($partie, $cycle);
            $clotures[$cycle] = $impots->cloture($partie);
        }

        self::assertFalse($clotures[1]);
        self::assertTrue($clotures[2]);
        self::assertFalse($clotures[3]);
        self::assertTrue($clotures[24]);
        self::assertFalse($clotures[25], 'Les jours épagomènes n\'appartiennent à aucun mois.');
        self::assertCount(12, array_filter($clotures), 'Douze mois, douze perceptions.');
    }

    public function testLImpotRenfloueLaCaisseEnFinDeMois(): void
    {
        self::bootKernel();
        $impots = static::getContainer()->get(Impots::class);
        $partie = $this->lancerPartie('impot-caisse@example.com');
        $ville = $partie->getVille();

        $this->placerAuCycle($partie, 2);
        $attendu = $ville->actifsValides() * Impots::DEBEN_PAR_ACTIF_ET_PAR_MOIS;
        self::assertGreaterThan(0, $attendu);

        $avant = $ville->getDeben();
        $messages = $impots->percevoir($partie);

        self::assertSame($avant + $attendu, $ville->getDeben());
        self::assertCount(1, $messages);
        self::assertStringContainsString((string) $attendu, $messages[0]);
    }

    public function testRienNEstPercuEnMilieuDeMois(): void
    {
        self::bootKernel();
        $impots = static::getContainer()->get(Impots::class);
        $partie = $this->lancerPartie('impot-milieu@example.com');

        $this->placerAuCycle($partie, 1);
        $avant = $partie->getVille()->getDeben();

        self::assertSame([], $impots->percevoir($partie));
        self::assertSame($avant, $partie->getVille()->getDeben());
    }

    public function testLEcheanceSeCompteEnQuinzaines(): void
    {
        self::bootKernel();
        $impots = static::getContainer()->get(Impots::class);
        $partie = $this->lancerPartie('impot-echeance@example.com');

        $this->placerAuCycle($partie, 1);
        self::assertSame(2, $impots->quinzainesAvantLaPerception($partie));

        $this->placerAuCycle($partie, 2);
        self::assertSame(1, $impots->quinzainesAvantLaPerception($partie));

        $this->placerAuCycle($partie, 25);
        self::assertSame(3, $impots->quinzainesAvantLaPerception($partie));
    }

    /**
     * Le filet joue dans le vrai passage de cycle : deux quinzaines sans
     * aucun commerce, et la caisse a reçu l'impôt une fois.
     */
    public function testLePassageDeCycleLaisseTomberUnImpotParMois(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('impot-cycle@example.com');
        $cycles = static::getContainer()->get(PassageDeCycle::class);

        $annonces = [...$cycles->passer($partie), ...$cycles->passer($partie)];

        $leves = array_filter($annonces, static fn (string $a): bool => str_contains($a, 'impôt du mois'));
        self::assertCount(1, $leves);
    }

    private function placerAuCycle(GameSave $partie, int $cycle): void
    {
        $propriete = new \ReflectionProperty(GameSave::class, 'cycle');
        $propriete->setValue($partie, $cycle);
    }

    private function lancerPartie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
