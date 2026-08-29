<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'une case porte, indépendamment de son terrain (doc 02).
 *
 * « Rien » est un résultat normal, pas un défaut de génération : un cinquième
 * des cases environ reste vide, pour que l'exploration ne soit jamais une
 * récompense garantie.
 *
 * L'ancien cas « terre fertile, mais non cultivable » a disparu : ce n'était
 * qu'une case fertile que le tirage n'avait pas retenue — un manque déguisé en
 * contenu. Le doc 02 en a fait un vrai terrain, `TypeDeTerrain::TerreClassique`,
 * avec sa ressource caractéristique.
 */
enum ContenuDeZone: string
{
    case Rien = 'rien';
    case Ressource = 'ressource';
    case ChampEligible = 'champ_eligible';
    case Evenement = 'evenement';

    public function libelle(): string
    {
        return match ($this) {
            self::Rien => 'Rien de notable',
            self::Ressource => 'Gisement',
            self::ChampEligible => 'Terre cultivable',
            self::Evenement => 'Quelque chose s\'y trame',
        };
    }
}
