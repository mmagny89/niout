<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\CataloguePartenaires;
use App\Game\Commerce;
use App\Game\CommerceImpossible;
use App\Game\EffetDeChef;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\SensDEchange;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CommerceTest extends KernelTestCase
{
    /**
     * Memphis est à deux quinzaines : un aller-retour en coûte quatre.
     */
    private const int DISTANCE_DE_MEMPHIS = 2;

    /**
     * **Ouvrir, c'est envoyer une première caravane** : on paie, elle part, et
     * la route n'existe qu'à son arrivée.
     */
    public function testOuvrirUneRoutePaieEtMetUnConvoiEnChemin(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('ouverture@example.com');
        $ville = $partie->getVille();

        $debenAvant = $ville->getDeben();
        $route = $this->commerce()->ouvrir($partie, 'memphis');

        self::assertSame($debenAvant - TypeDeRoute::Fluviale->coutDOuverture(), $ville->getDeben());
        self::assertFalse($route->estOuverte(), 'Le convoi doit encore faire le trajet.');
        self::assertGreaterThan(0, $route->getQuinzainesAvantOuverture());
    }

    /**
     * Le trajet prend le temps de la distance, puis la route s'ouvre — et
     * l'ouverture est annoncée, une fois.
     */
    public function testLaRouteSOuvreALArriveeDuConvoiEtLeDitUneFois(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('trajet@example.com');
        $ville = $partie->getVille();

        $route = $this->commerce()->ouvrir($partie, 'memphis');
        $distance = $route->getQuinzainesAvantOuverture();

        $annonces = 0;
        for ($i = 0; $i < $distance + 3; ++$i) {
            foreach ($this->cycle()->passer($partie) as $evenement) {
                if (str_contains($evenement, 'la route est ouverte')) {
                    ++$annonces;
                }
            }
        }

        self::assertTrue($ville->routeVers('memphis')?->estOuverte());
        self::assertSame(1, $annonces, 'L\'ouverture s\'annonce une seule fois.');
    }

    /**
     * **Le bâtiment décide de ce qu'on peut ouvrir** (doc 12) : une ville sans
     * quai ne commerce que par la piste. C'est un poids de plus donné à la
     * géographie.
     */
    public function testSansPortAucuneRouteFluvialeNiMaritime(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-quai@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 1000]);

        // La piste, elle, s'ouvre : c'est l'Entrepôt qui l'arme.
        $this->commerce()->ouvrir($partie, 'canaan');
        self::assertNotNull($ville->routeVers('canaan'));

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/Port/');

        $this->commerce()->ouvrir($partie, 'byblos');
    }

    public function testOnNOuvrePasDeuxFoisLaMemeRoute(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('doublon@example.com');
        $this->commerce()->ouvrir($partie, 'memphis');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/déjà engagée/');

        $this->commerce()->ouvrir($partie, 'memphis');
    }

    /**
     * Une cité d'une autre mission n'est pas à portée : les routes sont celles
     * de la région où l'on joue.
     */
    public function testUneCiteDuneAutreMissionNestPasAPortee(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('hors-portee@example.com');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/pas à votre portée/');

        $this->commerce()->ouvrir($partie, 'pount');
    }

    /**
     * Sans deben, rien ne part — et rien n'est débité.
     */
    public function testUneRouteHorsDeMoyensNeDebiteRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-le-sou@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);

        try {
            $this->commerce()->ouvrir($partie, 'canaan');
            self::fail('Une route sans deben doit être refusée.');
        } catch (CommerceImpossible) {
            self::assertSame(0, $ville->getDeben());
            self::assertNull($ville->routeVers('canaan'));
        }
    }

    /**
     * Le volume d'un convoi suit le niveau du bâtiment qui l'arme (doc 12) :
     * c'est ce qui donne à l'Entrepôt et au Port un effet de niveau de plus.
     */
    public function testLeVolumeDunConvoiSuitLeNiveauDuBatiment(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('volume@example.com', niveau: 1);
        $petit = $this->volumeVers($partie, 'memphis');

        $partie = $this->villeQuiCommerce('volume-grand@example.com', niveau: 4);
        $grand = $this->volumeVers($partie, 'memphis');

        self::assertGreaterThan($petit, $grand);
    }

    /**
     * Un ordre est une **annonce, pas une transaction** : rien n'est débité en
     * le posant, et il reste jusqu'à ce qu'on le retire.
     */
    public function testUnOrdreNeDebiteRienEtResteEnPlace(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('etal@example.com');
        $ville = $partie->getVille();

        $debenAvant = $ville->getDeben();
        $bleAvant = $ville->quantite(Ressource::Ble);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 3, 20);

        self::assertSame($debenAvant, $ville->getDeben());
        self::assertSame($bleAvant, $ville->quantite(Ressource::Ble));
        self::assertNotNull($ville->routeVers('memphis')?->ordrePour(Ressource::Ble));
    }

    /**
     * **On n'annonce que ce que la cité traite**, et dans le bon sens : Memphis
     * achète du blé, elle n'en vend pas.
     */
    public function testOnNAnnonceQueCeQueLaCiteTraite(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('sens@example.com');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/ne vend pas/');

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Acheter, 5, 10);
    }

    /**
     * Une route encore en chemin ne porte aucun étal : il n'y a personne au
     * bout tant que la première caravane n'est pas arrivée.
     */
    public function testUneRouteEnCheminNeSupportePasDOrdre(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('pas-encore@example.com');
        $this->commerce()->ouvrir($partie, 'memphis');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/pas encore arrivée/');

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 3, 10);
    }

    /**
     * **Le prix est le levier, et le seul.** Trop gourmand à la vente,
     * personne n'achète ; au cours local, la cité prend tout. C'est cet
     * arbitrage entre marge et volume qui fait du commerce autre chose qu'un
     * robinet.
     */
    public function testLePrixDecideDeLEmpressementDuPartenaire(): void
    {
        $byblos = (new CataloguePartenaires())->partenaire(1, 'byblos');
        self::assertNotNull($byblos);

        $cours = PrixDuMarche::pour(Ressource::Lin);
        self::assertNotNull($cours);
        $maximum = $byblos->prixMaximumALaVente(Ressource::Lin);
        self::assertNotNull($maximum);

        self::assertSame(100, $byblos->empressementALaVente(Ressource::Lin, $cours), 'Au cours local, ils prennent tout.');
        self::assertSame(0, $byblos->empressementALaVente(Ressource::Lin, $maximum + 1), 'Au-delà, personne n\'achète.');

        // Et l'empressement décroît, sans marche ni trou.
        $precedent = 101;
        for ($prix = $cours; $prix <= $maximum; ++$prix) {
            $empressement = $byblos->empressementALaVente(Ressource::Lin, $prix);

            self::assertLessThanOrEqual($precedent, $empressement);
            self::assertGreaterThan(0, $empressement, \sprintf('Dans la fourchette, on traite encore (prix %d).', $prix));
            $precedent = $empressement;
        }
    }

    /**
     * Le pendant à l'achat : trop pingre, rien n'arrive ; généreux, les
     * convois se pressent.
     */
    public function testTropPingreALAchatRienNArrive(): void
    {
        $byblos = (new CataloguePartenaires())->partenaire(1, 'byblos');
        self::assertNotNull($byblos);

        $minimum = $byblos->prixMinimumALAchat(Ressource::BoisDeCedre);
        self::assertNotNull($minimum);

        self::assertSame(0, $byblos->empressementALAchat(Ressource::BoisDeCedre, $minimum - 1));
        self::assertGreaterThan(0, $byblos->empressementALAchat(Ressource::BoisDeCedre, $minimum));
        self::assertSame(100, $byblos->empressementALAchat(Ressource::BoisDeCedre, $minimum * 3));
    }

    public function testOnNePoseQuUnOrdreParRessourceEtParRoute(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('doublon-ordre@example.com');
        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 3, 10);

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/porte déjà/');

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 4, 10);
    }

    /**
     * **La quantité par convoi est un garde-fou** : elle existe pour qu'un
     * ordre permanent ne vide jamais la ville sans prévenir. Un ordre sans
     * quantité n'aurait pas de sens.
     */
    public function testUnOrdreExigeUnPrixEtUneQuantite(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('garde-fou@example.com');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/au moins un/');

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 3, 0);
    }

    /**
     * L'étal annonce la fourchette **avant** que le joueur ne s'engage : c'est
     * ce qui lui permet de viser, plutôt que de deviner.
     */
    public function testLEtalAnnonceLaFourchetteAvantDeSEngager(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('fourchette@example.com');
        $route = $partie->getVille()->routeVers('memphis');
        self::assertNotNull($route);

        $etal = $this->commerce()->etalDe($partie, $route);
        self::assertNotEmpty($etal);

        foreach ($etal as $ligne) {
            self::assertGreaterThan(0, $ligne['plancher'], $ligne['ressource']->libelle());
            self::assertGreaterThanOrEqual($ligne['plancher'], $ligne['plafond']);
            self::assertNull($ligne['ordre'], 'Rien n\'est encore annoncé.');
        }
    }

    /**
     * **Un convoi parti est un engagement pris** : la marchandise quitte les
     * réserves au départ, pas à l'arrivée. Débiter à l'arrivée permettrait de
     * vendre deux fois la même chose — partir, puis tout écouler au Marché
     * avant que la caravane n'atteigne son but.
     */
    public function testCeQuiPartEstDebiteAuDepartEtRapporteAuRetour(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('engagement@example.com');
        $ville = $partie->getVille();
        // La poterie, que Memphis achète aussi, ne se mange pas : le blé
        // fausserait la mesure, la ville en consommant à chaque quinzaine.
        $ville->crediterRessources([Ressource::Poterie->value => 300]);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Poterie, SensDEchange::Vendre, 8, 10);

        $poterieAvant = $ville->quantite(Ressource::Poterie);
        $debenAvant = $ville->getDeben();
        $this->cycle()->passer($partie);

        $convoi = $ville->routeVers('memphis')?->convoiPour(Ressource::Poterie);
        self::assertNotNull($convoi, 'Un convoi doit être parti.');
        self::assertSame($poterieAvant - $convoi->getQuantite(), $ville->quantite(Ressource::Poterie), 'La marchandise est partie avec lui.');
        self::assertSame($debenAvant, $ville->getDeben(), 'Rien n\'est encaissé avant le retour.');

        $attendu = $convoi->valeur();

        // Exactement l'aller-retour : au-delà, la caravane repartirait et
        // encaisserait une seconde fois.
        for ($i = 0; $i < 2 * self::DISTANCE_DE_MEMPHIS; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame($debenAvant + $attendu, $ville->getDeben());
    }

    /**
     * À l'achat, c'est la bourse qu'on engage : les deben partent, la
     * marchandise revient.
     */
    public function testUnAchatEngageLaBourseEtRameneLaMarchandise(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('achat@example.com');
        $ville = $partie->getVille();

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Calcaire, SensDEchange::Acheter, 20, 10);

        $debenAvant = $ville->getDeben();
        $calcaireAvant = $ville->quantite(Ressource::Calcaire);
        $this->cycle()->passer($partie);

        $convoi = $ville->routeVers('memphis')?->convoiPour(Ressource::Calcaire);
        self::assertNotNull($convoi);
        self::assertSame($debenAvant - $convoi->valeur(), $ville->getDeben(), 'La bourse est engagée au départ.');
        self::assertSame($calcaireAvant, $ville->quantite(Ressource::Calcaire), 'Rien n\'arrive avant le retour.');

        $quantite = $convoi->getQuantite();

        for ($i = 0; $i < 2 * self::DISTANCE_DE_MEMPHIS; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame($calcaireAvant + $quantite, $ville->quantite(Ressource::Calcaire));
    }

    /**
     * **Un seul convoi en chemin par ressource** : la caravane doit revenir
     * avant que la suivante ne parte. C'est ce qui donne son poids à la
     * distance — une cité lointaine commerce rarement, quelle que soit sa
     * générosité.
     */
    public function testUnSeulConvoiEnCheminParRessource(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('un-convoi@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 500]);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 2, 10);

        for ($i = 0; $i < 3; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertCount(1, $ville->routeVers('memphis')?->getConvois() ?? []);
    }

    /**
     * Une caravane rentrée repart chargée à neuf : le commerce est un flux,
     * pas un coup unique.
     */
    public function testUneCaravaneRentreeRepart(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('flux@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 500]);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 2, 10);

        $retours = 0;
        for ($i = 0; $i < 20; ++$i) {
            foreach ($this->cycle()->passer($partie) as $evenement) {
                if (str_contains($evenement, 'vendus')) {
                    ++$retours;
                }
            }
        }

        self::assertGreaterThanOrEqual(2, $retours, 'La caravane doit repartir après chaque retour.');
    }

    /**
     * **Rien ne part sans de quoi l'honorer** : ni le stock ni la bourse ne
     * descendent sous zéro, un ordre permanent ne vidant jamais la ville sans
     * prévenir.
     */
    public function testRienNePartSansDeQuoiLHonorer(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('sans-stock@example.com');
        $ville = $partie->getVille();
        $ville->debiterRessources([Ressource::Ble->value => $ville->quantite(Ressource::Ble)]);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 2, 10);
        $this->cycle()->passer($partie);

        self::assertNull($ville->routeVers('memphis')?->convoiPour(Ressource::Ble));
        self::assertSame(0, $ville->quantite(Ressource::Ble));
    }

    /**
     * Un prix hors fourchette ne conclut rien : aucun convoi ne part, et rien
     * n'est débité pour autant.
     */
    public function testUnPrixHorsFourchetteNeFaitPartirAucunConvoi(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('trop-cher@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Poterie->value => 300]);

        $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Poterie, SensDEchange::Vendre, 999, 10);

        $avant = $ville->quantite(Ressource::Poterie);
        $this->cycle()->passer($partie);

        self::assertNull($ville->routeVers('memphis')?->convoiPour(Ressource::Poterie));
        self::assertSame($avant, $ville->quantite(Ressource::Poterie));
    }

    /**
     * Retirer une annonce n'annule pas ce qui est déjà en route : on ne
     * rappelle pas une caravane partie il y a trois quinzaines.
     */
    public function testRetirerUnOrdreNAnnulePasUnConvoiDejaParti(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('rappel@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 300]);

        $ordre = $this->commerce()->poserUnOrdre($partie, 'memphis', Ressource::Ble, SensDEchange::Vendre, 2, 10);
        $this->cycle()->passer($partie);

        $route = $ville->routeVers('memphis');
        self::assertNotNull($route?->convoiPour(Ressource::Ble));

        $this->commerce()->retirerUnOrdre($ordre);
        $debenAvant = $ville->getDeben();

        for ($i = 0; $i < 20; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertGreaterThan($debenAvant, $ville->getDeben(), 'Le convoi parti est allé au bout.');
        self::assertCount(0, $route->getConvois(), 'Et rien n\'est reparti derrière.');
    }

    /**
     * **Le Négociateur élargit la fourchette des deux côtés** (doc 03) : on
     * vend plus cher, on achète moins cher. C'est la première spécialité de
     * l'Entrepôt à faire quelque chose.
     */
    public function testLeNegociateurElargitLaFourchette(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('negociateur@example.com');
        $ville = $partie->getVille();
        $byblos = (new CataloguePartenaires())->partenaire(1, 'memphis');
        self::assertNotNull($byblos);

        self::assertSame(0, $this->commerce()->avantageDuNegociateur($ville, $partie->getCycle()));

        $this->engagerUnChef($partie, TypeDeBatiment::Entrepot, SpecialiteDeChef::EntrepotNegociateur);
        $avantage = $this->commerce()->avantageDuNegociateur($ville, $partie->getCycle());

        self::assertSame(EffetDeChef::BONUS_NEGOCIATEUR, $avantage);
        self::assertGreaterThan(
            $byblos->prixMaximumALaVente(Ressource::Poterie) ?? 0,
            $byblos->prixMaximumALaVente(Ressource::Poterie, $avantage) ?? 0,
            'On doit pouvoir vendre plus cher.',
        );
        self::assertLessThan(
            $byblos->prixMinimumALAchat(Ressource::Calcaire) ?? 0,
            $byblos->prixMinimumALAchat(Ressource::Calcaire, $avantage) ?? 0,
            'Et acheter moins cher.',
        );
    }

    /**
     * **Le Logisticien raccourcit les trajets** (doc 03), mais jamais sous une
     * quinzaine : une route reste une route, et c'est la distance qui décide
     * de la fréquence des convois.
     */
    public function testLeLogisticienRaccourcitLesTrajetsSansLesAbolir(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecRouteOuverte('logisticien@example.com');
        $ville = $partie->getVille();
        $catalogue = new CataloguePartenaires();

        $pount = $catalogue->partenaire(3, 'pount');
        $memphis = $catalogue->partenaire(1, 'memphis');
        self::assertNotNull($pount);
        self::assertNotNull($memphis);

        self::assertSame(
            $pount->distanceEnQuinzaines,
            $this->commerce()->trajetVers($pount, $ville, $partie->getCycle()),
        );

        $this->engagerUnChef($partie, TypeDeBatiment::Entrepot, SpecialiteDeChef::EntrepotLogisticien);

        self::assertLessThan(
            $pount->distanceEnQuinzaines,
            $this->commerce()->trajetVers($pount, $ville, $partie->getCycle()),
        );
        self::assertGreaterThanOrEqual(
            1,
            $this->commerce()->trajetVers($memphis, $ville, $partie->getCycle()),
            'Même la cité la plus proche reste à une quinzaine.',
        );
    }

    private function engagerUnChef(GameSave $partie, TypeDeBatiment $type, SpecialiteDeChef $specialite): void
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
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $partie->getCycle(),
        ));
    }

    private function villeAvecRouteOuverte(string $email): GameSave
    {
        $partie = $this->villeQuiCommerce($email);
        $route = $this->commerce()->ouvrir($partie, 'memphis');

        while (!$route->estOuverte()) {
            $this->cycle()->passer($partie);
        }

        return $partie;
    }

    private function volumeVers(GameSave $partie, string $cle): int
    {
        foreach ($this->commerce()->offrePour($partie) as $offre) {
            if ($offre['partenaire']->cle === $cle) {
                return $offre['volume'];
            }
        }

        self::fail(\sprintf('Aucune offre vers %s.', $cle));
    }

    private function villeQuiCommerce(string $email, int $niveau = 2): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, $niveau));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, $niveau));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000, Ressource::Ble->value => 5_000]);

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

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
