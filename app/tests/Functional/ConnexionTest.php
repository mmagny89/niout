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

    /**
     * La protection CSRF du projet est « stateless » : le jeton réel est produit
     * dans le navigateur, qui le dépose aussi en cookie (double-submit). Le
     * script qui s'en charge n'est chargé par Stimulus que si un élément porte
     * data-controller="csrf-protection".
     *
     * Les formulaires Symfony reçoivent cet attribut d'office. Celui de
     * connexion est écrit à la main : sans lui, toute connexion échoue sur
     * « Invalid CSRF token » — en navigateur seulement, jamais ici, puisque le
     * client de test n'exécute pas de JavaScript. D'où cette vérification de
     * structure, seule capable d'attraper la régression.
     */
    public function testLeChampCsrfDeConnexionActiveLeScriptQuiPoseLeCookie(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/connexion');

        $champ = $crawler->filter('input[name="_csrf_token"]');
        self::assertCount(1, $champ);
        self::assertSame('csrf-protection', $champ->attr('data-controller'));
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

        // La cible est verifiee, et pas seulement le fait qu'il y ait une
        // redirection : sans `default_target_path`, Symfony retombait sur `/`,
        // la page de presentation publique qui propose encore « Se connecter ».
        // On croyait la connexion sans effet. Un `assertResponseRedirects()`
        // nu laissait passer exactement cela.
        self::assertResponseRedirects('/parties');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes parties');
    }

    /**
     * Le chemin memorise garde la priorite sur la cible par defaut : qui
     * demande une page protegee avant de se connecter y revient apres, sinon
     * chaque lien profond partage se perdrait sur la liste des parties.
     */
    public function testUnePageDemandeeAvantConnexionRestePrioritaire(): void
    {
        $client = static::createClient();
        $this->creerUtilisateur('horemheb@example.com');

        $client->request('GET', '/compte');

        $crawler = $client->followRedirect();
        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => 'horemheb@example.com',
            '_password' => self::MOT_DE_PASSE,
        ]));

        self::assertResponseRedirects('http://localhost/compte');
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
        // En français : le message vient du catalogue « security » de Symfony,
        // qui suit `framework.default_locale`. Laissée à `en`, elle rendait
        // « Invalid credentials. » au milieu d'une interface française.
        self::assertSelectorTextContains('[role="alert"]', 'Identifiants invalides.');
    }

    /**
     * L'œil qui dévoile le mot de passe est produit par Stimulus, que le client
     * de test n'exécute pas : seule la structure est vérifiable ici — le champ
     * et son bouton portent bien les cibles du contrôleur qui les relie.
     */
    public function testLeChampMotDePasseOffreUnBoutonPourLeDevoiler(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/connexion');

        $conteneur = $crawler->filter('[data-controller="mot-de-passe"]');
        self::assertCount(1, $conteneur);
        self::assertCount(1, $conteneur->filter('input[data-mot-de-passe-target="champ"][name="_password"]'));

        $bouton = $conteneur->filter('button[data-mot-de-passe-target="bouton"]');
        self::assertCount(1, $bouton);
        self::assertSame('mot-de-passe#basculer', $bouton->attr('data-action'));
        // Rendu masqué : sans JavaScript, le bouton ne basculerait rien.
        self::assertNotNull($bouton->attr('hidden'));
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
