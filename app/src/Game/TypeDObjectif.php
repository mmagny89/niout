<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les six façons dont un pharaon juge une mission (doc 09).
 *
 * Chaque mission en retient deux ou trois, choisies selon ce que son
 * commanditaire avait réellement en tête : le commerce pour Ahmôsis qui sort
 * d'un conflit, la pierre pour Amenhotep III qui bâtit une cité de prestige,
 * la turquoise pour Ramsès IV au Sinaï.
 */
enum TypeDObjectif: string
{
    case Richesse = 'richesse';
    case Population = 'population';
    case Commerce = 'commerce';
    case Infrastructure = 'infrastructure';
    case Renommee = 'renommee';
    case Ressource = 'ressource';

    public function libelle(): string
    {
        return match ($this) {
            self::Richesse => 'Trésorerie',
            self::Population => 'Population',
            self::Commerce => 'Commerce',
            self::Infrastructure => 'Infrastructure',
            self::Renommee => 'Renommée',
            self::Ressource => 'Ressource',
        };
    }
}
