<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EtatDePartenaire;
use App\Entity\GameSave;
use App\Entity\Lignee;
use App\Entity\User;
use App\Game\Commerce;
use App\Game\EtatsDesCites;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Saturation et relation : chaque mesure qui doit bouger bouge, dans les deux sens
 * (`docs/plan-pnj-palier-a.md` § 10).
 */
final class CitesEtSaturationTest extends KernelTestCase
{
    public function testLIdentiteDeCiteSurvitAuxMissions(): void
    {
        self::bootKernel();
        $commerce = static::getContainer()->get(Commerce::class);
        $partie = $this->lancerPartie('identite@example.com');

        foreach ($commerce->partenairesDe($partie) as $partenaire) {
            self::assertStringStartsNotWith('-', $partenaire->cite());
            self::assertStringEndsNotWith('-levant', $partenaire->cite());
        }
    }

    public function testUnRetourFaitMonterLaSaturationEtLaRelationPuisLesErode(): void
    {
        $etat = new EtatDePartenaire($this->lancerPartieDeBase(), 'byblos', 0);

        $etat->enregistrerUnRetour(Ressource::Lin);
        self::assertSame(EtatDePartenaire::SATURATION_PAR_CONVOI, $etat->saturationDe(Ressource::Lin));
        self::assertSame(EtatDePartenaire::GAIN_PAR_CONVOI, $etat->getRelation());

        // Un tiers par quinzaine, arrondi au-dessus : elle finit par s'éteindre.
        $etat->laisserPasserUneQuinzaine();
        self::assertSame(2, $etat->saturationDe(Ressource::Lin));
        self::assertSame(1, $etat->getRelation());

        $etat->laisserPasserUneQuinzaine();
        $etat->laisserPasserUneQuinzaine();
        self::assertSame(0, $etat->saturationDe(Ressource::Lin));
        self::assertSame(0, $etat->getRelation(), 'La relation retombe, mais pas sous son plancher.');
    }

    public function testLaRelationNeDescendJamaisSousLAcquisDeLaLignee(): void
    {
        $etat = new EtatDePartenaire($this->lancerPartieDeBase(), 'byblos', 30);

        self::assertSame(30, $etat->getRelation());

        for ($i = 0; $i < 20; ++$i) {
            $etat->laisserPasserUneQuinzaine();
        }

        self::assertSame(30, $etat->getRelation());
    }

    public function testLaSaturationEtLaRelationSontBornees(): void
    {
        $etat = new EtatDePartenaire($this->lancerPartieDeBase(), 'byblos', 0);

        for ($i = 0; $i < 100; ++$i) {
            $etat->enregistrerUnRetour(Ressource::Lin);
        }

        self::assertSame(EtatDePartenaire::SATURATION_MAX, $etat->saturationDe(Ressource::Lin));
        self::assertSame(EtatDePartenaire::RELATION_MAX, $etat->getRelation());
    }

    public function testLaLigneeRetientLaRelationAuPlusHautSansDepasserLePlafond(): void
    {
        $lignee = new Lignee(new User());

        $lignee->releverLaRelation('byblos', 80);
        self::assertSame(Lignee::RELATION_ACQUISE_MAX, $lignee->relationAcquise('byblos'));

        $lignee->releverLaRelation('byblos', 10);
        self::assertSame(Lignee::RELATION_ACQUISE_MAX, $lignee->relationAcquise('byblos'), 'Un acquis ne descend pas.');
        self::assertSame(0, $lignee->relationAcquise('pount'));
    }

    public function testUneNouvellePartieDemarreALAcquisDeLaLignee(): void
    {
        self::bootKernel();
        $commerce = static::getContainer()->get(Commerce::class);
        $partie = $this->lancerPartie('acquis@example.com');
        $partenaire = $commerce->partenairesDe($partie)[0];

        static::getContainer()->get(Lignees::class)->pour($partie->getJoueur())->releverLaRelation($partenaire->cite(), 40);

        $etat = static::getContainer()->get(EtatsDesCites::class)->obtenir($partie, $partenaire);

        self::assertSame(40, $etat->getRelation());
        self::assertSame(40, $etat->getPlancher());
    }

    public function testLaSaturationRogneLAvantageEtLaRelationLeRelevePuisToutRetombe(): void
    {
        self::bootKernel();
        $commerce = static::getContainer()->get(Commerce::class);
        $cites = static::getContainer()->get(EtatsDesCites::class);
        $partie = $this->lancerPartie('avantage@example.com');
        $partenaire = $commerce->partenairesDe($partie)[0];
        $ressource = [] !== $partenaire->achete ? $partenaire->achete[0] : $partenaire->vend[0];

        $avant = $commerce->avantageDeNegoce($partie, $ressource, $partenaire);
        $sansCite = $commerce->avantageDeNegoce($partie, $ressource);

        // Un convoi rentre : la cité en a un peu assez de cette marchandise, et connaît mieux la famille
        // (deux points de relation : moins d'un point d'avantage, la division est entière).
        $cites->obtenir($partie, $partenaire)->enregistrerUnRetour($ressource);
        $apres = $commerce->avantageDeNegoce($partie, $ressource, $partenaire);

        self::assertLessThanOrEqual($avant, $apres, 'La saturation ne peut que rogner, à relation égale.');
        self::assertSame($sansCite, $commerce->avantageDeNegoce($partie, $ressource), 'Sans cité nommée, rien ne change.');

        // Une relation élevée prolonge le carnet, sans le doubler : c'est le plus grand des deux.
        $etat = $cites->obtenir($partie, $partenaire);

        for ($i = 0; $i < 50; ++$i) {
            $etat->enregistrerUnRetour($ressource);
        }

        self::assertSame(EtatDePartenaire::RELATION_MAX, $etat->getRelation());
    }

    private function lancerPartieDeBase(): GameSave
    {
        self::bootKernel();

        return $this->lancerPartie('base'.uniqid().'@example.com');
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
