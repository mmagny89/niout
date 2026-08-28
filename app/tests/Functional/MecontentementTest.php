<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\DepartsNaturels;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\Mecontentement;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MecontentementTest extends KernelTestCase
{
    /**
     * L'invariant du lot : **deux causes, un seul mécanisme**. La faim et les
     * salaires impayés mènent à la même colère, et il n'y a aucune raison de
     * la compter deux fois.
     */
    public function testLaFaimEtLImpayeAlimententLeMemeCompteur(): void
    {
        self::bootKernel();

        $parLaFaim = $this->accumuler('faim@example.com', affamer: true, ruiner: false);
        $parLImpaye = $this->accumuler('impaye-colere@example.com', affamer: false, ruiner: true);

        self::assertGreaterThan(0, $parLaFaim);
        self::assertSame($parLaFaim, $parLImpaye, 'Les deux causes doivent produire le même compteur.');
    }

    /**
     * Il se résorbe d'un cran par quinzaine, comme il monte : une ville qu'on
     * affame quatre quinzaines ne se calme pas en une.
     */
    public function testLeMecontentementSApaiseAussiLentementQuIlMonte(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('apaisement@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < 4; ++$i) {
            $this->cycle()->passer($partie);
        }

        $accumule = $partie->getQuinzainesDeMecontentement();
        self::assertGreaterThanOrEqual(Mecontentement::SEUIL, $accumule);

        // Les greniers se remplissent : la colère retombe, mais pas d'un coup.
        $ville->crediterRessources([Ressource::Ble->value => 100000, Ressource::Deben->value => 100000]);
        $this->cycle()->passer($partie);

        self::assertSame($accumule - 1, $partie->getQuinzainesDeMecontentement());
    }

    /**
     * Le mécontentement pèse sur la quinzaine **suivante**, jamais sur celle
     * qui vient de le produire : une seule mauvaise quinzaine ne doit pas se
     * payer deux fois.
     */
    public function testUneVilleMecontenteProduitMoins(): void
    {
        self::bootKernel();

        $sereine = $this->extraireApresQuatreQuinzaines('sereine@example.com', affamer: false);
        $fachee = $this->extraireApresQuatreQuinzaines('fachee@example.com', affamer: true);

        self::assertGreaterThan(0, $fachee, 'Une ville fâchée produit encore, mais moins.');
        self::assertLessThan($sereine, $fachee);
    }

    /**
     * Doc 13 : la réputation d'une famille se fait aussi sur le sort de ses
     * gens. Sans cet effet, le mécontentement n'aurait aucune conséquence
     * durable une fois la disette passée.
     */
    public function testUneVilleMecontenteFaitPerdreDeLaRenommee(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('renom@example.com');
        $partie->getFamille()->ajusterRenommee(50);
        $renomAvant = $partie->getFamille()->getRenommee();

        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());

        for ($i = 0; $i < Mecontentement::QUINZAINES_PAR_POINT_DE_RENOMMEE; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertLessThan($renomAvant, $partie->getFamille()->getRenommee());
    }

    /**
     * Le doc 03 fait annoncer une ancienneté probable à chaque candidat. Sans
     * départs, cette annonce ne voulait rien dire — et « Fidèle », le trait le
     * plus vendeur du document, était décoratif.
     */
    public function testUnChefFinitParPartirDeLuiMeme(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('depart@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 100000, Ressource::Deben->value => 100000]);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, anciennete: 20);

        $populationAvecLeChef = $ville->population();

        for ($i = 0; $i < 200 && [] !== $ville->chefsDe(TypeDeBatiment::Grenier); ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame([], $ville->chefsDe(TypeDeBatiment::Grenier), 'Aucun chef ne reste en poste pour toujours.');
        self::assertLessThan(
            $populationAvecLeChef,
            $ville->population(),
            'Le foyer s\'en va avec le chef : sinon un départ laisserait de la population gratuite.',
        );
    }

    /**
     * Doc 02 : le mécontentement précipite les départs. C'est ce qui fait de
     * la disette une spirale, et non un simple ralentissement.
     */
    public function testLeMecontentementPrecipiteLesDeparts(): void
    {
        $chef = $this->unChef(anciennete: 20);

        self::assertGreaterThan(
            DepartsNaturels::chanceDeDepart($chef, precipite: false),
            DepartsNaturels::chanceDeDepart($chef, precipite: true),
        );
    }

    /**
     * L'espérance de service doit tenir l'annonce faite au joueur : un chef
     * annoncé pour vingt quinzaines ne doit pas en tenir cinq.
     */
    public function testLaChanceDeDepartTientLAnnonceFaiteAuJoueur(): void
    {
        self::assertSame(5, DepartsNaturels::chanceDeDepart($this->unChef(anciennete: 20), precipite: false));
        self::assertSame(10, DepartsNaturels::chanceDeDepart($this->unChef(anciennete: 10), precipite: false));

        // Jamais nulle : une ancienneté démesurée ne rend pas un chef immortel.
        self::assertGreaterThan(0, DepartsNaturels::chanceDeDepart($this->unChef(anciennete: 999), precipite: false));
    }

    /**
     * Mesuré plutôt que postulé : sur 300 chefs annoncés pour vingt
     * quinzaines, la durée moyenne de service doit rester du bon ordre.
     */
    public function testLEsperanceDeServiceCorrespondALAnnonce(): void
    {
        $hasard = new Randomizer(new Mt19937(1789));
        $chance = DepartsNaturels::chanceDeDepart($this->unChef(anciennete: 20), precipite: false);
        $total = 0;

        for ($essai = 0; $essai < 300; ++$essai) {
            $quinzaines = 0;

            while ($hasard->getInt(1, 100) > $chance && $quinzaines < 200) {
                ++$quinzaines;
            }

            $total += $quinzaines;
        }

        $moyenne = $total / 300;

        self::assertGreaterThan(12, $moyenne, \sprintf('Service moyen mesuré : %.1f quinzaines.', $moyenne));
        self::assertLessThan(28, $moyenne, \sprintf('Service moyen mesuré : %.1f quinzaines.', $moyenne));
    }

    private function accumuler(string $email, bool $affamer, bool $ruiner): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        if ($affamer) {
            $ville->debiterNourriture($ville->getNourriture());
        } else {
            $ville->crediterRessources([Ressource::Ble->value => 100000]);
        }

        if ($ruiner) {
            // Une carrière ouverte, mais pas un deben pour la payer.
            $zone = $this->premiereZoneHorsVille($partie);
            $zone->decouvrir();
            $zone->poserUnGisement(Ressource::Calcaire, 999);
            static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);
            $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);
        }

        for ($i = 0; $i < 4; ++$i) {
            $this->cycle()->passer($partie);
        }

        return $partie->getQuinzainesDeMecontentement();
    }

    private function extraireApresQuatreQuinzaines(string $email, bool $affamer): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Deben->value => 100000]);

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Calcaire, 99999);
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);

        if ($affamer) {
            $ville->debiterNourriture($ville->getNourriture());
        } else {
            $ville->crediterRessources([Ressource::Ble->value => 100000]);
        }

        for ($i = 0; $i < 4; ++$i) {
            $this->cycle()->passer($partie);
        }

        // La quinzaine de mesure : le mécontentement accumulé pèse dessus.
        $avant = $ville->quantite(Ressource::Calcaire);
        $this->cycle()->passer($partie);

        return $ville->quantite(Ressource::Calcaire) - $avant;
    }

    private function unChef(int $anciennete): Employee
    {
        $partie = $this->lancerPartie(\sprintf('chef-%d-%s@example.com', $anciennete, uniqid()));
        $ville = $partie->getVille();

        return new Employee(
            $ville,
            TypeDeBatiment::Grenier,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: $anciennete,
                traits: [],
                specialite: null,
                actifsAmenes: 2,
                inactifsAmenes: 1,
            ),
            $partie->getCycle(),
        );
    }

    private function installerUnChef(GameSave $partie, int $anciennete): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            TypeDeBatiment::Grenier,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: $anciennete,
                traits: [],
                specialite: null,
                actifsAmenes: 2,
                inactifsAmenes: 1,
            ),
            $partie->getCycle(),
        ));
        $ville->accueillir(2, 1, 0);
    }

    private function premiereZoneHorsVille(GameSave $partie): \App\Entity\Zone
    {
        $ville = $partie->getVille();
        $zoneDeLaVille = $ville->zoneDeLaVille();

        foreach ($ville->getZones() as $zone) {
            if ($zone !== $zoneDeLaVille) {
                return $zone;
            }
        }

        self::fail('Une carte doit avoir des cases autour de sa ville.');
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
