<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Ce que la Résidence met sous les yeux du joueur pour qu'il comprenne les
 * leviers du jeu : l'impôt, l'écriture, les chefs.
 */
final class ResidenceValorisationTest extends WebTestCase
{
    public function testLaResidenceAnnonceLImpotEtSonEcheance(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-impot@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Impôt du mois');
        self::assertSelectorTextContains('body', 'perçu dans 2 quinzaines');
    }

    public function testLaResidenceMontreCeQueRapporteLEcriture(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-ecriture@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('body', 'Les écritures de votre ville');
        self::assertSelectorTextContains('body', 'Néophyte');
        self::assertSelectorTextContains('body', 'Maison des scribes');
    }

    public function testUnBatimentSansChefEstSignale(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-chef@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('body', 'sans chef');
        self::assertSelectorTextContains('body', 'Grenier');
    }

    public function testLAnnonceDitCeQueVautUnChef(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-annonce@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sans chef, ce bâtiment tourne à 50 %');
        self::assertSelectorTextContains('body', 'Spécialités possibles ici');
    }

    /**
     * Un bâtiment dressé n'est plus « à bâtir » : il porte son bouton
     * « Améliorer » sur sa propre carte.
     */
    public function testUnBatimentDresseSeMonteSurSaCarteEtQuitteLaListeABatir(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-ameliorer@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        $dresses = $crawler->filter('#residence-section-batiments ul')->eq(0)->text();
        $aBatir = $crawler->filter('#residence-section-batiments ul')->eq(1)->text();

        self::assertStringContainsString('Grenier', $dresses);
        self::assertStringContainsString('Améliorer', $dresses);
        self::assertStringNotContainsString('Grenier', $aBatir, 'Déjà dressé : il ne reste pas à bâtir.');
        self::assertStringNotContainsString('Améliorer', $aBatir);
    }

    /**
     * Le Quartier montre ses habitants rangés en maisons, chacune décrite en
     * toutes lettres : le dessin seul ne se lit pas à l'oreille.
     */
    public function testLeQuartierDessineLesMaisonnees(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'quartier-maisons@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=quartier_habitation', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[role="img"][aria-label^="Maisonnée 1 :"]')->count());
        self::assertSelectorTextContains('body', 'Maison libre');
        self::assertSelectorTextContains('body', 'Alité par la fièvre');
    }

    private function partie(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): \App\Entity\GameSave
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
