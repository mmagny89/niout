<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\RouteCommerciale;
use App\Entity\User;
use App\Game\CataloguePartenaires;
use App\Game\LanceurDePartie;
use App\Game\Rivaux;
use App\Game\RoleDePnj;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le rival raisonné : un but, une mémoire plafonnée, trois issues qui laissent une trace
 * (`docs/plan-pnj-palier-b.md` § 5.1, § 9).
 */
final class RivalRaisonneTest extends KernelTestCase
{
    public function testIlSInstalleAvecUnVisageEtSesRaisons(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('raisonne@example.com');
        $rivaux = $this->rivaux();

        $rival = $this->attendreUnRival($partie, $rivaux);

        self::assertNotSame(0, $rival->getIdentite());
        self::assertSame('canaan', $rival->getPartenaire());
        self::assertArrayHasKey('route', $rival->getRaisons());
        self::assertArrayHasKey('caractere', $rival->getRaisons());
        self::assertNotNull($this->npc($partie, $rival->getIdentite()), 'Sa mémoire naît à son installation.');
        self::assertStringContainsString('a choisi cette route', $rivaux->raconter($rival));
    }

    public function testInertieIlTientSaRouteJusquAuBout(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('inertie@example.com');
        $rivaux = $this->rivaux();
        $rival = $this->attendreUnRival($partie, $rivaux);
        $route = $rival->getPartenaire();
        $restantes = $rival->getQuinzainesRestantes();

        for ($i = 0; $i < $restantes - 1; ++$i) {
            $rivaux->avancerDUnCycle($partie);
            self::assertSame($route, $partie->getVille()->getRival()?->getPartenaire(), 'Il ne change pas de route en cours.');
        }
    }

    public function testPayerLaisseUneTraceEtRabatLePrixDuSuivant(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('paye@example.com');
        $rivaux = $this->rivaux();
        $rival = $this->attendreUnRival($partie, $rivaux);
        $partie->getVille()->crediterRessources(['deben' => 100_000]);
        $plein = $rivaux->prixDeLAccord($partie);

        $rivaux->passerUnAccord($partie);
        $npc = $this->npc($partie, $rival->getIdentite());

        self::assertNotNull($npc);
        self::assertSame(1, $npc->getAccords());
        self::assertSame(['paye', 'installe'], array_slice(array_column($npc->getHistoire(), 't'), 0, 2), 'Sa chronique garde ce qui lui est arrivé.');
        self::assertSame($partie->getCycle() + Rivaux::DELAI_APRES_UN_ACCORD, $npc->getIndisponibleJusqua());
        self::assertFalse($npc->peutParaitre($partie->getCycle() + Rivaux::DELAI_APRES_UN_ACCORD - 1), 'Jamais avant son délai plancher.');
        self::assertTrue($npc->peutParaitre($partie->getCycle() + Rivaux::DELAI_APRES_UN_ACCORD));

        // Le suivant : s'il revient, il coûte moins — jamais moins que le plancher.
        $this->forcerLeRetour($partie, $npc, $rivaux);
        $suivant = $partie->getVille()->getRival();
        self::assertNotNull($suivant);
        self::assertSame($rival->getIdentite(), $suivant->getIdentite(), 'C\'est le même marchand, avec le même nom.');
        self::assertSame($rival->getNom(), $suivant->getNom());
        self::assertLessThan($plein, $rivaux->prixDeLAccord($partie));
        self::assertGreaterThanOrEqual(Rivaux::PRIX_MINIMAL_DE_LACCORD, $rivaux->prixDeLAccord($partie));
    }

    public function testLEnqueteLeRetireTresLongtempsSansLeTuer(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('demonte@example.com');
        $rivaux = $this->rivaux();
        $rival = $this->attendreUnRival($partie, $rivaux);

        $rivaux->neutraliserParLEnquete($partie);
        $npc = $this->npc($partie, $rival->getIdentite());

        self::assertNotNull($npc);
        self::assertSame($partie->getCycle() + Rivaux::DELAI_APRES_L_ENQUETE, $npc->getIndisponibleJusqua());
        self::assertGreaterThan(Rivaux::DELAI_APRES_UN_ACCORD, Rivaux::DELAI_APRES_L_ENQUETE, 'L\'enquête est la plus longue des issues.');
        self::assertSame(0, $npc->getAccords(), 'Une enquête n\'est pas un accord.');
    }

    public function testPasDImmortelNiDeSecondRivalSurLaMemeVille(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('immortel@example.com');
        $rivaux = $this->rivaux();
        $presence = 0;
        $apparitions = 0;

        for ($i = 0; $i < 600; ++$i) {
            $avant = $partie->getVille()->getRival();
            $rivaux->avancerDUnCycle($partie);
            $this->synchroniser($partie);
            $rival = $partie->getVille()->getRival();
            $presence = null === $rival ? 0 : $presence + 1;

            if (null !== $rival && $rival !== $avant) {
                ++$apparitions;
            }

            self::assertLessThanOrEqual(Rivaux::QUINZAINES_MAXIMALES + 1, $presence, 'Un rival se lasse : aucun immortel.');
            $partie->avancerDUnCycle();
        }

        self::assertGreaterThan(5, $apparitions, 'Le monde doit en produire sur 600 quinzaines à renommée pleine.');
    }

    public function testLaMemoireEstPlafonnee(): void
    {
        self::bootKernel();
        $npc = new Npc($this->villeRenommee('plafond@example.com'), 1, RoleDePnj::Rival, 'Hori le marchand', \App\Game\TraitDeCandidat::Econome);

        for ($i = 0; $i < 20; ++$i) {
            $npc->etrePaye(0, 12);
        }

        self::assertSame(Npc::ACCORDS_RETENUS_MAX, $npc->getAccords(), 'Aucune rancune ni reconnaissance qui grandit sans fin.');
    }

    public function testIlNeReparaitPasPendantQuIlEstAilleurs(): void
    {
        self::bootKernel();
        $partie = $this->villeRenommee('ailleurs@example.com');
        $rivaux = $this->rivaux();
        $demonte = $this->attendreUnRival($partie, $rivaux)->getIdentite();
        $rivaux->neutraliserParLEnquete($partie);
        $this->synchroniser($partie);
        $retour = $partie->getCycle() + Rivaux::DELAI_APRES_L_ENQUETE;

        // Tant que le délai court, tout nouveau rival est quelqu'un d'autre.
        while ($partie->getCycle() < $retour - 1) {
            $partie->avancerDUnCycle();
            $rivaux->avancerDUnCycle($partie);
            $this->synchroniser($partie);
            $rival = $partie->getVille()->getRival();

            if (null !== $rival) {
                self::assertNotSame($demonte, $rival->getIdentite(), 'Un démonté ne reparaît pas avant son délai.');
                $partie->getVille()->chasserLeRival();
                $this->synchroniser($partie);
            }
        }
    }

    /**
     * Fait passer des quinzaines jusqu'à ce que ce marchand-là reparaisse : le monde tire aussi des
     * inconnus, on écarte ceux-là.
     */
    private function forcerLeRetour(GameSave $partie, Npc $npc, Rivaux $rivaux): void
    {
        for ($i = 0; $i < 6000; ++$i) {
            $rival = $partie->getVille()->getRival();

            if (null !== $rival && $rival->getIdentite() === $npc->getIdentite()) {
                return;
            }

            if (null !== $rival) {
                $partie->getVille()->chasserLeRival();
                $this->synchroniser($partie);
            }

            $partie->avancerDUnCycle();
            $rivaux->avancerDUnCycle($partie);
            $this->synchroniser($partie);
        }

        self::fail('Le marchand payé n\'est jamais revenu.');
    }

    private function attendreUnRival(GameSave $partie, Rivaux $rivaux): \App\Entity\RivalCommercial
    {
        for ($i = 0; $i < 4000; ++$i) {
            $rivaux->avancerDUnCycle($partie);
            $rival = $partie->getVille()->getRival();
            // Le passage de cycle flushe une fois par quinzaine ; ici, on le fait à sa place.
            $this->synchroniser($partie);

            if (null !== $rival) {
                return $rival;
            }

            $partie->avancerDUnCycle();
        }

        self::fail('Aucun rival en 4000 quinzaines à renommée pleine.');
    }

    /**
     * Ce que fait `PassageDeCycle` à chaque quinzaine : un flush — et, ici, de quoi que la ville relise son
     * rival en base, sans quoi un rival installé puis chassé dans la même requête ne se retire jamais.
     */
    private function synchroniser(GameSave $partie): void
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();
        $gestionnaire->refresh($partie->getVille());
    }

    private function npc(GameSave $partie, int $identite): ?Npc
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findOneBy(['partie' => $partie, 'identite' => $identite]);
    }

    private function rivaux(): Rivaux
    {
        return new Rivaux(
            static::getContainer()->get(EntityManagerInterface::class),
            new CataloguePartenaires(),
            new Randomizer(new Mt19937(20260901)),
        );
    }

    private function villeRenommee(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $partie->getFamille()->ajusterRenommee(100);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 1));
        $ville->ajouterRouteCommerciale(new RouteCommerciale($ville, 'canaan', TypeDeRoute::Terrestre, 0));
        $gestionnaire->flush();

        return $partie;
    }
}
