<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Atelier;
use App\Game\Candidat;
use App\Game\FabricationImpossible;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Recette;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AtelierTest extends KernelTestCase
{
    /**
     * Les matières sont **payées à l'engagement**, comme un chantier. Sans
     * cela, un joueur lancerait dix ordres avec les ressources d'un seul et
     * verrait lesquels aboutissent.
     */
    public function testLesMatieresSontDebiteesAuLancement(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('engagement@example.com');
        $ville = $partie->getVille();

        $argileAvant = $ville->quantite(Ressource::Argile);
        $boisAvant = $ville->quantite(Ressource::BoisLocal);

        $this->atelier()->lancer($partie, Recette::Poterie, lots: 1);

        self::assertSame($argileAvant - 8, $ville->quantite(Ressource::Argile));
        self::assertSame($boisAvant - 3, $ville->quantite(Ressource::BoisLocal));
    }

    /**
     * **Rien ne rentre avant l'achèvement** — la règle des champs.
     */
    public function testLesPiecesNArriventQuALAchevement(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('achevement@example.com');
        $ville = $partie->getVille();

        $ordre = $this->atelier()->lancer($partie, Recette::Poterie, lots: 2);
        $attendues = $ordre->piecesAttendues();

        self::assertSame(0, $ville->quantite(Ressource::Poterie), 'Rien n\'est livré au lancement.');

        for ($i = 0; $i < 20 && null !== $ville->ordreDeFabricationEnCours(); ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame($attendues, $ville->quantite(Ressource::Poterie));
        self::assertNull($ville->ordreDeFabricationEnCours(), 'L\'ordre achevé libère l\'Atelier.');
    }

    /**
     * **Un seul ouvrage à la fois** : c'est ce qui donne son coût
     * d'opportunité à la fabrication — tisser, c'est ne pas cuire.
     */
    public function testLAtelierNeMenePasDeuxOuvragesDeFront(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('un-seul@example.com');
        $this->atelier()->lancer($partie, Recette::Poterie, lots: 1);

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà un ouvrage/');

        $this->atelier()->lancer($partie, Recette::Pain, lots: 1);
    }

    /**
     * Le niveau ouvre les recettes (doc 01) : les tissus demandent un Atelier
     * de niveau 4, la poterie se fait dès le premier.
     */
    public function testLeNiveauDeLAtelierOuvreLesRecettes(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('niveaux@example.com', niveau: 1);

        $ouvertes = array_map(
            static fn (array $offre): Recette => $offre['recette'],
            $this->atelier()->offrePour($partie),
        );

        self::assertContains(Recette::Poterie, $ouvertes);
        self::assertNotContains(Recette::Tissus, $ouvertes);

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/niveau 4/');

        $this->atelier()->lancer($partie, Recette::Tissus, lots: 1);
    }

    /**
     * Le niveau élargit aussi la taille d'un ordre : un grand atelier
     * travaille par plus gros lots.
     */
    public function testLeNiveauDeLAtelierElargitLaTailleDesOrdres(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('lots@example.com', niveau: 1);

        self::assertSame(Atelier::LOTS_PAR_NIVEAU, Atelier::lotsMaximum(1));
        self::assertGreaterThan(Atelier::lotsMaximum(1), Atelier::lotsMaximum(4));

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/pas plus de/');

        $this->atelier()->lancer($partie, Recette::Poterie, lots: Atelier::lotsMaximum(1) + 1);
    }

    /**
     * **Les bras décident du rythme** (`EffetDeChef`) : un Atelier désert
     * tourne au plancher de 50 % et met donc deux fois plus longtemps, sans
     * jamais s'arrêter — « rien ne s'éteint faute d'employés ».
     */
    public function testUnAtelierDesertMetDeuxFoisPlusDeTemps(): void
    {
        self::bootKernel();

        $desert = $this->quinzainesPourUnLot('atelier-desert@example.com', avecChef: false);
        $tenu = $this->quinzainesPourUnLot('atelier-tenu@example.com', avecChef: true);

        self::assertGreaterThan($tenu, $desert, 'Un atelier sans personne doit être plus lent.');
        self::assertGreaterThan(0, $tenu, 'Et il ne s\'arrête jamais tout à fait.');
    }

    /**
     * **L'arbitrage central du lot** : transformer doit rapporter davantage
     * que vendre la matière brute. Sinon personne ne fabriquerait, et l'Atelier
     * ne serait qu'un bâtiment de plus à payer.
     */
    public function testTransformerRapportePlusQueVendreLaMatiereBrute(): void
    {
        foreach (Recette::cases() as $recette) {
            $brut = 0;

            foreach ($recette->ingredientsDunLot() as $valeur => $quantite) {
                $brut += (PrixDuMarche::pour(Ressource::from($valeur)) ?? 0) * $quantite;
            }

            $transforme = (PrixDuMarche::pour($recette->produit()) ?? 0) * $recette->piecesDunLot();

            self::assertGreaterThan(
                $brut,
                $transforme,
                \sprintf('%s : mieux vaudrait vendre la matière.', $recette->libelle()),
            );
        }
    }

    /**
     * Le pain sert de matière à la bière — des pains d'orge à peine cuits,
     * émiettés et mis à fermenter. C'est la seule recette qui en consomme une
     * autre, et l'ordre des niveaux le permet toujours.
     */
    public function testLaBiereSeFaitAvecDuPainEtLeNiveauLePermet(): void
    {
        self::assertArrayHasKey(Ressource::Pain->value, Recette::Biere->ingredientsDunLot());
        self::assertLessThanOrEqual(
            Recette::Biere->niveauRequis(),
            Recette::Pain->niveauRequis(),
            'Le pain doit être ouvert avant la bière qui en consomme.',
        );
    }

    private function quinzainesPourUnLot(string $email, bool $avecChef): int
    {
        $partie = $this->villeAvecAtelier($email);
        $ville = $partie->getVille();

        if ($avecChef) {
            $ville->ajouterEmploye(new Employee(
                $ville,
                TypeDeBatiment::Atelier,
                new Candidat(
                    competence: 90,
                    salaire: 8,
                    ancienneteProbable: 20,
                    traits: [],
                    specialite: null,
                    actifsAmenes: 0,
                    inactifsAmenes: 0,
                ),
                $partie->getCycle(),
            ));
        }

        $this->atelier()->lancer($partie, Recette::Poterie, lots: 3);

        $quinzaines = 0;
        while (null !== $ville->ordreDeFabricationEnCours() && $quinzaines < 40) {
            $this->cycle()->passer($partie);
            ++$quinzaines;
        }

        return $quinzaines;
    }

    private function villeAvecAtelier(string $email, int $niveau = 4): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Atelier, $niveau));
        // Le mode d'essai lève les plafonds : ces tests portent sur l'Atelier,
        // pas sur les réserves.
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([
            Ressource::Deben->value => 10_000,
            Ressource::Argile->value => 500,
            Ressource::BoisLocal->value => 500,
            Ressource::Ble->value => 500,
            Ressource::Orge->value => 500,
            Ressource::Roseaux->value => 500,
            Ressource::Lin->value => 500,
        ]);

        return $partie;
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

    private function atelier(): Atelier
    {
        return static::getContainer()->get(Atelier::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
