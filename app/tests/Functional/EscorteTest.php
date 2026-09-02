<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Bandits;
use App\Game\Commerce;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\Medjays;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * L'escorte : mener la troupe, et couvrir les routes (doc 03, lot 10.5).
 *
 * Deux emplois pour une même troupe, et c'est leur tension qui fait le lot :
 * les Medjaÿ qui partent déloger une bande sont les mêmes que ceux qui
 * couvrent les convois. Une sortie coûteuse en blessés découvre les routes.
 *
 * **Le risque de pillage est un système inventé**, et les tests le disent :
 * aucun document ne décrit de perte de convoi. Il est ancré sur le paramètre
 * « Danger » du doc 02 plutôt que libre, pour qu'une même règle serve deux
 * systèmes au lieu d'ajouter un hasard de plus.
 */
final class EscorteTest extends KernelTestCase
{
    /**
     * **Le chef d'expédition est le seul à partir en armes**, et il ne part
     * que déloger une bande : c'est ce qui lui donne enfin un emploi propre,
     * lui qui coûtait cinq fois l'éclaireur pour le même travail.
     */
    public function testLeChefDexpeditionNePartQueDelogerUneBande(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-emploi@example.com');
        $this->leverLaTroupe($partie, 3);

        $libre = $this->uneZoneReconnue($partie);

        try {
            $this->explorations()->envoyer($partie, $libre, RoleDExploration::ChefDExpedition);
            self::fail('Une expédition en armes sur une case libre aurait dû être refusée.');
        } catch (ExplorationImpossible $refus) {
            self::assertStringContainsString('rien à faire', $refus->getMessage());
        }

        $libre->installerUneBande(Bandits::DEFENSE_DE_BASE);

        self::assertSame(
            RoleDExploration::ChefDExpedition,
            $this->explorations()->envoyer($partie, $libre, RoleDExploration::ChefDExpedition)->getRole(),
        );
    }

    /**
     * **Les autres rôles refusent une case tenue.** On n'envoie pas un scribe
     * parlementer avec des brigands, et c'est ce qui oblige à lever une troupe
     * plutôt qu'à contourner le problème.
     */
    public function testLesAutresRolesRefusentUneCaseTenue(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-refus@example.com');

        $gardee = $this->uneZoneReconnue($partie);
        $gardee->installerUneBande(Bandits::DEFENSE_DE_BASE);

        $this->expectException(ExplorationImpossible::class);
        $this->explorations()->envoyer($partie, $gardee, RoleDExploration::Prospecteur);
    }

    /**
     * **Sans troupe, l'expédition ne part pas.** Le chef d'expédition mène des
     * hommes ; seul, il n'est rien.
     */
    public function testSansTroupeLexpeditionNePartPas(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-sans-troupe@example.com');

        $gardee = $this->uneZoneReconnue($partie);
        $gardee->installerUneBande(Bandits::DEFENSE_DE_BASE);

        $this->expectException(ExplorationImpossible::class);
        $this->explorations()->envoyer($partie, $gardee, RoleDExploration::ChefDExpedition);
    }

    /**
     * **Une région sans bande ne perd jamais un convoi.** Les deux premières
     * missions n'en portent aucune : le commerce y reste ce qu'il était avant
     * la Phase 10, ce qui protège l'économie calibrée aux phases 5 et 9.
     */
    public function testUneRegionSansBandeNeRisqueRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-sans-bande@example.com');

        self::assertSame(0, Bandits::compterLesZonesGardees($partie->getVille()));
        self::assertSame(0, $this->commerce()->risqueDePillage($partie));
    }

    /**
     * **Les bandes menacent les routes, la garnison les couvre.** C'est ce qui
     * donne aux Medjaÿ un emploi entre deux sorties, et ce qui rend une sortie
     * coûteuse : des blessés ne couvrent plus rien.
     */
    public function testLesBandesMenacentLesRoutesEtLaGarnisonLesCouvre(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-routes@example.com');

        $bandes = 0;

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->porteLaVille() && $bandes < 2) {
                $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
                ++$bandes;
            }
        }

        $sansTroupe = $this->commerce()->risqueDePillage($partie);
        self::assertSame(2 * Commerce::RISQUE_PAR_BANDE_DE_LA_REGION, $sansTroupe);

        $troupe = $this->leverLaTroupe($partie, 3);
        $avecTroupe = $this->commerce()->risqueDePillage($partie);

        self::assertLessThan($sansTroupe, $avecTroupe);

        // Un homme blessé ne couvre plus les routes : la sortie se paie deux
        // fois, une fois en sang et une fois en marchandise.
        $troupe[0]->blesser($partie->getCycle(), 2);
        self::assertGreaterThan($avecTroupe, $this->commerce()->risqueDePillage($partie));
    }

    /**
     * Assez d'hommes, et les routes ne craignent plus rien.
     */
    public function testUneGarnisonSuffisanteCouvreEntierementLesRoutes(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('escorte-couverture@example.com');

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->porteLaVille()) {
                $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
                break;
            }
        }

        $this->leverLaTroupe($partie, 7);

        self::assertSame(0, $this->commerce()->risqueDePillage($partie));
    }

    private function uneZoneReconnue(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->porteLaVille() && !$zone->estGardee()) {
                $zone->decouvrir();

                return $zone;
            }
        }

        self::fail('La carte ne porte aucune case ordinaire.');
    }

    /**
     * @return list<\App\Entity\Medjay>
     */
    private function leverLaTroupe(GameSave $partie, int $combien): array
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 9));
        $ville->crediterRessources([Ressource::Deben->value => 5_000]);

        $troupe = [];

        foreach (range(1, $combien) as $ignore) {
            $troupe[] = $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
        }

        return $troupe;
    }

    private function lancerUnePartie(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }

    private function medjays(): Medjays
    {
        return static::getContainer()->get(Medjays::class);
    }
}
