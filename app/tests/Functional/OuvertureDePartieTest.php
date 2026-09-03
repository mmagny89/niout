<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Chantiers;
use App\Game\Culture;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce qu'une ville neuve doit pouvoir faire dès sa première quinzaine
 * (décision de la joueuse) : « faire rouler la ville et ne pas être bloqué ».
 *
 * C'est l'invariant d'ouverture de partie, et il se vérifie de bout en bout —
 * la dotation, les coûts du catalogue et les garanties de génération de carte
 * doivent s'accorder. Chacun est juste de son côté sans que l'ensemble le soit.
 */
final class OuvertureDePartieTest extends KernelTestCase
{
    /**
     * Les quatre bâtiments d'ouverture, engagés **d'un coup** — le cas le plus
     * dur, la dotation ne laissant aucune marge en matériaux.
     */
    public function testUneVilleNeuveEngageSesQuatreBatimentsDOuverture(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('ouverture@example.com');
        $ville = $partie->getVille();
        $chantiers = static::getContainer()->get(Chantiers::class);

        foreach ([
            TypeDeBatiment::QuartierDHabitation,
            TypeDeBatiment::Grenier,
            TypeDeBatiment::Marche,
            TypeDeBatiment::Entrepot,
        ] as $type) {
            $chantiers->lancer($partie, $type);

            self::assertTrue(
                $ville->aUnChantierPour($type),
                \sprintf('Le %s doit être engageable dès la première quinzaine.', $type->libelle()),
            );
        }

        // Et il reste de quoi payer une année de salaires et manger une année :
        // le pharaon finance le démarrage entier, pas seulement les murs.
        self::assertGreaterThan(0, $ville->getDeben(), 'La dotation ne doit pas laisser la ville sans un deben.');
        self::assertGreaterThan(0, $ville->getNourriture());
    }

    /**
     * L'autre moitié de la demande : ouvrir un champ et une carrière de chaque
     * matériau ne coûte rien, et les matériaux vitaux sont garantis autour de
     * la ville. Rien ne s'oppose donc à ce qu'elle produise d'emblée.
     */
    public function testUneVilleNeuveOuvreUnChampEtUneCarriereDeChaqueMateriau(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('territoire@example.com');
        $ville = $partie->getVille();
        $exploitations = static::getContainer()->get(Exploitations::class);

        $champs = 0;
        $materiaux = [];

        foreach ($ville->getZones() as $zone) {
            if ($zone === $ville->zoneDeLaVille()) {
                continue;
            }

            $zone->decouvrir();

            foreach ($zone->getGisements() as $gisement) {
                $ressource = $gisement->getRessource();

                // La pêche demande un Port, qui n'est pas de l'ouverture.
                if (Ressource::Poisson === $ressource || isset($materiaux[$ressource->value])) {
                    continue;
                }

                $exploitations->exploiter($partie, $zone, $ressource);
                $materiaux[$ressource->value] = true;
            }

            if (0 === $champs && $zone->accepteUnChamp() && !$zone->porteUnChamp()) {
                $exploitations->semer($partie, $zone, 1, Culture::Ble);
                ++$champs;
            }
        }

        self::assertSame(1, $champs, 'Une carte doit porter au moins une case cultivable.');
        self::assertGreaterThanOrEqual(
            2,
            \count($materiaux),
            'Roseaux et argile sont garantis autour de la ville : les deux doivent être exploitables.',
        );
    }

    /**
     * Le Quartier d'habitation étant financé d'emblée, la ville cesse de
     * manquer de logements dès qu'il est dressé — ce qui débloque d'un coup
     * les naissances, l'appel d'habitants et l'embauche d'un chef, tous
     * bornés par le logement.
     */
    public function testLeQuartierFinanceLeveLeManqueDeLogements(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('logements@example.com');
        $ville = $partie->getVille();

        self::assertTrue($ville->manqueDeLogements(), 'La ville s\'ouvre à l\'étroit : c\'est ce qui rend le Quartier lisible.');

        $ville->ajouterBatiment(new \App\Entity\Building($ville, TypeDeBatiment::QuartierDHabitation));

        self::assertFalse($ville->manqueDeLogements());
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
