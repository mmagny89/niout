<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\GameMode;
use App\Game\DateDeJeu;
use App\Game\DotationRoyale;
use App\Game\LanceurDePartie;
use App\Game\Population;
use App\Game\Ressource;
use App\Game\Salaires;
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

    /**
     * Les deux modes sont des cartes cochables, mais restent **les boutons radio du formulaire** :
     * mêmes valeurs, même champ, atteints au clavier. Une carte qui n'envelopperait pas son radio
     * ne cocherait rien au clic, sans erreur visible.
     */
    public function testLesModesSontDesCartesQuiEnveloppentLeursRadios(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'cartes-modes@example.com');

        $crawler = $client->request('GET', '/partie/nouvelle');

        self::assertResponseIsSuccessful();
        $cartes = $crawler->filter('label.choix-mode');
        self::assertCount(2, $cartes);
        self::assertSame(
            ['campagne', 'aventure'],
            $cartes->each(static fn ($carte): string => (string) $carte->filter('input[type="radio"]')->attr('value')),
        );
        // Le contrôleur s'attache au groupe des modes et aux réglages, rien d'autre.
        self::assertCount(1, $crawler->filter('[data-nouvelle-partie-target="mode"]'));
        self::assertCount(1, $crawler->filter('[data-nouvelle-partie-target="reglagesAventure"]'));
    }

    /**
     * Les listes déroulantes du thème de formulaire portent les classes du projet. Celles du thème
     * de Symfony vivent dans `vendor/`, que Tailwind ne scanne pas : sans ce bloc, une liste
     * s'affichait sans cadre ni fond, et l'on ne voyait pas que c'en était une.
     */
    public function testLesListesDeroulantesPortentLeStyleDuProjet(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'listes@example.com');

        $crawler = $client->request('GET', '/partie/nouvelle');

        $listes = $crawler->filter('select');
        self::assertGreaterThan(0, $listes->count());
        $listes->each(static function ($liste): void {
            self::assertStringContainsString('liste-deroulante', (string) $liste->attr('class'));
            self::assertStringContainsString('border-ocre-500/40', (string) $liste->attr('class'));
        });
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
        // Dotation à difficulté 0 : de quoi dresser les quatre bâtiments
        // d'ouverture, plus l'année de salaires que le pharaon avance.
        self::assertSame(
            DotationRoyale::coutDesBatimentsDouverture()[Ressource::Deben->value] + self::anneeDeSalaires(),
            $partie->getVille()->getDeben(),
        );
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
        // Les quatre bâtiments d'ouverture, l'année de salaires, et 10 par
        // niveau de difficulté.
        self::assertSame(
            DotationRoyale::coutDesBatimentsDouverture()[Ressource::Deben->value] + self::anneeDeSalaires() + 40,
            $partie->getVille()->getDeben(),
        );
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

        self::assertResponseRedirects('/parties');
        self::assertSame(GameSave::MAX_PAR_COMPTE, $this->depot()->compterPourJoueur($joueur));
    }

    public function testLaCommandeDuPharaonEstAffichee(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'commande@example.com');
        $this->soumettreFormulaire($client, GameMode::Campagne, 'Sennefer');

        // La commande s'ouvre d'office, en fenêtre, au-dessus de la carte.
        $partie = $this->depot()->findPourJoueur($joueur)[0];
        self::assertResponseRedirects(\sprintf(
            '/partie/%1$d/carte?ouvre=/partie/%1$d/commande',
            $partie->getId(),
        ));

        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open] turbo-frame#fenetre');
        self::assertSelectorTextContains('dialog', 'Ahmôsis Ier');
        self::assertSelectorTextContains('dialog', 'Sennefer');
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

    /**
     * Ce que le pharaon avance en salaires : de quoi employer les bras qu'il
     * envoie pendant une année complète (lot 4.6).
     */
    private static function anneeDeSalaires(): int
    {
        return Population::ACTIFS_AU_DEPART * Salaires::SALAIRE_DUN_TRAVAILLEUR * DateDeJeu::CYCLES_PAR_ANNEE;
    }
}
