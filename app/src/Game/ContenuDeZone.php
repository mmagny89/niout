<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'une case porte, indépendamment de son terrain (doc 02).
 *
 * « Rien » est un résultat normal, pas un défaut de génération : un cinquième
 * des cases environ reste vide, pour que l'exploration ne soit jamais une
 * récompense garantie.
 */
enum ContenuDeZone: string
{
    case Rien = 'rien';
    case Ressource = 'ressource';
    case ChampEligible = 'champ_eligible';
    case TerreNonCultivable = 'terre_non_cultivable';
    case Evenement = 'evenement';

    public function libelle(): string
    {
        return match ($this) {
            self::Rien => 'Rien de notable',
            self::Ressource => 'Gisement',
            self::ChampEligible => 'Terre cultivable',
            self::TerreNonCultivable => 'Terre fertile, mais non cultivable',
            self::Evenement => 'Quelque chose s\'y trame',
        };
    }
}
