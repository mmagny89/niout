<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La fenêtre au-dessus de la carte : l'état est dans l'URL, le serveur rend la
 * carte avec la fenêtre déjà remplie, et le paramètre ne se suit jamais sans
 * être validé.
 */
final class FenetreTest extends WebTestCase
{
    public function testLaTuileDeLaVilleOuvreLaFenetreDansLeCadre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-tuile@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $lien = $crawler->filter(\sprintf('a[href="/partie/%d/ville?onglet=residence_familiale"][data-turbo-frame="fenetre"]', $partie->getId()));
        self::assertGreaterThan(0, $lien->count(), 'La ville s\'ouvre dans le cadre, pas en page.');
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"]:not([open])');
        self::assertSelectorExists('turbo-frame#fenetre');
        self::assertSelectorExists('turbo-frame#barre');
    }

    /**
     * Le JavaScript de la fenêtre ne se teste pas sans navigateur ; ce qu'on
     * peut garantir, c'est ce dont il dépend : le contrôleur, ses cibles, le
     * bouton de fermeture et l'adresse de la barre à recharger.
     */
    public function testLaFenetreFournitCeDontLeControleurDepend(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-structure@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $racine = $crawler->filter('[data-controller="fenetre"]');
        self::assertCount(1, $racine);
        self::assertStringContainsString(\sprintf('/partie/%d/barre', $partie->getId()), (string) $racine->attr('data-fenetre-barre-value'));
        self::assertCount(1, $crawler->filter('dialog[data-fenetre-target="fenetre"] button[data-action="fenetre#fermer"][aria-label]'));
        self::assertCount(1, $crawler->filter('dialog[data-fenetre-target="fenetre"] turbo-frame#fenetre'));
        self::assertSelectorExists('dialog[aria-labelledby="fenetre-titre"]');
    }

    public function testLaVilleRepondEnCadreAvecSonRailEtSonPanneau(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-cadre@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#fenetre');
        self::assertCount(1, $crawler->filter(\sprintf('nav a[href="/partie/%d/ville?onglet=grenier"][aria-current="page"]', $partie->getId())));
        self::assertCount(1, $crawler->filter(\sprintf('nav a[href="/partie/%d/ville?onglet=residence_familiale"]', $partie->getId())));
        self::assertSelectorExists('#panneau-grenier');
        self::assertSelectorNotExists('header', 'Un cadre de fenêtre ne rend pas la coque.');
        self::assertSelectorNotExists('turbo-frame#barre', 'Ni la barre.');
    }

    public function testSansEnTeteDeCadreLaVilleRendLaCarteOuverte(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-renvoi@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=marche', $partie->getId()));

        // Une adresse tapée, un lien partagé : la carte, avec la fenêtre ouverte.
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open]');
        self::assertCount(1, $crawler->filter('dialog #panneau-marche'));
        self::assertGreaterThan(0, $crawler->filter('[data-carte-target="grille"]')->count(), 'La carte est toujours là, derrière.');
    }

    public function testLaCarteRendLaFenetreDejaOuvertePourQuiRecharge(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-recharge@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->ajouterChantier(new Chantier($ville, TypeDeBatiment::Port, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $ouvre = \sprintf('/partie/%d/ville?onglet=marche', $partie->getId());
        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($ouvre)));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open]');
        self::assertCount(1, $crawler->filter('dialog #panneau-marche'));
        self::assertCount(1, $crawler->filter('dialog nav a[aria-current="page"][href$="onglet=marche"]'));
        // Un chantier de bâtiment qui n'existe pas encore ne fait pas un carré.
        self::assertCount(0, $crawler->filter('dialog nav a[href$="onglet=port"]'));
    }

    /**
     * **Chaque panneau prépare ses propres données** (phase 6) : un gabarit qui
     * lirait une variable que son fournisseur ne donne plus lèverait une
     * exception, le mode strict de Twig étant actif. Tous les bâtiments dressés,
     * chaque panneau rendu à son tour — c'est le filet de la refonte.
     */
    public function testChaquePanneauDeBatimentSeRendAvecSesSeulesDonnees(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-panneaux@example.com');
        $ville = $partie->getVille();

        foreach (TypeDeBatiment::cases() as $type) {
            if (!$type->estLeBatimentDeDepart() && null === $ville->batimentDeType($type)) {
                $ville->ajouterBatiment(new Building($ville, $type));
            }
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (TypeDeBatiment::cases() as $type) {
            $client->request('GET', \sprintf('/partie/%d/ville?onglet=%s', $partie->getId(), $type->value), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

            self::assertResponseIsSuccessful(\sprintf('Le panneau « %s » ne se rend pas.', $type->value));
            self::assertSelectorExists('#panneau-'.$type->value);
        }
    }

    /**
     * Le contrat d'accessibilité de la fenêtre : le `<dialog>` se nomme par son
     * titre (`aria-labelledby`), et **chaque contenu** en porte un — c'est sur lui
     * que le focus entre à l'ouverture, et ce que le lecteur d'écran annonce. Un
     * contenu sans titre ouvrirait une fenêtre muette.
     */
    public function testChaqueFenetreSeNommeParSonTitre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-titres@example.com');
        $id = $partie->getId();
        $zone = $partie->getVille()->getZones()->last();
        self::assertNotFalse($zone);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $id));
        self::assertSame('fenetre-titre', $crawler->filter('dialog')->attr('aria-labelledby'));

        foreach ([
            \sprintf('/partie/%d/ville?onglet=residence_familiale', $id),
            \sprintf('/partie/%d/commande', $id),
            \sprintf('/partie/%d/case/%d-%d', $id, $zone->getX(), $zone->getY()),
            \sprintf('/partie/%d/expeditions', $id),
        ] as $adresse) {
            $client->request('GET', $adresse, [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

            self::assertResponseIsSuccessful($adresse);
            self::assertSelectorCount(1, '#fenetre-titre', $adresse.' : un seul titre, celui que la fenêtre annonce.');
            // Un contenu long défile dans la fenêtre : il porte son « Retour en haut ».
            self::assertSelectorCount(1, '[data-controller="retour-en-haut"] [data-retour-en-haut-target="bouton"]', $adresse);
            self::assertSelectorExists('[data-controller="retour-en-haut"][data-action*="scroll->retour-en-haut#defiler"]', $adresse);
        }
    }

    /**
     * Le rail de la cité montre le sprite du palier de chaque bâtiment — le même
     * que sur la ville vue d'en haut —, et plus un monogramme.
     */
    public function testLeRailMontreLeSpriteDuPalierDeChaqueBatiment(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-rail@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 3));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 5));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        $image = static fn (string $type): string => (string) $crawler->filter(\sprintf('nav a[href$="onglet=%s"] img', $type))->attr('src');
        self::assertStringContainsString('/batiments/grenier_3', $image('grenier'), 'Le palier suit le niveau.');
        self::assertStringContainsString('/batiments/temple_4', $image('temple'), 'Le niveau cinq garde le dernier palier.');
        self::assertStringContainsString('/batiments/residence_familiale_1', $image('residence_familiale'), 'Le foyer de la lignée est au niveau un.');
    }

    /**
     * Sur un téléphone, la fenêtre est une feuille plein écran et ses cibles font
     * 44 px (WCAG 2.2). Le rendu réel ne se vérifie qu'au navigateur ; on garde
     * ici ce dont il dépend.
     */
    public function testLaFenetreEstUneFeuillePleinEcranSurTelephone(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-mobile@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $classes = (string) $crawler->filter('dialog[data-fenetre-target="fenetre"]')->attr('class');
        self::assertStringContainsString('inset-0', $classes, 'Plein écran sous `md`.');
        self::assertStringContainsString('md:inset-6', $classes, 'Une vraie fenêtre au-dessus.');
        self::assertStringContainsString('size-11', (string) $crawler->filter('dialog button[data-action="fenetre#fermer"]')->attr('class'));
    }

    public function testLaCommandeRepondEnCadreSansLaCoque(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-commande-cadre@example.com');

        $client->request('GET', \sprintf('/partie/%d/commande', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#fenetre');
        self::assertSelectorExists('#fenetre-titre');
        self::assertSelectorExists('turbo-frame#fenetre button[data-action="fenetre#fermer"]', 'Prendre ses fonctions ferme la fenêtre.');
        self::assertSelectorNotExists('turbo-frame#barre', 'Un cadre de fenêtre ne rend pas la barre.');
    }

    /**
     * Le décret porte la difficulté et la carte de la partie en dessin, **et** en lettres : le dessin
     * est décoratif, ce que lit un lecteur d'écran est dans le texte à côté.
     */
    public function testLaCommandeDitLaDifficulteEtLaCarteEnLettres(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-commande-lettres@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/commande', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.decret'));
        $partieTexte = $crawler->filter('#commande-section-partie')->text();
        self::assertStringContainsString(\sprintf('%d sur 9', $partie->getVille()->getDifficulte()), $partieTexte);
        self::assertStringContainsString(\sprintf('%1$d × %1$d', $partie->getVille()->getTailleGrille()), $partieTexte);
        self::assertSame(
            $partie->getVille()->getTailleGrille() ** 2,
            $crawler->filter('.apercu-carte--petit > span')->count(),
            'L\'aperçu a autant de cases que la grille.',
        );
    }

    public function testSansEnTeteDeCadreLaCommandeRendLaCarteOuverte(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-commande-renvoi@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/commande', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open]');
        self::assertCount(1, $crawler->filter('dialog #fenetre-titre'));
        self::assertGreaterThan(0, $crawler->filter('[data-carte-target="grille"]')->count(), 'La carte est toujours là, derrière.');
    }

    public function testLaCaseRepondEnCadreEnFeuille(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-case-cadre@example.com');
        $zone = $partie->getVille()->getZones()->last();
        self::assertNotFalse($zone);

        $client->request('GET', \sprintf('/partie/%d/case/%d-%d', $partie->getId(), $zone->getX(), $zone->getY()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#fenetre[data-forme="feuille"]', 'Le détail d\'une case est une feuille, pas la grande fenêtre.');
        self::assertSelectorExists('#fenetre-titre');
        self::assertSelectorNotExists('turbo-frame#barre');
    }

    public function testSansEnTeteDeCadreLaCaseRendLaCarteOuverteEtSurlignee(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-case-renvoi@example.com');
        $zone = $partie->getVille()->getZones()->last();
        self::assertNotFalse($zone);

        $crawler = $client->request('GET', \sprintf('/partie/%d/case/%d-%d', $partie->getId(), $zone->getX(), $zone->getY()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"][open] #fenetre-titre');
        self::assertGreaterThan(0, $crawler->filter('[data-carte-target="grille"]')->count());
        self::assertCount(1, $crawler->filter('a.bg-or-300\\/45'), 'La case ouverte est surlignée sur la carte.');
    }

    public function testUneCaseInconnueEstIntrouvable(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-case-inconnue@example.com');

        $client->request('GET', \sprintf('/partie/%d/case/99-99', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testLesExpeditionsSeLisentEnFenetre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-expeditions@example.com');

        $client->request('GET', \sprintf('/partie/%d/expeditions', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#fenetre-titre', 'Expéditions');
        self::assertSelectorTextContains('turbo-frame#fenetre', 'Aucune expédition en route');
    }

    /**
     * Une expédition en route est une piste : sa part du chemin est dite en lettres (la piste est
     * décorative), et le marcheur reçoit la même part, en nombre — `--part-nombre` place le marcheur
     * sur la ligne, `--part` remplit celle-ci.
     */
    public function testUneExpeditionEnRouteSeLitEnLettresEtEnPiste(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-piste@example.com');
        $ville = $partie->getVille();
        $destination = null;
        foreach ($ville->getZones() as $zone) {
            if (!$zone->porteLaVille()) {
                $destination = $zone;
                break;
            }
        }
        self::assertInstanceOf(\App\Entity\Zone::class, $destination);
        $ville->ajouterExpedition(new \App\Entity\Expedition($ville, $destination, \App\Game\RoleDExploration::Eclaireur, 4));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/expeditions', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('turbo-frame#fenetre', 'Éclaireur vers la case');
        self::assertSelectorTextContains('turbo-frame#fenetre', 'encore 4 cycles');
        $piste = $crawler->filter('.piste');
        self::assertCount(1, $piste);
        self::assertStringContainsString('--part: 0%', (string) $piste->attr('style'));
        self::assertStringContainsString('--part-nombre: 0', (string) $piste->attr('style'));
        self::assertSame('true', $piste->attr('aria-hidden'), 'La piste est décorative.');
    }

    /**
     * La carte n'a plus de panneau de signaux : ils vivent dans la barre.
     */
    public function testLaBarreDitLesSignauxEtLesExpeditions(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-pastilles@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/barre', $partie->getId()));

        self::assertResponseIsSuccessful();
        // Une partie neuve a des bras sans ouvrage : au moins un signal.
        self::assertGreaterThan(0, $crawler->filter('turbo-frame#barre a[data-turbo-frame="fenetre"]')->count());
    }

    /**
     * Le paramètre vient du visiteur : on ne le suit jamais sans le valider.
     */
    #[DataProvider('ouvertures')]
    public function testUneOuvertureSuspecteNOuvreRien(string $ouvre): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-suspecte-'.md5($ouvre).'@example.com');
        $ouvre = str_replace('{id}', (string) $partie->getId(), $ouvre);

        $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($ouvre)));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"]:not([open])');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ouvertures(): iterable
    {
        yield 'un autre site' => ['https://example.org/partie/{id}/ville?onglet=marche'];
        yield 'une URL relative au protocole' => ['//example.org/partie/{id}/ville?onglet=marche'];
        yield 'la partie d\'un autre' => ['/partie/999999/ville?onglet=marche'];
        yield 'la carte elle-même' => ['/partie/{id}/carte'];
        yield 'une route qui n\'est pas une fenêtre' => ['/partie/{id}/abandonner'];
        yield 'un jeton de requête forgé' => ['/partie/{id}/ville?onglet=<script>'];
        yield 'une remontée' => ['/partie/{id}/../{id}/ville'];
        yield 'un caractère de contrôle' => ["/partie/{id}/ville?onglet=marche\r\nX: 1"];
        yield 'du vide' => [''];
    }

    public function testLaBarreSeRechargeSeuleEtDitLaVerite(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-barre@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/barre?retour=app_partie_carte&ouvre=%s', $partie->getId(), rawurlencode(\sprintf('/partie/%d/ville?onglet=marche', $partie->getId()))));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('turbo-frame#barre[target="_top"]');
        self::assertSelectorTextContains('turbo-frame#barre', 'Deben');
        self::assertSame(
            \sprintf('/partie/%d/ville?onglet=marche', $partie->getId()),
            $crawler->filter('turbo-frame#barre input[name="ouvre"]')->attr('value'),
            'Le bouton de cycle reprend la fenêtre où elle est.',
        );
    }

    public function testUnRetourInconnuRetombeSurLaCarte(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-retour@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/barre?retour=app_admin', $partie->getId()));

        self::assertSame('app_partie_carte', $crawler->filter('turbo-frame#barre input[name="retour"]')->attr('value'));
    }

    public function testLaQuinzaineRouvreLaFenetreOuElleEtait(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-cycle@example.com');
        $cite = \sprintf('/partie/%d/ville?onglet=marche', $partie->getId());

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?ouvre=%s', $partie->getId(), rawurlencode($cite)));
        $formulaire = $crawler->filter(\sprintf('form[action="/partie/%d/cycle"]', $partie->getId()));
        self::assertSame($cite, $formulaire->filter('input[name="ouvre"]')->attr('value'));

        $client->request('POST', \sprintf('/partie/%d/cycle', $partie->getId()), [
            '_token' => $formulaire->filter('input[name="_token"]')->attr('value'),
            'retour' => 'app_partie_carte',
            'ouvre' => $cite,
        ]);

        self::assertResponseRedirects();
        $lieu = (string) $client->getResponse()->headers->get('Location');
        self::assertStringContainsString('ouvre=', $lieu);
        self::assertStringContainsString('ouvre='.$cite, urldecode($lieu));
    }

    public function testUnAutreJoueurNeVoitPasLaFenetre(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'fenetre-proprio@example.com');
        $autre = new User();
        $autre->setEmail('fenetre-intrus@example.com');
        $autre->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($autre);
        $gestionnaire->flush();
        $client->loginUser($autre);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', \sprintf('/partie/%d/barre', $partie->getId()));
        self::assertResponseStatusCodeSame(403);
    }

    private function partie(KernelBrowser $client, string $email): GameSave
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
