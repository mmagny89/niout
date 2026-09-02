<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Bandits;
use App\Game\Charrier;
use App\Game\Combat;
use App\Game\EffetDeChef;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\Medjays;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\SpecialisationMedjay;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le Charrier et les branchements dormants (doc 03, doc 01, lots 10.6 et 10.7).
 *
 * Le Charrier tient une **distinction historique** que le jeu porte jusque dans
 * son code : les Medjaÿ formaient un corps de sécurité intérieure, le char de
 * guerre appartenait à la *mesha*, l'armée d'État. Il se loue, il ne se recrute
 * pas — et il n'a donc ni entité, ni entretien, ni effectif.
 */
final class CharrierEtInstructeursTest extends KernelTestCase
{
    /**
     * **Caserne 7 et Forge 4**, comme le doc 01 et le doc 03 le demandent : le
     * pharaon ne prête ses chars qu'aux villes qui savent les recevoir et les
     * équiper.
     */
    public function testLaRequisitionDemandeUneCaserneEtUneForge(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('charrier-seuils@example.com', difficulte: 9);
        $ville = $partie->getVille();

        self::assertNotNull(Charrier::empechement($ville));

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, Charrier::NIVEAU_DE_CASERNE_REQUIS));
        self::assertNotNull(Charrier::empechement($ville), 'La Forge manque encore.');

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, Charrier::NIVEAU_DE_FORGE_REQUIS));
        self::assertNull(Charrier::empechement($ville));
        self::assertTrue(Charrier::disponiblePour($ville));
    }

    /**
     * **Les chars pèsent au combat sans rejoindre l'effectif.** Ils entrent
     * dans le score, ils ne figurent nulle part ensuite : on les rend.
     */
    public function testLesCharsPesentSansRejoindreLeffectif(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('charrier-force@example.com', difficulte: 9);
        $troupe = $this->leverLaTroupe($partie, 2);

        // Une case fabriquée en terrain neutre : le facteur de terrain
        // multiplie la somme entière, si bien que sur du sable la contribution
        // des chars ne serait pas leur force nue. Ce qu'on vérifie ici est
        // qu'ils s'ajoutent au brut, pas comment le terrain le module.
        $zone = new Zone($partie->getVille(), 0, 0, TypeDeTerrain::Fertile);
        $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);

        $sansChars = $this->combat()->scoreDattaque($partie, $zone, $troupe);
        $avecChars = $this->combat()->scoreDattaque($partie, $zone, $troupe, charriers: 2);

        self::assertSame(Combat::TERRAIN_NEUTRE, $this->combat()->facteurDeTerrain($partie, $zone));
        self::assertSame($sansChars + 2 * Charrier::FORCE, $avecChars);
        self::assertCount(2, $partie->getVille()->getMedjays(), 'Les chars ne sont pas des Medjaÿ.');
    }

    /**
     * **Ils se paient par sortie, et l'expédition les emporte.** Aucun
     * entretien : il n'y a rien à entretenir.
     */
    public function testLesCharsSePaientParSortie(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('charrier-cout@example.com', difficulte: 9);
        $ville = $partie->getVille();
        $zone = $this->uneZoneGardee($partie);

        $this->leverLaTroupe($partie, 2);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, Charrier::NIVEAU_DE_FORGE_REQUIS));
        $ville->crediterRessources([Ressource::Deben->value => 5_000]);

        $avant = $ville->getDeben();
        $expedition = $this->explorations()->envoyer($partie, $zone, RoleDExploration::ChefDExpedition, charriers: 2);

        self::assertSame(2, $expedition->getCharriers());
        self::assertSame(
            $avant - RoleDExploration::ChefDExpedition->cout() - 2 * Charrier::COUT_PAR_EXPEDITION,
            $ville->getDeben(),
        );
    }

    /**
     * Une ville qui ne remplit pas les conditions se voit refuser la
     * réquisition — dans le domaine, pas seulement dans le gabarit.
     */
    public function testUneVilleSansCaserneAssezHauteNeRequisitionnePas(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('charrier-refus@example.com', difficulte: 9);
        $zone = $this->uneZoneGardee($partie);
        $this->leverLaTroupe($partie, 2, niveauDeCaserne: 4);

        $this->expectException(ExplorationImpossible::class);
        $this->explorations()->envoyer($partie, $zone, RoleDExploration::ChefDExpedition, charriers: 1);
    }

    /**
     * **L'Instructeur n'aiguise que sa spécialisation** (doc 03) : un
     * instructeur au bouclier ne fait pas mieux tirer les archers, et c'est ce
     * qui donne un sens au choix entre les deux à l'embauche.
     */
    public function testLinstructeurNaiguiseQueSaSpecialisation(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('instructeur@example.com', difficulte: 9);
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();

        self::assertSame(0, EffetDeChef::bonusDInstructeur($ville, SpecialisationMedjay::Fantassin, $cycle));

        $this->placerUnChefDeCaserne($partie, SpecialiteDeChef::CaserneInstructeurBouclier);

        self::assertSame(
            EffetDeChef::BONUS_INSTRUCTEUR,
            EffetDeChef::bonusDInstructeur($ville, SpecialisationMedjay::Fantassin, $cycle),
        );
        self::assertSame(
            0,
            EffetDeChef::bonusDInstructeur($ville, SpecialisationMedjay::Archer, $cycle),
            'Un instructeur au bouclier ne fait pas mieux tirer les archers.',
        );
    }

    /**
     * **Le Bagarreur a enfin ses deux moitiés** (doc 03). Le jeu n'appliquait
     * ni le bonus de combat ni le malus civil ; il le disait lui-même dans le
     * libellé du trait.
     */
    public function testLeBagarreurAuneMoitieDeChaqueCote(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('bagarreur@example.com', difficulte: 9);
        $ville = $partie->getVille();

        // Le malus civil : il pèse sur la compétence, quel que soit le poste.
        self::assertSame(
            TraitDeCandidat::MALUS_CIVIL_DU_BAGARREUR,
            TraitDeCandidat::Bagarreur->effetSurCompetence(),
        );
        self::assertLessThan(0, TraitDeCandidat::Bagarreur->effetSurCompetence());

        // Le bonus de combat : il ne vaut qu'à la Caserne.
        self::assertSame(0, EffetDeChef::bonusDuBagarreur($ville, $partie->getCycle()));

        $this->placerUnChefDeCaserne($partie, null, [TraitDeCandidat::Bagarreur]);

        self::assertSame(
            TraitDeCandidat::BONUS_DE_COMBAT_DU_BAGARREUR,
            EffetDeChef::bonusDuBagarreur($ville, $partie->getCycle()),
        );
    }

    /**
     * **Plus aucun trait ne dort**, et il ne reste que deux spécialités sans
     * système — l'Acheteur du Marché, que le Marché purement local
     * n'accueillera jamais, et le Commerçant naval.
     */
    public function testCeQuiDortEncoreApresLaPhaseDix(): void
    {
        $traitsDormants = array_filter(
            TraitDeCandidat::cases(),
            static fn (TraitDeCandidat $t): bool => $t->dortEnAttendantSaPhase(),
        );

        self::assertSame([], array_values($traitsDormants));

        $specialitesInertes = array_values(array_filter(
            SpecialiteDeChef::cases(),
            static fn (SpecialiteDeChef $s): bool => !$s->agitDeja(),
        ));

        self::assertSame(
            [SpecialiteDeChef::MarcheAcheteur, SpecialiteDeChef::PortCommercantNaval],
            $specialitesInertes,
        );
    }

    /**
     * @param list<TraitDeCandidat> $traits
     */
    private function placerUnChefDeCaserne(GameSave $partie, ?SpecialiteDeChef $specialite, array $traits = []): void
    {
        $ville = $partie->getVille();

        if (!$ville->possede(TypeDeBatiment::Caserne)) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 9));
        }

        $chef = new \App\Entity\Employee(
            $ville,
            TypeDeBatiment::Caserne,
            new \App\Game\Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 20,
                traits: $traits,
                specialite: $specialite,
                actifsAmenes: 2,
                inactifsAmenes: 1,
            ),
            $partie->getCycle(),
        );

        $ville->ajouterEmploye($chef);
    }

    private function uneZoneGardee(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->porteLaVille() && !$zone->estGardee()) {
                $zone->decouvrir();
                $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);

                return $zone;
            }
        }

        self::fail('La carte ne porte aucune case où installer une bande.');
    }

    /**
     * @return list<\App\Entity\Medjay>
     */
    private function leverLaTroupe(GameSave $partie, int $combien, int $niveauDeCaserne = 9): array
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, $niveauDeCaserne));
        $ville->crediterRessources([Ressource::Deben->value => 5_000, Ressource::Ble->value => 200]);

        $troupe = [];

        foreach (range(1, $combien) as $ignore) {
            $troupe[] = $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
        }

        return $troupe;
    }

    private function lancerUnePartie(string $email, int $difficulte): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');
        $joueur->setRoles([User::ROLE_DIVIN]);

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        // La dernière mission : c'est la seule région où les bâtiments montent
        // assez haut pour que le pharaon prête ses chars.
        return static::getContainer()->get(LanceurDePartie::class)
            ->lancerCampagne($joueur, 'Nakht', numeroDeMission: 10);
    }

    private function combat(): Combat
    {
        return static::getContainer()->get(Combat::class);
    }

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }

    private function medjays(): Medjays
    {
        return static::getContainer()->get(Medjays::class);
    }
}
