<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'onglet du Temple : ce que le joueur voit et ce qu'il peut faire.
 *
 * Le Temple a quitté son écran propre pour un onglet de la ville — **un
 * onglet, un bâtiment**. Les panneaux restent tous dans le document, seulement
 * masqués, donc les assertions de contenu tiennent sans exécuter le
 * JavaScript.
 */
final class EcranDuTempleTest extends WebTestCase
{
    /**
     * Le panthéon entier s'affiche, chaque dieu avec son domaine et son
     * palier — c'est là que se prend la décision de donner.
     */
    public function testLeTempleMontreLesHuitDivinitesEtLeursPaliers(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecTemple($client, 'ecran-temple@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();

        foreach (Divinite::pantheon() as $divinite) {
            self::assertSelectorTextContains('body', $divinite->libelle());
            self::assertSelectorTextContains('body', $divinite->domaine());
        }

        self::assertSelectorTextContains('body', 'Neutre');
    }

    /**
     * **Un dieu sans emploi le dit à l'écran**, et pas seulement dans le code :
     * c'est au moment de choisir à qui donner que l'information compte.
     */
    public function testPlusAucunDieuNannonceDattente(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecTemple($client, 'dieux-inertes@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();

        // **Plus aucun dieu n'attend** : Thot a cessé au lot 7.7, Isis au
        // 10.4. Le mécanisme reste — l'écran doit annoncer l'inertie de celui
        // qu'on ajouterait demain — mais il n'a plus rien à dire aujourd'hui.
        foreach (Divinite::pantheon() as $divinite) {
            self::assertNull(
                $divinite->attente(),
                \sprintf('%s n\'a plus rien à faire attendre.', $divinite->libelle()),
            );
        }
    }

    /**
     * Le parcours complet : porter une offrande depuis l'écran, et voir la
     * faveur avoir bougé.
     */
    public function testOnPorteUneOffrandeDepuisLEcran(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecTemple($client, 'porter@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $jeton = $crawler->filter(\sprintf('form[action="/partie/%d/temple/offrir"] input[name="_token"]', $partie->getId()))
            ->first()->attr('value');

        $client->request('POST', \sprintf('/partie/%d/temple/offrir', $partie->getId()), [
            '_token' => $jeton,
            // Ce que le formulaire poste réellement : l'onglet d'où part le
            // geste, pour y revenir après la redirection.
            'onglet' => TypeDeBatiment::Temple->value,
            'divinite' => Divinite::Ptah->value,
            'ressource' => Ressource::Deben->value,
            'quantite' => 20,
        ]);

        self::assertResponseRedirects(\sprintf(
            '/partie/%d/ville?onglet=%s',
            $partie->getId(),
            TypeDeBatiment::Temple->value,
        ));

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ptah');
        self::assertSelectorTextContains('body', 'Favorable');

        $rechargee = static::getContainer()->get(EntityManagerInterface::class)
            ->find(GameSave::class, $partie->getId());
        self::assertNotNull($rechargee);
        self::assertGreaterThan(
            Divinite::FAVEUR_DE_DEPART,
            $rechargee->getVille()->faveurEnvers(Divinite::Ptah),
        );
    }

    /**
     * **Un onglet sur du vide n'est pas un onglet** : la ville ne propose le
     * Temple qu'une fois le Temple dressé.
     */
    public function testLOngletNapparaitQuUneFoisLeTempleDresse(): void
    {
        $client = static::createClient();
        $joueur = $this->connecter($client, 'lien-temple@example.com');
        $partie = $this->lancer($joueur);

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorNotExists('#onglet-temple');
        self::assertSelectorNotExists('#panneau-temple');

        $partie->getVille()->ajouterBatiment(new Building($partie->getVille(), TypeDeBatiment::Temple, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorExists('#onglet-temple');
        self::assertSelectorExists('#panneau-temple');
    }

    /**
     * L'ancienne adresse survit et ramène à la ville, **sur l'onglet du
     * Temple** : un signet ne doit ni tomber sur du vide, ni obliger à
     * retrouver soi-même le panneau qu'il désignait.
     */
    public function testLAncienneAdresseRamemeALaVille(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecTemple($client, 'ancienne-adresse@example.com');

        $client->request('GET', \sprintf('/partie/%d/temple', $partie->getId()));

        self::assertResponseRedirects(\sprintf(
            '/partie/%d/ville?onglet=%s',
            $partie->getId(),
            TypeDeBatiment::Temple->value,
        ));
    }

    private function partieAvecTemple(KernelBrowser $client, string $email): GameSave
    {
        $partie = $this->lancer($this->connecter($client, $email));
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 2));
        $ville->crediterRessources([Ressource::Deben->value => 500]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(User $joueur): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function connecter(KernelBrowser $client, string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $client->loginUser($user);

        return $user;
    }
}
