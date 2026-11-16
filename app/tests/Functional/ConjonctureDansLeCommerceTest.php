<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AvantageDeNegoce;
use App\Game\Commerce;
use App\Game\ConjonctureDeMarche;
use App\Game\LanceurDePartie;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le monde entre dans l'avantage de négoce comme une source de plus, signée, sous le plafond
 * commun (`docs/plan-pnj-palier-a.md` § 3).
 */
final class ConjonctureDansLeCommerceTest extends KernelTestCase
{
    public function testLaPartiePorteUneGraineStable(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('graine@example.com');

        self::assertGreaterThan(0, $partie->getGraine());
        self::assertSame($partie->getGraine(), $partie->getGraine());
    }

    public function testLeMondeSAjouteALAvantageEtBougeDUneQuinzaineALAutre(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('monde@example.com');
        $commerce = static::getContainer()->get(Commerce::class);
        $conjoncture = static::getContainer()->get(ConjonctureDeMarche::class);

        $partenaire = $commerce->partenairesDe($partie)[0];
        $ressource = [] !== $partenaire->achete ? $partenaire->achete[0] : $partenaire->vend[0];
        $vus = [];

        for ($i = 0; $i < 40; ++$i) {
            $monde = $conjoncture->pour($partie, $partenaire, $ressource)->points;
            $sans = $commerce->avantageDeNegoce($partie, $ressource);
            $avec = $commerce->avantageDeNegoce($partie, $ressource, $partenaire);

            // Le monde est une source de plus : jamais au-delà du plafond, jamais sous le plancher.
            self::assertGreaterThanOrEqual(AvantageDeNegoce::PLANCHER_TOTAL, $avec);
            self::assertLessThanOrEqual(AvantageDeNegoce::PLAFOND_TOTAL, $avec);
            // Sans partenaire nommé, le monde se tait ; avec lui, il s'ajoute (sous les bornes).
            self::assertSame(
                max(AvantageDeNegoce::PLANCHER_TOTAL, min(AvantageDeNegoce::PLAFOND_TOTAL, $sans + $monde)),
                $avec,
            );

            $vus[$avec] = true;
            $partie->avancerDUnCycle();
        }

        self::assertGreaterThan(1, \count($vus), 'Le monde doit changer l\'avantage d\'une quinzaine à l\'autre.');
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
