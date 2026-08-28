<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

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

    /**
     * La barre de jeu compte en deben, et l'or n'y est plus un compteur à part :
     * c'est un métal qui s'affiche parmi les matériaux, quand la ville en a.
     * Seul le rendu réel peut le vérifier — le gabarit distingue la monnaie des
     * matériaux par `estLaMonnaie`.
     */
    public function testLaBarreDeJeuCompteEnDebenEtRangeLOrParmiLesMateriaux(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'deben@example.com');
        $partie = $this->lancer($joueur);

        $partie->getVille()->crediterRessources([Ressource::Or->value => 7]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();

        $compteurs = [];
        $lus = $crawler->filter('dl[data-chiffres] > div')->each(
            static fn (Crawler $compteur): array => [trim($compteur->filter('dt')->text()), trim($compteur->filter('dd')->text())],
        );
        foreach ($lus as [$libelle, $valeur]) {
            $compteurs[$libelle] = $valeur;
        }

        // Les deux se comptent désormais séparément : la dotation royale est en
        // deben, et les 7 unités d'or créditées restent 7 unités de métal.
        self::assertArrayHasKey('Deben', $compteurs, 'La monnaie s\'appelle désormais le deben.');
        self::assertSame('50', $compteurs['Deben'], 'La dotation royale de la mission 1.');

        self::assertArrayHasKey('Or', $compteurs, 'L\'or reste affiché, mais comme un matériau.');
        self::assertSame('7', $compteurs['Or'], 'L\'or extrait ne se confond plus avec la bourse.');
    }

    /**
     * Une partie échouée reste consultable, mais aucune de ses actions ne
     * modifie plus rien — le cycle en est l'exemple le plus visible.
     */
    public function testUnePartieEchoueeNAcceptePlusDeCycle(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'echouee@example.com');
        $partie = $this->lancer($joueur);

        // Le jeton est lié à la session, pas à l'état de la partie : on le
        // récupère pendant que le cycle est encore jouable, sur le vrai
        // formulaire — l'écran d'une partie échouée ne le rend plus.
        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $token = $crawler->filter(\sprintf('form[action="/partie/%d/cycle"] input[name="_token"]', $partie->getId()))->attr('value');
        self::assertIsString($token);

        $partie->echouer();
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'échouée');

        $client->request('POST', \sprintf('/partie/%d/cycle', $partie->getId()), ['_token' => $token]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Une ville pleine ne propose pas d'appeler du monde : elle dit d'abord
     * quoi bâtir. C'est le seul endroit où le joueur apprend le lien entre le
     * Quartier d'habitation et sa population.
     */
    public function testUneVillePleineExpliqueQuIlFautBatirAvantDAppeler(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'complet@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertTrue($partie->getVille()->manqueDeLogements());
        self::assertCount(
            0,
            $crawler->filter(\sprintf('form[action="/partie/%d/ville/appeler"]', $partie->getId())),
            'Aucun bouton d\'appel tant que les maisons sont pleines.',
        );
        self::assertSelectorTextContains('body', 'Quartier d\'habitation');
    }

    /**
     * Le parcours complet, jeton compris : une ville logée et fournie fait
     * venir une maisonnée, et la page suivante le dit.
     */
    public function testUneVilleLogeeFaitVenirUneMaisonnee(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'appel-ecran@example.com');
        $partie = $this->lancer($joueur);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));
        $ville->crediterRessources([Ressource::Deben->value => 500]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $population = $ville->population();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $formulaire = $crawler->filter(\sprintf('form[action="/partie/%d/ville/appeler"]', $partie->getId()));

        self::assertCount(1, $formulaire, 'Une ville logée doit pouvoir appeler du monde.');

        $client->submit($formulaire->form());

        self::assertResponseRedirects(\sprintf('/partie/%d/ville', $partie->getId()));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'maisonnée s\'installe');

        // La requête a rejoué le noyau : l'objet d'avant n'appartient plus au
        // gestionnaire courant. On relit la partie plutôt que de le rafraîchir.
        $relue = static::getContainer()->get(EntityManagerInterface::class)
            ->find(\App\Entity\GameSave::class, $partie->getId());
        self::assertNotNull($relue);
        self::assertGreaterThan($population, $relue->getVille()->population());
    }

    /**
     * Le parcours complet d'un recrutement, jeton compris — et la règle
     * d'affichage du doc 03 : le joueur voit des étoiles, jamais la
     * compétence chiffrée.
     */
    public function testAfficherUneAnnoncePuisRetenirUnChef(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'recrutement@example.com');
        $partie = $this->lancer($joueur);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $annonce = $crawler->filter(\sprintf('form[action="/partie/%d/ville/poster"]', $partie->getId()));
        self::assertGreaterThan(0, $annonce->count(), 'Un Grenier doit pouvoir recevoir une annonce.');

        $client->submit($annonce->first()->form());
        $crawler = $client->followRedirect();

        $etoiles = $crawler->filter('[aria-label*="étoile"]');
        self::assertGreaterThanOrEqual(2, $etoiles->count(), 'Deux ou trois candidats se présentent.');

        // Doc 03 : « chiffré en interne, qualitatif à l'affichage ». L'offre
        // se relit depuis la base : celle en mémoire date d'avant la requête,
        // qui a rejoué le noyau.
        $offre = $this->relire($partie)->getVille()->offrePour(TypeDeBatiment::Grenier);
        self::assertNotNull($offre);

        foreach ($offre->candidats() as $candidat) {
            self::assertStringNotContainsString(
                \sprintf('>%d<', $candidat->competence),
                $crawler->html(),
                'La compétence chiffrée ne doit jamais être imprimée.',
            );
        }

        $embauche = $crawler->filter(\sprintf('form[action="/partie/%d/ville/embaucher"]', $partie->getId()));
        $client->submit($embauche->first()->form());
        $crawler = $client->followRedirect();

        self::assertSelectorTextContains('body', 'prendra son poste');

        self::assertCount(1, $this->relire($partie)->getVille()->chefsDe(TypeDeBatiment::Grenier));
    }

    /**
     * Relit une partie depuis la base. Chaque requête du client rejoue le
     * noyau : l'objet d'avant n'appartient plus au gestionnaire courant, et
     * ne voit donc rien de ce que la requête a écrit.
     */
    private function relire(\App\Entity\GameSave $partie): \App\Entity\GameSave
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->clear();
        $relue = $gestionnaire->find(\App\Entity\GameSave::class, $partie->getId());

        self::assertNotNull($relue);

        return $relue;
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
