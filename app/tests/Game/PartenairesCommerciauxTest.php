<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\CataloguePartenaires;
use App\Game\MissionCatalogue;
use App\Game\PartenaireCommercial;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\TypeDeRoute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CataloguePartenaires::class)]
#[CoversClass(PartenaireCommercial::class)]
#[CoversClass(TypeDeRoute::class)]
final class PartenairesCommerciauxTest extends TestCase
{
    /**
     * **Chaque mission a de quoi commercer.** Une région sans partenaire serait
     * une impasse : le doc 08 dit que seul le Delta est autosuffisant, donc
     * toutes les autres dépendent du commerce pour bâtir.
     */
    public function testChaqueMissionAAuMoinsUnPartenaire(): void
    {
        $catalogue = new CataloguePartenaires();

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            self::assertNotEmpty(
                $catalogue->pourLaMission($mission->numero),
                \sprintf('%s n\'a personne avec qui commercer.', $mission->ville),
            );
        }
    }

    /**
     * Les clés servent d'identité en base : deux partenaires d'une même
     * mission qui la partageraient se confondraient sur la route ouverte.
     */
    public function testLesClesSontUniquesParMission(): void
    {
        $catalogue = new CataloguePartenaires();

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            $cles = array_map(
                static fn (PartenaireCommercial $p): string => $p->cle,
                $catalogue->pourLaMission($mission->numero),
            );

            self::assertSame(array_unique($cles), $cles, $mission->ville);
        }
    }

    /**
     * **Un partenaire ne vend jamais ce qu'il achète.** Sans cette règle, une
     * route deviendrait une machine à arbitrer : acheter et revendre à la même
     * cité sans rien produire.
     */
    public function testAucunPartenaireNAchateCeQuIlVend(): void
    {
        $catalogue = new CataloguePartenaires();

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach ($catalogue->pourLaMission($mission->numero) as $partenaire) {
                foreach ($partenaire->vend as $ressource) {
                    self::assertFalse(
                        $partenaire->acheteDe($ressource),
                        \sprintf('%s vend et achète du %s.', $partenaire->nom, $ressource->libelle()),
                    );
                }
            }
        }
    }

    /**
     * Tout ce qui s'échange doit avoir un cours : une ressource sans prix
     * n'aurait aucune fourchette, et la négociation n'aurait aucun sens.
     */
    public function testToutCeQuiSEchangeAUnPrix(): void
    {
        $catalogue = new CataloguePartenaires();

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach ($catalogue->pourLaMission($mission->numero) as $partenaire) {
                foreach ([...$partenaire->vend, ...$partenaire->achete] as $ressource) {
                    self::assertNotNull(
                        PrixDuMarche::pour($ressource),
                        \sprintf('%s échange du %s, qui n\'a pas de cours.', $partenaire->nom, $ressource->libelle()),
                    );
                }
            }
        }
    }

    /**
     * **La fourchette est le levier du joueur** : vendre au loin rapporte plus
     * que le Marché local, acheter au loin coûte plus cher que d'y produire.
     * C'est l'écart entre les deux qui fait du commerce un choix.
     */
    public function testVendreAuLoinRapportePlusQueLeMarcheLocal(): void
    {
        $byblos = (new CataloguePartenaires())->partenaire(1, 'byblos');
        self::assertNotNull($byblos);

        $localement = PrixDuMarche::pour(Ressource::Lin);
        $auLoin = $byblos->prixMaximumALaVente(Ressource::Lin);

        self::assertNotNull($auLoin);
        self::assertGreaterThan($localement, $auLoin);

        // Et ce qu'il vend coûte plus cher que son cours local : c'est le
        // transport (doc 08, majoration d'import).
        $cedre = $byblos->prixMinimumALAchat(Ressource::BoisDeCedre);
        self::assertNotNull($cedre);
        self::assertGreaterThan(PrixDuMarche::pour(Ressource::BoisDeCedre), $cedre);
    }

    /**
     * Une fourchette n'existe que pour ce que le partenaire traite : demander
     * le prix d'autre chose ne doit pas inventer un marché.
     */
    public function testAucuneFourchettePourCeQueLePartenaireNeTraitePas(): void
    {
        $byblos = (new CataloguePartenaires())->partenaire(1, 'byblos');
        self::assertNotNull($byblos);

        self::assertNull($byblos->prixMaximumALaVente(Ressource::Granite));
        self::assertNull($byblos->prixMinimumALAchat(Ressource::Granite));
    }

    /**
     * Le type de route décide du bâtiment et du volume (doc 12) : une caravane
     * relève de l'Entrepôt, un navire du Port, et le bateau porte davantage.
     */
    public function testLeTypeDeRouteDecideDuBatimentEtDuVolume(): void
    {
        self::assertSame(\App\Game\TypeDeBatiment::Entrepot, TypeDeRoute::Terrestre->batiment());
        self::assertSame(\App\Game\TypeDeBatiment::Port, TypeDeRoute::Maritime->batiment());
        self::assertSame(\App\Game\TypeDeBatiment::Port, TypeDeRoute::Fluviale->batiment());

        self::assertGreaterThan(
            TypeDeRoute::Terrestre->volumeParNiveau(),
            TypeDeRoute::Maritime->volumeParNiveau(),
        );
        self::assertGreaterThan(
            TypeDeRoute::Terrestre->coutDOuverture(),
            TypeDeRoute::Maritime->coutDOuverture(),
        );
    }

    /**
     * Pount est au bout du monde, une remontée du fleuve est courte : les
     * distances doivent rester lisibles les unes par rapport aux autres.
     */
    public function testLesDistancesRestentLisiblesEntreElles(): void
    {
        $catalogue = new CataloguePartenaires();

        $pount = $catalogue->partenaire(3, 'pount');
        $memphis = $catalogue->partenaire(1, 'memphis');
        $byblos = $catalogue->partenaire(1, 'byblos');

        self::assertNotNull($pount);
        self::assertNotNull($memphis);
        self::assertNotNull($byblos);

        self::assertGreaterThan($byblos->distanceEnQuinzaines, $pount->distanceEnQuinzaines);
        self::assertGreaterThan($memphis->distanceEnQuinzaines, $byblos->distanceEnQuinzaines);

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach ($catalogue->pourLaMission($mission->numero) as $partenaire) {
                self::assertGreaterThan(0, $partenaire->distanceEnQuinzaines, $partenaire->nom);
                self::assertLessThanOrEqual(10, $partenaire->distanceEnQuinzaines, $partenaire->nom);
            }
        }
    }
}
