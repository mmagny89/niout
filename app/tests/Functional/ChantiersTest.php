<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\ChantierImpossible;
use App\Game\Chantiers;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ChantiersTest extends KernelTestCase
{
    public function testEngagerUnChantierDebiteLesRessources(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('debit@example.com');
        $ville = $partie->getVille();
        $debenAvant = $ville->getDeben();

        $roseauxAvant = $ville->quantite(Ressource::Roseaux);
        $argileAvant = $ville->quantite(Ressource::Argile);
        $boisAvant = $ville->quantite(Ressource::BoisLocal);

        $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);

        // Entrepôt, fondation : 15 argile, 15 roseaux, 10 bois local et
        // **aucun deben** (doc 01 révisé) — la brique crue d'un premier niveau
        // ne s'achetait pas, elle se façonnait.
        self::assertSame($debenAvant, $ville->getDeben());
        self::assertSame($roseauxAvant - 15, $ville->quantite(Ressource::Roseaux));
        self::assertSame($argileAvant - 15, $ville->quantite(Ressource::Argile));
        self::assertSame($boisAvant - 10, $ville->quantite(Ressource::BoisLocal));
        self::assertCount(1, $ville->getChantiers());
    }

    public function testUnChantierNeDonnePasImmediatementLeBatiment(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('patience@example.com');

        $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);

        self::assertFalse(
            $partie->getVille()->possede(TypeDeBatiment::Entrepot),
            'Construire n\'est jamais immédiat.',
        );
    }

    public function testLeBatimentApparaitQuandLesCyclesSontPasses(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('achevement@example.com');
        $chantier = $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);
        $duree = $chantier->getDureeEnCycles();

        for ($i = 0; $i < $duree; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertTrue($partie->getVille()->possede(TypeDeBatiment::Entrepot));
        self::assertCount(0, $partie->getVille()->getChantiers(), 'Le chantier achevé disparaît.');
    }

    public function testLAchevementEstRapporteAuJoueur(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rapport@example.com');
        $chantier = $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);

        $evenements = [];
        for ($i = 0; $i < $chantier->getDureeEnCycles(); ++$i) {
            $evenements = array_merge($evenements, $this->cycle()->passer($partie));
        }

        self::assertNotEmpty($evenements);
        self::assertStringContainsString('Entrepôt', implode(' ', $evenements));
    }

    public function testChaqueCycleFaitAvancerLeTemps(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('temps@example.com');
        self::assertSame(1, $partie->getCycle());

        $this->cycle()->passer($partie);

        self::assertSame(2, $partie->getCycle());
    }

    public function testOnNeLancePasDeuxChantiersSurLeMemeBatiment(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('doublon@example.com');
        $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);

        $this->expectException(ChantierImpossible::class);
        $this->expectExceptionMessageMatches('/déjà en cours/');

        $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);
    }

    public function testUnChantierHorsDeMoyensEstRefuseSansRienDebiter(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('pauvre@example.com');
        $ville = $partie->getVille();

        // On vide les réserves d'argile : la Caserne en réclame 30.
        $ville->debiterRessources([Ressource::Argile->value => $ville->quantite(Ressource::Argile)]);
        $debenAvant = $ville->getDeben();

        try {
            $this->chantiers()->lancer($partie, TypeDeBatiment::Caserne);
            self::fail('Le chantier aurait dû être refusé.');
        } catch (ChantierImpossible) {
            self::assertSame($debenAvant, $ville->getDeben(), 'Rien ne doit être débité sur un refus.');
            self::assertCount(0, $ville->getChantiers());
        }
    }

    public function testAmeliorerUnBatimentLeMonteDUnNiveau(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('amelioration@example.com');
        $ville = $partie->getVille();

        // On s'offre les moyens, l'équilibrage n'est pas le sujet ici.
        $ville->crediterRessources([
            Ressource::Deben->value => 500,
            Ressource::Roseaux->value => 500,
            Ressource::Calcaire->value => 500,
        ]);
        $chantier = $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);
        $this->acheverLesTravaux($partie, $chantier->getDureeEnCycles());

        $entrepot = $ville->batimentDeType(TypeDeBatiment::Entrepot);
        self::assertInstanceOf(Building::class, $entrepot);
        self::assertSame(1, $entrepot->getNiveau());

        $montee = $this->chantiers()->lancer($partie, TypeDeBatiment::Entrepot);
        self::assertSame(2, $montee->getNiveauVise());
        $this->acheverLesTravaux($partie, $montee->getDureeEnCycles());

        self::assertSame(2, $entrepot->getNiveau());
    }

    private function acheverLesTravaux(GameSave $partie, int $cycles): void
    {
        for ($i = 0; $i < $cycles; ++$i) {
            $this->cycle()->passer($partie);
        }
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

    private function chantiers(): Chantiers
    {
        return static::getContainer()->get(Chantiers::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
