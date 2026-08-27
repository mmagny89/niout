<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExplorationTest extends KernelTestCase
{
    public function testEnvoyerUnEclaireurDebiteSonSolde(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('solde@example.com');
        $orAvant = $partie->getVille()->getOr();

        $this->explorations()->envoyer($partie, $this->caseInconnue($partie), RoleDExploration::Eclaireur);

        self::assertSame($orAvant - RoleDExploration::Eclaireur->cout(), $partie->getVille()->getOr());
        self::assertCount(1, $partie->getVille()->getExpeditions());
    }

    public function testLaCaseResteSousBrouillardTantQueLEclaireurEstEnRoute(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('route@example.com');
        $cible = $this->caseInconnue($partie);

        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        self::assertFalse($cible->estDecouverte(), 'Reconnaître prend du temps.');
    }

    public function testLaCaseSeReveleQuandLEclaireurArrive(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('arrivee@example.com');
        $cible = $this->caseInconnue($partie);
        $expedition = $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        for ($i = 0; $i < $expedition->getDureeEnCycles(); ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertTrue($cible->estDecouverte());
        self::assertCount(0, $partie->getVille()->getExpeditions(), 'L\'expédition arrivée disparaît.');
    }

    public function testLeRapportDeLEclaireurNommeLeTerrain(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rapport-eclaireur@example.com');
        $cible = $this->caseInconnue($partie);
        $expedition = $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        $evenements = [];
        for ($i = 0; $i < $expedition->getDureeEnCycles(); ++$i) {
            $evenements = array_merge($evenements, $this->cycle()->passer($partie));
        }

        self::assertNotEmpty($evenements);
        self::assertStringContainsString($cible->getTerrain()->libelle(), implode(' ', $evenements));
    }

    public function testOnNEnvoiePasDeuxEclaireursSurLaMemeCase(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('doublon-eclaireur@example.com');
        $cible = $this->caseInconnue($partie);
        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà en route/');

        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);
    }

    public function testOnNExplorePasUneCaseDejaReconnue(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('deja-vu@example.com');
        $centre = $partie->getVille()->zoneDeLaVille();
        self::assertInstanceOf(Zone::class, $centre);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà reconnue/');

        $this->explorations()->envoyer($partie, $centre, RoleDExploration::Eclaireur);
    }

    public function testSansOrAucunEclaireurNePartEtRienNEstDebite(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-le-sou@example.com');
        $ville = $partie->getVille();
        $ville->debiterRessources([Ressource::Or->value => $ville->getOr()]);

        try {
            $this->explorations()->envoyer($partie, $this->caseInconnue($partie), RoleDExploration::Eclaireur);
            self::fail('L\'expédition aurait dû être refusée.');
        } catch (ExplorationImpossible) {
            self::assertSame(0, $ville->getOr());
            self::assertCount(0, $ville->getExpeditions());
        }
    }

    public function testPlusieursExpeditionsPeuventCourirDeFront(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('de-front@example.com');
        $inconnues = $this->casesInconnues($partie);
        self::assertGreaterThanOrEqual(3, \count($inconnues));

        foreach (\array_slice($inconnues, 0, 3) as $zone) {
            $this->explorations()->envoyer($partie, $zone, RoleDExploration::Eclaireur);
        }

        self::assertCount(3, $partie->getVille()->getExpeditions());
    }

    public function testUneExpeditionEtUnChantierAvancentDansLaMemeQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('ensemble@example.com');
        $expedition = $this->explorations()->envoyer($partie, $this->caseInconnue($partie), RoleDExploration::Eclaireur);
        $restantAvant = $expedition->cyclesRestants();

        $this->cycle()->passer($partie);

        self::assertSame($restantAvant - 1, $expedition->cyclesRestants());
        self::assertSame(2, $partie->getCycle());
    }

    private function caseInconnue(GameSave $partie): Zone
    {
        return $this->casesInconnues($partie)[0];
    }

    /**
     * @return list<Zone>
     */
    private function casesInconnues(GameSave $partie): array
    {
        $inconnues = [];

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                $inconnues[] = $zone;
            }
        }

        self::assertNotEmpty($inconnues, 'Une carte neuve doit avoir des cases inexplorées.');

        return $inconnues;
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

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
