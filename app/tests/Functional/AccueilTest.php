<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccueilTest extends WebTestCase
{
    public function testLAccueilEstPublicEtPresenteLeJeu(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Le pharaon vous confie une ville');
    }

    public function testLAccueilOffreLesDeuxPointsDEntreeDeCompte(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertGreaterThan(0, $crawler->filter('a[href="/inscription"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/connexion"]')->count());
    }
}
