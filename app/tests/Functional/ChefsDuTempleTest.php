<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\Divinite;
use App\Game\EffetDeChef;
use App\Game\LanceurDePartie;
use App\Game\Negligence;
use App\Game\Offrandes;
use App\Game\Ressource;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le trait « Pieux » et la spécialité « Dévot » (lot 6.7).
 *
 * Deux dormeurs posés au lot 4.2 se réveillent ici, et ils ne passent ni l'un
 * ni l'autre par la qualité de direction : leur effet n'est pas une
 * production. C'est le canal du Négociateur, pas celui du Potier.
 */
final class ChefsDuTempleTest extends KernelTestCase
{
    /**
     * **Le Dévot fait peser chaque offrande davantage.** Son effet ne dépend
     * ni de ce qu'on donne ni à qui : il tient à l'homme qui porte le présent.
     */
    public function testLeDevotFaitPeserChaqueOffrande(): void
    {
        self::bootKernel();

        $sansLui = $this->offrirVingtDeben('sans-devot@example.com', null);
        $avecLui = $this->offrirVingtDeben('avec-devot@example.com', SpecialiteDeChef::TempleDevot);

        self::assertSame($sansLui + EffetDeChef::BONUS_DU_DEVOT, $avecLui);
    }

    /**
     * Une autre spécialité du même bâtiment ne fait rien de tel : c'est le
     * Dévot qu'on paie, pas le fait d'avoir un chef au Temple.
     */
    public function testUnAutreChefDuTempleNyChangeRien(): void
    {
        self::bootKernel();

        $sansLui = $this->offrirVingtDeben('temple-quelconque@example.com', null);
        $autre = $this->offrirVingtDeben('temple-autre@example.com', SpecialiteDeChef::GrenierGestionnaire);

        self::assertSame($sansLui, $autre);
    }

    /**
     * **Un chef pieux fait durer le répit.** Sa maisonnée entretient les rites
     * quotidiens : la ville oublie ses dieux moins vite — mais elle finit par
     * les oublier quand même.
     */
    public function testUnChefPieuxRetardeLOubliSansLEmpecher(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('pieux@example.com');
        $ville = $partie->getVille();

        $this->engager($partie, TypeDeBatiment::Grenier, null, [TraitDeCandidat::Croyant]);
        self::assertSame(1, EffetDeChef::chefsPieux($ville, $partie->getCycle()));

        static::getContainer()->get(Offrandes::class)->offrir($partie, Divinite::Ptah, Ressource::Deben, 60);
        $apresLOffrande = $ville->faveurEnvers(Divinite::Ptah);

        $negligence = static::getContainer()->get(Negligence::class);

        // Là où un dieu commencerait à se détourner sans lui, il ne bouge pas.
        for ($i = 0; $i < Negligence::QUINZAINES_DE_GRACE + 2; ++$i) {
            $negligence->avancerDUnCycle($partie);
        }

        self::assertSame($apresLOffrande, $ville->faveurEnvers(Divinite::Ptah));

        // Mais le répit n'est pas l'éternité.
        for ($i = 0; $i < EffetDeChef::REPIT_DUN_CHEF_PIEUX + 5; ++$i) {
            $negligence->avancerDUnCycle($partie);
        }

        self::assertLessThan($apresLOffrande, $ville->faveurEnvers(Divinite::Ptah));
    }

    /**
     * Le trait vaut où qu'il serve : ce n'est pas une spécialité du Temple, et
     * un contremaître dévot fait dire les prières sur son chantier.
     */
    public function testLeTraitVautDansNimporteQuelBatiment(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('contremaitre@example.com');

        $this->engager($partie, TypeDeBatiment::Marche, null, [TraitDeCandidat::Croyant]);

        self::assertSame(1, EffetDeChef::chefsPieux($partie->getVille(), $partie->getCycle()));
    }

    /**
     * **Il ne reste que « Bagarreur ».** Un trait qui promettrait un effet
     * inexistant tromperait le joueur au moment précis où il compare des
     * candidats.
     */
    public function testSeulBagarreurDortEncore(): void
    {
        foreach (TraitDeCandidat::cases() as $trait) {
            self::assertSame(
                TraitDeCandidat::Bagarreur === $trait,
                $trait->dortEnAttendantSaPhase(),
                \sprintf('%s : l\'affichage doit dire la vérité sur ce qu\'il fait.', $trait->libelle()),
            );
        }

        self::assertTrue(SpecialiteDeChef::TempleDevot->agitDeja());
    }

    private function offrirVingtDeben(string $email, ?SpecialiteDeChef $specialite): int
    {
        $partie = $this->villeAvecTemple($email);

        if (null !== $specialite) {
            $this->engager($partie, TypeDeBatiment::Temple, $specialite, []);
        }

        return static::getContainer()->get(Offrandes::class)
            ->offrir($partie, Divinite::Ptah, Ressource::Deben, 20);
    }

    /**
     * @param list<TraitDeCandidat> $traits
     */
    private function engager(GameSave $partie, TypeDeBatiment $type, ?SpecialiteDeChef $specialite, array $traits): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 200,
                traits: $traits,
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $partie->getCycle(),
        ));
    }

    private function villeAvecTemple(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000]);

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
}
