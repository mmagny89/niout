<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\OffrandeImpossible;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\Ressource;
use App\Game\Temple;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le Temple et l'offrande (lot 6.1).
 *
 * Ce qui se juge ici : que donner coûte réellement, que le bâtiment limite
 * vraiment, et que les deux limites — combien de dieux, jusqu'où — ne disent
 * pas la même chose.
 */
final class TempleTest extends KernelTestCase
{
    /**
     * **On n'honore pas un dieu sur un terrain vague.** Sans Temple, pas
     * d'offrande — et la ville n'en est pas punie pour autant : ses dieux
     * restent neutres.
     */
    public function testSansTempleOnNoffrePas(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-temple@example.com');
        $ville = $partie->getVille();

        self::assertSame(0, Temple::divinitesPortables($ville));
        self::assertSame(Divinite::FAVEUR_DE_DEPART, Temple::plafondDeFaveur($ville));

        $this->expectException(OffrandeImpossible::class);
        $this->expectExceptionMessage('Temple');
        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 100);
    }

    /**
     * **Offrir coûte.** C'est le seul geste du jeu sans contrepartie
     * immédiate : la réserve baisse, la faveur monte, et le reste attendra.
     */
    public function testUneOffrandeEnDebenCoutteEtFaitMonterLaFaveur(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('offrande@example.com', niveau: 2);
        $ville = $partie->getVille();

        $avant = $ville->quantite(Ressource::Deben);
        $points = $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 20);

        self::assertSame(2 * Offrandes::POINTS_PAR_OFFRANDE, $points, 'Vingt deben valent deux offrandes.');
        self::assertSame($avant - 20, $ville->quantite(Ressource::Deben));
        self::assertSame(Divinite::FAVEUR_DE_DEPART + $points, $ville->faveurEnvers(Divinite::Ptah));
    }

    /**
     * **On offre en ressources autant qu'en deben** (décision de la joueuse),
     * et la conversion passe par le cours du Marché — jamais par un second
     * barème, qui finirait par diverger et deviendrait la bonne affaire.
     *
     * C'est aussi le premier débouché du surplus que le plafond de stock
     * refuse : un Grenier plein ne se vide plus seulement au Marché.
     */
    public function testOffrirDuGrainVautOffrirSaValeurEnDeben(): void
    {
        self::bootKernel();
        $enNature = $this->villeAvecTemple('nature@example.com', niveau: 2);
        $enDeben = $this->villeAvecTemple('deben@example.com', niveau: 2);

        // Le blé cote 2 deben : dix mesures valent vingt deben.
        $this->offrandes()->offrir($enNature, Divinite::Hapi, Ressource::Ble, 10);
        $this->offrandes()->offrir($enDeben, Divinite::Hapi, Ressource::Deben, 20);

        self::assertSame(
            $enDeben->getVille()->faveurEnvers(Divinite::Hapi),
            $enNature->getVille()->faveurEnvers(Divinite::Hapi),
            'Dix blés valent vingt deben, au cours du Marché et à rien d\'autre.',
        );
    }

    /**
     * Une offrande qui ne pèse rien ne se remarque pas — sans quoi offrir un
     * roseau à la fois vaudrait offrir un lingot, à force de répétitions
     * gratuites.
     */
    public function testUneOffrandeDerisoireEstRefusee(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('derisoire@example.com');

        $this->expectException(OffrandeImpossible::class);
        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Argile, 1);
    }

    /**
     * **Le Temple plafonne la faveur** : au premier niveau, un dieu peut
     * devenir Favorable, pas davantage. Le palier Dévoué est une conquête de
     * partie avancée, pas un achat de début.
     */
    public function testLeNiveauDuTemplePlafonneLaFaveur(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('plafond@example.com', niveau: 1);
        $ville = $partie->getVille();

        self::assertSame(
            Temple::PLAFOND_DE_BASE + Temple::PLAFOND_PAR_NIVEAU,
            Temple::plafondDeFaveur($ville),
        );

        // Une offrande démesurée ne dépasse pas ce que le Temple peut porter.
        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 400);

        self::assertSame(Temple::plafondDeFaveur($ville), $ville->faveurEnvers(Divinite::Ptah));
        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Ptah));

        // Et l'offrande suivante est refusée plutôt que gaspillée en silence.
        $this->expectException(OffrandeImpossible::class);
        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 100);
    }

    /**
     * **Le Temple limite aussi le nombre de dieux portés haut**, et c'est ce
     * qui fait de la répartition des offrandes une stratégie : un Temple
     * modeste oblige à choisir.
     */
    public function testUnTempleModesteObligeAChoisir(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('choisir@example.com', niveau: 1);
        $ville = $partie->getVille();

        self::assertSame(1, Temple::divinitesPortables($ville));

        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 40);
        self::assertSame([Divinite::Ptah], $ville->divinitesHonorees());

        // Le second dieu est refusé tant que le premier occupe la place.
        try {
            $this->offrandes()->offrir($partie, Divinite::Hapi, Ressource::Deben, 40);
            self::fail('Un Temple de niveau 1 ne porte qu\'une divinité.');
        } catch (OffrandeImpossible $impossible) {
            self::assertStringContainsString('Temple', $impossible->getMessage());
        }

        // Mais celui qu'on porte déjà ne consomme pas une place de plus.
        self::assertTrue(Temple::peutEncorePorter($ville, Divinite::Ptah));
        self::assertFalse(Temple::peutEncorePorter($ville, Divinite::Hapi));
    }

    /**
     * Le mode d'essai lève les deux limites, comme il lève les plafonds de
     * réserve : on l'utilise justement pour examiner ce qu'une partie avancée
     * donnerait.
     */
    public function testLeModeDivinLeveLesDeuxLimites(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('divin-temple@example.com')->getVille();
        $ville->basculerLeModeDivin(true);

        self::assertSame(\count(Divinite::pantheon()), Temple::divinitesPortables($ville));
        self::assertSame(Divinite::FAVEUR_MAXIMALE, Temple::plafondDeFaveur($ville));
    }

    /**
     * L'écran doit dire ce qu'une offrande vaut **avant** qu'on la fasse —
     * même exigence que pour un ordre commercial, où le prix montre son effet
     * avant l'engagement.
     */
    public function testLeCoutDuneOffrandeSeLitAvantDeLaFaire(): void
    {
        self::assertSame(Offrandes::POINTS_PAR_OFFRANDE, Offrandes::pointsPour(Ressource::Deben, 10));
        self::assertSame(20, Offrandes::valeurDe(Ressource::Ble, 10));
        self::assertSame(10, Offrandes::valeurDe(Ressource::Deben, 10));
        self::assertSame(0, Offrandes::pointsPour(Ressource::Argile, 1));
    }

    private function villeAvecTemple(string $email, int $niveau = 1): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, $niveau));
        $ville->crediterRessources([
            Ressource::Deben->value => 1_000,
            Ressource::Ble->value => 100,
            Ressource::Argile->value => 100,
        ]);

        return $partie;
    }

    private function lancerPartie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function offrandes(): Offrandes
    {
        return static::getContainer()->get(Offrandes::class);
    }
}
