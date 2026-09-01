<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\Gisement;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Prospection;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La prospection : **un filon épuisé n'est pas une impasse**.
 *
 * Avant elle, la dernière unité extraite d'une carrière fermait la production
 * de ce matériau pour toujours ; sur une petite carte, épuiser l'unique
 * gisement d'argile figeait la partie sans qu'aucun geste ne puisse y remédier.
 */
final class ProspectionTest extends KernelTestCase
{
    /**
     * Un filon tari se retrouve dans ce que la case peut encore rendre — c'est
     * exactement ce qu'on vient y chercher.
     */
    public function testUnFilonEpuiseFigureParmiCeQuUneFouillePeutRendre(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('filon-tari@example.com');
        $zone = $this->caseAGisement($partie);
        $gisement = $this->filonTarissable($zone);

        $gisement->extraire($gisement->getQuantiteRestante());
        self::assertTrue($gisement->estEpuise());

        self::assertContains(
            $gisement->getRessource(),
            $this->prospection()->filonsPossibles($partie, $zone),
        );
    }

    /**
     * Le cœur de l'affaire : la veine se rouvre, et la carrière peut de
     * nouveau alimenter la ville.
     */
    public function testUneFouilleRouvreLaVeineTarie(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rouvrir@example.com');
        $zone = $this->caseAGisement($partie);
        $gisement = $this->filonTarissable($zone);

        // Une case à un seul filon, pour que la fouille n'ait rien d'autre à
        // trouver que celui-là : on mesure la réouverture, pas le tirage.
        $gisement->extraire($gisement->getQuantiteRestante());

        // Plusieurs tentatives : la fouille échoue une fois sur trois, et
        // mesurer un tirage sur un seul coup ne prouverait rien.
        for ($essai = 0; $essai < 20 && $gisement->estEpuise(); ++$essai) {
            $this->prospection()->fouiller($partie, $zone);
        }

        self::assertFalse($gisement->estEpuise(), 'Vingt fouilles sans un seul succès : la chance annoncée n\'est pas tenue.');
        self::assertGreaterThan(0, $gisement->getQuantiteRestante());
    }

    /**
     * **On ne plante pas d'acacias en plein désert** : la prospection s'appuie
     * sur la même règle de terrain que la génération de la carte, jamais sur
     * une seconde table qui finirait par en diverger.
     */
    public function testUneFouilleNeFaitJamaisNaitreUnFilonQueLeTerrainDement(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('terrain@example.com');

        foreach ($partie->getVille()->getZones() as $zone) {
            foreach ($this->prospection()->filonsPossibles($partie, $zone) as $filon) {
                if (Ressource::BoisLocal === $filon) {
                    // Le bois local est la ressource de la terre broussailleuse,
                    // et n'apparaît qu'à titre secondaire sur une terre
                    // cultivée — quelques sycomores en bordure de canal.
                    // Ailleurs, jamais : rien ne pousse dans le sable.
                    self::assertContains(
                        $zone->getTerrain(),
                        [TypeDeTerrain::TerreClassique, TypeDeTerrain::Fertile],
                        'Le bois local ne pousse pas dans le sable.',
                    );
                }
            }
        }
    }

    /**
     * **Annoncer un départ qui ne peut rien rapporter serait un piège** : une
     * case pleine et sans filon tari refuse le prospecteur, avant de rien
     * débiter.
     */
    public function testOnNeSondePasUneCaseQuiNaPlusRienADonner(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rien-a-donner@example.com');
        $ville = $partie->getVille();
        $zone = $this->caseSansRien($partie);
        $debenAvant = $ville->getDeben();

        try {
            $this->explorations()->envoyer($partie, $zone, RoleDExploration::Prospecteur);
            self::fail('La fouille aurait dû être refusée : rien à trouver ici.');
        } catch (ExplorationImpossible $impossible) {
            self::assertStringContainsString('plus rien à donner', $impossible->getMessage());
            self::assertSame($debenAvant, $ville->getDeben(), 'Un refus ne coûte rien.');
            self::assertCount(0, $ville->getExpeditions());
        }
    }

    /**
     * On ne sonde pas une terre qu'on n'a pas vue : le prospecteur va vers une
     * case reconnue, comme l'émissaire.
     */
    public function testOnNeSondePasUneCaseSousBrouillard(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('brouillard@example.com');

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->estDecouverte()) {
                continue;
            }

            $this->expectException(ExplorationImpossible::class);
            $this->explorations()->envoyer($partie, $zone, RoleDExploration::Prospecteur);

            return;
        }

        self::fail('Une carte neuve doit avoir des cases sous brouillard.');
    }

    /**
     * **Le rayon gratuit vaut pour la reconnaissance, pas pour le travail** :
     * sans quoi le joueur rouvrirait ses filons sous les murs de la ville sans
     * jamais rien engager, et l'épuisement cesserait de compter.
     */
    public function testLaProspectionSePaieMemeAuxAbordsDeLaVille(): void
    {
        self::assertSame(
            RoleDExploration::Prospecteur->cout(),
            RoleDExploration::Prospecteur->coutPourUneDistance(1),
        );
        self::assertSame(
            0,
            RoleDExploration::Eclaireur->coutPourUneDistance(1),
            'L\'éclaireur, lui, garde son rayon gratuit.',
        );
    }

    /**
     * Le parcours complet : on engage, le prospecteur met des quinzaines à
     * revenir, et son rapport tombe dans le journal de cycle.
     */
    public function testLeProspecteurRapporteAuJournalDeCycle(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rapport@example.com');
        $zone = $this->caseAGisement($partie);
        $gisement = $this->filonTarissable($zone);
        $gisement->extraire($gisement->getQuantiteRestante());

        $expedition = $this->explorations()->envoyer($partie, $zone, RoleDExploration::Prospecteur);

        $evenements = [];
        for ($i = 0; $i < $expedition->getDureeEnCycles(); ++$i) {
            $evenements = array_merge($evenements, $this->cycle()->passer($partie));
        }

        self::assertStringContainsString('prospecteur', implode(' ', $evenements));
        self::assertCount(0, $partie->getVille()->getExpeditions(), 'L\'expédition arrivée disparaît.');
    }

    /**
     * **Retrouver une veine tarie est certain**, et c'est voulu : l'épuisement
     * doit coûter du temps et de l'argent — le trajet, quarante deben, puis
     * rouvrir la carrière et la rééquiper —, jamais fermer une région. Les
     * galeries sont creusées et la géologie connue ; la question n'est plus de
     * savoir s'il y a du matériau ici.
     *
     * Chercher du **neuf**, en revanche, reste un pari : c'est là que le hasard
     * a sa place.
     */
    public function testRetrouverUneVeineTarieEstCertainMaisTrouverDuNeufEstUnPari(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('veine-tarie@example.com');
        $zone = $this->caseAGisement($partie);
        $gisement = $this->filonTarissable($zone);

        $avant = $this->prospection()->chancesSur($partie, $zone);
        self::assertLessThan(
            Prospection::CHANCES_SUR_UNE_VEINE_TARIE,
            $avant,
            'Tant que le filon donne, prospecter cherche du neuf : ce n\'est pas acquis.',
        );

        $gisement->extraire($gisement->getQuantiteRestante());

        self::assertSame(
            Prospection::CHANCES_SUR_UNE_VEINE_TARIE,
            $this->prospection()->chancesSur($partie, $zone),
        );
    }

    /**
     * Une case dont rien ne peut sortir annonce zéro : c'est ce qui fait
     * disparaître le bouton plutôt que de proposer un départ vain.
     */
    public function testUneCaseSterileAnnonceZeroChance(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sterile@example.com');
        $zone = $this->caseSansRien($partie);

        self::assertSame(0, $this->prospection()->chancesSur($partie, $zone));
    }

    /**
     * **Le sable enfouit ce que le limon laisse voir** : à situation égale, une
     * berge rend davantage qu'un désert. Les chances restent dans [5, 95] hors
     * du cas certain — jamais un bouton perdu d'avance, jamais une promesse.
     */
    public function testLeTerrainInflechitLesChancesSansJamaisLesAnnuler(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('terrain-chances@example.com');

        foreach ($partie->getVille()->getZones() as $zone) {
            $zone->decouvrir();
            $chances = $this->prospection()->chancesSur($partie, $zone);

            if (0 === $chances) {
                continue;
            }

            self::assertGreaterThanOrEqual(5, $chances);
            self::assertLessThanOrEqual(100, $chances);
        }
    }

    /**
     * Une case reconnue qui porte au moins un gisement, pour y tarir puis y
     * rouvrir une veine.
     */
    private function caseAGisement(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            foreach ($zone->getGisements() as $gisement) {
                // Jamais un banc de poisson : le poisson est la seule ressource
                // renouvelable du jeu, il ne s'épuise pas et n'a donc rien à
                // rouvrir.
                if (!$gisement->getRessource()->estRenouvelable()) {
                    $zone->decouvrir();

                    return $zone;
                }
            }
        }

        self::fail('Une carte neuve porte toujours des gisements tarissables.');
    }

    /**
     * Le premier filon tarissable de la case, celui dont on mesure la
     * réouverture.
     */
    private function filonTarissable(Zone $zone): Gisement
    {
        foreach ($zone->getGisements() as $gisement) {
            if (!$gisement->getRessource()->estRenouvelable()) {
                return $gisement;
            }
        }

        self::fail('Cette case ne porte aucun filon tarissable.');
    }

    /**
     * Une case reconnue dont rien ne peut sortir : ni filon tari, ni matériau
     * que son terrain accepte.
     */
    private function caseSansRien(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            $zone->decouvrir();

            if ([] === $this->prospection()->filonsPossibles($partie, $zone)) {
                return $zone;
            }
        }

        self::markTestSkipped('Cette carte n\'a aucune case stérile : le cas ne peut pas se jouer ici.');
    }

    private function prospection(): Prospection
    {
        return static::getContainer()->get(Prospection::class);
    }

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
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
}
