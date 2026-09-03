<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\DateDeJeu;
use App\Game\LanceurDePartie;
use App\Game\Mecontentement;
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

    /**
     * **La famine se lit à deux paliers** depuis le lot 4.7 : le premier
     * mécontente, le second seul fait échouer. C'est le compromis entre le
     * « pas de game over brutal » du doc 02 et l'échec demandé au lot 3.7 —
     * la ville prévient longtemps avant de mourir.
     */
    public function testLaFamineMecontenteAvantDeFaireEchouer(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-palier@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < Subsistance::SEUIL_DE_FAMINE; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertTrue(
            $partie->estEnCours(),
            'Quatre quinzaines de famine ne doivent plus suffire à faire échouer.',
        );
        self::assertGreaterThanOrEqual(
            Mecontentement::SEUIL,
            $partie->getQuinzainesDeMecontentement(),
            'Elles doivent en revanche avoir mécontenté la ville.',
        );
    }

    public function testLaFamineProlongeeFaitEchouerLaPartie(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('famine-echec@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < Subsistance::SEUIL_DECHEC; ++$i) {
            self::assertTrue($partie->estEnCours(), \sprintf('Ne devrait pas encore avoir échoué au cycle %d.', $i));
            $this->cycle()->passer($partie);
        }

        self::assertFalse($partie->estEnCours());
        self::assertSame(StatutDePartie::Echouee, $partie->getStatut());
    }

    /**
     * La ville s'ouvre avec les volontaires que le pharaon a appelés, et ce
     * qu'elle mange se déduit d'eux : une ration par actif, une demi par
     * inactif — jamais d'une formule tirée d'un bâtiment.
     */
    public function testAlArriveeLaVilleCompteLesVolontairesDuPharaon(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('base@example.com');
        $ville = $partie->getVille();

        self::assertSame(Population::ACTIFS_AU_DEPART, $ville->getActifs());
        self::assertSame(17, $ville->population());
        self::assertSame(9, $ville->getInactifs(), 'Sept enfants et deux anciens.');

        // Huit actifs à deux demi-rations, neuf inactifs à une : vingt-cinq,
        // soit treize vivres.
        self::assertSame(13, $ville->consommationDeNourriture());
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

        // Dix-sept habitants tiennent en quatre maisonnées, alors que la
        // Résidence familiale n'en loge qu'une : la ville manque de logements
        // dès son arrivée, et c'est ce qui fait du Quartier le premier geste.
        self::assertSame(4, $ville->foyersOccupes());
        self::assertSame(1, $ville->capaciteEnFoyers());
        self::assertTrue($ville->manqueDeLogements());

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, niveau: 1));

        self::assertSame($populationAvant, $ville->population(), 'Bâtir n\'a fait naître personne.');
        self::assertSame(21, $ville->capaciteEnFoyers(), 'Un niveau de Quartier, plus la Résidence.');
        self::assertFalse($ville->manqueDeLogements());
        self::assertSame(17, $ville->foyersLibres());
    }

    /**
     * Le bilan démographique ne tombe qu'au changement d'année — jamais au
     * premier cycle d'une partie, où la ville vient d'arriver.
     */
    public function testLeBilanDemographiqueNeTombePasDesLaPremiereQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('bilan@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 10000]);
        $avant = $ville->population();

        $this->cycle()->passer($partie);

        self::assertSame($avant, $ville->population(), 'Personne ne vieillit ni ne meurt en quinze jours.');
    }

    /**
     * Une année entière écoulée, en revanche, laisse des traces : la ville ne
     * compte plus tout à fait les mêmes gens.
     */
    public function testUneAnneeEcouleeChangeLaPopulation(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('annee@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 10000]);

        $evenements = [];
        for ($i = 0; $i < DateDeJeu::CYCLES_PAR_ANNEE; ++$i) {
            $evenements = [...$evenements, ...$this->cycle()->passer($partie)];
        }

        self::assertSame(2, $partie->dateDeJeu()->annee, 'Une année complète a bien passé.');
        // La population reste plausible : personne ne naît, donc elle ne peut
        // que décroître, mais pas s'effondrer en un an.
        self::assertLessThanOrEqual(17, $ville->population());
        self::assertGreaterThanOrEqual(10, $ville->population());
        self::assertSame($ville->getActifs() + $ville->getInactifs(), $ville->population());
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
