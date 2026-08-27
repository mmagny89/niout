<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les deux familles de matériaux dans lesquelles s'expriment les coûts de
 * construction du doc 01 : « bois » et « pierre ».
 *
 * Ce ne sont pas des ressources mais des **catégories**. Le doc 01 chiffre tous
 * ses bâtiments en bois et en pierre ; le doc 08 ne connaît ni l'un ni l'autre,
 * seulement des matériaux nommés — calcaire, grès, granite d'un côté, roseaux
 * et bois de cèdre de l'autre. Aucun document ne raccorde les deux listes.
 *
 * Prise au pied de la lettre, cette contradiction rendrait la première mission
 * injouable : le Delta ne porte qu'argile, roseaux et calcaire, donc ni « bois »
 * ni « pierre ». Un coût se paie donc avec n'importe quel matériau de la
 * famille demandée, et chaque région fournit le sien — ce qui est aussi la
 * réalité historique : on bâtissait avec la pierre qu'on avait sous la main.
 *
 * L'argile relève de la maçonnerie, décision de conception : le doc 01 précise
 * que la quasi-totalité des bâtiments sont en brique crue, faite du limon du
 * fleuve. Un grenier du Delta se bâtit en brique, un temple en calcaire, et
 * tous deux paient la même ligne.
 */
enum FamilleDeMateriau: string
{
    case Bois = 'bois';
    case Pierre = 'pierre';

    public function libelle(): string
    {
        return match ($this) {
            self::Bois => 'bois',
            self::Pierre => 'pierre',
        };
    }

    /**
     * Ce qui se substitue à un « bois de charpente » sous un climat qui n'en
     * produit pas : le doc 01 décrit lui-même les toitures en troncs de palmier
     * et en nattes — donc en roseau. Hors Levant, aucune région d'Égypte n'a de
     * bois d'œuvre, et les roseaux sont ce avec quoi on couvrait réellement.
     *
     * @return list<Ressource>
     */
    public function ressources(): array
    {
        return match ($this) {
            self::Bois => [Ressource::Roseaux, Ressource::BoisDeCedre],
            self::Pierre => [
                Ressource::Argile,
                Ressource::Calcaire,
                Ressource::Gres,
                Ressource::Grauwacke,
                Ressource::Albatre,
                Ressource::Granite,
            ],
        };
    }

    public function contient(Ressource $ressource): bool
    {
        return \in_array($ressource, $this->ressources(), true);
    }
}
