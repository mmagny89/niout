<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\ChantierRoyal;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use App\Game\QueteImpossible;
use App\Game\QuetesDeChantier;
use App\Game\Successions;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les quêtes de chantier (lot 8.3).
 */
final class QuetesDeChantierTest extends WebTestCase
{
    /**
     * **Chaque pharaon de la campagne a son chantier**, et c'en est un vrai :
     * un monument inventé pour les besoins d'une quête trahirait l'objectif
     * pédagogique du projet.
     */
    public function testChaquePharaonDeLaCampagneABatiQuelqueChose(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            $chantier = ChantierRoyal::pour($mission->pharaon);

            self::assertNotNull($chantier, \sprintf('Mission %d : %s.', $mission->numero, $mission->pharaon));
            self::assertNotSame('', $chantier->libelle());
            self::assertNotSame('', $chantier->ceQuOnEnSait());
        }
    }

    /**
     * **Le pharaon réclame ce que la région porte.** Envoyer chercher au loin
     * ce qu'on a sous les pieds n'aurait pas de sens, et rendrait la quête
     * impossible dans la moitié des missions.
     */
    public function testIlReclameCeQueLaRegionPorte(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('reclame@example.com');

        $quete = $this->jusquALaQuete($partie);

        self::assertContains(
            $quete->getRessource(),
            (new MissionCatalogue())->get(1)->geographie->ressourcesDeZone,
        );
        self::assertGreaterThanOrEqual(QuetesDeChantier::QUANTITE_MINIMALE, $quete->getQuantite());
        self::assertLessThanOrEqual(QuetesDeChantier::QUANTITE_MAXIMALE, $quete->getQuantite());
    }

    /**
     * Livrer rapporte du renom, la faveur du dieu que le monument honore, et
     * **ce qu'on apprend au passage**.
     */
    public function testLivrerRapporteDuRenomEtDeLaFaveur(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('livrer@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 5));

        $quete = $this->jusquALaQuete($partie);
        $ville->crediterRessources([$quete->getRessource()->value => $quete->getQuantite()]);

        $renommee = $partie->getFamille()->getRenommee();
        $divinite = $quete->getChantier()->divinite();
        self::assertNotNull($divinite, 'Ahmôsis honore Osiris à Abydos.');
        $faveur = $ville->faveurEnvers($divinite);

        $message = static::getContainer()->get(QuetesDeChantier::class)->livrer($partie);

        self::assertSame($renommee + QuetesDeChantier::RENOMMEE_GAGNEE, $partie->getFamille()->getRenommee());
        self::assertGreaterThan($faveur, $ville->faveurEnvers($divinite));
        self::assertStringContainsString('pyramide', $message);
        self::assertNull($ville->getQueteDeChantier());
    }

    /**
     * **Refuser coûte deux points de renommée, et rien d'autre** : le joueur
     * reste libre de prioriser sa propre stratégie.
     */
    public function testRefuserNeCouteQueDuRenom(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('refuser@example.com');
        $ville = $partie->getVille();

        // La renommée ne descend pas sous zéro : une famille inconnue ne peut
        // pas l'être davantage. On lui en donne de quoi perdre.
        $partie->getFamille()->ajusterRenommee(10);

        $quete = $this->jusquALaQuete($partie);
        $ville->crediterRessources([$quete->getRessource()->value => 100]);

        $renommee = $partie->getFamille()->getRenommee();
        $stock = $ville->quantite($quete->getRessource());

        static::getContainer()->get(QuetesDeChantier::class)->refuser($partie);

        self::assertSame($renommee - QuetesDeChantier::RENOMMEE_PERDUE, $partie->getFamille()->getRenommee());
        self::assertSame($stock, $ville->quantite($quete->getRessource()), 'Rien n\'est prélevé.');
        self::assertNull($ville->getQueteDeChantier());
    }

    /**
     * Sans de quoi livrer, la demande est refusée plutôt qu'honorée à crédit.
     */
    public function testOnNeLivrePasCeQuOnNaPas(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-stock@example.com');
        $this->jusquALaQuete($partie);

        $this->expectException(QueteImpossible::class);
        static::getContainer()->get(QuetesDeChantier::class)->livrer($partie);
    }

    /**
     * **Laisser filer le délai revient à refuser.** Sans cela, attendre serait
     * toujours meilleur que décliner, et le délai ne voudrait rien dire.
     */
    public function testLaisserFilerLeDelaiRevientARefuser(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('delai@example.com');
        $quetes = $this->quetes();

        $partie->getFamille()->ajusterRenommee(10);
        $this->jusquALaQuete($partie);
        $renommee = $partie->getFamille()->getRenommee();

        for ($i = 0; $i < QuetesDeChantier::DELAI_EN_QUINZAINES; ++$i) {
            $partie->avancerDUnCycle();
            $quetes->avancerDUnCycle($partie);
        }

        self::assertNull($partie->getVille()->getQueteDeChantier());
        self::assertSame($renommee - QuetesDeChantier::RENOMMEE_PERDUE, $partie->getFamille()->getRenommee());
    }

    /**
     * Le monument d'Akhenaton n'honore aucun dieu du panthéon du jeu : sa
     * quête ne rapporte donc pas de faveur, et c'est historiquement juste —
     * il n'honorait qu'Aton.
     */
    public function testLeChantierDAkhenatonNhonoreAucunDieuDuPantheon(): void
    {
        self::assertNull(ChantierRoyal::GrandTempleDAton->divinite());

        foreach (ChantierRoyal::cases() as $chantier) {
            if (ChantierRoyal::GrandTempleDAton === $chantier) {
                continue;
            }

            $divinite = $chantier->divinite();
            self::assertNotNull($divinite);
            self::assertContains($divinite, [Divinite::AmonRe, Divinite::Osiris]);
        }
    }

    private function jusquALaQuete(GameSave $partie): \App\Entity\QueteDeChantier
    {
        $quetes = $this->quetes();

        for ($i = 0; $i < 12 && null === $partie->getVille()->getQueteDeChantier(); ++$i) {
            $partie->avancerDUnCycle();
            $quetes->avancerDUnCycle($partie);
        }

        $quete = $partie->getVille()->getQueteDeChantier();
        self::assertNotNull($quete, 'Le pharaon doit finir par réclamer quelque chose.');

        return $quete;
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

    private function quetes(): QuetesDeChantier
    {
        return new QuetesDeChantier(
            static::getContainer()->get(EntityManagerInterface::class),
            new MissionCatalogue(),
            static::getContainer()->get(Successions::class),
            new Randomizer(new Mt19937(20260901)),
        );
    }
}
