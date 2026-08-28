<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\LanceurDePartie;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CarteTest extends WebTestCase
{
    public function testLaCarteAfficheAutantDeTuilesQueDeCases(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'grille@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertResponseIsSuccessful();
        // Le Delta se joue en 3×3 (doc 06).
        self::assertCount(9, $crawler->filter('img[src*="/images/tuiles/"]'));
    }

    /**
     * Le détail d'une case semée affiche son étape (semis, pousse, récolte ou
     * repos) — la régression à surveiller est une erreur Twig si l'étape
     * n'est pas calculable.
     */
    public function testLeDetailDUnChampAfficheSonEtape(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'champ-detail@example.com');
        $partie = $this->lancer($joueur);

        $zone = null;
        foreach ($partie->getVille()->getZones() as $candidate) {
            if (!$candidate->porteLaVille()) {
                $zone = $candidate;
                break;
            }
        }
        self::assertInstanceOf(Zone::class, $zone);
        $zone->definirTerrain(TypeDeTerrain::Fertile)
            ->poserUnContenu(ContenuDeZone::ChampEligible)
            ->decouvrir()
            ->semer(Culture::Ble);

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#case', 'Semis');
    }

    public function testUneCarteNeuveNeMontreQueLaVilleEtDuBrouillard(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'brouillard@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $familles = [];
        foreach ($crawler->filter('img[src*="/images/tuiles/"]')->extract(['src']) as $src) {
            self::assertIsString($src);
            // AssetMapper sert « brouillard-yuseMRK.png » : le nom de la tuile
            // précède l'empreinte de version.
            $familles[] = explode('-', basename($src))[0];
        }

        $familles = array_unique($familles);
        sort($familles);

        // Aucun terrain ne doit fuiter avant qu'un éclaireur y soit passé.
        self::assertSame(['brouillard', 'ville'], $familles);
    }

    public function testUneCaseReconnueSeDetaille(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'detail@example.com');
        $partie = $this->lancer($joueur);
        $centre = $partie->getVille()->zoneDeLaVille();
        self::assertInstanceOf(Zone::class, $centre);

        $client->request('GET', \sprintf(
            '/partie/%d/carte?zone=%d-%d',
            $partie->getId(),
            $centre->getX(),
            $centre->getY(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Votre ville se dresse ici');
    }

    /**
     * Une case sous brouillard s'ouvre — il faut bien pouvoir y envoyer un
     * éclaireur — mais elle ne doit rien livrer de ce qu'il ira chercher.
     */
    public function testUneCaseSousBrouillardNeLivreNiTerrainNiContenu(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'secret@example.com');
        $partie = $this->lancer($joueur);
        $inconnue = $this->premiereZoneNonDecouverte($partie);

        $client->request('GET', \sprintf(
            '/partie/%d/carte?zone=%d-%d',
            $partie->getId(),
            $inconnue->getX(),
            $inconnue->getY(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Territoire inexploré');
        self::assertSelectorTextNotContains('article', $inconnue->getTerrain()->libelle());

        foreach ($inconnue->getGisements() as $gisement) {
            self::assertSelectorTextNotContains('article', $gisement->getRessource()->libelle());
        }
    }

    public function testDesCoordonneesFantaisistesNeCassentPasLaPage(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'fantaisie@example.com');
        $partie = $this->lancer($joueur);

        foreach (['99-99', 'nimportequoi', '../../etc/passwd', '1'] as $coordonnees) {
            $client->request('GET', \sprintf('/partie/%d/carte?zone=%s', $partie->getId(), urlencode($coordonnees)));

            self::assertResponseIsSuccessful(\sprintf('Coordonnées « %s ».', $coordonnees));
        }
    }

    /**
     * La carte est l'écran principal d'une partie : c'est la tuile de la ville
     * qui mène à ses bâtiments, et non l'inverse.
     */
    public function testCliquerLaVilleOuvreSesBatiments(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'entrer@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $lien = $crawler->filter(\sprintf('a[href="/partie/%d/ville"]', $partie->getId()));

        self::assertGreaterThan(0, $lien->count());

        $client->click($lien->first()->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Bâtiments dressés');
    }

    public function testLaRepriseMeneAuTerritoire(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'reprise-carte@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d', $partie->getId()));

        self::assertGreaterThan(
            0,
            $crawler->filter(\sprintf('a[href="/partie/%d/carte"]', $partie->getId()))->count(),
        );
    }

    public function testAvancerLeTempsDepuisLaVilleYRamene(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-ville@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $client->submit($crawler->selectButton('Quinzaine suivante')->form());

        self::assertResponseRedirects(\sprintf('/partie/%d/ville', $partie->getId()));
    }

    public function testAvancerLeTempsDepuisLaCarteYRamene(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-carte@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $client->submit($crawler->selectButton('Quinzaine suivante')->form());

        self::assertResponseRedirects(\sprintf('/partie/%d/carte', $partie->getId()));
    }

    public function testUnRetourFantaisisteRetombeSurLaCarte(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-force@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $formulaire = $crawler->selectButton('Quinzaine suivante')->form();
        $formulaire['retour'] = 'app_partie_abandonner';

        $client->submit($formulaire);

        self::assertResponseRedirects(\sprintf('/partie/%d/carte', $partie->getId()));
    }

    public function testUnJoueurNeVoitPasLaCarteDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->creerJoueur('proprio-carte@example.com');
        $partie = $this->lancer($proprietaire);

        $this->connecter($client, 'intrus-carte@example.com');
        $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    private function premiereZoneNonDecouverte(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                return $zone;
            }
        }

        self::fail('Une carte neuve devrait avoir des cases inexplorées.');
    }

    private function lancer(User $joueur): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
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

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
