<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\GameMode;
use App\Game\LanceurDePartie;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NouvellePartieTest extends WebTestCase
{
    public function testLeParcoursExigeUneConnexion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/partie/nouvelle');

        self::assertResponseRedirects('/connexion');
    }

    public function testUneCampagneDemarreAAvarisAvecSaDotation(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'campagne@example.com');

        $this->soumettreFormulaire($client, GameMode::Campagne, 'Sennefer');

        $parties = $this->depot()->findPourJoueur($joueur);
        self::assertCount(1, $parties);

        $partie = $parties[0];
        self::assertSame('Avaris', $partie->getVille()->getNom());
        self::assertSame(GameSave::PREMIERE_MISSION, $partie->getMission());
        self::assertSame('Sennefer', $partie->getFamille()->getNom());
        // Dotation à difficulté 0 : 50 + 10 × 0 (doc 13).
        self::assertSame(50, $partie->getVille()->getOr());
    }

    public function testUneAventureSeDerouleAMemphisAvecLesReglagesChoisis(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'aventure@example.com');

        $this->soumettreFormulaire($client, GameMode::Aventure, 'Nakht', difficulte: 4, tailleGrille: 10);

        $partie = $this->depot()->findPourJoueur($joueur)[0];
        self::assertSame(LanceurDePartie::VILLE_DU_MODE_AVENTURE, $partie->getVille()->getNom());
        self::assertNull($partie->getMission(), 'Le mode Aventure ne suit pas de missions.');
        self::assertSame(4, $partie->getVille()->getDifficulte());
        self::assertSame(10, $partie->getVille()->getTailleGrille());
        // 50 + 10 × 4.
        self::assertSame(90, $partie->getVille()->getOr());
    }

    public function testLaCampagneIgnoreLesReglagesDuModeAventure(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'ignore@example.com');

        // Le joueur soumet des réglages Aventure tout en choisissant Campagne :
        // l'ordre des missions étant imposé, ils ne doivent rien changer.
        $this->soumettreFormulaire($client, GameMode::Campagne, 'Nakht', difficulte: 9, tailleGrille: 10);

        $partie = $this->depot()->findPourJoueur($joueur)[0];
        self::assertSame(0, $partie->getVille()->getDifficulte());
        self::assertSame(3, $partie->getVille()->getTailleGrille());
    }

    public function testUnNomDeFamilleVideEstRefuse(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'anonyme@example.com');

        $this->soumettreFormulaire($client, GameMode::Campagne, '');

        self::assertResponseIsUnprocessable();
        self::assertSame(0, $this->depot()->compterPourJoueur($joueur));
    }

    public function testLePlafondDePartiesEmpecheDEnCreerUneDeTrop(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'plafond@example.com');

        for ($i = 0; $i < GameSave::MAX_PAR_COMPTE; ++$i) {
            $this->soumettreFormulaire($client, GameMode::Campagne, 'Nakht');
        }

        $client->request('GET', '/partie/nouvelle');

        self::assertResponseRedirects('/compte');
        self::assertSame(GameSave::MAX_PAR_COMPTE, $this->depot()->compterPourJoueur($joueur));
    }

    public function testLaCommandeDuPharaonEstAffichee(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'commande@example.com');
        $this->soumettreFormulaire($client, GameMode::Campagne, 'Sennefer');

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Ahmôsis Ier');
        self::assertSelectorTextContains('body', 'Sennefer');
    }

    public function testUnJoueurNePeutPasVoirLaPartieDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->connecter($client, 'proprietaire@example.com');
        $this->soumettreFormulaire($client, GameMode::Campagne, 'Nakht');
        $partie = $this->depot()->findPourJoueur($proprietaire)[0];

        // On rebascule sur un autre compte, puis on vise l'identifiant en clair.
        $this->connecter($client, 'intrus@example.com');
        $client->request('GET', \sprintf('/partie/%d/commande', $partie->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    private function connecter(KernelBrowser $client, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $client->loginUser($user);

        return $user;
    }

    private function soumettreFormulaire(
        KernelBrowser $client,
        GameMode $mode,
        string $nomDeFamille,
        int $difficulte = 0,
        int $tailleGrille = 8,
    ): void {
        $crawler = $client->request('GET', '/partie/nouvelle');
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->selectButton('Lancer la partie')->form([
            'nouvelle_partie[mode]' => $mode->value,
            'nouvelle_partie[nomDeFamille]' => $nomDeFamille,
            'nouvelle_partie[difficulte]' => (string) $difficulte,
            'nouvelle_partie[tailleGrille]' => (string) $tailleGrille,
        ]);

        $client->submit($formulaire);
    }

    private function depot(): GameSaveRepository
    {
        return static::getContainer()->get(GameSaveRepository::class);
    }
}
