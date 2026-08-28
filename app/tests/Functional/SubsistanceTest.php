<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\Subsistance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SubsistanceTest extends KernelTestCase
{
    public function testUneQuinzainePayeeEntameLesVivresSelonLaPopulation(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rations@example.com');
        $ville = $partie->getVille();
        $avant = $ville->getNourriture();

        $this->cycle()->passer($partie);

        self::assertSame($avant - $ville->consommationDeNourriture(), $ville->getNourriture());
        self::assertSame(0, $partie->getQuinzainesDeFamine());
    }

    public function testSansVivresLaFamineSAccumule(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-debut@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        $this->cycle()->passer($partie);

        self::assertSame(1, $partie->getQuinzainesDeFamine());
        self::assertTrue($partie->estEnCours(), 'Un seul cycle de famine ne doit pas encore faire échouer la partie.');
    }

    public function testUnRavitaillementReinitialiseLaFamine(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-repit@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());
        $this->cycle()->passer($partie);
        self::assertSame(1, $partie->getQuinzainesDeFamine());

        $ville->crediterRessources([Ressource::Ble->value => 1000]);
        $this->cycle()->passer($partie);

        self::assertSame(0, $partie->getQuinzainesDeFamine());
    }

    public function testLaFamineProlongeeFaitEchouerLaPartie(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-echec@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < Subsistance::SEUIL_DE_FAMINE; ++$i) {
            self::assertTrue($partie->estEnCours(), \sprintf('Ne devrait pas encore avoir échoué au cycle %d.', $i));
            $this->cycle()->passer($partie);
        }

        self::assertFalse($partie->estEnCours());
        self::assertSame(StatutDePartie::Echouee, $partie->getStatut());
    }

    public function testUneQuinzaineSansHabitantsSupplementairesNeCoutePasPlusDeVivres(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('base@example.com');
        $ville = $partie->getVille();

        // Sans Quartier d'habitation, seule la famille fondatrice mange.
        self::assertSame($ville->population() * 1, $ville->consommationDeNourriture());
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

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
