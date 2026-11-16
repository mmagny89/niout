<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\User;
use App\Fenetre\LeMonde;
use App\Game\Bandits;
use App\Game\ChefsDeBande;
use App\Game\Commerce;
use App\Game\CommerceImpossible;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\RoleDePnj;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le risque de pillage a un visage, et une troisième issue : le droit de passage
 * (`docs/plan-pnj-palier-b.md` § 5.3, § 9).
 */
final class BandesParlantesTest extends WebTestCase
{
    public function testLeChefDeBandeSeDeduitDeLaGraineEtDeLaCase(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-chef@example.com', 2);
        $zones = $this->gardees($partie);
        $bandes = static::getContainer()->get(ChefsDeBande::class);

        $premier = $bandes->chefDe($partie, $zones[0]);
        self::assertSame($premier, $bandes->chefDe($partie, $zones[0]), 'Même graine, même case, même chef.');
        self::assertStringEndsWith('le chef de bande', $premier['nom']);
        self::assertNotSame($premier['identite'], $bandes->chefDe($partie, $zones[1])['identite']);
    }

    public function testUnPillageSeLitAuNomDuChefEtFaitMonterSaReputation(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-reputation@example.com', 1);
        $bandes = static::getContainer()->get(ChefsDeBande::class);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);

        $nom = $bandes->attribuerUnPillage($partie, 7);
        $gestionnaire->flush();
        $bandes->attribuerUnPillage($partie, 8);
        $gestionnaire->flush();

        self::assertNotNull($nom);
        $liste = $bandes->lesBandes($partie);
        self::assertSame($nom, $liste[0]['nom']);
        self::assertSame(2, $liste[0]['inquietes'], 'Chaque pillage compte, et rien d\'autre.');

        $npc = $gestionnaire->getRepository(Npc::class)->findOneBy(['partie' => $partie, 'role' => RoleDePnj::ChefDeBande]);
        self::assertSame('pille', $npc?->getHistoire()[0]['t']);
    }

    public function testSansBandeAucunPillageNiAucunPrix(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-aucune@example.com', 0);
        $bandes = static::getContainer()->get(ChefsDeBande::class);

        self::assertNull($bandes->attribuerUnPillage($partie, 1));
        self::assertSame(0, $bandes->prixDuDroit($partie));
        $this->expectException(CommerceImpossible::class);
        $bandes->payerLeDroit($partie);
    }

    public function testLeDroitDePassageRabatLeRisqueSansLEteindre(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-droit@example.com', 2);
        $commerce = static::getContainer()->get(Commerce::class);
        $bandes = static::getContainer()->get(ChefsDeBande::class);
        $partie->getVille()->crediterRessources([Ressource::Deben->value => 5000]);

        $avant = $commerce->risqueDePillage($partie);
        $prix = $bandes->prixDuDroit($partie);
        $deben = $partie->getVille()->quantite(Ressource::Deben);
        self::assertGreaterThan(0, $avant);
        self::assertGreaterThan(0, $prix);

        $bandes->payerLeDroit($partie);

        self::assertSame($deben - $prix, $partie->getVille()->quantite(Ressource::Deben));
        $apres = $commerce->risqueDePillage($partie);
        self::assertLessThan($avant, $apres);
        self::assertGreaterThan(0, $apres, 'Il réduit le risque, il ne le supprime pas.');
        self::assertSame($avant - intdiv($avant * ChefsDeBande::REDUCTION_DU_DROIT, 100), $apres);
    }

    public function testLeDroitSeterminePuisSePaieDeNouveauEtNeSeCumulePas(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-duree@example.com', 1);
        $bandes = static::getContainer()->get(ChefsDeBande::class);
        $commerce = static::getContainer()->get(Commerce::class);
        $partie->getVille()->crediterRessources([Ressource::Deben->value => 5000]);

        $sansDroit = $commerce->risqueDePillage($partie);
        $bandes->payerLeDroit($partie);
        $deben = $partie->getVille()->quantite(Ressource::Deben);

        try {
            $bandes->payerLeDroit($partie);
            self::fail('On ne paie pas deux fois un droit qui court.');
        } catch (CommerceImpossible) {
            self::assertSame($deben, $partie->getVille()->quantite(Ressource::Deben), 'Rien n\'est débité.');
        }

        for ($i = 0; $i < ChefsDeBande::DUREE_DU_DROIT; ++$i) {
            self::assertTrue($bandes->droitActif($partie));
            $partie->avancerDUnCycle();
        }

        self::assertFalse($bandes->droitActif($partie));
        self::assertSame($sansDroit, $commerce->risqueDePillage($partie));
        $bandes->payerLeDroit($partie);
        self::assertTrue($bandes->droitActif($partie));
    }

    public function testOnNePeutPasPayerCeQuOnNaPas(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-pauvre@example.com', 1);
        $partie->getVille()->debiterRessources([Ressource::Deben->value => $partie->getVille()->quantite(Ressource::Deben)]);

        try {
            static::getContainer()->get(ChefsDeBande::class)->payerLeDroit($partie);
            self::fail('Sans deben, pas de passage.');
        } catch (CommerceImpossible $e) {
            self::assertStringContainsString('deben', $e->getMessage());
            self::assertFalse(static::getContainer()->get(ChefsDeBande::class)->droitActif($partie));
        }
    }

    public function testLeChefDeBandeEtSaReputationSeLisentDansLeMonde(): void
    {
        static::createClient();
        $partie = $this->partieAvecBandes('bande-monde@example.com', 1);
        $bandes = static::getContainer()->get(ChefsDeBande::class);
        $bandes->attribuerUnPillage($partie, 3);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $monde = static::getContainer()->get(LeMonde::class)->pour($partie);
        $fiche = $monde['personnes'][0];

        self::assertTrue($fiche['bande']);
        self::assertSame(1, $fiche['inquietes']);
        self::assertSame('pille', $monde['chronique'][0]['type']);
    }

    public function testLEntrepotMontreLeRisqueAvantDEngager(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecBandes('bande-ecran@example.com', 2);
        $client->loginUser($partie->getJoueur());
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 2));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=entrepot', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Les bandes de la région');
        self::assertSelectorTextContains('body', 'd\'être pillé');
        self::assertSelectorTextContains('body', 'Acheter le passage');
        self::assertSelectorTextContains('body', 'le chef de bande');
    }

    private function partieAvecBandes(string $email, int $combien): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');

        // On part d'une carte sans bande, puis on en pose le nombre voulu : le test ne dépend pas de la difficulté.
        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->estGardee()) {
                $zone->pacifier();
            }
        }

        $poses = 0;

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($poses >= $combien) {
                break;
            }

            if (!$zone->porteLaVille() && !$zone->estGardee()) {
                $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);
                ++$poses;
            }
        }

        $gestionnaire->flush();

        return $partie;
    }

    /**
     * @return list<\App\Entity\Zone>
     */
    private function gardees(GameSave $partie): array
    {
        $liste = [];

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->estGardee()) {
                $liste[] = $zone;
            }
        }

        return $liste;
    }
}
