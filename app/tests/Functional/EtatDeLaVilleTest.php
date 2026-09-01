<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\EtatDeLaVille;
use App\Game\LanceurDePartie;
use App\Game\QualiteDeCrue;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'état de la ville, lisible depuis les deux écrans.
 *
 * On passe des quinzaines entières sur la carte à explorer et à exploiter : la
 * fièvre, la disette ou une fête se découvraient en rentrant, plusieurs
 * quinzaines trop tard.
 */
final class EtatDeLaVilleTest extends WebTestCase
{
    /**
     * **Une maladie se voit depuis la carte**, et pas seulement depuis la
     * ville — c'est tout l'objet.
     */
    public function testUneEpidemieSeLitDepuisLaCarteCommeDepuisLaVille(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'fievre-carte@example.com');
        $partie->getVille()->declarerUneEpidemie(3, 30);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        self::assertSelectorTextContains('body', 'La fièvre couche');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorTextContains('body', 'La fièvre couche');
    }

    /**
     * **Le bon compte autant que le mauvais** (décision de la joueuse) : un
     * écran qui ne signalerait que les ennuis ferait du jeu une liste de
     * pannes, et l'on manquerait les moments à saisir.
     */
    public function testUneBonneNouvelleSeSignaleAussi(): void
    {
        $client = static::createClient();
        $partie = $this->lancer($client, 'bonne-nouvelle@example.com');
        $partie->annoncerLaCrue(QualiteDeCrue::Forte);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $bonnes = $this->etat()->bonnesNouvelles($partie);

        self::assertNotEmpty($bonnes);
        foreach ($bonnes as $signal) {
            self::assertSame('bon', $signal['ton']);
        }

        $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));
        self::assertSelectorTextContains('body', 'La crue est forte');
    }

    /**
     * **Chaque signal nomme la cause et le geste** : un diagnostic sans remède
     * se subit.
     */
    public function testChaqueSignalPorteUnTonUnTitreEtUnDetail(): void
    {
        self::bootKernel();
        $partie = $this->lancerSansClient('signaux-complets@example.com');

        $signaux = $this->etat()->signaux($partie);
        self::assertNotEmpty($signaux, 'Une ville neuve a au moins ses bras sans ouvrage.');

        foreach ($signaux as $signal) {
            self::assertContains($signal['ton'], ['bon', 'mauvais']);
            self::assertNotSame('', $signal['titre']);
            self::assertNotSame('', $signal['detail'], 'Un signal sans remède se subit.');
        }
    }

    /**
     * **Les deux écrans lisent la même liste** : deux listes écrites séparément
     * auraient fini par diverger, et c'est la carte qui aurait cessé de dire la
     * vérité.
     */
    public function testLesEnnuisEtLesBonnesNouvellesComposentLaListeEntiere(): void
    {
        self::bootKernel();
        $partie = $this->lancerSansClient('meme-liste@example.com');
        $etat = $this->etat();

        self::assertSame(
            \count($etat->ennuis($partie)) + \count($etat->bonnesNouvelles($partie)),
            \count($etat->signaux($partie)),
        );
    }

    /**
     * L'autonomie en vivres se compte en quinzaines de rations, et vaut null
     * pour une ville qui ne mange rien — ce qui n'arrive qu'à une ville vide.
     */
    public function testLAutonomieSeCompteEnQuinzainesDeRations(): void
    {
        self::bootKernel();
        $partie = $this->lancerSansClient('autonomie@example.com');
        $ville = $partie->getVille();

        $attendue = intdiv($ville->getNourriture(), $ville->consommationDeNourriture());

        self::assertGreaterThan(0, $ville->consommationDeNourriture());
        self::assertSame($attendue, $this->etat()->autonomieEnVivres($partie));
    }

    /**
     * Une réserve qui fond se signale **avant** que la disette ne commence à
     * compter : le joueur doit pouvoir semer ou acheter à temps.
     */
    public function testUneReserveQuiFondSeSignaleAvantLaDisette(): void
    {
        self::bootKernel();
        $partie = $this->lancerSansClient('reserve-basse@example.com');
        $ville = $partie->getVille();

        $ville->debiterNourriture($ville->getNourriture());
        $ville->crediterRessources([Ressource::Ble->value => $ville->consommationDeNourriture()]);

        $titres = array_column($this->etat()->ennuis($partie), 'titre');

        self::assertNotEmpty(
            array_filter($titres, static fn (string $t): bool => str_contains($t, 'vivres ne tiennent')),
            \sprintf('Une quinzaine de vivres devrait alerter. Signaux : %s', implode(' | ', $titres)),
        );
    }

    private function etat(): EtatDeLaVille
    {
        return static::getContainer()->get(EtatDeLaVille::class);
    }

    private function lancer(KernelBrowser $client, string $email): GameSave
    {
        $user = $this->creer($email);
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function lancerSansClient(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->creer($email), 'Nakht');
    }

    private function creer(string $email): User
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
