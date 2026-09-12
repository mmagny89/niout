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

        self::assertResponseRedirects('/parties');

        $user = $this->depotUtilisateurs()->findOneBy(['email' => 'nakht@example.com']);
        self::assertInstanceOf(User::class, $user);
    }

    /**
     * **Les règles du mot de passe se lisent avant d'être opposées.**.
     *
     * Les contraintes existaient — douze caractères, une force minimale,
     * l'absence dans les fuites connues — mais rien ne les annonçait : on
     * proposait un mot de passe, on se le voyait refuser, et le message ne
     * disait pas quoi corriger.
     *
     * Ce que ce test peut garder, c'est la structure : le bloc de règles est
     * présent, il est rattaché au champ par `aria-describedby` — sans quoi un
     * lecteur d'écran ne l'associe pas à la saisie —, et le champ porte le
     * contrôleur qui l'anime. La justesse de l'indicateur, elle, relève de
     * `SeuilsDeForceTest`, et son animation ne se voit qu'en navigateur.
     */
    public function testLEcranAnnonceLesReglesDuMotDePasse(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/inscription');

        $champ = $crawler->filter('[data-force-du-mot-de-passe-target="champ"]');
        self::assertCount(1, $champ);
        self::assertSame('regles-du-mot-de-passe', $champ->attr('aria-describedby'));

        $regles = $crawler->filter('#regles-du-mot-de-passe');
        self::assertCount(1, $regles);
        self::assertStringContainsString('douze caractères', $regles->text());
        self::assertStringContainsString('fuites de données connues', $regles->text());

        // La jauge et les deux critères que le contrôleur coche en direct.
        self::assertCount(1, $crawler->filter('[data-force-du-mot-de-passe-target="jauge"]'));
        self::assertCount(1, $crawler->filter('[data-force-du-mot-de-passe-target="critereLongueur"]'));
        self::assertCount(1, $crawler->filter('[data-force-du-mot-de-passe-target="critereForce"]'));
    }

    public function testLeCompteEstUtilisableImmediatementMaisNonVerifie(): void
    {
        $client = static::createClient();

        $this->soumettreInscription($client, 'ahmosis@example.com');

        $user = $this->depotUtilisateurs()->findOneBy(['email' => 'ahmosis@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified(), 'L\'adresse ne doit pas être vérifiée d\'emblée.');

        // Connexion automatique : la page des parties, protégée, est accessible.
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mes parties');
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
