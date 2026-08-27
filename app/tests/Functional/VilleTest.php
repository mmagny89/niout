<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VilleTest extends WebTestCase
{
    public function testUneVilleNeuvePossedeSaResidenceFamiliale(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'residence@example.com');
        $partie = $this->lancer($joueur);

        $ville = $partie->getVille();

        self::assertTrue(
            $ville->possede(TypeDeBatiment::ResidenceFamiliale),
            'La Résidence familiale est là dès l\'arrivée, jamais construite.',
        );
        self::assertCount(1, $ville->getBatiments());
    }

    public function testLaVilleAfficheLesBatimentsDressesEtCeuxABatir(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'vue@example.com');
        $partie = $this->lancer($joueur);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Avaris');
        self::assertSelectorTextContains('body', 'Résidence familiale');
        self::assertSelectorTextContains('body', 'Bâtiments dressés');
        self::assertSelectorTextContains('body', 'À bâtir');
    }

    public function testLesEmpechementsSontExpliquesAuJoueur(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'motifs@example.com');
        $partie = $this->lancer($joueur);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        // Un bâtiment grisé sans motif laisserait deviner s'il s'agit d'un
        // manque de ressources, d'une contrainte, ou d'un défaut du jeu.
        self::assertSelectorTextContains('body', 'Il vous manque');
        self::assertSelectorTextContains('body', 'point d\'eau');
    }

    public function testLaDotationPermetDeBatirLEntrepot(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'entrepot@example.com');
        $partie = $this->lancer($joueur);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        // L'Entrepôt coûte exactement la dotation en matériaux (doc 01, doc 13) :
        // c'est le premier chantier que le joueur peut réellement engager.
        $offres = static::getContainer()->get(\App\Game\CatalogueDeLaVille::class)->pour($partie->getVille());
        $realisables = array_filter($offres, static fn ($offre): bool => $offre->estRealisable());

        self::assertNotEmpty($realisables, 'La dotation doit permettre au moins un chantier.');
    }

    /**
     * Le document 15 veut les compteurs visibles en permanence, pas seulement
     * sur l'écran de la ville : le joueur doit toujours savoir de quoi il
     * dispose sans changer de page.
     */
    public function testLesCompteursSuiventLeJoueurSurTousLesEcransDePartie(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'barre@example.com');
        $partie = $this->lancer($joueur);
        $id = $partie->getId();

        foreach ([\sprintf('/partie/%d', $id), \sprintf('/partie/%d/ville', $id), \sprintf('/partie/%d/commande', $id)] as $url) {
            $crawler = $client->request('GET', $url);

            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('body', 'Tekhi, an 1 — Akhèt', \sprintf('Date absente sur %s.', $url));
            self::assertGreaterThan(
                0,
                $crawler->filter(\sprintf('form[action="/partie/%d/cycle"]', $id))->count(),
                \sprintf('Le passage de cycle doit rester à portée sur %s.', $url),
            );
        }
    }

    public function testLEcranDAbandonNePropoSePasDAvancerLeTemps(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'renoncement@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/abandonner', $partie->getId()));

        self::assertCount(
            0,
            $crawler->filter(\sprintf('form[action="/partie/%d/cycle"]', $partie->getId())),
            'Avancer le temps n\'a pas de sens sur un écran de renoncement.',
        );
    }

    public function testUnJoueurNeVoitPasLaVilleDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->creerJoueur('proprio-ville@example.com');
        $partie = $this->lancer($proprietaire);

        $this->connecter($client, 'intrus-ville@example.com');
        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    private function lancer(User $joueur): \App\Entity\GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function connecter(KernelBrowser $client, string $email): User
    {
        $user = $this->creerJoueur($email);
        $client->loginUser($user);

        return $user;
    }

    private function creerJoueur(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
