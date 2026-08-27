<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ConnexionTest extends WebTestCase
{
    private const string MOT_DE_PASSE = 'Ouadi-Hammamat-1194';

    public function testLaPageDeCompteExigeUneConnexion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/compte');

        self::assertResponseRedirects('/connexion');
    }

    public function testUnJoueurSeConnecteAvecSesIdentifiants(): void
    {
        $client = static::createClient();
        $this->creerUtilisateur('seti@example.com');

        $crawler = $client->request('GET', '/connexion');
        $formulaire = $crawler->selectButton('Se connecter')->form([
            '_username' => 'seti@example.com',
            '_password' => self::MOT_DE_PASSE,
        ]);
        $client->submit($formulaire);

        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testUnMauvaisMotDePasseNeConnectePas(): void
    {
        $client = static::createClient();
        $this->creerUtilisateur('ramses@example.com');

        $crawler = $client->request('GET', '/connexion');
        $formulaire = $crawler->selectButton('Se connecter')->form([
            '_username' => 'ramses@example.com',
            '_password' => 'mauvais-mot-de-passe',
        ]);
        $client->submit($formulaire);
        $client->followRedirect();

        // L'utilisateur reste sur la page de connexion, avec un message d'erreur.
        self::assertRouteSame('app_login');
        self::assertSelectorExists('[role="alert"]');
    }

    public function testUnJoueurConnectePeutSeDeconnecter(): void
    {
        $client = static::createClient();
        $client->loginUser($this->creerUtilisateur('thot@example.com'));

        $client->request('GET', '/compte');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/deconnexion');
        $client->request('GET', '/compte');

        self::assertResponseRedirects('/connexion');
    }

    private function creerUtilisateur(string $email): User
    {
        $conteneur = static::getContainer();
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $conteneur->get(UserPasswordHasherInterface::class)->hashPassword($user, self::MOT_DE_PASSE),
        );

        $gestionnaire = $conteneur->get('doctrine')->getManager();
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
