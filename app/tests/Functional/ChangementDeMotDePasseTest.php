<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Changer son mot de passe depuis son compte.
 *
 * Le parcours « mot de passe oublié » existait déjà, mais il suppose de
 * recevoir un message — donc d'attendre, et d'avoir une adresse qui fonctionne.
 * Celui-ci demande l'ancien mot de passe à la place.
 */
final class ChangementDeMotDePasseTest extends WebTestCase
{
    private const string ANCIEN = 'Ouadi-Hammamat-1194';
    private const string NOUVEAU = 'grenier-flamant-cuivre-orage';

    public function testUnJoueurChangeSonMotDePasse(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'change@example.com');

        $this->soumettre($client, self::ANCIEN, self::NOUVEAU);

        self::assertResponseRedirects('/compte');
        self::assertTrue($this->verifie($joueur, self::NOUVEAU), 'Le nouveau mot de passe doit être posé.');
        self::assertFalse($this->verifie($joueur, self::ANCIEN), 'L\'ancien ne doit plus ouvrir.');
    }

    /**
     * **Le joueur reste connecté après le changement.** Le jeton en session
     * porte l'utilisateur tel qu'il était ; sans reconnexion explicite, il se
     * retrouverait déconnecté au clic suivant — après une action réussie, ce
     * qui se lit comme un échec.
     */
    public function testLeJoueurResteConnecteApresLeChangement(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'reste@example.com');

        $this->soumettre($client, self::ANCIEN, self::NOUVEAU);
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Mon compte');
    }

    /**
     * Sans l'ancien mot de passe, rien ne change : une session laissée ouverte
     * sur une machine partagée ne doit pas suffire à s'approprier le compte.
     */
    public function testUnAncienMotDePasseFauxNeChangeRien(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'usurpe@example.com');

        $this->soumettre($client, 'ce-n-est-pas-le-bon', self::NOUVEAU);

        // 422 et non une redirection : la page revient avec ses erreurs, sinon
        // le refus se perdrait en chemin.
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.form-error-message, [class*="text-terre"]');
        self::assertTrue($this->verifie($joueur, self::ANCIEN), 'Le mot de passe doit être resté le même.');
    }

    /**
     * Les exigences sont celles de l'inscription, et depuis la même source :
     * voir ContraintesDeMotDePasse. Un mot de passe faible accepté ici rendrait
     * les deux autres formulaires inutiles.
     */
    public function testUnNouveauMotDePasseFaibleEstRefuse(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'faible@example.com');

        $this->soumettre($client, self::ANCIEN, 'azertyuiopqs');

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($this->verifie($joueur, self::ANCIEN));
    }

    public function testUnVisiteurAnonymeNAccedePasAuFormulaire(): void
    {
        $client = static::createClient();

        $client->request('POST', '/compte/mot-de-passe');

        self::assertResponseRedirects('http://localhost/connexion');
    }

    private function soumettre(KernelBrowser $client, string $ancien, string $nouveau): void
    {
        $crawler = $client->request('GET', '/compte');
        $formulaire = $crawler->selectButton('Changer le mot de passe')->form([
            'account_password_form[ancienMotDePasse]' => $ancien,
            'account_password_form[plainPassword][first]' => $nouveau,
            'account_password_form[plainPassword][second]' => $nouveau,
        ]);

        $client->submit($formulaire);
    }

    private function verifie(User $joueur, string $motDePasse): bool
    {
        $conteneur = static::getContainer();
        $gestionnaire = $conteneur->get('doctrine')->getManager();
        $gestionnaire->clear();

        $frais = $gestionnaire->getRepository(User::class)->find($joueur->getId());
        self::assertInstanceOf(User::class, $frais);

        return $conteneur->get(UserPasswordHasherInterface::class)->isPasswordValid($frais, $motDePasse);
    }

    private function connecter(KernelBrowser $client, string $email): User
    {
        $conteneur = static::getContainer();
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword(
            $conteneur->get(UserPasswordHasherInterface::class)->hashPassword($joueur, self::ANCIEN),
        );

        $gestionnaire = $conteneur->get('doctrine')->getManager();
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        $client->loginUser($joueur);

        return $joueur;
    }
}
