<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AppelDHabitants;
use App\Game\AppelImpossible;
use App\Game\LanceurDePartie;
use App\Game\PalierDeRenommee;
use App\Game\Population;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AppelDHabitantsTest extends KernelTestCase
{
    /**
     * Le verrou qui donne sa raison d'être au Quartier d'habitation : les dix
     * volontaires du pharaon remplissent déjà la Résidence familiale, et rien
     * ne peut venir de plus avant qu'on ne bâtisse.
     */
    public function testOnNAppellePersonneSansLogement(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-logement@example.com');
        $partie->getVille()->crediterRessources([Ressource::Deben->value => 1000]);

        self::assertTrue($partie->getVille()->manqueDeLogements());

        $this->expectException(AppelImpossible::class);
        $this->expectExceptionMessageMatches('/Quartier d\'habitation/');

        $this->appels()->appeler($partie);
    }

    /**
     * L'autre verrou : la bourse. Le refus ne doit rien changer à la ville —
     * ni deben débités, ni habitants arrivés.
     */
    public function testUnAppelRefuseFauteDeDebenNeChangeRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-le-sou@example.com');
        $this->loger($partie);
        $ville = $partie->getVille();

        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);
        $population = $ville->population();

        try {
            $this->appels()->appeler($partie);
            self::fail('Un appel sans deben doit être refusé.');
        } catch (AppelImpossible) {
        }

        self::assertSame(0, $ville->getDeben());
        self::assertSame($population, $ville->population());
    }

    public function testUnAppelReussiCouteLePrixDuPalierEtAmeneDuMonde(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('appel@example.com');
        $this->loger($partie);
        $ville = $partie->getVille();

        $ville->crediterRessources([Ressource::Deben->value => 500]);
        $debenAvant = $ville->getDeben();
        $actifsAvant = $ville->getActifs();
        $cout = $this->appels()->cout($partie);

        $maisonnee = $this->appels()->appeler($partie);

        self::assertSame(PalierDeRenommee::Inconnue->coutDAppel(), $cout, 'La partie démarre sans renommée.');
        self::assertSame($debenAvant - $cout, $ville->getDeben());
        self::assertSame($actifsAvant + $maisonnee['actifs'], $ville->getActifs());
        self::assertGreaterThan(0, $maisonnee['actifs'], 'Une maisonnée amène toujours des bras.');
    }

    /**
     * Se faire un nom doit se payer en retour : le prix d'un appel suit le
     * palier de la famille, pas une constante.
     */
    public function testLaRenommeeFaitBaisserLePrixDeLAppel(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('renommee@example.com');
        $inconnue = $this->appels()->cout($partie);

        $partie->getFamille()->ajusterRenommee(100);

        self::assertLessThan($inconnue, $this->appels()->cout($partie));
    }

    /**
     * Assez de Quartier pour que la ville ait de la place — c'est la seule
     * façon d'atteindre les autres règles.
     */
    private function loger(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));

        self::assertFalse(
            $ville->manqueDeLogements(),
            \sprintf(
                'Un Quartier de niveau 1 doit loger la troupe de départ (%d foyers pour %d habitants).',
                $ville->capaciteEnFoyers(),
                Population::ACTIFS_AU_DEPART + Population::ENFANTS_AU_DEPART + Population::ANCIENS_AU_DEPART,
            ),
        );
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

    private function appels(): AppelDHabitants
    {
        return static::getContainer()->get(AppelDHabitants::class);
    }
}
