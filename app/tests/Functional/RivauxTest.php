<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\RivalCommercial;
use App\Entity\RouteCommerciale;
use App\Entity\User;
use App\Game\CommerceImpossible;
use App\Game\Enquete;
use App\Game\Enquetes;
use App\Game\LanceurDePartie;
use App\Game\NatureDIndice;
use App\Game\Ressource;
use App\Game\Rivaux;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les marchands rivaux (lot 7.8).
 *
 * Reportés en bloc de la Phase 5 parce que l'une de leurs trois issues est une
 * enquête. Ce qui se vérifie ici est surtout ce qu'un rival **ne fait pas** :
 * il ne ferme rien, il ne détruit rien, et l'on peut ne rien faire.
 */
final class RivauxTest extends WebTestCase
{
    /**
     * **C'est la renommée qui les attire** (doc 08). Une famille obscure ne
     * dérange personne — ce qui fait de la renommée autre chose qu'un compteur
     * qui monte.
     */
    public function testUneFamilleObscureNeDerangePersonne(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('obscure@example.com');

        self::assertSame(0, $partie->getFamille()->getRenommee());
        self::assertSame(0, $this->rivaux()->chanceEnPourMille($partie));

        for ($i = 0; $i < 200; ++$i) {
            $this->rivaux()->avancerDUnCycle($partie);
        }

        self::assertNull($partie->getVille()->getRival(), 'Nul ne vient disputer ses routes.');
    }

    /**
     * Et le plafond du doc 08 : 5 % par quinzaine à renommée pleine, pas
     * davantage.
     */
    public function testLaChanceMonteAvecLaRenommeeEtSArreteLa(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('renommee@example.com');

        $partie->getFamille()->ajusterRenommee(20);
        self::assertSame(10, $this->rivaux()->chanceEnPourMille($partie), 'Un pour cent à renommée 20.');

        $partie->getFamille()->ajusterRenommee(200);
        self::assertSame(Rivaux::PLAFOND_EN_POUR_MILLE, $this->rivaux()->chanceEnPourMille($partie));
    }

    /**
     * **Un rival vient concurrencer quelque chose** : sans route ouverte, il
     * n'a rien à prendre et ne paraît pas.
     */
    public function testSansRouteOuverteAucunRivalNeSInstalle(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-route@example.com');
        $partie->getFamille()->ajusterRenommee(100);

        for ($i = 0; $i < 200; ++$i) {
            $this->rivaux()->avancerDUnCycle($partie);
        }

        self::assertNull($partie->getVille()->getRival());
    }

    /**
     * **Il rogne, il ne ferme pas.** Le volume baisse sur sa route, et sur
     * elle seule — il tient une route, pas tout le commerce.
     */
    public function testIlRogneUneRouteEtElleSeule(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('rogne@example.com');

        self::assertSame(0, Rivaux::malusSur($partie, 'memphis'));

        $this->installer($partie, 'memphis', malus: 20);

        self::assertSame(20, Rivaux::malusSur($partie, 'memphis'));
        self::assertSame(0, Rivaux::malusSur($partie, 'byblos'), 'Les autres routes ne le regardent pas.');
    }

    /**
     * **Ignorer est une des trois issues** : il se lasse et s'en va. Ce n'est
     * pas une impasse, c'est une décision.
     */
    public function testOnPeutNeRienFaireEtIlFinitParPartir(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('ignorer@example.com');
        $this->installer($partie, 'memphis', malus: 15, quinzaines: 3);

        $messages = [];

        for ($i = 0; $i < 3; ++$i) {
            $messages = [...$messages, ...$this->rivaux()->avancerDUnCycle($partie)];
        }

        self::assertNull($partie->getVille()->getRival());
        self::assertNotSame([], $messages);
    }

    /**
     * **L'accord** : on paie, il s'écarte. La seule issue qui coûte des deben
     * plutôt que du temps.
     */
    public function testUnAccordLeFaitSEcarterContreDesDeben(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('accord@example.com');
        $this->installer($partie, 'memphis', malus: 15);

        $prix = $this->rivaux()->prixDeLAccord($partie);
        $partie->getVille()->crediterRessources([Ressource::Deben->value => $prix]);
        $avant = $partie->getVille()->quantite(Ressource::Deben);

        $this->rivaux()->passerUnAccord($partie);

        self::assertNull($partie->getVille()->getRival());
        self::assertSame($avant - $prix, $partie->getVille()->quantite(Ressource::Deben));
    }

    /**
     * Sans de quoi payer, l'accord est refusé plutôt que consenti à crédit.
     */
    public function testUnAccordQuOnNePeutPasPayerEstRefuse(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('sans-le-sou@example.com');
        $this->installer($partie, 'memphis', malus: 15);
        $partie->getVille()->debiterRessources([
            Ressource::Deben->value => $partie->getVille()->quantite(Ressource::Deben),
        ]);

        $this->expectException(CommerceImpossible::class);
        $this->rivaux()->passerUnAccord($partie);
    }

    /**
     * **L'enquête** : la troisième issue, la plus longue et la plus payante.
     * C'est elle qui a fait reporter tout le système de la Phase 5 à ici.
     */
    public function testLEnqueteLeDemonteDefinitivement(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRoute('enquete-rival@example.com');
        $partie->getVille()->ajouterBatiment(
            new Building($partie->getVille(), TypeDeBatiment::MaisonDesScribes, 1),
        );
        $this->installer($partie, 'memphis', malus: 15);

        $dossier = $partie->getVille()->ouvrirLeDossierDe(Enquete::MalversationDuRival);

        foreach (Enquete::MalversationDuRival->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                $dossier->verser($indice);
            }
        }

        $verdict = $this->enquetes()->conclure(
            $partie,
            Enquete::MalversationDuRival,
            Enquete::MalversationDuRival->bonneConclusion(),
        );

        self::assertTrue($verdict['juste']);
        self::assertNull($partie->getVille()->getRival(), 'Il ne revient pas.');
        self::assertGreaterThan(
            Enquete::CarrieresAbandonnees->recompenseEnDeben(),
            Enquete::MalversationDuRival->recompenseEnDeben(),
            'La plus longue des issues est la plus payante.',
        );
    }

    /**
     * **On ne démonte pas un marchand avant qu'il n'arrive** : ses indices ne
     * se ramassent pas tant que personne ne vous concurrence.
     */
    public function testLesIndicesDuRivalNeSeRamassentPasSansRival(): void
    {
        self::assertTrue(Enquete::MalversationDuRival->viseUnRival());

        foreach (Enquete::cases() as $enquete) {
            if (Enquete::MalversationDuRival !== $enquete) {
                self::assertFalse($enquete->viseUnRival());
            }
        }
    }

    private function installer(GameSave $partie, string $partenaire, int $malus, int $quinzaines = 10): void
    {
        $rival = new RivalCommercial($partie->getVille(), $partenaire, 'Hori le marchand', $malus, $quinzaines);
        $partie->getVille()->installerUnRival($rival);
        static::getContainer()->get(EntityManagerInterface::class)->persist($rival);
    }

    private function villeAvecRoute(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 1));

        $route = new RouteCommerciale($ville, 'memphis', TypeDeRoute::Fluviale, 0);
        $ville->ajouterRouteCommerciale($route);

        static::getContainer()->get(EntityManagerInterface::class)->flush();

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

    private function rivaux(): Rivaux
    {
        return new Rivaux(
            static::getContainer()->get(EntityManagerInterface::class),
            new \App\Game\CataloguePartenaires(),
            new Randomizer(new Mt19937(20260901)),
        );
    }

    private function enquetes(): Enquetes
    {
        return static::getContainer()->get(Enquetes::class);
    }
}
