<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\MissionCatalogue;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Les douze ressources que le travail produit (doc 08) : sept à l'Atelier,
 * deux à la Forge, trois au craft de luxe.
 */
#[CoversClass(Ressource::class)]
#[CoversClass(PrixDuMarche::class)]
final class RessourcesFabriqueesTest extends TestCase
{
    /**
     * **Aucune ne pousse.** Une ressource fabriquée qu'une région déclarerait
     * en ressource de zone se retrouverait en gisement — de la poterie dans le
     * sable. Le contrôle porte sur les dix missions, à la source.
     */
    public function testAucuneRegionNeDeclareUneRessourceFabriquee(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            foreach ($mission->geographie->ressourcesDeZone as $ressource) {
                self::assertFalse(
                    $ressource->estFabriquee(),
                    \sprintf('%s déclare %s en ressource de zone.', $mission->ville, $ressource->libelle()),
                );
            }
        }
    }

    /**
     * Principe de commerce universel (doc 08) : n'importe quelle ressource
     * peut être vendue. Un objet fabriqué sans prix serait un objet qu'on ne
     * peut ni écouler ni employer — le craft n'aurait aucun intérêt.
     */
    public function testChaqueRessourceFabriqueeSeVend(): void
    {
        foreach (Ressource::fabriquees() as $ressource) {
            self::assertNotNull(
                PrixDuMarche::pour($ressource),
                \sprintf('%s ne se vend nulle part.', $ressource->libelle()),
            );
        }
    }

    /**
     * **Transformer doit rapporter.** Le doc 08 chiffre ce que coûte chaque
     * recette, jamais ce que l'objet vaut : les prix s'en déduisent, à environ
     * deux tiers de plus. En deçà personne ne fabriquerait — vendre brut irait
     * aussi vite sans immobiliser l'Atelier ; au-delà, vendre brut n'aurait
     * plus jamais de sens.
     *
     * La table des recettes est reprise du doc 08 telle quelle. **Le lot 5.2
     * la remplacera** par un vrai catalogue, à plusieurs ingrédients (décision
     * de la joueuse) : ce test devra alors s'y adosser plutôt qu'à cette copie.
     */
    public function testUnObjetVautEnvironDeuxTiersDePlusQueSaFabrication(): void
    {
        $total = 0;
        $recettes = 0;
        $laPlusFaible = 1000;
        $laPlusForte = 0;

        foreach (self::recettesDuDocument() as $objet => [$ingredients, $deben]) {
            $cout = $deben;

            foreach ($ingredients as $valeur => $quantite) {
                $prix = PrixDuMarche::pour(Ressource::from($valeur));
                self::assertNotNull($prix);
                $cout += $prix * $quantite;
            }

            $prixDeVente = PrixDuMarche::pour(Ressource::from($objet));
            self::assertNotNull($prixDeVente);

            $marge = intdiv(100 * $prixDeVente, $cout);

            self::assertGreaterThan(100, $marge, \sprintf('%s se vendrait à perte.', $objet));

            $total += $marge;
            ++$recettes;
            $laPlusFaible = min($laPlusFaible, $marge);
            $laPlusForte = max($laPlusForte, $marge);
        }

        $moyenne = intdiv($total, $recettes);

        self::assertEqualsWithDelta(
            PrixDuMarche::MARGE_DE_TRANSFORMATION,
            $moyenne,
            5,
            \sprintf('Marge moyenne mesurée : %d %%.', $moyenne),
        );
        self::assertGreaterThan(140, $laPlusFaible, 'Une recette trop peu rentable pour valoir l\'Atelier.');
        self::assertLessThan(200, $laPlusForte, 'Une recette qui rendrait la vente brute absurde.');
    }

    /**
     * Le pain et la bière nourrissent : ce sont les deux formes sous
     * lesquelles l'Égypte consommait son grain, et les ostraca de Deir
     * el-Médineh paient les ouvriers en pains et en cruches, pas en épis.
     * Les autres objets fabriqués, non — on ne mange pas des sandales.
     */
    public function testSeulsLePainEtLaBiereNourrissentParmiLesObjetsFabriques(): void
    {
        $nourrissants = array_values(array_filter(
            Ressource::fabriquees(),
            static fn (Ressource $r): bool => $r->estNourriture(),
        ));

        self::assertSame([Ressource::Pain, Ressource::Biere], $nourrissants);
    }

    /**
     * Un objet fabriqué n'est ni agricole — il ne sort pas d'un champ — ni
     * « toujours importé » : on peut le produire soi-même, c'est même le
     * propos.
     */
    public function testUnObjetFabriqueNestNiAgricoleNiToujoursImporte(): void
    {
        foreach (Ressource::fabriquees() as $ressource) {
            self::assertFalse($ressource->estAgricole(), $ressource->libelle());
            self::assertFalse($ressource->estToujoursImportee(), $ressource->libelle());
            self::assertFalse($ressource->estRenouvelable(), $ressource->libelle());
        }
    }

    /**
     * On ne bâtit pas avec des objets manufacturés : la brique crue, le roseau
     * et le bois local suffisent (doc 01). Le jour où un bâtiment réclamera des
     * outils, ce sera une décision, pas un glissement.
     */
    public function testAucunBatimentNeSeConstruitEnObjetsFabriques(): void
    {
        foreach (TypeDeBatiment::cases() as $type) {
            foreach ($type->coutDeBase()->ressources() as $ressource) {
                self::assertFalse(
                    $ressource->estFabriquee(),
                    \sprintf('Le %s réclame %s.', $type->libelle(), $ressource->libelle()),
                );
            }
        }
    }

    /**
     * Les recettes du doc 08, reprises telles quelles — à remplacer par le
     * catalogue du lot 5.2.
     *
     * @return array<string, array{array<string, int>, int}>
     */
    private static function recettesDuDocument(): array
    {
        return [
            'poterie' => [['argile' => 5], 2],
            'pain' => [['ble' => 5], 1],
            'biere' => [['orge' => 5], 2],
            'vannerie' => [['roseaux' => 4], 2],
            'papyrus' => [['roseaux' => 6], 3],
            'sandales' => [['roseaux' => 4], 2],
            'tissus' => [['lin' => 8], 5],
            'bijoux' => [['or' => 3, 'turquoise' => 2], 10],
            'statuettes' => [['bois_de_cedre' => 4, 'ivoire' => 2], 15],
            'vases' => [['albatre' => 6], 8],
        ];
    }
}
