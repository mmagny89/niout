<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ExplorationTest extends KernelTestCase
{
    public function testEnvoyerUnEclaireurAuLoinDebiteSonSolde(): void
    {
        self::bootKernel();
        $partie = $this->lancerSurUneGrandeCarte('solde@example.com');
        $debenAvant = $partie->getVille()->getDeben();

        $this->explorations()->envoyer($partie, $this->caseEloignee($partie), RoleDExploration::Eclaireur);

        self::assertSame($debenAvant - RoleDExploration::Eclaireur->cout(), $partie->getVille()->getDeben());
        self::assertCount(1, $partie->getVille()->getExpeditions());
    }

    /**
     * On voit ses propres abords depuis les murs : les huit cases qui touchent
     * la ville se reconnaissent sans bourse délier, en orthogonal comme en
     * diagonale. Faire payer le premier pas d'une partie neuve reviendrait à
     * taxer le joueur pour découvrir où il vient d'être envoyé.
     */
    public function testReconnaitreLesAbordsDeLaVilleNeCoutePasDeDeben(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('abords@example.com');
        $debenAvant = $partie->getVille()->getDeben();

        $this->explorations()->envoyer($partie, $this->caseAdjacente($partie), RoleDExploration::Eclaireur);

        self::assertSame($debenAvant, $partie->getVille()->getDeben());
        self::assertCount(1, $partie->getVille()->getExpeditions(), 'L\'expédition part bel et bien.');
    }

    /**
     * Une case à moins de trois cases de la ville ne coûte rien du tout — ni
     * deben ni vivres (décision de la joueuse) : assez proche pour qu'un
     * éclaireur y aille sans qu'on lui compte sa peine.
     */
    public function testLesAbordsNeCoutentPlusDeVivresNonPlus(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('abords-vivres@example.com');
        $avant = $partie->getVille()->getNourriture();

        $this->explorations()->envoyer($partie, $this->caseAdjacente($partie), RoleDExploration::Eclaireur);

        self::assertSame(
            $avant,
            $partie->getVille()->getNourriture(),
            'Une case à moins de trois cases de la ville ne coûte rien, pas même des vivres.',
        );
    }

    public function testSansUnSeulDebenOnPeutEncoreReconnaitreSesAbords(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('ruine@example.com');
        $ville = $partie->getVille();
        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);

        $this->explorations()->envoyer($partie, $this->caseAdjacente($partie), RoleDExploration::Eclaireur);

        self::assertCount(1, $ville->getExpeditions(), 'Une partie ne doit jamais se retrouver bloquée sans issue.');
    }

    public function testSansUnSeulVivreOnPeutEncoreReconnaitreSesAbords(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('affame@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        $this->explorations()->envoyer($partie, $this->caseAdjacente($partie), RoleDExploration::Eclaireur);

        self::assertCount(1, $ville->getExpeditions(), 'Les abords ne coûtent pas de vivres : la famine ne doit pas bloquer la partie.');
    }

    /**
     * On ne part pas explorer les mains vides (doc 04) : c'est ce qui donne à
     * la nourriture un usage avant même qu'il y ait une population à nourrir —
     * mais seulement au-delà du rayon gratuit.
     */
    public function testEnvoyerUnEclaireurAuLoinEntameLesVivres(): void
    {
        self::bootKernel();
        $partie = $this->lancerSurUneGrandeCarte('vivres@example.com');
        $avant = $partie->getVille()->getNourriture();

        $this->explorations()->envoyer($partie, $this->caseEloignee($partie), RoleDExploration::Eclaireur);

        self::assertSame($avant - RoleDExploration::Eclaireur->provisions(), $partie->getVille()->getNourriture());
    }

    public function testSansVivresAucunEclaireurNePartAuLoinEtLaMonnaieResteIntacte(): void
    {
        self::bootKernel();
        $partie = $this->lancerSurUneGrandeCarte('famine@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());
        $debenAvant = $ville->getDeben();

        try {
            $this->explorations()->envoyer($partie, $this->caseEloignee($partie), RoleDExploration::Eclaireur);
            self::fail('L\'expédition aurait dû être refusée faute de vivres.');
        } catch (ExplorationImpossible $impossible) {
            self::assertStringContainsString('vivres', $impossible->getMessage());
            self::assertSame($debenAvant, $ville->getDeben(), 'La monnaie ne doit pas être prélevée pour rien.');
            self::assertCount(0, $ville->getExpeditions());
        }
    }

    public function testLaCaseResteSousBrouillardTantQueLEclaireurEstEnRoute(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('route@example.com');
        $cible = $this->caseInconnue($partie);

        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        self::assertFalse($cible->estDecouverte(), 'Reconnaître prend du temps.');
    }

    public function testLaCaseSeReveleQuandLEclaireurArrive(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('arrivee@example.com');
        $cible = $this->caseInconnue($partie);
        $expedition = $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        for ($i = 0; $i < $expedition->getDureeEnCycles(); ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertTrue($cible->estDecouverte());
        self::assertCount(0, $partie->getVille()->getExpeditions(), 'L\'expédition arrivée disparaît.');
    }

    public function testLeRapportDeLEclaireurNommeLeTerrain(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rapport-eclaireur@example.com');
        $cible = $this->caseInconnue($partie);
        $expedition = $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        $evenements = [];
        for ($i = 0; $i < $expedition->getDureeEnCycles(); ++$i) {
            $evenements = array_merge($evenements, $this->cycle()->passer($partie));
        }

        self::assertNotEmpty($evenements);
        self::assertStringContainsString($cible->getTerrain()->libelle(), implode(' ', $evenements));
    }

    public function testOnNEnvoiePasDeuxEclaireursSurLaMemeCase(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('doublon-eclaireur@example.com');
        $cible = $this->caseInconnue($partie);
        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà en route/');

        $this->explorations()->envoyer($partie, $cible, RoleDExploration::Eclaireur);
    }

    public function testOnNExplorePasUneCaseDejaReconnue(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('deja-vu@example.com');
        $centre = $partie->getVille()->zoneDeLaVille();
        self::assertInstanceOf(Zone::class, $centre);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà reconnue/');

        $this->explorations()->envoyer($partie, $centre, RoleDExploration::Eclaireur);
    }

    public function testSansDebenAucunEclaireurNePartAuLoinEtRienNEstDebite(): void
    {
        self::bootKernel();
        $partie = $this->lancerSurUneGrandeCarte('sans-le-sou@example.com');
        $ville = $partie->getVille();
        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);

        try {
            $this->explorations()->envoyer($partie, $this->caseEloignee($partie), RoleDExploration::Eclaireur);
            self::fail('L\'expédition aurait dû être refusée.');
        } catch (ExplorationImpossible) {
            self::assertSame(0, $ville->getDeben());
            self::assertCount(0, $ville->getExpeditions());
        }
    }

    public function testPlusieursExpeditionsPeuventCourirDeFront(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('de-front@example.com');
        $inconnues = $this->casesInconnues($partie);
        self::assertGreaterThanOrEqual(3, \count($inconnues));

        foreach (\array_slice($inconnues, 0, 3) as $zone) {
            $this->explorations()->envoyer($partie, $zone, RoleDExploration::Eclaireur);
        }

        self::assertCount(3, $partie->getVille()->getExpeditions());
    }

    public function testUneExpeditionEtUnChantierAvancentDansLaMemeQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('ensemble@example.com');
        $expedition = $this->explorations()->envoyer($partie, $this->caseInconnue($partie), RoleDExploration::Eclaireur);
        $restantAvant = $expedition->cyclesRestants();

        $this->cycle()->passer($partie);

        self::assertSame($restantAvant - 1, $expedition->cyclesRestants());
        self::assertSame(2, $partie->getCycle());
    }

    private function caseInconnue(GameSave $partie): Zone
    {
        return $this->casesInconnues($partie)[0];
    }

    /**
     * Une case inconnue proche de la ville — reconnaissance gratuite.
     */
    private function caseAdjacente(GameSave $partie): Zone
    {
        return $this->caseInconnueADistance($partie, static fn (int $distance): bool => $distance < 3);
    }

    /**
     * Une case inconnue au-delà des abords — reconnaissance payante.
     */
    private function caseEloignee(GameSave $partie): Zone
    {
        return $this->caseInconnueADistance($partie, static fn (int $distance): bool => $distance >= 3);
    }

    private function caseInconnueADistance(GameSave $partie, callable $critere): Zone
    {
        $centre = $partie->getVille()->zoneDeLaVille();
        self::assertInstanceOf(Zone::class, $centre);

        foreach ($this->casesInconnues($partie) as $zone) {
            if ($critere($zone->distanceDepuis($centre))) {
                return $zone;
            }
        }

        self::fail('Aucune case inconnue ne répond à ce critère de distance.');
    }

    /**
     * @return list<Zone>
     */
    private function casesInconnues(GameSave $partie): array
    {
        $inconnues = [];

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                $inconnues[] = $zone;
            }
        }

        self::assertNotEmpty($inconnues, 'Une carte neuve doit avoir des cases inexplorées.');

        return $inconnues;
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

    /**
     * Le Delta se joue en 3×3 : si la ville tombe au centre, **toute** la carte
     * lui est adjacente et rien n'y coûte d'or. Les cas qui portent sur une
     * case éloignée réclament donc une grille assez large pour en garantir une,
     * sans quoi ils dépendraient du tirage de la carte.
     */
    private function lancerSurUneGrandeCarte(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)
            ->lancerAventure($user, 'Nakht', difficulte: 0, tailleGrille: 7);
    }

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
