<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\ModeDivin;
use App\Game\Ressource;
use App\Game\Stockage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le mode d'essai, et surtout ce qui le garde fermé.
 */
final class ModeDivinTest extends WebTestCase
{
    /**
     * **La barrière, et la seule qui compte.** Le rôle ne s'accorde qu'en
     * console : un joueur ordinaire qui forgerait la requête doit être refusé,
     * l'absence de bouton n'étant pas une protection.
     */
    public function testUnJoueurOrdinaireNePeutPasSeFaireDivinite(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'mortel@example.com', divinite: false);
        $partie = $this->lancer($joueur);

        $client->request('POST', \sprintf('/partie/%d/divin', $partie->getId()), ['_token' => 'peu-importe']);

        self::assertResponseStatusCodeSame(403);
        self::assertFalse($this->relire($partie)->estEnModeDivin());
    }

    /**
     * Le rôle ne suffit pas : la partie doit rester celle du joueur.
     */
    public function testUneDiviniteNeTouchePasALaPartieDUnAutre(): void
    {
        $client = static::createClient();
        $autre = $this->creerJoueur('proprietaire@example.com');
        $partie = $this->lancer($autre);

        $this->connecter($client, 'divinite-curieuse@example.com', divinite: true);
        $client->request('POST', \sprintf('/partie/%d/divin', $partie->getId()), ['_token' => 'peu-importe']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Le bouton ne s'affiche pas pour qui n'a pas le rôle — ce n'est pas la
     * barrière, mais l'écran ne doit pas proposer ce qu'il refusera.
     */
    public function testLeBoutonNApparaitQuePourUneDivinite(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'sans-role@example.com', divinite: false);
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(\sprintf('form[action="/partie/%d/divin"]', $partie->getId())));
    }

    /**
     * Le parcours complet : la partie bascule, se comble, et le dit.
     */
    public function testUneDiviniteComblSaPartieEtLEcranLAnnonce(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'divinite@example.com', divinite: true);
        $partie = $this->lancer($joueur);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $bascule = $crawler->filter(\sprintf('form[action="/partie/%d/divin"]', $partie->getId()));
        self::assertCount(1, $bascule);

        $client->submit($bascule->form());
        $client->followRedirect();

        $relue = $this->relire($partie);
        self::assertTrue($relue->estEnModeDivin());
        self::assertGreaterThanOrEqual(ModeDivin::RICHESSE, $relue->getVille()->quantite(Ressource::Argile));
        self::assertGreaterThanOrEqual(ModeDivin::RICHESSE, $relue->getVille()->getDeben());
        self::assertSelectorTextContains('body', 'Partie d\'essai');
    }

    /**
     * Le million ne rentrerait pas sans la levée des plafonds : c'est la règle
     * du lot 5.1 qui refuserait le don du lot suivant.
     */
    public function testLeModeDivinLeveLesPlafondsDeReserve(): void
    {
        self::bootKernel();
        $partie = $this->lancer($this->creerJoueur('plafonds@example.com'));
        $ville = $partie->getVille();

        self::assertNotNull(Stockage::plafondPour($ville, Ressource::Argile));

        static::getContainer()->get(ModeDivin::class)->basculer($partie);

        self::assertNull(Stockage::plafondPour($ville, Ressource::Argile));
        self::assertGreaterThanOrEqual(ModeDivin::RICHESSE, $ville->quantite(Ressource::Argile));
    }

    /**
     * **Le mode remet une partie échouée debout** — la seule chose du jeu qui
     * défait un échec. Sans cela, une partie tombée en famine ne pourrait plus
     * servir à rien, alors que c'est souvent celle qu'on veut examiner. C'est
     * aussi pourquoi la route passe par `VOIR` et non par `JOUER`.
     */
    public function testLeModeDivinRelveUnePartieEchouee(): void
    {
        self::bootKernel();
        $partie = $this->lancer($this->creerJoueur('ressuscitee@example.com'));
        $partie->echouer();

        self::assertFalse($partie->estEnCours());

        static::getContainer()->get(ModeDivin::class)->basculer($partie);

        self::assertTrue($partie->estEnCours());
        self::assertSame(0, $partie->getQuinzainesDeFamine());
        self::assertSame(0, $partie->getQuinzainesDeMecontentement());
    }

    /**
     * En sortir ne reprend rien : les plafonds ne portent que sur ce qui
     * entre, jamais sur ce qui est déjà rangé.
     */
    public function testSortirDuModeNeRetirePasCeQuiAEteDonne(): void
    {
        self::bootKernel();
        $partie = $this->lancer($this->creerJoueur('retour@example.com'));
        $modeDivin = static::getContainer()->get(ModeDivin::class);

        $modeDivin->basculer($partie);
        $modeDivin->basculer($partie);

        self::assertFalse($partie->estEnModeDivin());
        self::assertGreaterThanOrEqual(ModeDivin::RICHESSE, $partie->getVille()->quantite(Ressource::Argile));
    }

    /**
     * L'autre moitié du mode : les dix régions, autrement hors d'atteinte tant
     * que la Phase 8 n'a pas écrit l'enchaînement des missions.
     */
    public function testUneDiviniteOuvreLesDixMissions(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('exploratrice@example.com');

        $partie = static::getContainer()->get(LanceurDePartie::class)
            ->lancerCampagne($joueur, 'Nakht', numeroDeMission: 7);

        self::assertSame(7, $partie->getMission());
        self::assertSame('Éléphantine', $partie->getVille()->getNom());
    }

    private function relire(GameSave $partie): GameSave
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->clear();
        $relue = $gestionnaire->find(GameSave::class, $partie->getId());

        self::assertNotNull($relue);

        return $relue;
    }

    private function lancer(User $joueur): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function connecter(KernelBrowser $client, string $email, bool $divinite): User
    {
        $joueur = $this->creerJoueur($email, $divinite);
        $client->loginUser($joueur);

        return $joueur;
    }

    private function creerJoueur(string $email, bool $divinite = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        if ($divinite) {
            $user->setRoles([User::ROLE_DIVIN]);
        }

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
