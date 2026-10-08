<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CarteTest extends WebTestCase
{
    public function testLaCarteAfficheAutantDeTuilesQueDeCases(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'grille@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertResponseIsSuccessful();
        // Le Delta se joue en 3×3 (doc 06).
        self::assertCount(9, $crawler->filter('img[src*="/images/tuiles/"]'));
    }

    /**
     * Chaque case est un conteneur qui porte sa tuile ET sa zone cliquable : c'est ce qui
     * permet à la tuile de se soulever (`:has()`) quand on survole **sa** zone. Une zone
     * cliquable hors de son conteneur ne soulèverait rien, sans erreur visible.
     */
    public function testChaqueCaseRegroupeSaTuileEtSaZoneCliquable(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'case-iso@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $cases = $crawler->filter('.case-iso');
        self::assertCount(9, $cases);
        $cases->each(static function ($case): void {
            self::assertCount(1, $case->children()->filter('img.case-iso__tuile'));
            self::assertCount(1, $case->children()->filter('a[aria-label]'));
        });
    }

    /**
     * Les conteneurs de cases sont des rectangles qui se recouvrent : ils doivent laisser passer
     * les clics, et seul le losange du lien les capte. Sans cette règle, une case du premier plan
     * interceptait les clics de sa voisine dans les coins — des zones où l'on ne pouvait plus
     * cliquer. Aucun test fonctionnel n'exécute la CSS : on garde la règle elle-même.
     */
    public function testLesConteneursDeCasesLaissentPasserLesClics(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/app.css');

        self::assertMatchesRegularExpression('/\.case-iso\s*\{\s*pointer-events:\s*none;\s*\}/', $css);
        self::assertMatchesRegularExpression('/\.case-iso\s*>\s*a\s*\{\s*pointer-events:\s*auto;\s*\}/', $css);
    }

    /**
     * **Accessibilité et mobile**, deux gardes que seul le source peut tenir (aucun test fonctionnel
     * n'exécute la CSS ni ne mesure d'écran) :
     * - un bloc final ramène toute animation et toute transition à l'instantané si l'on a demandé
     *   moins de mouvement — il y avait quatorze transitions hors du garde ;
     * - les volets de la barre sortent de la rangée qui défile (`fixed` sur téléphone, `absolute`
     *   dès `md`) : dans la rangée, un volet s'ouvrait mais restait rogné, donc invisible.
     */
    public function testLeMouvementReduitEtLesVoletsMobilesSontGardes(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 2).'/assets/styles/app.css');
        self::assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\)\s*\{[^@]*transition-duration:\s*0\.01ms\s*!important/s', $css);
        self::assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\)\s*\{[^@]*animation-duration:\s*0\.01ms\s*!important/s', $css);

        $barre = (string) file_get_contents(\dirname(__DIR__, 2).'/templates/partie/_barre.html.twig');
        self::assertSame(2, substr_count($barre, 'fixed inset-x-3 top-28'), 'Les deux volets de la barre sortent de la rangée sur téléphone.');
        self::assertSame(2, substr_count($barre, 'md:absolute'));
    }

    /**
     * Le détail d'une case range ce qu'on y fait en onglets — gisements, champs, envois —, **seulement
     * ceux qui servent** : une case sous le brouillard n'a qu'une chose à offrir et pas de barre ;
     * une terre cultivable a ses champs. Onglets et panneaux s'apparient par rang, dans le même ordre.
     */
    public function testLeDetailDUneCaseRangeSesActionsEnOngletsUtiles(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'case-onglets@example.com');
        $partie = $this->lancer($joueur);

        $zones = [];
        foreach ($partie->getVille()->getZones() as $candidate) {
            if (!$candidate->porteLaVille()) {
                $zones[] = $candidate;
            }
        }
        $fertile = $zones[0];
        $fertile->definirTerrain(TypeDeTerrain::Fertile)->poserUnContenu(ContenuDeZone::ChampEligible)->decouvrir();
        $brouillard = $zones[1];
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/case/%d-%d', $partie->getId(), $fertile->getX(), $fertile->getY()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseIsSuccessful();
        $onglets = $crawler->filter('nav[aria-label="Sections de la case"] [role="tab"]')->each(static fn ($n): string => (string) $n->attr('aria-controls'));
        $panneaux = $crawler->filter('[role="tabpanel"]')->each(static fn ($n): string => (string) $n->attr('id'));
        self::assertNotEmpty($onglets, 'Une terre cultivable a des onglets.');
        self::assertSame($onglets, $panneaux, 'Onglets et panneaux s\'apparient dans le même ordre.');
        self::assertStringContainsString('champs', implode(' ', $onglets));
        // Une seule liste pour les champs : une ligne par parcelle, la culture se choisit sur place.
        // Elles étaient deux (des cartes d'état, puis un formulaire qui répétait les mêmes parcelles).
        $champs = $crawler->filter('[role="tabpanel"][id$="-section-champs"]');
        self::assertCount(Zone::CHAMPS_MAX, $champs->filter('li'), 'Une ligne par parcelle, sans doublon.');
        self::assertCount(Zone::CHAMPS_MAX, $champs->filter('select[name^="culture-"]'));
        self::assertCount(1, $champs->filter('form[action$="/semer"]'));
        self::assertCount(1, $crawler->filter('[data-forme="feuille"]'), 'La forme est portée par le contenu.');
        self::assertCount(0, $crawler->filter('turbo-frame#fenetre[data-forme]'), 'Turbo ne recopie pas les attributs d\'un cadre : la forme n\'y vit pas.');

        $crawler = $client->request('GET', \sprintf('/partie/%d/case/%d-%d', $partie->getId(), $brouillard->getX(), $brouillard->getY()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[role="tablist"]'), 'Une seule chose à offrir : pas de barre d\'onglets.');
        self::assertSelectorTextContains('turbo-frame#fenetre', 'Envoyer un éclaireur');
    }

    /**
     * Une case tenue par des brigands se repère **sur la carte**, avant même d'ouvrir son détail.
     */
    public function testUneCaseGardeeAUnRepereDeDanger(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'repere-danger@example.com');
        $partie = $this->lancer($joueur);

        $zone = null;
        foreach ($partie->getVille()->getZones() as $candidate) {
            if (!$candidate->porteLaVille()) {
                $zone = $candidate;
                break;
            }
        }
        self::assertInstanceOf(Zone::class, $zone);
        $zone->decouvrir()->installerUneBande(5);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertCount(1, $crawler->filter('.repere--danger'));
        self::assertStringContainsString('tenue par des brigands', (string) $crawler->filter('.case-iso a[aria-label*="brigands"]')->attr('aria-label'));
    }

    /**
     * Le détail d'une case semée affiche son étape (semis, pousse, récolte ou
     * repos) — la régression à surveiller est une erreur Twig si l'étape
     * n'est pas calculable.
     */
    public function testLeDetailDUnChampAfficheSonEtape(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'champ-detail@example.com');
        $partie = $this->lancer($joueur);

        $zone = null;
        foreach ($partie->getVille()->getZones() as $candidate) {
            if (!$candidate->porteLaVille()) {
                $zone = $candidate;
                break;
            }
        }
        self::assertInstanceOf(Zone::class, $zone);
        $zone->definirTerrain(TypeDeTerrain::Fertile)
            ->poserUnContenu(ContenuDeZone::ChampEligible)
            ->decouvrir()
            ->semerLaParcelle(1, Culture::Ble);

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dialog', 'Semis');
    }

    /**
     * La case poissonneuse dit ce qui la bloque tant que le Port n'est pas
     * dressé, puis propose les filets une fois qu'il l'est. Le rendu réel est
     * la seule façon de vérifier la branche Twig qui distingue les deux.
     */
    public function testUneCasePoissonneuseAttendLePortPuisProposeLesFilets(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'filets@example.com');
        $partie = $this->lancer($joueur);
        $ville = $partie->getVille();

        $zone = null;
        foreach ($ville->getZones() as $candidate) {
            if (!$candidate->porteLaVille()) {
                $zone = $candidate;
                break;
            }
        }
        self::assertInstanceOf(Zone::class, $zone);

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);

        // Vidée en deux temps : la carte générée a pu poser du poisson ici, et
        // Doctrine insérerait le nouveau filon avant d'avoir supprimé l'ancien.
        $zone->definirTerrain(TypeDeTerrain::Mediterranee)->poserUnContenu(ContenuDeZone::Rien)->decouvrir();
        $gestionnaire->flush();
        $zone->poserUnGisement(Ressource::Poisson, 200);
        $gestionnaire->flush();

        $url = \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY());

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dialog', 'Il faudra un Port');

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port));
        $gestionnaire->flush();

        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('dialog', 'Jeter les filets');
        // Un banc se reconstitue : afficher un compteur figé tromperait.
        self::assertSelectorTextContains('dialog', 'inépuisable');
    }

    public function testUneCarteNeuveNeMontreQueLaVilleEtDuBrouillard(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'brouillard@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        $familles = [];
        foreach ($crawler->filter('img[src*="/images/tuiles/"]')->extract(['src']) as $src) {
            self::assertIsString($src);
            // AssetMapper sert « brouillard-yuseMRK.png » : le nom de la tuile
            // précède l'empreinte de version.
            $familles[] = explode('-', basename($src))[0];
        }

        $familles = array_unique($familles);
        sort($familles);

        // Aucun terrain ne doit fuiter avant qu'un éclaireur y soit passé.
        self::assertSame(['brouillard', 'ville'], $familles);
    }

    public function testUneCaseReconnueSeDetaille(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'detail@example.com');
        $partie = $this->lancer($joueur);
        $centre = $partie->getVille()->zoneDeLaVille();
        self::assertInstanceOf(Zone::class, $centre);

        $client->request('GET', \sprintf(
            '/partie/%d/carte?zone=%d-%d',
            $partie->getId(),
            $centre->getX(),
            $centre->getY(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Votre ville se dresse ici');
    }

    /**
     * Une case sous brouillard s'ouvre — il faut bien pouvoir y envoyer un
     * éclaireur — mais elle ne doit rien livrer de ce qu'il ira chercher.
     */
    public function testUneCaseSousBrouillardNeLivreNiTerrainNiContenu(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'secret@example.com');
        $partie = $this->lancer($joueur);
        $inconnue = $this->premiereZoneNonDecouverte($partie);

        $client->request('GET', \sprintf(
            '/partie/%d/carte?zone=%d-%d',
            $partie->getId(),
            $inconnue->getX(),
            $inconnue->getY(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('article', 'Territoire inexploré');
        self::assertSelectorTextNotContains('article', $inconnue->getTerrain()->libelle());

        foreach ($inconnue->getGisements() as $gisement) {
            self::assertSelectorTextNotContains('article', $gisement->getRessource()->libelle());
        }
    }

    public function testDesCoordonneesFantaisistesNeCassentPasLaPage(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'fantaisie@example.com');
        $partie = $this->lancer($joueur);

        foreach (['99-99', 'nimportequoi', '../../etc/passwd', '1'] as $coordonnees) {
            $client->request('GET', \sprintf('/partie/%d/carte?zone=%s', $partie->getId(), urlencode($coordonnees)));

            self::assertResponseIsSuccessful(\sprintf('Coordonnées « %s ».', $coordonnees));
        }
    }

    // Les tests de la cité en fenêtre vivent dans FenetreTest.

    public function testLaRepriseMeneAuTerritoire(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'reprise-carte@example.com');
        $partie = $this->lancer($joueur);

        $client->request('GET', \sprintf('/partie/%d', $partie->getId()));

        self::assertResponseRedirects(\sprintf('/partie/%d/carte', $partie->getId()));
    }

    public function testAvancerLeTempsDepuisLaVilleYRamene(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-ville@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $client->submit($crawler->selectButton('Quinzaine suivante')->form());

        // Et sur la fenêtre d'où l'on est parti : on passe souvent plusieurs
        // quinzaines de suite depuis le même panneau. La ville est une fenêtre
        // de la carte : le cycle ramène à la carte, fenêtre rouverte.
        self::assertResponseRedirects();
        $lieu = urldecode((string) $client->getResponse()->headers->get('Location'));
        self::assertStringContainsString(\sprintf('/partie/%d/carte', $partie->getId()), $lieu);
        self::assertStringContainsString(\sprintf('ouvre=/partie/%d/ville', $partie->getId()), $lieu);
    }

    /**
     * **La case sélectionnée survit à la quinzaine.** On avance souvent le
     * temps en surveillant une expédition, un chantier ou une carrière :
     * repartir d'une carte sans sélection obligeait à retrouver sa case à
     * chaque cycle.
     */
    public function testLaCaseSelectionneeSurvitAuPassageDuTemps(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'case-apres-cycle@example.com');
        $partie = $this->lancer($joueur);
        $zone = $partie->getVille()->getZones()->first();
        self::assertNotFalse($zone);

        $adresse = \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY());
        $crawler = $client->request('GET', $adresse);
        $client->submit($crawler->selectButton('Quinzaine suivante')->form());

        // La case est une fenêtre : c'est `ouvre` qui la rouvre après la quinzaine.
        self::assertResponseRedirects();
        self::assertStringContainsString(
            \sprintf('ouvre=/partie/%d/case/%d-%d', $partie->getId(), $zone->getX(), $zone->getY()),
            urldecode((string) $client->getResponse()->headers->get('Location')),
        );
    }

    public function testAvancerLeTempsDepuisLaCarteYRamene(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-carte@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $client->submit($crawler->selectButton('Quinzaine suivante')->form());

        self::assertResponseRedirects(\sprintf('/partie/%d/carte', $partie->getId()));
    }

    public function testUnRetourFantaisisteRetombeSurLaCarte(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'retour-force@example.com');
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        $formulaire = $crawler->selectButton('Quinzaine suivante')->form();
        $formulaire['retour'] = 'app_partie_abandonner';

        $client->submit($formulaire);

        self::assertResponseRedirects(\sprintf('/partie/%d/carte', $partie->getId()));
    }

    public function testUnJoueurNeVoitPasLaCarteDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->creerJoueur('proprio-carte@example.com');
        $partie = $this->lancer($proprietaire);

        $this->connecter($client, 'intrus-carte@example.com');
        $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertResponseStatusCodeSame(403);
    }

    private function premiereZoneNonDecouverte(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                return $zone;
            }
        }

        self::fail('Une carte neuve devrait avoir des cases inexplorées.');
    }

    private function lancer(User $joueur): GameSave
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
