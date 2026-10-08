<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\Stockage;
use App\Game\TypeDeBatiment;
use App\Game\VueDeLaReserve;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VueDeLaReserveTest extends WebTestCase
{
    public function testLesCasesRendentLOccupationEtLaPlaceLibre(): void
    {
        self::bootKernel();
        $partie = $this->partie('reserve-cases@example.com');
        $ville = $partie->getVille();

        $vue = VueDeLaReserve::pour($ville, vivres: true);

        self::assertSame(Stockage::plafondDesVivres($ville), $vue['plafond']);
        self::assertSame($ville->getNourriture(), $vue['occupation']);
        self::assertLessThanOrEqual(VueDeLaReserve::CASES_MAX, $vue['casesEnTout']);
        self::assertLessThanOrEqual($vue['casesEnTout'], $vue['casesPleines']);
        self::assertSame(
            $vue['casesPleines'],
            array_sum(array_column($vue['groupes'], 'cases')),
            'Chaque case pleine appartient à un groupe.',
        );
    }

    public function testLesMateriauxSeRangentParFamille(): void
    {
        self::bootKernel();
        $partie = $this->partie('reserve-familles@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Argile->value => 30, Ressource::Roseaux->value => 10, Ressource::Poterie->value => 5]);

        $vue = VueDeLaReserve::pour($ville, vivres: false);
        $cles = array_column($vue['groupes'], 'cle');

        self::assertContains('materiaux', $cles);
        self::assertContains('ouvrages', $cles);
        self::assertNotContains('deben', $cles, 'La monnaie n\'occupe aucune réserve.');
    }

    public function testLeLotMinusculeGardeUneCase(): void
    {
        self::bootKernel();
        $partie = $this->partie('reserve-minuscule@example.com');
        $ville = $partie->getVille();
        $ville->debiterNourriture($ville->getNourriture());
        $ville->crediterRessources([Ressource::Ble->value => 400, Ressource::Dattes->value => 1]);

        $vue = VueDeLaReserve::pour($ville, vivres: true);

        foreach ($vue['groupes'] as $groupe) {
            self::assertGreaterThanOrEqual(1, $groupe['cases'], $groupe['libelle'].' doit rester visible.');
        }
    }

    public function testLePasGardeLaGrilleEnSoixanteCases(): void
    {
        foreach ([250, 450, 1000, 1450, 5000] as $taille) {
            $pas = VueDeLaReserve::pasPour($taille);

            self::assertLessThanOrEqual(VueDeLaReserve::CASES_MAX, (int) ceil($taille / $pas));
        }
    }

    public function testLeGrenierEtLEntrepotMontrentLeurReserve(): void
    {
        $client = static::createClient();
        $partie = $this->partie('reserve-ecran@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($partie->getJoueur());

        // Un panneau à la fois : le Grenier, puis l'Entrepôt.
        foreach (['grenier', 'entrepot'] as $lieu) {
            $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=%s', $partie->getId(), $lieu));

            self::assertResponseIsSuccessful();
            self::assertCount(1, $crawler->filter('[role="img"][aria-label^="Réserve :"]'), $lieu);
            self::assertCount(1, $crawler->filter('.reserve-libre'), 'La place libre est la partie hachurée de la jauge : '.$lieu);
        }
    }

    private function partie(string $email): \App\Entity\GameSave
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
