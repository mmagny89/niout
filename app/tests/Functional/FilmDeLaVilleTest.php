<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le film sur la ville vue d'en haut : ce que la page donne au navigateur, et ce qu'elle
 * garde pour qui n'a pas de JavaScript ou demande moins de mouvement (lot V3,
 * `docs/plan-pnj-ville-vivante.md`).
 *
 * Que la lecture marche, fluide et sans erreur, ne se voit qu'au navigateur.
 */
final class FilmDeLaVilleTest extends WebTestCase
{
    /**
     * Sans trace, rien à rejouer : ni contrôleur, ni barre, ni calque de film.
     */
    public function testSansTraceIlNYAPasDeFilm(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'sans-film@example.com');

        $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller="ville-vivante"]');
        self::assertSelectorNotExists('[data-ville-vivante-target="barre"]');
        self::assertSelectorNotExists('[data-ville-vivante-target="film"]');
    }

    /**
     * Avec une trace : le contrôleur, ses données, la barre — cachée —, et le calque du
     * film — caché — à côté du calque immobile, qui reste la vérité sans JavaScript.
     */
    public function testUneQuinzainePasseeSeRejoue(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'film@example.com');
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="ville-vivante"]');
        self::assertSelectorExists('[data-ville-vivante-target="barre"][hidden]', 'La barre attend le JavaScript : sans lui, des boutons qui ne feraient rien.');
        self::assertSelectorExists('[data-ville-vivante-target="film"][hidden]');
        self::assertSelectorExists('[data-ville-vivante-target="statique"]:not([hidden])', 'Le calque immobile reste visible tant qu\'on ne lance rien.');

        $donnees = json_decode((string) $crawler->filter('[data-controller="ville-vivante"]')->attr('data-ville-vivante-film-value'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(15, $donnees['jours']);
        self::assertSame(60, $donnees['moments']);
        self::assertNotEmpty($donnees['figures']);
        self::assertSame(['À l\'aube', 'Le matin', 'L\'après-midi', 'Le soir'], $donnees['libellesDesMoments']);
        self::assertCount(\count($donnees['figures']), $crawler->filter('[data-ville-vivante-target="figure"]'));
    }

    /**
     * Le film ne contient que ce que la ville montre déjà : aucune silhouette de plus que
     * le plafond, et la barre propose de suivre les désignés seulement.
     */
    public function testLaBarreProposeDeSuivreLesDesignes(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'suivi@example.com');
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));
        $options = $crawler->filter('[data-ville-vivante-target="suivi"] option')->each(static fn ($o): string => (string) $o->attr('value'));

        self::assertSame('', $options[0], 'Personne, d\'abord.');
        self::assertContains('famille', $options);

        foreach (\array_slice($options, 1) as $cle) {
            self::assertMatchesRegularExpression('/^(famille|chef-\d+|medjay-\d+|rival)$/', $cle, 'Ni les travailleurs ni les maisons : ils sont représentatifs.');
        }
    }

    /**
     * Dans une ville peuplée, on se parle : les bulles sont dans la page — cachées, absentes sous
     * `md` —, la case « Lire les conversations » aussi, et **le texte de la fenêtre est celui de la
     * bulle**, pour qui ne la voit pas.
     */
    public function testLesConversationsSontDansLaVilleEtDansLaFenetre(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'bulles@example.com');
        $partie->getVille()->accueillir(60, 30, 6);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));
        $donnees = json_decode((string) $crawler->filter('[data-controller="ville-vivante"]')->attr('data-ville-vivante-film-value'), true, 512, \JSON_THROW_ON_ERROR);

        self::assertNotEmpty($donnees['paroles'], 'Une ville peuplée, une quinzaine : on s\'est parlé.');
        self::assertCount(2 * \count($donnees['paroles']), $crawler->filter('[data-ville-vivante-target="bulle"].max-md\:hidden'));
        self::assertSelectorExists('input[data-ville-vivante-target="ecouter"][type="checkbox"]');

        $parole = $donnees['paroles'][0];
        $jour = intdiv((int) $parole['t'], 4) + 1;
        $fenetre = $client->request('GET', \sprintf('/partie/%d/ville/gens?jour=%d&moment=1', $partie->getId(), $jour), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertStringContainsString($parole['texteA'], $fenetre->filter('[role="status"]')->text(), 'La bulle se lit aussi dans la fenêtre.');
        self::assertStringContainsString($parole['texteB'], $fenetre->filter('[role="status"]')->text());
    }

    /**
     * Sans conversation, rien à cocher.
     */
    public function testSansConversationPasDeCaseACocher(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'sans-bulle@example.com');
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));
        $donnees = json_decode((string) $crawler->filter('[data-controller="ville-vivante"]')->attr('data-ville-vivante-film-value'), true, 512, \JSON_THROW_ON_ERROR);

        if ([] === $donnees['paroles']) {
            self::assertSelectorNotExists('input[data-ville-vivante-target="ecouter"]');
            self::assertSelectorNotExists('[data-ville-vivante-target="bulle"]');
        } else {
            self::assertSelectorExists('input[data-ville-vivante-target="ecouter"]');
        }
    }

    /**
     * La page de contrôle des rues est un outil de développement : hors de cet
     * environnement, la route n'existe pas.
     */
    public function testLaPageDeControleDesRuesNExisteQuEnDeveloppement(): void
    {
        $client = static::createClient();
        $this->lancerConnecte($client, 'controle@example.com');

        $client->request('GET', '/dev/reseau-de-rues');

        self::assertResponseStatusCodeSame(404);
    }

    private function lancerConnecte(KernelBrowser $client, string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();
        $client->loginUser($joueur);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }
}
