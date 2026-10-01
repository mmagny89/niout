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
