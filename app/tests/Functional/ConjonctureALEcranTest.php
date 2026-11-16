<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\Commerce;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'étal dit **pourquoi** la même annonce rend plus ou moins cette quinzaine
 * (`docs/plan-pnj-palier-a.md` § 6.1) — en signe, en chiffre et en mots, jamais en couleur seule.
 */
final class ConjonctureALEcranTest extends WebTestCase
{
    public function testLEtalNommeLaConjonctureEtSaCause(): void
    {
        $client = static::createClient();
        $joueur = new User();
        $joueur->setEmail('etal-monde@example.com');
        $joueur->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();
        $client->loginUser($joueur);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, niveau: 2));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, niveau: 2));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000, Ressource::Ble->value => 5_000]);

        $route = static::getContainer()->get(Commerce::class)->ouvrir($partie, 'canaan');

        while (!$route->estOuverte()) {
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
        }

        $gestionnaire->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=entrepot', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame(1, preg_match('/(Marché (calme|porteur|lourd)|Disette|Bonne récolte|Foire|Besoin d\'un ouvrage) — /u', $crawler->text()), 'L\'étal doit dire pourquoi.');
        self::assertGreaterThan(0, $crawler->filter('.sr-only:contains("Neutre"), .sr-only:contains("Favorable"), .sr-only:contains("Défavorable")')->count());
    }
}
