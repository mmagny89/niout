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

    /**
     * Le titre de l'onglet est du texte : un `{% endblock %}` remplacé au mauvais endroit y a un
     * jour glissé une balise fermante, visible seulement dans l'onglet du navigateur. Et la page
     * s'ouvre sur le contrôleur d'apparition, dont les blocs `.revele` dépendent.
     */
    public function testLeTitreEstDuTexteEtLaPageSAnime(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/');

        self::assertSame(
            'Niout — bâtir une ville dans l\'Égypte du Nouvel Empire',
            trim($crawler->filter('title')->text()),
        );
        self::assertCount(1, $crawler->filter('[data-controller="apparition"]'));
        self::assertGreaterThan(0, $crawler->filter('[data-controller="apparition"] .revele')->count());
    }
}
