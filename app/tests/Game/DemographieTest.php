<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Demographie;
use App\Game\Population;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * La démographie tient du tirage : ces tests portent sur des **invariants et
 * des tendances**, jamais sur un résultat attendu pour une graine donnée. Une
 * assertion du type « la graine 7 donne deux naissances » ne dirait rien de la
 * règle et casserait au premier réglage.
 */
#[CoversClass(Demographie::class)]
final class DemographieTest extends TestCase
{
    /**
     * Le verrou central du modèle : la ville ne déborde jamais de son
     * logement. Sans Quartier d'habitation, la seule Résidence familiale loge
     * une maisonnée — les dix volontaires du pharaon la remplissent déjà.
     */
    public function testAucuneNaissanceQuandLesMaisonsSontPleines(): void
    {
        $partie = $this->partie(quartier: 0);
        $demographie = new Demographie(new Randomizer(new Mt19937(1)));

        self::assertTrue($partie->getVille()->manqueDeLogements());

        $depart = $partie->getVille()->population();

        for ($annee = 0; $annee < 20; ++$annee) {
            $demographie->bilanDeLAnnee($partie);
        }

        self::assertLessThan(
            $depart,
            $partie->getVille()->population(),
            'Sans logement, rien ne naît : la ville ne peut que décroître.',
        );
    }

    /**
     * Le pendant du test précédent, et la raison d'être des naissances : une
     * ville logée se maintient seule. Mesuré sur cent parties parce qu'une
     * seule ne dirait rien d'un processus aléatoire.
     */
    public function testUneVilleLogeeSeMaintientSurVingtAns(): void
    {
        $populations = [];

        for ($graine = 0; $graine < 100; ++$graine) {
            $partie = $this->partie(quartier: 1);
            $demographie = new Demographie(new Randomizer(new Mt19937($graine)));

            for ($annee = 0; $annee < 20; ++$annee) {
                $demographie->bilanDeLAnnee($partie);
            }

            $populations[] = $partie->getVille()->population();
        }

        self::assertNotContains(
            0,
            $populations,
            'Une ville logée ne doit jamais s\'éteindre : les naissances existent pour ça.',
        );

        self::assertGreaterThanOrEqual(
            10,
            array_sum($populations) / 100,
            'En moyenne, les naissances doivent au moins compenser les décès.',
        );
    }

    /**
     * Sous les paliers « Respectée » et « Illustre », le doc 13 n'accorde
     * aucune migration spontanée : la seule croissance possible vient des
     * naissances, plafonnées par le logement.
     */
    public function testUneFamilleInconnueNAttirePersonneToutSeule(): void
    {
        $partie = $this->partie(quartier: 1);
        $demographie = new Demographie(new Randomizer(new Mt19937(3)));

        for ($annee = 0; $annee < 20; ++$annee) {
            $evenements = $demographie->bilanDeLAnnee($partie);

            foreach ($evenements as $evenement) {
                self::assertStringNotContainsString('renommée', $evenement);
            }
        }
    }

    /**
     * Une ville vide le reste : aucune règle ne doit la repeupler par magie.
     */
    public function testUneVilleVideNeRessuscitePas(): void
    {
        $partie = $this->partie(quartier: 1);
        $ville = $partie->getVille();
        $ville->appliquerLeBilanDeLAnnee(0, 0, $ville->getEnfants(), $ville->getActifs(), $ville->getAnciens());

        self::assertSame(0, $ville->population());

        $evenements = (new Demographie(new Randomizer(new Mt19937(5))))->bilanDeLAnnee($partie);

        self::assertSame([], $evenements);
        self::assertSame(0, $ville->population());
    }

    private function partie(int $quartier): GameSave
    {
        $joueur = new User();
        $joueur->setEmail('joueur@example.com');

        $ville = new City('Avaris', 0, 3);
        $ville->accueillir(
            Population::ACTIFS_AU_DEPART,
            Population::ENFANTS_AU_DEPART,
            Population::ANCIENS_AU_DEPART,
        );

        if ($quartier > 0) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, $quartier));
        }

        return GameSave::pourCampagne($joueur, new Family(Family::NOM_PAR_DEFAUT), $ville);
    }
}
