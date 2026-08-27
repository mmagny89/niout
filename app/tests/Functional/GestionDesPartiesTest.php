<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GestionDesPartiesTest extends WebTestCase
{
    public function testLaPageDeCompteListeLesParties(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'liste@example.com');
        $this->creerPartie($joueur, 'Avaris');
        $this->creerPartie($joueur, 'Memphis');

        $crawler = $client->request('GET', '/compte');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Avaris');
        self::assertSelectorTextContains('body', 'Memphis');
        self::assertCount(2, $crawler->filter('a:contains("Reprendre")'));
    }

    public function testUnCompteSansPartieLeDitClairement(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'vide@example.com');

        $client->request('GET', '/compte');

        self::assertSelectorTextContains('body', 'Aucune partie en cours');
    }

    public function testLaListeNAffichePasLesPartiesDesAutres(): void
    {
        $client = static::createClient();
        $autre = $this->creerJoueur('voisin@example.com');
        $this->creerPartie($autre, 'Saï');
        $this->connecter($client, 'curieux@example.com');

        $client->request('GET', '/compte');

        self::assertSelectorTextNotContains('body', 'Saï');
    }

    public function testReprendreUnePartieAfficheSonEtat(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'reprise@example.com');
        $partie = $this->creerPartie($joueur, 'Avaris');

        $client->request('GET', \sprintf('/partie/%d', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Avaris');
        // Le récapitulatif rend le contexte, pas un journal d'événements.
        self::assertSelectorTextContains('body', 'Où vous en êtes');
    }

    public function testLaRepriseEnregistreLaDateDOuverture(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'horodatage@example.com');
        $partie = $this->creerPartie($joueur, 'Avaris');
        $this->reculerDerniereOuverture($partie, 3600);
        $avant = $this->recharger($partie)->getLastOpenedAt();

        $client->request('GET', \sprintf('/partie/%d', $partie->getId()));

        self::assertGreaterThan($avant, $this->recharger($partie)->getLastOpenedAt());
    }

    public function testLaConfirmationDAbandonPrevientQueCEstDefinitif(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'confirmation@example.com');
        $partie = $this->creerPartie($joueur, 'Avaris');

        $client->request('GET', \sprintf('/partie/%d/abandonner', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'définitive');
    }

    public function testAbandonnerSupprimeDefinitivementLaPartie(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'abandon@example.com');
        $partie = $this->creerPartie($joueur, 'Avaris');
        $id = $partie->getId();

        $crawler = $client->request('GET', \sprintf('/partie/%d/abandonner', $id));
        $client->submit($crawler->selectButton('Oui, abandonner définitivement')->form());

        self::assertResponseRedirects('/compte');
        self::assertNull($this->depot()->find($id));
        self::assertSame(0, $this->depot()->compterPourJoueur($joueur));
    }

    public function testUnAbandonSansJetonValideEstRefuse(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'sansjeton@example.com');
        $partie = $this->creerPartie($joueur, 'Avaris');

        $client->request('POST', \sprintf('/partie/%d/abandonner', $partie->getId()), ['_token' => 'invalide']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->depot()->compterPourJoueur($joueur));
    }

    public function testUnJoueurNePeutPasAbandonnerLaPartieDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->creerJoueur('cible@example.com');
        $partie = $this->creerPartie($proprietaire, 'Avaris');

        $this->connecter($client, 'saboteur@example.com');
        $client->request('GET', \sprintf('/partie/%d/abandonner', $partie->getId()));

        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->depot()->find($partie->getId()));
    }

    public function testUnePartieAbandonneeLibereUnePlace(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'place@example.com');

        for ($i = 0; $i < GameSave::MAX_PAR_COMPTE; ++$i) {
            $this->creerPartie($joueur, 'Ville '.$i);
        }
        self::assertTrue($this->depot()->plafondAtteintPour($joueur));

        $partie = $this->depot()->findPourJoueur($joueur)[0];
        $crawler = $client->request('GET', \sprintf('/partie/%d/abandonner', $partie->getId()));
        $client->submit($crawler->selectButton('Oui, abandonner définitivement')->form());

        self::assertFalse($this->depot()->plafondAtteintPour($joueur));
        $client->request('GET', '/partie/nouvelle');
        self::assertResponseIsSuccessful();
    }

    private function connecter(KernelBrowser $client, string $email): User
    {
        $user = $this->creerJoueur($email);
        $client->loginUser($user);

        return $user;
    }

    private function creerJoueur(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }

    private function creerPartie(User $joueur, string $nomDeVille): GameSave
    {
        $partie = GameSave::pourCampagne(
            $joueur,
            new Family(Family::NOM_PAR_DEFAUT),
            new City($nomDeVille, 0, 3),
        );

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($partie);
        $gestionnaire->flush();

        return $partie;
    }

    private function reculerDerniereOuverture(GameSave $partie, int $secondes): void
    {
        $this->gestionnaire()->getConnection()->executeStatement(
            'UPDATE game_save SET last_opened_at = last_opened_at - INTERVAL \'1 second\' * :secondes WHERE id = :id',
            ['secondes' => $secondes, 'id' => $partie->getId()],
        );
        $this->gestionnaire()->clear();
    }

    private function recharger(GameSave $partie): GameSave
    {
        $this->gestionnaire()->clear();
        $rechargee = $this->depot()->find($partie->getId());
        self::assertInstanceOf(GameSave::class, $rechargee);

        return $rechargee;
    }

    private function depot(): GameSaveRepository
    {
        return static::getContainer()->get(GameSaveRepository::class);
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
