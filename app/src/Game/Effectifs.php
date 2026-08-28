<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\City;

/**
 * Qui tient les bâtiments, et à quel rendement (doc 01, doc 05).
 *
 * Deux formules viennent du doc 01 :
 *
 * - `nbChefs(niveau) = arrondiSupérieur(niveau / 3)` — porté par
 *   `Building::nombreDeChefs()`, avancé au lot 4.3.
 * - `travailleursParChef(niveau) = travailleursDeBase + arrondiInférieur((niveau - 1) / 3)`.
 *
 * **Ce sont les chefs qui recrutent** (doc 05). Un bâtiment sans chef ne
 * réclame donc aucun travailleur — et c'est ce qui donne sa définition au
 * demi-rendement : sans personne pour l'ouvrir, il tourne à moitié.
 *
 * **Rien ne s'éteint faute d'employés** (décision de la joueuse) : le doc 01
 * ne parlait que de « capacité réduite », jamais de l'arrêt. Une partie sans
 * deben pour embaucher continue de tourner, au ralenti. Les employés cessent
 * ainsi d'être une taxe obligatoire pour devenir un investissement — c'est ce
 * qui rend la phase jouable.
 *
 * ```
 * rendement = 0,5 + 0,5 × (effectif réel / effectif requis)
 * ```
 *
 * Compté **en centièmes**, jamais en flottants : c'est la règle du projet, et
 * elle importe ici plus qu'ailleurs — ce rendement multipliera des quantités
 * de ressources, où un demi perdu à chaque quinzaine finirait par se voir.
 *
 * Rien n'est persisté : tout se dérive de la population, des niveaux et des
 * chefs en poste, comme le niveau maximal régional se dérive de la difficulté.
 */
final readonly class Effectifs
{
    /**
     * Le rendement d'un bâtiment que personne ne tient. Le plancher, jamais
     * zéro.
     */
    public const int RENDEMENT_PLANCHER = 50;
    public const int RENDEMENT_PLEIN = 100;

    /**
     * Combien de travailleurs un chef encadre à ce niveau (doc 01).
     */
    public static function travailleursParChef(TypeDeBatiment $type, int $niveau): int
    {
        return $type->travailleursDeBase() + intdiv($niveau - 1, 3);
    }

    /**
     * Combien de travailleurs ce bâtiment réclame en tout : ce que chacun de
     * ses chefs **en poste** encadre.
     *
     * Un chef embauché mais pas encore à l'ouvrage ne compte pas : il n'a rien
     * recruté tant qu'il n'a pas pris son poste (doc 05).
     */
    public static function travailleursRequis(Building $batiment, int $cycle): int
    {
        return self::chefsEnPoste($batiment, $cycle)
            * self::travailleursParChef($batiment->getType(), $batiment->getNiveau());
    }

    public static function chefsEnPoste(Building $batiment, int $cycle): int
    {
        $enPoste = 0;

        foreach ($batiment->getVille()->chefsDe($batiment->getType()) as $chef) {
            if ($chef->estEnPoste($cycle)) {
                ++$enPoste;
            }
        }

        return $enPoste;
    }

    /**
     * Le rendement d'un bâtiment, en centièmes, selon ce qu'il a réellement de
     * bras face à ce qu'il en réclame.
     */
    public static function rendementEnCentiemes(int $affectes, int $requis): int
    {
        if ($requis <= 0) {
            return self::RENDEMENT_PLANCHER;
        }

        return self::RENDEMENT_PLANCHER + intdiv(
            self::RENDEMENT_PLANCHER * min($affectes, $requis),
            $requis,
        );
    }

    /**
     * Les bras disponibles pour tenir les bâtiments : les actifs de la ville,
     * moins les chefs, qui en font partie et ne s'encadrent pas eux-mêmes.
     */
    public static function brasDisponibles(City $ville, int $cycle): int
    {
        $chefs = 0;

        foreach ($ville->getEmployes() as $chef) {
            if ($chef->estEnPoste($cycle)) {
                ++$chefs;
            }
        }

        return max(0, $ville->getActifs() - $chefs);
    }

    /**
     * Répartit les bras de la ville entre ses bâtiments, et rend pour chacun
     * son effectif et son rendement.
     *
     * **L'ordre de service est alphabétique**, donc stable et explicable, mais
     * arbitraire : le joueur ne peut pas encore dire quel bâtiment servir en
     * premier quand les bras manquent. C'est un manque assumé de ce lot, à
     * reprendre si le playtest montre qu'il coûte des parties.
     *
     * @return array<string, array{batiment: Building, requis: int, affectes: int, rendement: int}>
     *                                                                                              indexé par la valeur du type de bâtiment
     */
    public static function repartir(City $ville, int $cycle): array
    {
        $batiments = array_values($ville->getBatiments()->toArray());
        usort(
            $batiments,
            static fn (Building $a, Building $b): int => $a->getType()->libelle() <=> $b->getType()->libelle(),
        );

        $bras = self::brasDisponibles($ville, $cycle);
        $repartition = [];

        foreach ($batiments as $batiment) {
            $requis = self::travailleursRequis($batiment, $cycle);
            $affectes = min($requis, $bras);
            $bras -= $affectes;

            $repartition[$batiment->getType()->value] = [
                'batiment' => $batiment,
                'requis' => $requis,
                'affectes' => $affectes,
                'rendement' => self::rendementEnCentiemes($affectes, $requis),
            ];
        }

        return $repartition;
    }

    /**
     * Le rendement d'un bâtiment donné, ou le plancher s'il n'est pas dressé —
     * un bâtiment absent ne produit pas « rien », il n'a simplement pas de
     * rendement à faire valoir, et c'est à l'appelant de vérifier sa présence.
     */
    public static function rendementDe(City $ville, TypeDeBatiment $type, int $cycle): int
    {
        return self::repartir($ville, $cycle)[$type->value]['rendement'] ?? self::RENDEMENT_PLANCHER;
    }
}
