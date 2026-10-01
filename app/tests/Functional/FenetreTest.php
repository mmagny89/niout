<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La fenêtre au-dessus de la carte : l'état est dans l'URL, le serveur rend la
 * carte avec la fenêtre déjà remplie, et le paramètre ne se suit jamais sans
 * être validé.
 */
final class FenetreTest extends WebTestCase
{
    public function testLaTuileDeLaVilleOuvreLaFenetreDansLeCadre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-tuile@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $lien = $crawler->filter(\sprintf('a[href="/partie/%d/ville?onglet=residence_familiale"][data-turbo-frame="fenetre"]', $partie->getId()));
        self::assertGreaterThan(0, $lien->count(), 'La ville s\'ouvre dans le cadre, pas en page.');
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"]:not([open])');
        self::assertSelectorExists('turbo-frame#fenetre');
        self::assertSelectorExists('turbo-frame#barre');
    }

    /**
     * Le JavaScript de la fenêtre ne se teste pas sans navigateur ; ce qu'on
     * peut garantir, c'est ce dont il dépend : le contrôleur, ses cibles, le
     * bouton de fermeture et l'adresse de la barre à recharger.
     */
    public function testLaFenetreFournitCeDontLeControleurDepend(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-structure@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $racine = $crawler->filter('[data-controller="fenetre"]');
        self::assertCount(1, $racine);
        self::assertStringContainsString(\sprintf('/partie/%d/barre', $partie->getId()), (string) $racine->attr('data-fenetre-barre-value'));
        self::assertCount(1, $crawler->filter('dialog[data-fenetre-target="fenetre"] button[data-action="fenetre#fermer"][aria-label]'));
        self::assertCount(1, $crawler->filter('dialog[data-fenetre-target="fenetre"] turbo-frame#fenetre'));
        self::assertSelectorExists('dialog[aria-labelledby="fenetre-titre"]');
    }

    public function testLaVilleRepondEnCadreAvecSonRailEtSonPanneau(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-cadre@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#fenetre');
        self::assertCount(1, $crawler->filter(\sprintf('nav a[href="/partie/%d/ville?onglet=grenier"][aria-current="page"]', $partie->getId())));
        self::assertCount(1, $crawler->filter(\sprintf('nav a[href="/partie/%d/ville?onglet=residence_familiale"]', $partie->getId())));
        self::assertSelectorExists('#panneau-grenier');
        self::assertSelectorNotExists('header', 'Un cadre de fenêtre ne rend pas la coque.');
        self::assertSelectorNotExists('turbo-frame#barre', 'Ni la barre.');
    }

    public function testSansEnTeteDeCadreLaVilleRendLaCarteOuverte(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-renvoi@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=marche', $partie->getId()));

        // Une adresse tapée, un lien partagé : la carte, avec la fenêtre ouverte.
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open]');
        self::assertCount(1, $crawler->filter('dialog #panneau-marche'));
        self::assertGreaterThan(0, $crawler->filter('[data-carte-target="grille"]')->count(), 'La carte est toujours là, derrière.');
    }

    public function testLaCarteRendLaFenetreDejaOuvertePourQuiRecharge(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-recharge@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->ajouterChantier(new Chantier($ville, TypeDeBatiment::Port, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $ouvre = \sprintf('/partie/%d/ville?onglet=marche', $partie->getId());
        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($ouvre)));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open]');
        self::assertCount(1, $crawler->filter('dialog #panneau-marche'));
        self::assertCount(1, $crawler->filter('dialog nav a[aria-current="page"][href$="onglet=marche"]'));
        // Un chantier de bâtiment qui n'existe pas encore ne fait pas un carré.
        self::assertCount(0, $crawler->filter('dialog nav a[href$="onglet=port"]'));
    }

    /**
     * Le paramètre vient du visiteur : on ne le suit jamais sans le valider.
     */
    #[DataProvider('ouvertures')]
    public function testUneOuvertureSuspecteNOuvreRien(string $ouvre): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-suspecte-'.md5($ouvre).'@example.com');
        $ouvre = str_replace('{id}', (string) $partie->getId(), $ouvre);

        $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($ouvre)));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"]:not([open])');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ouvertures(): iterable
    {
        yield 'un autre site' => ['https://example.org/partie/{id}/ville?onglet=marche'];
        yield 'une URL relative au protocole' => ['//example.org/partie/{id}/ville?onglet=marche'];
        yield 'la partie d\'un autre' => ['/partie/999999/ville?onglet=marche'];
        yield 'la carte elle-même' => ['/partie/{id}/carte'];
        yield 'une route qui n\'est pas une fenêtre' => ['/partie/{id}/commande'];
        yield 'un jeton de requête forgé' => ['/partie/{id}/ville?onglet=<script>'];
        yield 'une remontée' => ['/partie/{id}/../{id}/ville'];
        yield 'un caractère de contrôle' => ["/partie/{id}/ville?onglet=marche\r\nX: 1"];
        yield 'du vide' => [''];
    }

    public function testLaBarreSeRechargeSeuleEtDitLaVerite(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-barre@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/barre?retour=app_partie_carte&ouvre=%s', $partie->getId(), rawurlencode(\sprintf('/partie/%d/ville?onglet=marche', $partie->getId()))));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#barre[target="_top"]');
        self::assertSelectorTextContains('turbo-frame#barre', 'Deben');
        self::assertSame(
            \sprintf('/partie/%d/ville?onglet=marche', $partie->getId()),
            $crawler->filter('turbo-frame#barre input[name="ouvre"]')->attr('value'),
            'Le bouton de cycle reprend la fenêtre où elle est.',
        );
    }

    public function testUnRetourInconnuRetombeSurLaCarte(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-retour@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/barre?retour=app_admin', $partie->getId()));

        self::assertSame('app_partie_carte', $crawler->filter('turbo-frame#barre input[name="retour"]')->attr('value'));
    }

    public function testLaQuinzaineRouvreLaFenetreOuElleEtait(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-cycle@example.com');
        $cite = \sprintf('/partie/%d/ville?onglet=marche', $partie->getId());

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($cite)));
        $formulaire = $crawler->filter(\sprintf('form[action="/partie/%d/cycle"]', $partie->getId()));
        self::assertSame($cite, $formulaire->filter('input[name="ouvre"]')->attr('value'));

        $client->request('POST', \sprintf('/partie/%d/cycle', $partie->getId()), [
            '_token' => $formulaire->filter('input[name="_token"]')->attr('value'),
            'retour' => 'app_partie_carte',
            'ouvre' => $cite,
        ]);

        self::assertResponseRedirects();
        $lieu = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('ouvre=', $lieu);
        self::assertStringContainsString('ouvre='.$cite, urldecode($lieu));
    }

    public function testUnAutreJoueurNeVoitPasLaFenetre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-proprio@example.com');
        $autre = new User();
        $autre->setEmail('fenetre-intrus@example.com');
        $autre->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($autre);
        $gestionnaire->flush();
        $client->loginUser($autre);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', \sprintf('/partie/%d/barre', $partie->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    private function partie(KernelBrowser $client, string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
