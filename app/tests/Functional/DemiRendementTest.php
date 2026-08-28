<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\Effectifs;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce que le demi-rendement change réellement en partie (lot 4.4).
 *
 * La formule a ses tests unitaires ; ceux-ci vérifient qu'elle **mord** — un
 * Grenier sans personne conserve moins qu'un Grenier tenu, et la différence
 * arrive bien dans le stock.
 */
final class DemiRendementTest extends KernelTestCase
{
    /**
     * L'exemple donné par la joueuse elle-même : « un bâtiment sans chef
     * fonctionne mais partiellement, il ne stocke que la moitié de ce qui est
     * produit ».
     */
    public function testUnGrenierSansChefNeConserveQueLaMoitieDeLaRecolte(): void
    {
        self::bootKernel();

        $sansChef = $this->recolterSurUnCycleComplet('sans-chef@example.com', avecChef: false);
        $avecChef = $this->recolterSurUnCycleComplet('avec-chef@example.com', avecChef: true);

        self::assertGreaterThan(
            0,
            $sansChef,
            'Rien ne s\'éteint faute d\'employés : un Grenier sans personne conserve encore.',
        );

        self::assertSame(
            intdiv($avecChef, 2),
            $sansChef,
            'Sans chef, la moitié exactement de ce qu\'un Grenier tenu rentre.',
        );
    }

    /**
     * Les bras d'un bâtiment se puisent dans le vivier d'actifs (doc 05) : une
     * ville sans actifs disponibles ne tient rien, même avec un chef.
     */
    public function testUnChefSansBrasNeTientPasSonBatiment(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-bras@example.com');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier);

        self::assertSame(
            Effectifs::RENDEMENT_PLEIN,
            Effectifs::rendementDe($ville, TypeDeBatiment::Grenier, $partie->getCycle()),
        );

        // La ville perd tous ses actifs : il ne reste personne à envoyer.
        $ville->laisserPartir($ville->getActifs(), 0);

        self::assertSame(
            Effectifs::RENDEMENT_PLANCHER,
            Effectifs::rendementDe($ville, TypeDeBatiment::Grenier, $partie->getCycle()),
        );
    }

    /**
     * Un chef embauché n'a rien recruté tant qu'il n'a pas pris son poste
     * (doc 05) : le bâtiment ne réclame donc encore aucun bras.
     */
    public function testUnChefPasEncoreEnPosteNeReclameAucunBras(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('pas-en-poste@example.com');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier, dansUnCycle: true);

        $batiment = $ville->batimentDeType(TypeDeBatiment::Grenier);
        self::assertNotNull($batiment);

        self::assertSame(0, Effectifs::travailleursRequis($batiment, $partie->getCycle()));
        self::assertSame(1, Effectifs::travailleursRequis($batiment, $partie->getCycle() + 1));
    }

    /**
     * Les chefs sont des actifs comme les autres : ils ne s'encadrent pas
     * eux-mêmes, et ne comptent donc pas parmi les bras à placer.
     */
    public function testUnChefNeSeCompteJamaisParmiLesBrasQuIlEncadre(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('bras@example.com');
        $ville = $partie->getVille();
        $actifs = $ville->getActifs();

        self::assertSame($actifs, Effectifs::brasDisponibles($ville, $partie->getCycle()));

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier);

        self::assertSame($actifs - 1, Effectifs::brasDisponibles($ville, $partie->getCycle()));
    }

    /**
     * Sème un champ, fait tourner un cycle agricole complet et rend ce qui est
     * réellement entré au stock — consommation neutralisée.
     */
    private function recolterSurUnCycleComplet(string $email, bool $avecChef): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->definirTerrain(TypeDeTerrain::Fertile)
            ->poserUnContenu(ContenuDeZone::ChampEligible)
            ->decouvrir();

        static::getContainer()->get(Exploitations::class)->semer($partie, $zone, Culture::Ble);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));

        if ($avecChef) {
            $this->installerUnChef($partie, TypeDeBatiment::Grenier);
        }

        $recolte = 0;

        for ($i = 0; $i < \App\Game\CycleAgricoleTerrestre::DUREE_TOTALE; ++$i) {
            $avant = $ville->quantite(Ressource::Ble);
            $consommation = $ville->consommationDeNourriture();
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
            $recolte += $ville->quantite(Ressource::Ble) - $avant + $consommation;
        }

        return $recolte;
    }

    private function installerUnChef(GameSave $partie, TypeDeBatiment $type, bool $dansUnCycle = false): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 20,
                traits: [],
                specialite: null,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $dansUnCycle ? $partie->getCycle() + 1 : $partie->getCycle(),
        ));
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
}
