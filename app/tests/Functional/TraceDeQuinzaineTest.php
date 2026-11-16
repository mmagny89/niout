<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\FaitDeMonde;
use App\Entity\GameSave;
use App\Entity\InstantaneDeQuinzaine;
use App\Entity\User;
use App\Fenetre\GensDeLaVille;
use App\Game\Candidat;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\PhotoDeLaVille;
use App\Game\TraceDeQuinzaine;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeFait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La trace d'une quinzaine : ce que le jeu garde pour pouvoir la rejouer
 * (`docs/plan-pnj-ville-vivante.md` §§ 7 et 8, lot V2) — la photographie du début
 * et les faits, trois quinzaines au plus, rattachées à leur partie.
 */
final class TraceDeQuinzaineTest extends WebTestCase
{
    public function testLaQuinzaineQuiPasseLaisseUneTrace(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('trace@example.com');
        $cycle = $partie->getCycle();

        self::assertNull($this->trace()->derniere($partie), 'Rien à rejouer avant la première quinzaine.');

        $this->passer($partie);
        $derniere = $this->trace()->derniere($partie);

        self::assertNotNull($derniere);
        self::assertSame($cycle, $derniere['cycle'], 'La trace porte la quinzaine vécue, pas celle qui s\'ouvre.');
        self::assertSame(15, $derniere['photo']['jours']);
        self::assertContains('famille', array_column($derniere['photo']['candidats'], 'cle'));
    }

    /**
     * Trois quinzaines au plus : rejouer plus loin n'est pas possible, et la purge
     * fait partie du contrat.
     */
    public function testLaTraceNeGardeQueLesTroisDernieresQuinzaines(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('purge@example.com');
        $premier = $partie->getCycle();

        foreach (range(1, 5) as $ignore) {
            $this->passer($partie);
        }

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $instantanes = $gestionnaire->getRepository(InstantaneDeQuinzaine::class)->findBy(['partie' => $partie]);
        $cycles = array_map(static fn (InstantaneDeQuinzaine $i): int => $i->getCycle(), $instantanes);
        sort($cycles);

        self::assertSame([$premier + 2, $premier + 3, $premier + 4], $cycles);
        self::assertSame($premier + 4, $this->trace()->derniere($partie)['cycle'] ?? null);

        foreach ($gestionnaire->getRepository(FaitDeMonde::class)->findBy(['partie' => $partie]) as $fait) {
            self::assertGreaterThanOrEqual($premier + 2, $fait->getQuinzaine(), 'Les faits d\'une quinzaine purgée partent avec elle.');
        }
    }

    /**
     * Réécrire une quinzaine déjà tracée la remplace : les faits ne doublent pas.
     */
    public function testEnregistrerDeuxFoisLaMemeQuinzaineNeDoublePas(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('double@example.com');
        $photo = static::getContainer()->get(PhotoDeLaVille::class);

        $avant = $photo->prendre($partie);
        $apres = $avant;
        $apres['epidemie'] = true;
        $apres['malades'] = 3;

        $this->trace()->enregistrer($partie, $avant, $apres);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        // Une seconde écriture, dans une requête d'après : elle remplace, elle ne double pas.
        $this->trace()->enregistrer($partie, $avant, $apres);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $faits = static::getContainer()->get(EntityManagerInterface::class)->getRepository(FaitDeMonde::class)->findBy(['partie' => $partie]);
        $epidemies = array_filter($faits, static fn (FaitDeMonde $f): bool => TypeDeFait::EpidemieEclate === $f->getType());

        self::assertCount(1, $epidemies);
        self::assertSame(3, array_values($epidemies)[0]->getQuantite());
    }

    /**
     * La trace se vide avec sa partie, sans que rien n'ait à y penser : la base
     * s'en charge, et rien ne référence `User`.
     */
    public function testLaTraceSeVideAvecLaPartie(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('cascade@example.com');
        $this->passer($partie);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $id = $partie->getId();

        self::assertGreaterThan(0, $gestionnaire->getRepository(InstantaneDeQuinzaine::class)->count(['partie' => $id]));

        $gestionnaire->remove($partie);
        $gestionnaire->flush();

        self::assertSame(0, (int) $gestionnaire->getConnection()->fetchOne('SELECT COUNT(*) FROM instantane_de_quinzaine WHERE partie_id = ?', [$id]));
        self::assertSame(0, (int) $gestionnaire->getConnection()->fetchOne('SELECT COUNT(*) FROM fait_de_monde WHERE partie_id = ?', [$id]));
    }

    /**
     * Les faits se déduisent de l'état, pas du texte : une fièvre d'une quinzaine
     * qui s'éteint est un fait.
     */
    public function testUneFievreQuiSEteintEstUnFait(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('fievre@example.com');
        $partie->getVille()->declarerUneEpidemie(1, 30);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $this->passer($partie);
        $types = array_map(static fn (array $f): TypeDeFait => $f['type'], $this->trace()->derniere($partie)['faits'] ?? []);

        self::assertContains(TypeDeFait::EpidemieFinie, $types);
    }

    /**
     * Un chef que la quinzaine emporte se retrouve dans la trace : le fait est
     * déduit de sa disparition, pas d'un message.
     */
    public function testUnChefQuiDisparaitEstUnFaitDeLaQuinzaine(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('chef-parti@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $chef->donnerUnPrenom('Nebnefer');
        $ville->ajouterEmploye($chef);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();

        $idDuChef = $chef->getId();
        $photo = static::getContainer()->get(PhotoDeLaVille::class);
        $avant = $photo->prendre($partie);
        $ville->retirerEmploye($chef);
        $apres = $photo->prendre($partie);
        $this->trace()->enregistrer($partie, $avant, $apres);
        $gestionnaire->flush();

        $faits = $this->trace()->derniere($partie)['faits'] ?? [];
        $partis = array_filter($faits, static fn (array $f): bool => TypeDeFait::ChefParti === $f['type']);

        self::assertCount(1, $partis);
        self::assertSame('chef-'.$idDuChef, array_values($partis)[0]['acteur']);
        self::assertStringContainsString('Nebnefer', (string) array_values($partis)[0]['details']['libelle']);
    }

    /**
     * La photographie sait quels bâtiments sont debout — la Résidence, là dès le premier jour,
     * et ceux qu'on a bâtis : c'est ce qui dit à la chorégraphie où l'on peut aller.
     */
    public function testLaPhotographieNommeLesBatimentsDebout(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('batiments@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Auberge, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $batiments = static::getContainer()->get(PhotoDeLaVille::class)->prendre($partie)['batiments'] ?? [];

        self::assertContains('residence_familiale', $batiments);
        self::assertContains('auberge', $batiments);
        self::assertNotContains('temple', $batiments, 'Un bâtiment non bâti n\'est pas un lieu où aller.');
    }

    /**
     * Un jour de la quinzaine passée remplace la ville d'à présent par sa
     * reconstitution ; un jour hors de la quinzaine, ou une partie sans trace,
     * retombe sur la ville d'à présent.
     */
    public function testUnJourReconstitueLaVilleEtUnJourHorsDeLaQuinzaineRetombeSurLeMaintenant(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('jour@example.com');
        $gens = static::getContainer()->get(GensDeLaVille::class);

        self::assertNull($gens->pour($partie, 3)['jour'], 'Sans trace, rien à rejouer.');
        self::assertNull($gens->frise($partie));

        $this->passer($partie);

        self::assertSame(3, $gens->pour($partie, 3, 2)['jour']);
        self::assertSame('L\'après-midi', $gens->pour($partie, 3, 2)['momentLibelle']);
        self::assertNull($gens->pour($partie, 99)['jour'], 'Un jour hors de la quinzaine : la ville d\'à présent.');
        self::assertNull($gens->pour($partie, 0)['jour']);
        self::assertSame(1, $gens->pour($partie, 3, 42)['moment'], 'Un moment inconnu retombe sur le matin.');
        self::assertEquals($gens->pour($partie, 3, 1), $gens->pour($partie, 3, 1), 'Le même film, à chaque lecture.');
    }

    /**
     * La fenêtre porte la frise : un lien par jour, le jour courant marqué, et
     * la mention que tout est reconstitué.
     */
    public function testLaFenetrePorteLaFriseEtLeJournalDUnChef(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'frise@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $chef->donnerUnPrenom('Nebnefer');
        $ville->ajouterEmploye($chef);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $this->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville/gens?jour=3&moment=1', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertCount(15, $crawler->filter('ol[class*="flex"] a[aria-label^="Jour "]'));
        self::assertCount(1, $crawler->filter('a[aria-label^="Jour 3"][aria-current="true"]'));
        self::assertSelectorTextContains('body', 'sans valeur de règle');
        self::assertSelectorTextContains('body', 'Jour 3 sur 15');

        $fiche = $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=chef-%d&jour=3', $partie->getId(), $chef->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#journal-titre', 'jour par jour');
        self::assertCount(15, $fiche->filter('#journal-titre ~ ol > li'));
        self::assertStringContainsString('À son poste', $fiche->filter('#journal-titre ~ ol')->text());
    }

    /**
     * **Passer la quinzaine depuis la ville vue d'en haut en joue le film** : le retour porte `film=1`,
     * la page demande au contrôleur de se lancer, et l'adresse seule ne suffit pas à le rejouer ailleurs.
     */
    public function testPasserLaQuinzaineDepuisLaVilleLanceLeFilm(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'film-auto@example.com');
        $id = $partie->getId();

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $id));
        $jeton = (string) $crawler->filter('form[action$="/cycle"] input[name="_token"]')->first()->attr('value');

        $client->request('POST', \sprintf('/partie/%d/cycle', $id), ['_token' => $jeton, 'retour' => 'app_partie_carte', 'vue' => 'ville', 'ouvre' => '']);

        self::assertResponseRedirects();
        $adresse = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('vue=ville', $adresse);
        self::assertStringContainsString('film=1', $adresse);

        $page = $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $page->filter('[data-controller="ville-vivante"][data-ville-vivante-lancer-value="true"]'));

        // Sans la demande, ou en regardant un jour précis, le film ne se lance pas.
        $sans = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $id));
        self::assertCount(1, $sans->filter('[data-controller="ville-vivante"]'));
        self::assertCount(0, $sans->filter('[data-ville-vivante-lancer-value]'));

        $unJour = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville&film=1&jour=3', $id));
        self::assertCount(0, $unJour->filter('[data-ville-vivante-lancer-value]'));
    }

    /**
     * **Depuis le territoire, ou depuis une fenêtre, on arrive sur la ville** : la fenêtre se referme (elle
     * masquerait le film), la case choisie est laissée, et le film se lance. Depuis la ville (l'écran des
     * bâtiments), on reste où l'on est : il n'y a pas de ville à regarder derrière un panneau.
     */
    public function testPasserLaQuinzaineDepuisLeTerritoireOuUneFenetreMeneALaVille(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'film-territoire@example.com');
        $id = $partie->getId();

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $id));
        $jeton = (string) $crawler->filter('form[action$="/cycle"] input[name="_token"]')->first()->attr('value');

        // Territoire, avec une case choisie et une fenêtre ouverte.
        $client->request('POST', \sprintf('/partie/%d/cycle', $id), ['_token' => $jeton, 'retour' => 'app_partie_carte', 'zone' => '1-1', 'ouvre' => '/partie/'.$id.'/ville/gens']);

        self::assertResponseRedirects();
        $adresse = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('vue=ville', $adresse);
        self::assertStringContainsString('film=1', $adresse);
        self::assertStringNotContainsString('ouvre=', $adresse, 'La fenêtre masquerait le film.');
        self::assertStringNotContainsString('zone=', $adresse);

        // L'écran des bâtiments : on y reste.
        $client->request('POST', \sprintf('/partie/%d/cycle', $id), ['_token' => $jeton, 'retour' => 'app_partie_ville', 'onglet' => 'residence_familiale']);

        self::assertResponseRedirects();
        $adresse = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('/ville', $adresse);
        self::assertStringNotContainsString('film=1', $adresse);
    }

    /**
     * **Le test ne dépend pas du hasard** : un chef d'ancienneté 2 (une chance sur deux de partir à chaque
     * quinzaine) reste en poste pendant huit passages, parce que le hasard des départs est neutralisé
     * en test (`services_test.yaml`). C'est ce qui rendait la fenêtre de ce fichier instable.
     */
    public function testAucunChefNePartAuHasardPendantLesTests(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('sans-depart@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 2, [], null, 2, 0), $partie->getCycle());
        $ville->ajouterEmploye($chef);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (range(1, 8) as $ignore) {
            $this->passer($partie);
        }

        self::assertContains($chef, $ville->getEmployes()->toArray());
    }

    /**
     * La ville vue d'en haut annonce qu'elle montre un jour de la quinzaine
     * passée, et offre de revenir à maintenant.
     */
    public function testLaVilleAnnonceLeJourRejoue(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'bandeau@example.com');
        $this->passer($partie);

        $client->request('GET', \sprintf('/partie/%d/carte?vue=ville&jour=3&moment=2', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Jour 3 de la quinzaine passée, l\'après-midi');
        self::assertSelectorExists('a[href="/partie/'.$partie->getId().'/carte?vue=ville"]');

        // Un jour qui n'existe pas : la ville d'à présent, sans erreur ni bandeau.
        $client->request('GET', \sprintf('/partie/%d/carte?vue=ville&jour=99', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('de la quinzaine passée,', (string) $client->getResponse()->getContent());
    }

    private function passer(GameSave $partie): void
    {
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);
    }

    private function trace(): TraceDeQuinzaine
    {
        return static::getContainer()->get(TraceDeQuinzaine::class);
    }

    private function lancerPartie(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function lancerConnecte(KernelBrowser $client, string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $client->loginUser($partie->getJoueur());

        return $partie;
    }
}
