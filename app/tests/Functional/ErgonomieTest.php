<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Enigme;
use App\Game\FamilleDeRessource;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'ergonomie de l'écran de jeu.
 *
 * Ce qui se vérifie ici est de la **structure**, jamais un rendu : un test
 * fonctionnel n'a pas de fenêtre, ne calcule aucune hauteur et n'exécute pas
 * le JavaScript. Il peut en revanche garantir que la coque du jeu est bien
 * celle qui interdit le défilement, que chaque onglet a son panneau, et que
 * les compteurs sont rangés — c'est-à-dire tout ce qu'une régression casserait
 * en silence. La parade est la même que pour le jeton CSRF sans état.
 */
final class ErgonomieTest extends WebTestCase
{
    /**
     * **Deux coques, et une seule ne peut pas servir les deux.** La
     * présentation se lit dans une colonne étroite ; le jeu occupe la fenêtre
     * et n'y défile jamais.
     */
    public function testLeJeuEstEnPleinEcranEtLaPresentationContenue(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'coque@example.com');

        $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $corpsDuJeu = $client->getCrawler()->filter('body')->attr('class') ?? '';

        self::assertStringContainsString('h-screen', $corpsDuJeu);
        self::assertStringContainsString('overflow-hidden', $corpsDuJeu);
        self::assertCount(0, $client->getCrawler()->filter('footer'), 'Le pied de page appartient à la présentation.');

        $client->request('GET', '/compte');
        $corpsDeLaPresentation = $client->getCrawler()->filter('body')->attr('class') ?? '';

        self::assertStringContainsString('min-h-screen', $corpsDeLaPresentation);
        self::assertStringNotContainsString('overflow-hidden', $corpsDeLaPresentation);
        self::assertCount(1, $client->getCrawler()->filter('footer'));
    }

    /**
     * **Les compteurs sont rangés par famille**, chacune derrière un volet.
     * Quarante nombres alignés, c'était une barre où l'on ne trouvait plus
     * rien — et qui débordait sur un écran étroit.
     */
    public function testLaBarreDeJeuRangeLesCompteursParFamille(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'familles@example.com');
        $partie->getVille()->crediterRessources([
            Ressource::Or->value => 5,
            Ressource::Poterie->value => 3,
        ]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $barre = $crawler->filter('header')->first()->text();

        foreach (FamilleDeRessource::ordreDAffichage() as $famille) {
            self::assertStringContainsString($famille->libelle(), $barre);
        }

        // Le détail reste lisible : une famille qui ne dirait que son total
        // n'aiderait pas à décider d'une dépense.
        self::assertStringContainsString('Or', $barre);
        self::assertStringContainsString('Poterie', $barre);

        // Le deben ne se range nulle part : il est la monnaie.
        self::assertNull(Ressource::Deben->famille());
    }

    /**
     * Chaque onglet a son panneau, et **un seul est ouvert**. Le contrôle est
     * structurel : sans JavaScript, le test ne peut pas cliquer, mais un
     * onglet sans panneau est un bouton mort et se verrait ici.
     */
    public function testChaqueOngletDeLaVilleAUnPanneau(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'onglets@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        $onglets = $crawler->filter('[role="tab"]')->each(static fn ($n): string => (string) $n->attr('aria-controls'));
        $panneaux = $crawler->filter('[role="tabpanel"]')->each(static fn ($n): string => (string) $n->attr('id'));

        self::assertNotSame([], $onglets);
        self::assertSame($onglets, $panneaux, 'Un onglet sans panneau est un bouton mort.');
        self::assertCount(
            \count($onglets) - 1,
            $crawler->filter('[role="tabpanel"][hidden]'),
            'Un seul panneau est ouvert à la fois.',
        );
    }

    /**
     * **Tout le contenu reste dans le document**, seulement masqué : la page
     * est rendue d'un bloc, et changer d'onglet ne demande aucun aller-retour.
     * C'est aussi ce qui laisse les tests fonctionnels lire des sections que
     * le joueur n'a pas encore ouvertes.
     */
    public function testLesPanneauxFermesRestentDansLaPage(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'contenu@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('body', 'À bâtir');
        self::assertSelectorTextContains('body', 'Habitants');
    }

    /**
     * La carte se met à l'échelle plutôt que de déborder : une grille du Sinaï
     * fait près de mille six cents pixels de large, et le joueur perdait son
     * territoire hors de la fenêtre.
     */
    public function testLaCarteSAjusteAuPanneau(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'zoom@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertCount(1, $crawler->filter('[data-controller="carte"]'));
        self::assertCount(1, $crawler->filter('[data-carte-target="grille"]'));
        self::assertCount(3, $crawler->filter('[data-action^="carte#"]'), 'Approcher, éloigner, ajuster.');
    }

    /**
     * **La barre de jeu passe au-dessus de tout.** Sans position ni z-index,
     * elle ne crée aucun contexte d'empilement, et ses volets déroulants
     * passaient sous le contenu — sous la carte mise à l'échelle en
     * particulier, dont le `transform` crée le sien. Défaut réel, signalé.
     */
    public function testLaBarreDeJeuSEmpileAuDessusDuContenu(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'empilement@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $barre = $crawler->filter('header')->first()->attr('class') ?? '';

        self::assertStringContainsString('relative', $barre, 'Un z-index ne s\'applique qu\'à un élément positionné.');
        self::assertStringContainsString('z-50', $barre);
    }

    /**
     * **Rien de haut ne reste fixe.** Tout ce qui ne défile pas est de la
     * hauteur en moins pour le panneau ouvert : la ville n'a gardé au-dessus
     * de ses onglets que son titre, une ligne de saison et ses alertes. Le
     * mode d'essai, qui prenait à lui seul un tiers de l'écran, a pris un
     * onglet.
     */
    public function testLeModeDessaiNeMangePlusLaHauteurDeLEcran(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'essai-onglet@example.com', divin: true);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertCount(1, $crawler->filter('#panneau-essai'), 'Le mode d\'essai est un onglet.');
        self::assertCount(1, $crawler->filter('#onglet-essai'));
        self::assertCount(1, $crawler->filter('#panneau-essai[hidden]'), 'Et il est fermé par défaut.');

        // L'en-tête fixe ne porte plus le formulaire du mode d'essai.
        $fixe = $crawler->filter('section > div')->first()->html();
        self::assertStringNotContainsString('app_partie_divin', $fixe);
        self::assertStringNotContainsString('Passer en partie d\'essai', $fixe);
    }

    /**
     * **Un onglet, un bâtiment** (décision de la joueuse) : le Temple,
     * l'Auberge et la Maison des scribes se lisent chacun dans le sien, et
     * chaque onglet n'apparaît qu'une fois son bâtiment dressé — un onglet sur
     * du vide n'est pas un onglet.
     *
     * C'est aussi ce qui range les énigmes là où on les entend : celles de
     * l'Auberge dans l'Auberge, plus toutes entassées chez les scribes.
     */
    public function testChaqueBatimentQuiSeLitASonPropreOnglet(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'un-onglet-un-batiment@example.com');
        $ville = $partie->getVille();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        foreach (['temple', 'auberge', 'maison_des_scribes'] as $lieu) {
            self::assertCount(0, $crawler->filter('#onglet-'.$lieu), 'Sans le bâtiment, pas d\'onglet.');
        }

        foreach ([TypeDeBatiment::Temple, TypeDeBatiment::Auberge, TypeDeBatiment::MaisonDesScribes] as $type) {
            $ville->ajouterBatiment(new Building($ville, $type, 1));
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        foreach (['temple', 'auberge', 'maison_des_scribes'] as $lieu) {
            self::assertCount(1, $crawler->filter('#onglet-'.$lieu));
            self::assertCount(1, $crawler->filter('#panneau-'.$lieu));
        }

        // Les devinettes de l'Auberge se lisent dans l'Auberge, pas ailleurs.
        $auberge = $crawler->filter('#panneau-auberge')->html();
        self::assertStringContainsString(Enigme::DevinetteDuFleuve->enonce(), $auberge);
        self::assertStringNotContainsString(
            Enigme::DevinetteDuFleuve->enonce(),
            $crawler->filter('#panneau-maison_des_scribes')->html(),
        );

        // Et l'oracle se pose au Temple.
        self::assertStringContainsString(
            Enigme::OracleDeKarnak->enonce(),
            $crawler->filter('#panneau-temple')->html(),
        );
    }

    /**
     * **Un onglet par bâtiment dressé**, et rien d'autre : le découpage par
     * thème obligeait le joueur à deviner dans quel panneau ranger quoi. La
     * Résidence familiale est là dès le premier jour — elle recueille tout ce
     * qui n'appartient à aucun bâtiment.
     */
    public function testChaqueBatimentDresseAUnOngletEtRienDePlus(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'onglet-par-batiment@example.com');
        $ville = $partie->getVille();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSame(
            ['panneau-residence_familiale'],
            $crawler->filter('[role="tab"]')->each(static fn ($n): string => (string) $n->attr('aria-controls')),
            'Une ville neuve n\'a que le foyer de sa lignée.',
        );

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 1));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSame(
            ['panneau-residence_familiale', 'panneau-grenier', 'panneau-port'],
            $crawler->filter('[role="tab"]')->each(static fn ($n): string => (string) $n->attr('aria-controls')),
            'L\'ordre suit celui de TypeDeBatiment, stable d\'un rendu à l\'autre.',
        );
    }

    private function lancer(KernelBrowser $client, string $email, bool $divin = false): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        if ($divin) {
            $user->setRoles([User::ROLE_DIVIN]);
        }

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
