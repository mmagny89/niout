<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\CleDeLecture;
use App\Game\Divinite;
use App\Game\EffetDeChef;
use App\Game\Enigme;
use App\Game\Enigmes;
use App\Game\Enquetes;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\Ressource;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les deux Scribes, et Thot (lot 7.7).
 *
 * Aucun des trois ne passe par la qualité de direction : leur effet n'est pas
 * une production. C'est le canal du Négociateur et du Dévot.
 */
final class ScribesTest extends WebTestCase
{
    /**
     * **Le Déchiffreur lit ce que le bâtiment n'ouvre pas encore.** C'est ce
     * que vaut un homme qui a passé sa vie sur les pierres.
     */
    public function testLeDechiffreurElargitLaCleDeLecture(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('dechiffreur@example.com');
        $ville = $partie->getVille();

        $sansLui = \count(CleDeLecture::pour($ville, $partie->getCycle()));
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur);

        self::assertSame(
            $sansLui + EffetDeChef::SIGNES_DU_DECHIFFREUR,
            \count(CleDeLecture::pour($ville, $partie->getCycle())),
        );
    }

    /**
     * Un autre chef du même bâtiment n'y change rien : c'est le Déchiffreur
     * qu'on paie, pas le fait d'avoir quelqu'un chez les scribes.
     */
    public function testUnAutreChefDesScribesNyChangeRien(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('autre-scribe@example.com');
        $ville = $partie->getVille();

        $avant = \count(CleDeLecture::pour($ville, $partie->getCycle()));
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);

        self::assertCount($avant, CleDeLecture::pour($ville, $partie->getCycle()));
    }

    /**
     * **L'Oraculaire resserre le doute, il ne donne pas la réponse.** Une
     * mauvaise proposition disparaît ; la bonne reste, et l'énigme reste une
     * question.
     */
    public function testLOraculaireEcarteUneMauvaiseProposition(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('oraculaire@example.com');
        $enigmes = static::getContainer()->get(Enigmes::class);

        $toutes = $enigmes->propositionsMontrees($partie, Enigme::IbisDeThot);
        self::assertCount(\count(Enigme::IbisDeThot->propositions()), $toutes);

        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);
        $resserrees = $enigmes->propositionsMontrees($partie, Enigme::IbisDeThot);

        self::assertCount(\count($toutes) - EffetDeChef::PROPOSITIONS_ECARTEES_PAR_LORACULAIRE, $resserrees);
        self::assertContains(Enigme::IbisDeThot->bonneReponse(), $resserrees, 'La bonne réponse ne s\'écarte jamais.');
    }

    /**
     * **Thot éclaire les écrits** (doc 07) : sa faveur ouvre des signes que le
     * bâtiment ne porte pas encore. C'est par là qu'il cesse d'être un dieu
     * offrable et inerte.
     */
    public function testThotOuvreDesSignes(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('thot@example.com');
        $ville = $partie->getVille();

        self::assertSame(0, CleDeLecture::signesDeThot($ville));

        $this->porterThotAuFavorable($partie);

        self::assertGreaterThan(0, CleDeLecture::signesDeThot($ville));
        self::assertTrue(Divinite::Thot->agitDeja(), 'Thot ne doit plus annoncer son inertie.');
        self::assertNull(Divinite::Thot->attente());
    }

    /**
     * **Thot abrège la reprise d'un dossier mal conclu** — jamais jusqu'à
     * l'annuler : une erreur sans conséquence n'en serait plus une.
     */
    public function testThotAbregeLaRepriseDunDossierSansLAnnuler(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('thot-dossier@example.com');

        self::assertSame(Enquetes::RETARD_DUNE_ERREUR, Enquetes::retardDUneErreur($partie));

        $this->porterThotAuFavorable($partie);

        $abrege = Enquetes::retardDUneErreur($partie);
        self::assertLessThan(Enquetes::RETARD_DUNE_ERREUR, $abrege);
        self::assertGreaterThanOrEqual(1, $abrege, 'Une erreur doit toujours coûter quelque chose.');
    }

    /**
     * Le compte des dormeurs : il ne reste qu'Isis parmi les dieux, et trois
     * spécialités, toutes suspendues à des phases qui ont leur propre système
     * à écrire.
     */
    public function testCeQuiDortEncoreEtRienDautre(): void
    {
        $dieuxInertes = array_filter(Divinite::pantheon(), static fn (Divinite $d): bool => !$d->agitDeja());
        self::assertSame([], array_values($dieuxInertes), 'Isis agit depuis le lot 10.4 : plus aucun dieu ne dort.');

        $specialitesInertes = array_filter(
            SpecialiteDeChef::cases(),
            static fn (SpecialiteDeChef $s): bool => !$s->agitDeja(),
        );
        self::assertSame([
            SpecialiteDeChef::MarcheAcheteur,
            SpecialiteDeChef::PortCommercantNaval,
        ], array_values($specialitesInertes));
    }

    private function porterThotAuFavorable(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->crediterRessources([Ressource::Deben->value => 1_000]);

        $offrandes = static::getContainer()->get(Offrandes::class);

        while (!$ville->palierDe(Divinite::Thot)->estAuDessusDuNeutre()) {
            $offrandes->offrir($partie, Divinite::Thot, Ressource::Deben, 20);
        }

        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Thot));
    }

    private function engager(GameSave $partie, TypeDeBatiment $type, SpecialiteDeChef $specialite): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 200,
                traits: [],
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $partie->getCycle(),
        ));
    }

    private function villeAvecScribes(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 2));
        $gestionnaire->flush();

        return $partie;
    }
}
