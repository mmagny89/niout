<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AvantageDeNegoce;
use App\Game\CataloguePartenaires;
use App\Game\EffetDeChef;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\PartenaireCommercial;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La renommée infléchit les prix (doc 13, lot 9.3).
 *
 * Le document est chiffré : −0,2 % par point à l'achat, plafonné à −20 %, et la
 * majoration symétrique à la vente. Deux disciplines du projet s'y appliquent,
 * et ces tests les portent : **jamais de flottants**, et **un seul
 * multiplicateur par chaîne** — la renommée entre dans un facteur qui existe.
 */
final class RenommeeDansLesPrixTest extends KernelTestCase
{
    /**
     * Le compte du document, à la lettre : cent points valent vingt points de
     * pourcentage, et tout se lit en entiers.
     */
    public function testCentPointsDeRenommeeValentVingtPourCent(): void
    {
        self::assertSame(0, AvantageDeNegoce::deLaRenommee(0));
        self::assertSame(2, AvantageDeNegoce::deLaRenommee(10));
        self::assertSame(10, AvantageDeNegoce::deLaRenommee(50));
        self::assertSame(20, AvantageDeNegoce::deLaRenommee(Family::RENOMMEE_MAX));
    }

    /**
     * **Le plafond porte sur la somme, pas sur chaque source** (arbitrage
     * 9.0) : trois plafonds séparés se cumulent et n'en plafonnent aucun. Un
     * Négociateur en poste et une famille illustre dépassent ensemble ce que le
     * commerce peut supporter — et c'est là que le plafond doit mordre.
     */
    public function testLePlafondPorteSurLaSommeEtNonSurChaqueSource(): void
    {
        $sansPlafond = AvantageDeNegoce::deLaRenommee(Family::RENOMMEE_MAX) + EffetDeChef::BONUS_NEGOCIATEUR;

        self::assertGreaterThan(AvantageDeNegoce::PLAFOND_TOTAL, $sansPlafond);
        self::assertSame(
            AvantageDeNegoce::PLAFOND_TOTAL,
            AvantageDeNegoce::total(Family::RENOMMEE_MAX, EffetDeChef::BONUS_NEGOCIATEUR),
        );
    }

    /**
     * **Le plancher d'achat ne descend jamais sous le cours local.** C'est la
     * raison d'être du plafond, et non un chiffre choisi au hasard : si importer
     * coûtait moins que produire sur place, la distance, les routes et les
     * convois cesseraient de peser quoi que ce soit.
     *
     * L'écart n'est **strict que sur les ressources qui valent une dizaine de
     * deben**. En dessous, la division entière avale tout l'avantage : c'est le
     * prix des prix comptés en entiers, assumé partout dans le projet, et cela
     * ne dessert personne — le commerce lointain ne porte pas de l'argile.
     */
    public function testLePlancherDachatNeDescendJamaisSousLeCoursLocal(): void
    {
        $partenaire = $this->unPartenaireQuiVend();

        foreach ($partenaire->vend as $ressource) {
            $cours = PrixDuMarche::pour($ressource);

            if (null === $cours) {
                continue;
            }

            self::assertGreaterThanOrEqual(
                $cours,
                $partenaire->prixMinimumALAchat($ressource, AvantageDeNegoce::PLAFOND_TOTAL),
                \sprintf('Importer du %s reviendrait moins cher que le cours local.', $ressource->libelle()),
            );
        }

        // L'écart reste strict dès que la ressource vaut assez pour qu'un
        // pourcentage ait de quoi mordre. C'est l'arithmétique du plafond
        // qu'on vérifie ici, indépendamment de ce que tel partenaire porte.
        $cours = PrixDuMarche::pour(Ressource::LapisLazuli);
        self::assertNotNull($cours);

        self::assertGreaterThan(
            $cours,
            intdiv(
                $cours * max(100, PartenaireCommercial::PRIX_MINIMUM_A_LACHAT - AvantageDeNegoce::PLAFOND_TOTAL),
                100,
            ),
        );
    }

    /**
     * Une famille connue achète moins cher et vend plus cher : la fourchette
     * s'élargit des deux côtés, exactement comme celle du Négociateur — c'est
     * bien le même facteur.
     */
    public function testLaRenommeeElargitLaFourchetteDesDeuxCotes(): void
    {
        $partenaire = $this->unPartenaireQuiVend();
        $illustre = AvantageDeNegoce::total(Family::RENOMMEE_MAX);

        // Sur les ressources qui valent quelque chose : l'argile à un deben ne
        // laisse rien à arrondir.
        $achat = $this->laPlusChere($partenaire->vend);
        self::assertLessThan(
            $partenaire->prixMinimumALAchat($achat, 0),
            $partenaire->prixMinimumALAchat($achat, $illustre),
        );

        $vente = $this->laPlusChere($partenaire->achete);
        self::assertGreaterThan(
            $partenaire->prixMaximumALaVente($vente, 0),
            $partenaire->prixMaximumALaVente($vente, $illustre),
        );
    }

    /**
     * Au Marché, la renommée se voit sur la recette. Elle s'ajoute au
     * coefficient de la chaîne plutôt que de s'appliquer après lui : une
     * multiplication, une division, et pas de deben perdus dans une seconde
     * division entière.
     */
    public function testUneFamilleConnueVendPlusCherAuMarche(): void
    {
        self::bootKernel();

        $inconnue = $this->lancerAvecMarche('prix-inconnue@example.com');
        $connue = $this->lancerAvecMarche('prix-connue@example.com');
        $connue->getFamille()->ajusterRenommee(Family::RENOMMEE_MAX);

        foreach ([$inconnue, $connue] as $partie) {
            $partie->getVille()->crediterRessources([Ressource::Calcaire->value => 10]);
        }

        $sansRenom = $this->marche()->vendre($inconnue, Ressource::Calcaire, 10);
        $avecRenom = $this->marche()->vendre($connue, Ressource::Calcaire, 10);

        self::assertGreaterThan($sansRenom, $avecRenom);

        // Le compte exact : le coefficient de direction, plus les vingt points
        // de la renommée pleine, appliqués en une seule fois.
        $prix = PrixDuMarche::pour(Ressource::Calcaire);
        self::assertNotNull($prix);
        self::assertSame(
            intdiv($prix * 10 * (50 + AvantageDeNegoce::deLaRenommee(Family::RENOMMEE_MAX)), 100),
            $avecRenom,
        );
    }

    /**
     * @param list<Ressource> $ressources
     */
    private function laPlusChere(array $ressources): Ressource
    {
        $meilleure = null;
        $prix = 0;

        foreach ($ressources as $ressource) {
            $cours = PrixDuMarche::pour($ressource);

            if (null !== $cours && $cours > $prix) {
                $meilleure = $ressource;
                $prix = $cours;
            }
        }

        self::assertNotNull($meilleure, 'Aucune de ces ressources ne se négocie.');

        return $meilleure;
    }

    private function unPartenaireQuiVend(): PartenaireCommercial
    {
        self::bootKernel();
        $catalogue = static::getContainer()->get(CataloguePartenaires::class);

        foreach ($catalogue->pourLaMission(1) as $partenaire) {
            if ([] !== $partenaire->vend && [] !== $partenaire->achete) {
                return $partenaire;
            }
        }

        self::fail('Aucun partenaire de la première mission ne commerce dans les deux sens.');
    }

    private function lancerAvecMarche(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));

        return $partie;
    }

    private function marche(): Marche
    {
        return static::getContainer()->get(Marche::class);
    }
}
