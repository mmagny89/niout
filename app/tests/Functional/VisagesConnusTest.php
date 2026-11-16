<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Lignee;
use App\Entity\Npc;
use App\Entity\User;
use App\Game\BancDEssaiDuMonde;
use App\Game\ChefsVoyageurs;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use App\Game\RoleDePnj;
use App\Game\TraitDeCandidat;
use App\Game\Visages;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Une personne rencontrée peut se reconnaître d'une partie à l'autre : douze visages au plus, un par partie,
 * aucune rancune (`docs/plan-pnj-palier-b.md` § 7, § 9).
 */
final class VisagesConnusTest extends KernelTestCase
{
    public function testLaLigneeGardeDouzeVisagesEtLePlusAncienCede(): void
    {
        $lignee = new Lignee(new User());

        for ($i = 1; $i <= 20; ++$i) {
            $lignee->retenirUnVisage($i, 'rival', 'Hori le marchand '.$i, 'econome', 1);
        }

        $visages = $lignee->getVisagesConnus();
        self::assertCount(Lignee::VISAGES_MAX, $visages);
        self::assertSame(20, $visages[0]['identite'], 'Le plus récent d\'abord.');
        self::assertSame(9, $visages[11]['identite'], 'Les huit premiers se sont effacés.');
    }

    public function testRevoirUnVisageLeRafraichitSansLeDupliquer(): void
    {
        $lignee = new Lignee(new User());
        $lignee->retenirUnVisage(1, 'rival', 'Hori le marchand', 'econome', 1);
        $lignee->retenirUnVisage(2, 'rival', 'Panehsy le marchand', 'ambitieux', 0);
        $lignee->retenirUnVisage(1, 'rival', 'Hori le marchand', 'econome', 2);

        $visages = $lignee->getVisagesConnus();

        self::assertCount(2, $visages);
        self::assertSame(1, $visages[0]['identite']);
        self::assertSame(2, $visages[0]['accords']);
    }

    public function testLesAccordsRetenusSontPlafonnesAucuneRancune(): void
    {
        $lignee = new Lignee(new User());
        $lignee->retenirUnVisage(1, 'rival', 'Hori le marchand', 'econome', 99);

        self::assertSame(3, $lignee->getVisagesConnus()[0]['accords']);
    }

    public function testUneNouvellePartieSemeUnVisageConnuEtPasDavantage(): void
    {
        self::bootKernel();
        $joueur = $this->joueur('visages-semis@example.com');
        $lignee = static::getContainer()->get(Lignees::class)->pour($joueur);
        $lignee->retenirUnVisage(111, 'rival', 'Hori le marchand', 'econome', 2);
        $lignee->retenirUnVisage(222, 'rival', 'Panehsy le marchand', 'ambitieux', 1);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
        $semes = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findBy(['partie' => $partie]);

        self::assertCount(1, $semes, 'Un seul visage figure, jamais plus.');
        self::assertContains($semes[0]->getIdentite(), [111, 222]);
        self::assertFalse($semes[0]->peutParaitre(0), 'Il n\'attend pas la famille à la porte.');
        self::assertTrue($semes[0]->peutParaitre(Visages::DELAI_AVANT_DE_PARAITRE));
        self::assertSame('reconnu', $semes[0]->getHistoire()[0]['t']);
    }

    public function testSansVisageConnuRienNeFigure(): void
    {
        self::bootKernel();
        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->joueur('visages-aucun@example.com'), 'Nakht');

        self::assertSame([], static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findBy(['partie' => $partie]));
    }

    public function testUnJalonRetientLesRivauxEtLesChefsPartisEtPasLeRestePuisIlsFigurentAilleurs(): void
    {
        self::bootKernel();
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $joueur = $this->joueur('visages-jalon@example.com');
        $premiere = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');

        $rival = new Npc($premiere, 4242, RoleDePnj::Rival, 'Hori le marchand', TraitDeCandidat::Econome);
        $rival->etrePaye(3, 12);
        $voyageur = new Npc($premiere, 5151, RoleDePnj::ChefVoyageur, 'Ouserhat', TraitDeCandidat::Fidele);
        $voyageur->definirLeProfil(['candidat' => ['competence' => 60, 'salaire' => 8, 'anciennete' => 20, 'traits' => [], 'specialite' => null, 'actifs' => 2, 'inactifs' => 1, 'prenom' => 'Ouserhat'], 'batiment' => 'entrepot', 'destination' => 'canaan', 'depart' => 4, 'gagne' => 10, 'revenu' => false]);
        $courtisan = new Npc($premiere, 6060, RoleDePnj::Courtisan, 'Le vizir', TraitDeCandidat::Ambitieux);
        $bande = new Npc($premiere, 7070, RoleDePnj::ChefDeBande, 'Bak le chef de bande', TraitDeCandidat::Bagarreur);

        foreach ([$rival, $voyageur, $courtisan, $bande] as $npc) {
            $gestionnaire->persist($npc);
        }

        $gestionnaire->flush();

        static::getContainer()->get(Lignees::class)->encaisser($premiere);
        $gestionnaire->flush();

        $visages = static::getContainer()->get(Lignees::class)->pour($joueur)->getVisagesConnus();
        $identites = array_column($visages, 'identite');
        sort($identites);

        self::assertSame([4242, 5151], $identites, 'Ni un courtisan ni un chef de bande ne survivent à leur partie.');

        // Une partie neuve : le chef parti, seul visage possible dans ce test, arrive avec sa candidature.
        $lignee = static::getContainer()->get(Lignees::class)->pour($joueur);
        $seconde = static::getContainer()->get(LanceurDePartie::class)->lancerAventure($joueur, 'Nakht', 1, 8);
        $figure = static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findOneBy(['partie' => $seconde]);

        self::assertNotNull($figure);
        self::assertContains($figure->getIdentite(), [4242, 5151]);

        if (RoleDePnj::ChefVoyageur === $figure->getRole()) {
            self::assertSame('entrepot', $figure->getProfil()['batiment']);
            self::assertSame(0, $figure->getProfil()['gagne'], 'Ce qu\'il avait gagné ailleurs ne se transmet pas.');
            self::assertFalse($figure->getProfil()['revenu']);
            self::assertLessThanOrEqual(-ChefsVoyageurs::DELAI_DE_RETOUR, $figure->getProfil()['depart']);
        } else {
            self::assertSame(1, $figure->getAccords());
        }

        self::assertCount(2, $lignee->getVisagesConnus());
    }

    public function testLeBancDEssaiDesRivauxVarieSansJamaisChoisirLaPireRoute(): void
    {
        $rapport = static::getContainer()->get(BancDEssaiDuMonde::class)->mesurerLesRivaux(500);

        self::assertSame(0, $rapport['pireChoisie']);
        self::assertSame(0, $rapport['instable'], 'La décision ne dépend pas de l\'ordre des routes.');
        // L'économe préfère le marché riche, l'ambitieux le commerce vivant — en majorité, pas en totalité.
        self::assertGreaterThan($rapport['parCaractere']['econome']['commerce-vivant'] ?? 0, $rapport['parCaractere']['econome']['marche-riche']);
        self::assertGreaterThan($rapport['parCaractere']['ambitieux']['marche-riche'] ?? 0, $rapport['parCaractere']['ambitieux']['commerce-vivant']);
    }

    private function joueur(string $email): User
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
