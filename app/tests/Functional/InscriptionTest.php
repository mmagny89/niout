<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InscriptionTest extends WebTestCase
{
    private const string MOT_DE_PASSE_VALIDE = 'Ouadi-Hammamat-1194';

    public function testUnVisiteurPeutCreerUnCompte(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'nakht@example.com');

        self::assertResponseRedirects('/compte');

        $user = $this->depotUtilisateurs()->findOneBy(['email' => 'nakht@example.com']);
        self::assertInstanceOf(User::class, $user);
    }

    public function testLeCompteEstUtilisableImmediatementMaisNonVerifie(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'ahmosis@example.com');

        $user = $this->depotUtilisateurs()->findOneBy(['email' => 'ahmosis@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified(), 'L\'adresse ne doit pas être vérifiée d\'emblée.');

        // Connexion automatique : la page de compte, protégée, est accessible.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon compte');
    }

    public function testUnEmailDeVerificationEstEnvoye(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'hatchepsout@example.com');

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $email);
        self::assertSame('Vérifiez votre adresse — Niout', $email->getSubject());
    }

    public function testUneAdresseDejaUtiliseeEstRefusee(): void
    {
        $client = static::createClient();
        $this->creerUtilisateur('doublon@example.com');

        $this->soumettreInscription($client, 'doublon@example.com');

        self::assertResponseIsUnprocessable();
        self::assertSelectorTextContains('body', 'Un compte existe déjà avec cette adresse.');
    }

    public function testUnMotDePasseTropCourtEstRefuse(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'faible@example.com', 'court1!');

        self::assertResponseIsUnprocessable();
        self::assertNull($this->depotUtilisateurs()->findOneBy(['email' => 'faible@example.com']));
    }

    public function testLesDeuxMotsDePasseDoiventCorrespondre(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'discordant@example.com', self::MOT_DE_PASSE_VALIDE, 'Autre-Chose-4242');

        self::assertResponseIsUnprocessable();
        self::assertNull($this->depotUtilisateurs()->findOneBy(['email' => 'discordant@example.com']));
    }

    private function soumettreInscription(
        KernelBrowser $client,
        string $email,
        string $motDePasse = self::MOT_DE_PASSE_VALIDE,
        ?string $confirmation = null,
    ): void {
        $crawler = $client->request('GET', '/inscription');
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->selectButton('Créer mon compte')->form([
            'registration_form[email]' => $email,
            'registration_form[plainPassword][first]' => $motDePasse,
            'registration_form[plainPassword][second]' => $confirmation ?? $motDePasse,
        ]);

        $client->submit($formulaire);
    }

    private function creerUtilisateur(string $email): User
    {
        $conteneur = static::getContainer();
        $user = new User();
        $user->setEmail($email);
        $user->setPassword(
            $conteneur->get(UserPasswordHasherInterface::class)->hashPassword($user, self::MOT_DE_PASSE_VALIDE),
        );

        $gestionnaire = $conteneur->get('doctrine')->getManager();
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }

    private function depotUtilisateurs(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
