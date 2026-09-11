<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\Lignee;
use App\Entity\User;
use App\Repository\GameSaveRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdministrationDesComptesTest extends WebTestCase
{
    public function testLEcranEstFermeAUnVisiteur(): void
    {
        $client = static::createClient();

        $client->request('GET', '/admin/comptes');

        self::assertResponseRedirects('/connexion');
    }

    /**
     * L'écran supprime définitivement le compte d'un tiers : un inscrit
     * ordinaire n'y entre pas, et le rôle ne s'accorde qu'en console
     * (`app:users:admin`).
     */
    public function testLEcranEstFermeAUnJoueurOrdinaire(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'ordinaire@example.com');

        $client->request('GET', '/admin/comptes');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLaPageDeGardeEstFermeeAUnJoueurOrdinaire(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'ordinaire@example.com');

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * La page de garde ne redirige pas vers les comptes : `/admin` et
     * `/admin/comptes` doivent rester deux endroits distincts, sans quoi la
     * deuxième section n'aurait nulle part où se poser.
     */
    public function testLaPageDeGardeMeneAuxComptesSansSYSubstituer(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $joueur = $this->creerJoueur('compte@example.com');
        $this->creerPartie($joueur, 'Avaris');

        $crawler = $client->request('GET', '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Administration');
        self::assertCount(1, $crawler->filter('a[href="/admin/comptes"]'));
        // Les chiffres de garde comptent réellement ce qu'il y a en base. La
        // base de test n'est pas remise à zéro entre les cas : on la compare à
        // elle-même plutôt qu'à un nombre écrit en dur, qui dépendrait de
        // l'ordre d'exécution.
        self::assertSelectorTextContains('dl', 'Comptes inscrits');
        self::assertSelectorTextContains('dl', 'Parties');
        self::assertSelectorTextContains('dl', (string) $this->depotDeComptes()->count([]));
        self::assertSelectorTextContains('dl', (string) $this->depotDeParties()->count([]));
    }

    public function testLEcranRecapituleLesComptesEtLeursParties(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $joueur = $this->creerJoueur('seti@example.com');
        $this->creerPartie($joueur, 'Avaris');
        $this->creerPartie($joueur, 'Memphis');

        $client->request('GET', '/admin/comptes');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'seti@example.com');
        self::assertSelectorTextContains('body', 'Avaris');
        self::assertSelectorTextContains('body', 'Memphis');
        // Où elles en sont : le numéro de mission et la région, pas seulement
        // un décompte.
        self::assertSelectorTextContains('body', 'Mission 1 sur 10');
        self::assertSelectorTextContains('body', '2 parties en cours');
    }

    public function testUnCompteSansPartieLeDitPlutotQueDeRienAfficher(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $this->creerJoueur('neuf@example.com');

        $client->request('GET', '/admin/comptes');

        self::assertSelectorTextContains('body', 'Aucune partie lancée');
    }

    /**
     * Rien ne se supprime en GET : un lien suffirait sinon à détruire un compte
     * depuis n'importe quelle page qui le pointerait.
     */
    public function testLAppelEnGetNeSupprimeRienEtDemandeConfirmation(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $joueur = $this->creerJoueur('sursis@example.com');

        $client->request('GET', \sprintf('/admin/comptes/%d/supprimer', $joueur->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Supprimer ce compte');
        self::assertNotNull($this->depotDeComptes()->find($joueur->getId()));
    }

    public function testLaSuppressionEmporteLeComptesSesPartiesEtSaLignee(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $joueur = $this->creerJoueur('adieu@example.com');
        $partie = $this->creerPartie($joueur, 'Bouhen');
        $this->creerLignee($joueur);

        $idDuJoueur = $joueur->getId();
        $idDeLaPartie = $partie->getId();

        $crawler = $client->request('GET', \sprintf('/admin/comptes/%d/supprimer', $idDuJoueur));
        $client->submit($crawler->selectButton('Oui, supprimer définitivement')->form());

        self::assertResponseRedirects('/admin/comptes');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'adieu@example.com');

        $this->gestionnaire()->clear();
        self::assertNull($this->depotDeComptes()->find($idDuJoueur));
        self::assertNull($this->depotDeParties()->find($idDeLaPartie));
        // La lignée appartient au joueur et survit à ses parties : aucune
        // cascade ne l'emporte, elle doit être supprimée explicitement — sans
        // quoi la contrainte de clé étrangère fait échouer la suppression.
        self::assertSame(0, $this->compterLesLignees());
    }

    /**
     * Se supprimer soi-même déconnecterait la session sur un utilisateur
     * disparu, et pourrait retirer le dernier accès à cet écran.
     */
    public function testUneAdministratriceNeSeSupprimePasElleMeme(): void
    {
        $client = static::createClient();
        $elle = $this->connecter($client, 'gardienne@example.com', administratrice: true);

        $client->request('GET', \sprintf('/admin/comptes/%d/supprimer', $elle->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    public function testLaSuppressionExigeUnJetonValide(): void
    {
        $client = static::createClient();
        $this->connecter($client, 'gardienne@example.com', administratrice: true);
        $joueur = $this->creerJoueur('jeton@example.com');
        $idDuJoueur = $joueur->getId();

        $client->request(
            'POST',
            \sprintf('/admin/comptes/%d/supprimer', $idDuJoueur),
            ['_token' => 'jeton-fantaisiste'],
        );

        self::assertResponseStatusCodeSame(403);
        $this->gestionnaire()->clear();
        self::assertNotNull($this->depotDeComptes()->find($idDuJoueur));
    }

    private function connecter(KernelBrowser $client, string $email, bool $administratrice = false): User
    {
        $user = $this->creerJoueur($email, $administratrice);
        $client->loginUser($user);

        return $user;
    }

    private function creerJoueur(string $email, bool $administratrice = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        if ($administratrice) {
            $user->setRoles([User::ROLE_ADMIN]);
        }

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

    private function creerLignee(User $joueur): Lignee
    {
        $lignee = new Lignee($joueur);

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($lignee);
        $gestionnaire->flush();

        return $lignee;
    }

    private function compterLesLignees(): int
    {
        return (int) $this->gestionnaire()
            ->createQuery('SELECT COUNT(l.id) FROM App\Entity\Lignee l')
            ->getSingleScalarResult()
        ;
    }

    private function depotDeComptes(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }

    private function depotDeParties(): GameSaveRepository
    {
        return static::getContainer()->get(GameSaveRepository::class);
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
