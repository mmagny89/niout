<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que valent les ressources à la vente locale (doc 08).
 *
 * Le doc 08 pose le principe — « toute ressource peut toujours être achetée ou
 * vendue » — et chiffre l'import (`prixLocal × 1,5`) et les biens exotiques
 * (15 or l'unité), mais **jamais les prix locaux eux-mêmes**. Ceux-ci sont donc
 * inventés, calibrés en ordre de grandeur les uns par rapport aux autres :
 * l'argile et les roseaux, qu'on ramasse au bord du fleuve, ne valent presque
 * rien ; le granite d'Assouan et la turquoise du Sinaï valent qu'on aille les
 * chercher.
 *
 * À revoir au premier playtest — c'est le curseur qui décide si l'or reste rare.
 */
final readonly class PrixDuMarche
{
    /**
     * Prix de vente unitaire, en or. Une ressource absente de cette table ne se
     * vend pas au Marché local.
     *
     * @return array<string, int>
     */
    public static function table(): array
    {
        return [
            // Matériaux communs, ramassés sur les berges.
            Ressource::Argile->value => 1,
            Ressource::Roseaux->value => 1,
            // Vivres et textile.
            Ressource::Ble->value => 2,
            Ressource::Orge->value => 2,
            Ressource::Dattes->value => 2,
            Ressource::Poisson->value => 2,
            Ressource::Lin->value => 4,
            // Pierres de taille, par dureté croissante d'extraction.
            Ressource::Calcaire->value => 3,
            Ressource::Gres->value => 4,
            Ressource::Albatre->value => 6,
            Ressource::Grauwacke->value => 7,
            Ressource::Granite->value => 8,
            // Minerais et biens de prestige.
            Ressource::Natron->value => 4,
            Ressource::Sel->value => 4,
            Ressource::Cuivre->value => 8,
            Ressource::Turquoise->value => 12,
            Ressource::Encens->value => 12,
            Ressource::Myrrhe->value => 12,
            Ressource::BoisDeCedre->value => 10,
            Ressource::Ivoire->value => 14,
            Ressource::Ebene->value => 14,
            Ressource::LapisLazuli->value => 15,
            Ressource::PeauxEtPlumes->value => 15,
        ];
    }

    /**
     * Prix unitaire d'une ressource, ou null si elle ne se négocie pas. L'or ne
     * s'y trouve pas : il est la monnaie, pas une marchandise.
     */
    public static function pour(Ressource $ressource): ?int
    {
        return self::table()[$ressource->value] ?? null;
    }

    public static function seVend(Ressource $ressource): bool
    {
        return null !== self::pour($ressource);
    }
}
