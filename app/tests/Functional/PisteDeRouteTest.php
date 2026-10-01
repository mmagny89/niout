<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\Commerce;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\SensDEchange;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les routes se lisent par état, et chaque caravane se suit sur sa piste.
 */
final class PisteDeRouteTest extends WebTestCase
{
    public function testUneRouteEnCheminPuisOuverteSeRangeDansLeBonGroupe(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('piste@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 2));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, 2));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Poterie->value => 300, Ressource::Deben->value => 1000]);
        $gestionnaire->flush();

        $commerce = static::getContainer()->get(Commerce::class);
        $route = $commerce->ouvrir($partie, 'memphis');
        $adresse = \sprintf('/partie/%d/ville?onglet=port', $partie->getId());

        $crawler = $client->request('GET', $adresse);
        self::assertSelectorTextContains('body', 'Convois en chemin');
        self::assertSelectorTextContains('body', 'Convoi en chemin : encore');

        $cycles = static::getContainer()->get(PassageDeCycle::class);

        while (!$route->estOuverte()) {
            $cycles->passer($partie);
        }

        $commerce->poserUnOrdre($partie, 'memphis', Ressource::Poterie, SensDEchange::Vendre, 8, 10);
        $cycles->passer($partie);

        $crawler = $client->request('GET', $adresse);
        self::assertSelectorTextContains('body', 'Routes ouvertes');
        self::assertSelectorTextContains('body', 'retour dans');
    }
}
