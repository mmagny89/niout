<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Game\DotationRoyale;
use App\Game\Ressource;
use App\Game\Stockage;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Stockage::class)]
final class StockageTest extends TestCase
{
    /**
     * **La vérification que le plan réclamait avant d'écrire ce lot** : le
     * pharaon ne doit pas faire un cadeau dont une partie s'évapore à la
     * première quinzaine.
     *
     * Et pas au ras : une ville qui démarrerait pleine perdrait sa première
     * carrière avant d'avoir compris qu'elle a un plafond.
     */
    public function testLaDotationRoyaleTientDansLesReservesDeDepart(): void
    {
        $ville = new City('Avaris', 0, 3);
        $dotation = DotationRoyale::pour(0, 7);

        $vivres = 0;
        $materiaux = 0;

        foreach ($dotation->enRessources() as $valeur => $quantite) {
            $ressource = Ressource::from($valeur);

            if ($ressource->estLaMonnaie()) {
                continue;
            }

            if ($ressource->estNourriture()) {
                $vivres += $quantite;
            } else {
                $materiaux += $quantite;
            }
        }

        self::assertLessThan(Stockage::plafondDesVivres($ville), $vivres);
        self::assertLessThan(Stockage::plafondDesMateriaux($ville), $materiaux);

        // Et avec de la marge : la ville ne démarre pas en alerte.
        self::assertFalse(Stockage::saturationProche($vivres, Stockage::plafondDesVivres($ville)));
        self::assertFalse(Stockage::saturationProche($materiaux, Stockage::plafondDesMateriaux($ville)));
    }

    /**
     * Le doc 01 chiffre le Grenier à `100 × niveau` unités de nourriture ; le
     * chiffre de l'Entrepôt est inventé. Dans les deux cas, monter le bâtiment
     * doit élever le plafond — c'est le seul effet concret que ces niveaux
     * avaient à gagner.
     */
    public function testMonterLeGrenierEtLEntrepotEleveLesPlafonds(): void
    {
        $ville = new City('Avaris', 0, 3);

        $vivresNus = Stockage::plafondDesVivres($ville);
        $materiauxNus = Stockage::plafondDesMateriaux($ville);

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, niveau: 3));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, niveau: 2));

        self::assertSame($vivresNus + 3 * Stockage::VIVRES_PAR_NIVEAU_DE_GRENIER, Stockage::plafondDesVivres($ville));
        self::assertSame($materiauxNus + 2 * Stockage::MATERIAUX_PAR_NIVEAU_DENTREPOT, Stockage::plafondDesMateriaux($ville));
    }

    /**
     * **Le deben n'est pas stocké.** La monnaie n'occupe ni grenier ni
     * entrepôt : c'est ce qui fait de la vente l'issue au surplus, une valeur
     * qui ne déborde jamais.
     */
    public function testLaMonnaieNaAucunPlafond(): void
    {
        $ville = new City('Avaris', 0, 3);

        self::assertNull(Stockage::plafondPour($ville, Ressource::Deben));
        self::assertNotNull(Stockage::plafondPour($ville, Ressource::Argile));

        $ville->crediterRessources([Ressource::Deben->value => 1_000_000]);

        self::assertSame(1_000_000, $ville->getDeben());
    }

    /**
     * Le surplus ne rentre pas ; ce qui est rangé, lui, y reste — la
     * péremption du doc 01 est écartée (décision de la joueuse).
     */
    public function testCeQuiDepasseNeRentrePasEtLeResteNeSAbimeJamais(): void
    {
        $ville = new City('Avaris', 0, 3);
        $plafond = Stockage::plafondDesMateriaux($ville);

        $ville->crediterRessources([Ressource::Argile->value => $plafond + 500]);

        self::assertSame($plafond, $ville->quantite(Ressource::Argile));
        self::assertSame($plafond, $ville->getMateriaux());

        // Rien ne se dégrade : recréditer zéro ne retire rien non plus.
        $ville->crediterRessources([Ressource::Argile->value => 0]);

        self::assertSame($plafond, $ville->quantite(Ressource::Argile));
    }

    /**
     * **Les matériaux se partagent une seule réserve** : ranger des roseaux,
     * c'est autant de moins pour l'argile. C'est ce qui rend l'Entrepôt
     * intéressant à monter plutôt qu'un compteur par ressource, qu'aucun
     * joueur n'aurait à surveiller.
     */
    public function testLesMateriauxSePartagentLaMemeReserve(): void
    {
        $ville = new City('Avaris', 0, 3);
        $plafond = Stockage::plafondDesMateriaux($ville);

        $ville->crediterRessources([Ressource::Roseaux->value => $plafond]);
        $ville->crediterRessources([Ressource::Argile->value => 50]);

        self::assertSame($plafond, $ville->getMateriaux(), 'La réserve était déjà pleine.');
        self::assertSame(0, $ville->quantite(Ressource::Argile));
    }

    /**
     * Les vivres et les matériaux ont **deux réserves distinctes** : un
     * grenier plein n'empêche pas de rentrer de l'argile.
     */
    public function testUneReservePleineNeBloquePasLAutre(): void
    {
        $ville = new City('Avaris', 0, 3);

        $ville->crediterRessources([Ressource::Ble->value => Stockage::plafondDesVivres($ville) + 100]);
        $ville->crediterRessources([Ressource::Argile->value => 40]);

        self::assertSame(40, $ville->quantite(Ressource::Argile));
    }

    /**
     * `surplusRefuse()` doit annoncer exactement ce que `crediterRessources()`
     * laissera dehors — c'est sur cette promesse que le passage de cycle dit
     * au joueur ce qu'il a perdu.
     */
    public function testLeSurplusAnnonceEstCeluiQuiEstReellementRefuse(): void
    {
        $ville = new City('Avaris', 0, 3);
        $ville->crediterRessources([Ressource::Roseaux->value => Stockage::plafondDesMateriaux($ville) - 10]);

        $arrivage = [Ressource::Argile->value => 30, Ressource::Calcaire->value => 20];

        $annonce = $ville->surplusRefuse($arrivage);
        $avant = $ville->getMateriaux();
        $ville->crediterRessources($arrivage);

        $rentre = $ville->getMateriaux() - $avant;
        $demande = array_sum($arrivage);

        self::assertSame($demande - $rentre, array_sum($annonce));
        self::assertSame(10, $rentre, 'Il ne restait que dix places.');
    }

    /**
     * L'alerte doit précéder la perte : l'écran prévient à 85 % de la réserve,
     * pas quand la moisson est déjà à terre.
     */
    public function testLAlerteSeDeclencheAvantLaSaturation(): void
    {
        self::assertFalse(Stockage::saturationProche(80, 100));
        self::assertTrue(Stockage::saturationProche(85, 100));
        self::assertTrue(Stockage::saturationProche(100, 100));
    }
}
