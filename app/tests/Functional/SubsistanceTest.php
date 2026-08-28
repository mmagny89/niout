<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Population;
use App\Game\Ressource;
use App\Game\Subsistance;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SubsistanceTest extends KernelTestCase
{
    public function testUneQuinzainePayeeEntameLesVivresSelonLaPopulation(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rations@example.com');
        $ville = $partie->getVille();
        $avant = $ville->getNourriture();

        $this->cycle()->passer($partie);

        self::assertSame($avant - $ville->consommationDeNourriture(), $ville->getNourriture());
        self::assertSame(0, $partie->getQuinzainesDeFamine());
    }

    public function testSansVivresLaFamineSAccumule(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-debut@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        $this->cycle()->passer($partie);

        self::assertSame(1, $partie->getQuinzainesDeFamine());
        self::assertTrue($partie->estEnCours(), 'Un seul cycle de famine ne doit pas encore faire échouer la partie.');
    }

    public function testUnRavitaillementReinitialiseLaFamine(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-repit@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());
        $this->cycle()->passer($partie);
        self::assertSame(1, $partie->getQuinzainesDeFamine());

        $ville->crediterRessources([Ressource::Ble->value => 1000]);
        $this->cycle()->passer($partie);

        self::assertSame(0, $partie->getQuinzainesDeFamine());
    }

    public function testLaFamineProlongeeFaitEchouerLaPartie(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-echec@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < Subsistance::SEUIL_DE_FAMINE; ++$i) {
            self::assertTrue($partie->estEnCours(), \sprintf('Ne devrait pas encore avoir échoué au cycle %d.', $i));
            $this->cycle()->passer($partie);
        }

        self::assertFalse($partie->estEnCours());
        self::assertSame(StatutDePartie::Echouee, $partie->getStatut());
    }

    /**
     * La ville ne compte à l'arrivée que sa famille fondatrice : entre deux et
     * huit personnes, dont deux adultes. Ce qu'elle mange se déduit de sa
     * composition — une ration par adulte, une demi par enfant — et jamais
     * d'une formule tirée d'un bâtiment.
     */
    public function testAlArriveeSeuleLaFamilleFondatriceMange(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('base@example.com');
        $ville = $partie->getVille();

        self::assertCount(1, $ville->getFoyers(), 'Une ville neuve n\'héberge que la famille du joueur.');
        self::assertGreaterThanOrEqual(2, $ville->population());
        self::assertLessThanOrEqual(8, $ville->population());
        self::assertSame(2, $ville->brasDisponibles(), 'Les deux adultes du foyer, et personne d\'autre.');

        $foyer = $ville->getFoyers()->first();
        self::assertNotFalse($foyer);
        self::assertSame(
            Population::vivresPourDemiRations($foyer->demiRations()),
            $ville->consommationDeNourriture(),
        );
    }

    /**
     * Le Quartier d'habitation ne peuple pas la ville, il la plafonne : le
     * monter n'ajoute pas un habitant, il fait de la place.
     */
    public function testLeQuartierDHabitationPlafonneSansPeupler(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('plafond@example.com');
        $ville = $partie->getVille();
        $populationAvant = $ville->population();

        self::assertSame(1, $ville->capaciteEnFamilles(), 'La Résidence familiale loge la seule famille fondatrice.');

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, niveau: 2));

        self::assertSame($populationAvant, $ville->population(), 'Bâtir n\'a fait naître personne.');
        self::assertSame(41, $ville->capaciteEnFamilles(), 'Deux niveaux de Quartier, plus la Résidence.');
        self::assertTrue($ville->peutAccueillirUnFoyer());
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

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
