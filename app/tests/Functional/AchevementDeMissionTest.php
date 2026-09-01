<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\AchevementDeMission;
use App\Game\Dechiffrage;
use App\Game\Enquetes;
use App\Game\FilRouge;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\NatureDIndice;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\SymboleHieroglyphique;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Clore une mission (lot 8.2).
 */
final class AchevementDeMissionTest extends WebTestCase
{
    /**
     * **Le fil rouge résolu suffit** (doc 09) : la mission se termine même si
     * les objectifs chiffrés ne sont pas atteints, et la reconnaissance est
     * proportionnelle. Pas de blocage, pas de « game over ».
     */
    public function testLeFilRougeResoluCloseLaMissionMemeSansLesObjectifs(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('partielle@example.com');

        self::assertSame(StatutDePartie::EnCours, $partie->getStatut());

        $messages = $this->achevement()->verifier($partie);

        self::assertSame(StatutDePartie::Achevee, $partie->getStatut());
        self::assertNotSame([], $messages);
        self::assertLessThan(100, $partie->getScoreDeMission(), 'Aucun objectif chiffré n\'a été atteint.');
    }

    /**
     * Et la réussite pleine se reconnaît comme telle.
     */
    public function testUneMissionPleinementAccomplieVautCent(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('pleine@example.com');
        $ville = $partie->getVille();

        // Les deux objectifs de la mission 1 : du commerce, et un Entrepôt.
        $ville->compterUnEchange(10_000);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 4));

        $this->achevement()->verifier($partie);

        self::assertSame(100, $partie->getScoreDeMission());
    }

    /**
     * **Une mission accomplie ne rebascule jamais** : ni en cours, ni en
     * échec. La famine qui la rattraperait ensuite n'a plus d'objet.
     */
    public function testUneMissionAccomplieNeSEchouePlus(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('inebranlable@example.com');
        $this->achevement()->verifier($partie);

        $score = $partie->getScoreDeMission();
        $partie->echouer();

        self::assertSame(StatutDePartie::Achevee, $partie->getStatut());
        self::assertSame($score, $partie->getScoreDeMission());
    }

    /**
     * **Le score est celui du moment.** Une ville qui s'enrichirait après coup
     * ne doit pas voir sa réussite monter, et une ville qui se viderait ne
     * doit pas la voir se dégrader : la mission est finie.
     */
    public function testLeScoreNeSeRecalculePasApresCoup(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('fige@example.com');
        $this->achevement()->verifier($partie);

        $score = $partie->getScoreDeMission();

        $partie->getVille()->compterUnEchange(100_000);
        $partie->getVille()->ajouterBatiment(new Building($partie->getVille(), TypeDeBatiment::Entrepot, 5));
        $this->achevement()->verifier($partie);

        self::assertSame($score, $partie->getScoreDeMission());
    }

    /**
     * **Une partie close ne se joue plus.** Le temps ne s'y avance pas, et la
     * route qui l'avancerait doit le refuser — `PartieVoter::JOUER` s'appuie
     * sur `estEnCours()`.
     */
    public function testUnePartieAcheveeNeSeJouePlus(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('close@example.com');
        $this->achevement()->verifier($partie);

        self::assertFalse($partie->estEnCours());
        self::assertTrue($partie->getStatut()->estClose());
    }

    /**
     * Le cycle appelle bien l'achèvement : sans ce branchement, une mission
     * résolue resterait ouverte indéfiniment.
     */
    public function testLeCyclePasseParLAchevement(): void
    {
        self::bootKernel();
        $partie = $this->partieAuBoutDuFilRouge('cycle-achevement@example.com');

        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        self::assertSame(StatutDePartie::Achevee, $partie->getStatut());
    }

    /**
     * Tant que le fil rouge n'est pas résolu, rien ne se clôt.
     */
    public function testTantQueLeFilRougeCourtRienNeSeClot(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('en-cours@example.com');

        self::assertSame([], $this->achevement()->verifier($partie));
        self::assertSame(StatutDePartie::EnCours, $partie->getStatut());
    }

    /**
     * Mène une partie jusqu'à l'accomplissement du fil rouge, sans rien
     * atteindre d'autre.
     */
    private function partieAuBoutDuFilRouge(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $ville->crediterRessources([Ressource::Deben->value => 200]);

        $this->lire($partie, Inscription::CommandeDAhmosis);

        $enquete = FilRouge::enquete($partie);
        self::assertNotNull($enquete);
        $dossier = $ville->ouvrirLeDossierDe($enquete);

        foreach ($enquete->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                $dossier->verser($indice);
            }
        }

        static::getContainer()->get(Enquetes::class)->conclure(
            $partie,
            $enquete,
            $enquete->bonneConclusion(),
        );

        foreach (Inscription::LaRouteEstRouverte->signes() as $signe) {
            $ville->apprendreUnSymbole($signe);
        }

        $this->lire($partie, Inscription::LaRouteEstRouverte);

        return $partie;
    }

    private function lire(GameSave $partie, Inscription $inscription): void
    {
        static::getContainer()->get(Dechiffrage::class)->verifier($partie, $inscription, array_map(
            static fn (SymboleHieroglyphique $signe): string => $signe->value,
            $inscription->signes(),
        ));
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

    private function achevement(): AchevementDeMission
    {
        return static::getContainer()->get(AchevementDeMission::class);
    }
}
