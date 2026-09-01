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

    /**
     * **Deux mesures n'existent pas encore** : rien ne cumule la valeur
     * échangée, et rien ne distingue une ressource *rapportée* d'un stock
     * courant qui monte et descend. Le lot 8.1 les ajoute ; d'ici là, l'écran
     * annonce l'objectif sans mentir sur son avancement.
     *
     * Le piège d'`ajusterRenommee()` est exactement celui-là : une règle
     * indexée sur une valeur que rien ne fait bouger. Le dire vaut mieux que
     * d'afficher un zéro qui ne bougera jamais.
     */
    public function seMesureDeja(): bool
    {
        return match ($this) {
            self::Commerce, self::Ressource => false,
            default => true,
        };
    }
}
