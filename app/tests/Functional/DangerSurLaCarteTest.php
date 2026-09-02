<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Bandits;
use App\Game\ExploitationImpossible;
use App\Game\Exploitations;
use App\Game\GenerateurDeCarte;
use App\Game\GeographieDeRegion;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le danger sur la carte (doc 02, doc 03, lot 10.1).
 *
 * Deux invariants portent tout le lot : le danger **se superpose** au contenu
 * d'une case au lieu de le remplacer — c'est ce qui rend possible le filon
 * gardé —, et il **ne touche jamais l'anneau des huit cases** autour de la
 * ville, sans quoi la première carrière serait imprenable et la partie
 * injouable au premier cycle.
 */
final class DangerSurLaCarteTest extends KernelTestCase
{
    /**
     * Le compte du doc 02, à la lettre : `partieEntiere(difficulté × 0,5)`.
     * Aucune bande avant la difficulté 2 — les deux premières missions laissent
     * le temps d'ouvrir une Caserne avant d'en avoir besoin.
     */
    public function testLeNombreDeBandesSuitLaDifficulte(): void
    {
        self::assertSame(0, Bandits::nombrePour(0));
        self::assertSame(0, Bandits::nombrePour(1));
        self::assertSame(1, Bandits::nombrePour(2));
        self::assertSame(4, Bandits::nombrePour(9));
    }

    /**
     * **L'anneau de la ville reste libre**, sur les dix régions de la campagne
     * et sur plusieurs tirages : le générateur y garantit un gisement de chaque
     * matériau vital, et une bande posée dessus bloquerait la partie avant
     * qu'elle commence.
     */
    public function testAucuneBandeNeSInstalleDansLanneauDeLaVille(): void
    {
        self::bootKernel();
        $missions = static::getContainer()->get(MissionCatalogue::class);

        foreach (range(1, 10) as $numero) {
            $mission = $missions->get($numero);

            foreach (range(1, 3) as $essai) {
                $ville = $this->carteDe($mission->difficulte, $mission->tailleDeGrille(), $mission->geographie);
                $centre = $this->zoneDeLaVille($ville);

                foreach ($ville->getZones() as $zone) {
                    if (!$zone->estGardee()) {
                        continue;
                    }

                    self::assertFalse(
                        $zone->porteLaVille(),
                        'Une bande campe sur la ville elle-même.',
                    );
                    self::assertFalse(
                        $zone->estAdjacenteA($centre),
                        \sprintf(
                            'Mission %d, essai %d : une bande campe dans l\'anneau de la ville.',
                            $numero,
                            $essai,
                        ),
                    );
                }
            }
        }
    }

    /**
     * **Le danger se superpose, il ne remplace pas.** Une case gardée garde
     * son contenu : c'est le filon gardé, celui qui donne envie de lever une
     * troupe. Un contenu de zone l'aurait rendu impossible.
     */
    public function testUneCaseGardeePeutPorterUnGisement(): void
    {
        $zone = new Zone(new City('Ville', 0, 3), 1, 1, \App\Game\TypeDeTerrain::Fertile);
        $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);

        self::assertTrue($zone->estGardee());
        self::assertSame(Bandits::DEFENSE_DE_BASE, $zone->getDefenseDesBandits());
    }

    /**
     * **Une case pacifiée le reste** (arbitrage 10.0) : le combat est une
     * conquête, pas un péage qu'on repaie.
     */
    public function testUneCasePacifieeLeReste(): void
    {
        $zone = new Zone(new City('Ville', 0, 3), 1, 1, \App\Game\TypeDeTerrain::Fertile);
        $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
        $zone->pacifier();

        self::assertFalse($zone->estGardee());
        self::assertSame(0, Bandits::defenseDe(new City('Ville', 0, 3), $zone));
    }

    /**
     * **Une région dangereuse est plus dure partout**, pas seulement sur ses
     * cases gardées : c'est le sens du facteur du doc 03, et ce qui fait du
     * nombre de bandes un curseur de difficulté régionale.
     *
     * Corollaire qui compte : nettoyer une case affaiblit toutes les autres.
     */
    public function testChaqueBandeRenforceLesAutres(): void
    {
        $ville = new City('Ville', 0, 3);
        $zones = [];

        foreach ([[0, 0], [0, 1], [0, 2]] as [$x, $y]) {
            $zone = new Zone($ville, $x, $y, \App\Game\TypeDeTerrain::Fertile);
            $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
            $ville->ajouterZone($zone);
            $zones[] = $zone;
        }

        $aTrois = Bandits::defenseDe($ville, $zones[0]);

        self::assertSame(
            intdiv(Bandits::DEFENSE_DE_BASE * (100 + 3 * Bandits::RENFORT_PAR_ZONE_DE_LA_REGION), 100),
            $aTrois,
        );

        // On en nettoie une : les deux autres faiblissent.
        $zones[2]->pacifier();

        self::assertLessThan($aTrois, Bandits::defenseDe($ville, $zones[0]));
    }

    /**
     * **Une case gardée ne se travaille pas.** C'est tout l'intérêt du filon
     * gardé : il faut le prendre avant de l'exploiter, et c'est ce qui donne
     * une raison de lever des Medjaÿ.
     */
    public function testOnNexploitePasUneCaseGardee(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie();
        $ville = $partie->getVille();

        $gardee = null;

        foreach ($ville->getZones() as $zone) {
            if (!$zone->porteLaVille() && !$zone->getGisements()->isEmpty()) {
                $zone->decouvrir();
                $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
                $gardee = $zone;
                break;
            }
        }

        self::assertNotNull($gardee, 'La carte doit porter au moins un gisement hors de la ville.');

        $gisement = $gardee->getGisements()->first();
        self::assertNotFalse($gisement);

        $this->expectException(ExploitationImpossible::class);
        static::getContainer()->get(Exploitations::class)
            ->exploiter($partie, $gardee, $gisement->getRessource());
    }

    private function lancerUnePartie(): GameSave
    {
        $joueur = new User();
        $joueur->setEmail('danger-exploitation@example.com');
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function carteDe(int $difficulte, int $taille, GeographieDeRegion $geographie): City
    {
        $ville = new City('Ville', $difficulte, $taille);
        static::getContainer()->get(GenerateurDeCarte::class)->peupler($ville, $geographie);

        return $ville;
    }

    private function zoneDeLaVille(City $ville): Zone
    {
        foreach ($ville->getZones() as $zone) {
            if ($zone->porteLaVille()) {
                return $zone;
            }
        }

        self::fail('Aucune case ne porte la ville.');
    }
}
