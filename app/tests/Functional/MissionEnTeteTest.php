<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\QueteDeChantier;
use App\Entity\User;
use App\Fenetre\ResumeDeMission;
use App\Game\ChantierRoyal;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La mission du pharaon se voit toujours : une pastille en tête de l'en-tête, un panneau qui s'ouvre dessous.
 */
final class MissionEnTeteTest extends WebTestCase
{
    public function testLaMissionSeVoitDansLEnTeteAvecSesObjectifs(): void
    {
        $client = static::createClient();
        $partie = $this->campagne('mission-entete@example.com');
        $client->loginUser($partie->getJoueur());

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertResponseIsSuccessful();
        $pastille = $crawler->filter('header details summary:contains("Mission")');
        self::assertCount(1, $pastille);
        self::assertStringContainsString('objectifs', $pastille->text());

        $resume = static::getContainer()->get(ResumeDeMission::class)->pour($partie);
        self::assertNotNull($resume);
        self::assertStringContainsString(\sprintf('Mission %d/10', $resume['numero']), $pastille->text());
        self::assertCount($resume['total'], $crawler->filter('header details ul li div.h-1\.5'), 'Une jauge par objectif.');
        self::assertCount(1, $crawler->filter('header details a:contains("Ouvrir le décret")'));
    }

    /**
     * Une requête du pharaon se voit dans l'en-tête, avec son délai : elle expire.
     */
    public function testUneRequeteDuPharaonSeLitAvecSonDelai(): void
    {
        $client = static::createClient();
        $partie = $this->campagne('requete-entete@example.com');
        $client->loginUser($partie->getJoueur());
        $partie->getVille()->recevoirUneQuete(new QueteDeChantier($partie->getVille(), ChantierRoyal::PyramideDAbydos, Ressource::Ble, 40, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $resume = static::getContainer()->get(ResumeDeMission::class)->pour($partie);
        self::assertNotNull($resume);
        self::assertNotNull($resume['quete']);
        self::assertTrue($resume['quete']['urgent'], 'À une quinzaine de l\'échéance, l\'alerte est rouge.');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $pastille = $crawler->filter('header details summary:contains("Mission")');
        self::assertStringContainsString('requête : 1 q.', $pastille->text());
        self::assertStringContainsString('signal--mauvais', (string) $pastille->attr('class'));
    }

    /**
     * En Aventure il n'y a pas de pharaon à qui répondre : aucune pastille.
     */
    public function testSansMissionPasDePastille(): void
    {
        $client = static::createClient();
        $joueur = $this->joueur('aventure-entete@example.com');
        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerAventure($joueur, 'Nakht', 3, 5);
        $client->loginUser($joueur);

        self::assertNull(static::getContainer()->get(ResumeDeMission::class)->pour($partie));

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        self::assertCount(0, $crawler->filter('header details summary:contains("Mission")'));
    }

    private function campagne(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->joueur($email), 'Nakht');
    }

    private function joueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return $joueur;
    }
}
