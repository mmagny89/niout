<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\User;
use App\Fenetre\LeMonde;
use App\Game\Candidat;
use App\Game\ChefsVoyageurs;
use App\Game\Commerce;
use App\Game\DepartsNaturels;
use App\Game\EtatsDesCites;
use App\Game\GenerateurDeCandidat;
use App\Game\LanceurDePartie;
use App\Game\Mecontentement;
use App\Game\Recrutements;
use App\Game\RoleDePnj;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Un chef qui part va quelque part, y fait quelque chose, et peut revenir
 * (`docs/plan-pnj-palier-b.md` § 5.2, § 9).
 */
final class ChefsVoyageursTest extends KernelTestCase
{
    public function testUnChefQuiPartRejointUneCiteEtDeviantUnePersonne(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-part@example.com');
        $chef = $this->chef($partie, SpecialiteDeChef::EntrepotNegociateur, 'Ouserhat');
        $population = $partie->getVille()->population();

        $messages = $this->departs()->avancerDUnCycle($partie);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        self::assertCount(1, $messages);
        self::assertStringContainsString('Ouserhat', $messages[0]);
        self::assertStringContainsString('Il rejoint', $messages[0]);
        self::assertFalse($partie->getVille()->getEmployes()->contains($chef));
        self::assertLessThan($population, $partie->getVille()->population(), 'Le foyer part avec lui.');

        $voyageur = $this->voyageurs($partie)[0];
        self::assertSame('Ouserhat', $voyageur->getNom());
        self::assertSame('part', $voyageur->getHistoire()[0]['t']);
        self::assertNotNull(static::getContainer()->get(Commerce::class)->partenaireDe($partie, (string) $voyageur->getProfil()['destination']));
    }

    public function testLaDestinationSeDeduitDeLaGraine(): void
    {
        self::bootKernel();
        $a = $this->partie('voyageur-graine-a@example.com');
        $chef = $this->chef($a, SpecialiteDeChef::EntrepotNegociateur, 'Hori');

        // Deux chefs identiques partis la même quinzaine de la même partie vont au même endroit.
        $premier = static::getContainer()->get(ChefsVoyageurs::class)->emmener($a, $chef);
        $second = static::getContainer()->get(ChefsVoyageurs::class)->emmener($a, $chef);

        self::assertSame($premier?->cle, $second?->cle);
    }

    public function testUnNegociateurPartiEleveLaRelationDeSaCiteAvecUnPlafond(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-negociateur@example.com');
        $this->chef($partie, SpecialiteDeChef::EntrepotNegociateur, 'Nakht');
        $this->departs()->avancerDUnCycle($partie);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();

        $voyageurs = static::getContainer()->get(ChefsVoyageurs::class);
        $cite = static::getContainer()->get(Commerce::class)->partenaireDe($partie, (string) $this->voyageurs($partie)[0]->getProfil()['destination']);
        self::assertNotNull($cite);
        $etat = static::getContainer()->get(EtatsDesCites::class);
        $avant = $etat->obtenir($partie, $cite)->getRelation();

        for ($i = 0; $i < 30; ++$i) {
            $voyageurs->avancerDUnCycle($partie);
        }

        // Dix points au plus, quel que soit le nombre de quinzaines.
        self::assertSame($avant + ChefsVoyageurs::RELATION_GAGNEE_MAX, $etat->obtenir($partie, $cite)->getRelation());
        self::assertSame(ChefsVoyageurs::RELATION_GAGNEE_MAX, $this->voyageurs($partie)[0]->getProfil()['gagne']);
    }

    public function testUnAutreChefNeChangeRienALaRelation(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-autre@example.com');
        $this->chef($partie, SpecialiteDeChef::EntrepotLogisticien, 'Hori');
        $this->departs()->avancerDUnCycle($partie);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $cite = static::getContainer()->get(Commerce::class)->partenaireDe($partie, (string) $this->voyageurs($partie)[0]->getProfil()['destination']);
        self::assertNotNull($cite);

        for ($i = 0; $i < 10; ++$i) {
            static::getContainer()->get(ChefsVoyageurs::class)->avancerDUnCycle($partie);
        }

        self::assertSame(0, static::getContainer()->get(EtatsDesCites::class)->obtenir($partie, $cite)->getRelation());
    }

    public function testIlRevientCommeCandidatPlusAguerriAvecSonPrenomEtUneSeuleFois(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-retour@example.com');
        $chef = $this->chef($partie, SpecialiteDeChef::EntrepotNegociateur, 'Ouserhat');
        $competence = $chef->getCompetence();
        $this->departs()->avancerDUnCycle($partie);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();

        $voyageurs = static::getContainer()->get(ChefsVoyageurs::class);
        $tires = [new Candidat(50, 5, 20, [], null, 2, 1, null)];

        // Trop tôt : il n'a rien vu qui le change.
        self::assertSame($tires, $voyageurs->auRetour($partie, TypeDeBatiment::Entrepot, $tires));

        $revenu = null;

        for ($i = 0; $i < 200 && null === $revenu; ++$i) {
            $partie->avancerDUnCycle();
            $liste = $voyageurs->auRetour($partie, TypeDeBatiment::Entrepot, $tires);
            $revenu = $liste === $tires ? null : $liste[0];
        }

        self::assertNotNull($revenu, 'Une fois sur deux par annonce : il revient.');
        self::assertSame('Ouserhat', $revenu->prenom);
        self::assertSame(min(GenerateurDeCandidat::COMPETENCE_MAX, $competence + ChefsVoyageurs::COMPETENCE_RAPPORTEE), $revenu->competence);
        self::assertSame(SpecialiteDeChef::EntrepotNegociateur, $revenu->specialite);

        // Une seule fois.
        for ($i = 0; $i < 50; ++$i) {
            $partie->avancerDUnCycle();
            self::assertSame($tires, $voyageurs->auRetour($partie, TypeDeBatiment::Entrepot, $tires));
        }
    }

    public function testLAnnonceGardeLePrenomDuRevenantEtNeLeDonnePasAUnAutre(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-annonce@example.com');
        $this->chef($partie, SpecialiteDeChef::EntrepotNegociateur, 'Ouserhat');
        $this->departs()->avancerDUnCycle($partie);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $ville = $partie->getVille();
        $ville->basculerLeModeDivin(true);

        // Une annonce qui reçoit le revenant : tous les prénoms de l'annonce sont distincts.
        for ($i = 0; $i < 200; ++$i) {
            $partie->avancerDUnCycle();
            $offre = static::getContainer()->get(Recrutements::class)->poster($partie, TypeDeBatiment::Entrepot);
            $prenoms = [];

            foreach ([0, 1, 2] as $rang) {
                $prenoms[] = $offre->candidat($rang)?->prenom;
            }

            $prenoms = array_filter($prenoms);
            self::assertSame($prenoms, array_unique($prenoms), 'Deux candidats ne partagent pas un prénom.');

            if (\in_array('Ouserhat', $prenoms, true)) {
                return;
            }

            static::getContainer()->get(Recrutements::class)->retirer($offre);
        }

        self::fail('Le chef parti ne s\'est jamais présenté à une annonce.');
    }

    public function testLaFicheEtLaChroniqueLeMontrent(): void
    {
        self::bootKernel();
        $partie = $this->partie('voyageur-fiche@example.com');
        $this->chef($partie, SpecialiteDeChef::EntrepotNegociateur, 'Ouserhat');
        $this->departs()->avancerDUnCycle($partie);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $monde = static::getContainer()->get(LeMonde::class)->pour($partie);
        $fiche = $monde['personnes'][0];

        self::assertTrue($fiche['voyageur']);
        self::assertNotNull($fiche['destination']);
        self::assertFalse($fiche['revenu']);
        self::assertSame('part', $monde['chronique'][0]['type']);
    }

    private function departs(): DepartsNaturels
    {
        // Le chef part à coup sûr : le hasard est forcé par une ancienneté d'une quinzaine, soit 100 %.
        return new DepartsNaturels(
            static::getContainer()->get(Mecontentement::class),
            new Randomizer(new Mt19937(1)),
            static::getContainer()->get(ChefsVoyageurs::class),
        );
    }

    private function chef(GameSave $partie, SpecialiteDeChef $specialite, string $prenom): Employee
    {
        $ville = $partie->getVille();
        $chef = new Employee($ville, TypeDeBatiment::Entrepot, new Candidat(
            competence: 60,
            salaire: 8,
            ancienneteProbable: 1,
            traits: [TraitDeCandidat::Ambitieux],
            specialite: $specialite,
            actifsAmenes: 2,
            inactifsAmenes: 3,
        ), $partie->getCycle());
        $chef->donnerUnPrenom($prenom);
        $ville->ajouterEmploye($chef);
        $ville->accueillir(2, 3, 0);
        static::getContainer()->get(EntityManagerInterface::class)->persist($chef);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $chef;
    }

    /**
     * @return list<Npc>
     */
    private function voyageurs(GameSave $partie): array
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findBy(['partie' => $partie, 'role' => RoleDePnj::ChefVoyageur]);
    }

    private function partie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 2));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, 3));
        $gestionnaire->flush();

        return $partie;
    }
}
