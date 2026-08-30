<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que valent les ressources à la vente locale (doc 08).
 *
 * Le doc 08 pose le principe — « toute ressource peut toujours être achetée ou
 * vendue » — et chiffre l'import (`prixLocal × 1,5`) et les biens exotiques
 * (15 l'unité), mais **jamais les prix locaux eux-mêmes**. Ceux-ci sont donc
 * inventés, calibrés en ordre de grandeur les uns par rapport aux autres :
 * l'argile et les roseaux, qu'on ramasse au bord du fleuve, ne valent presque
 * rien ; le granite d'Assouan et la turquoise du Sinaï valent qu'on aille les
 * chercher.
 *
 * À revoir au premier playtest — c'est le curseur qui décide si le deben reste
 * rare.
 */
final readonly class PrixDuMarche
{
    /**
     * Ce qu'un objet fabriqué vaut, rapporté à ce qu'il coûte à produire, en
     * centièmes. **Valeur inventée** : le doc 08 chiffre les recettes mais pas
     * les prix de vente des objets.
     *
     * Environ deux tiers de plus que la matière et le deben qu'on y met. En
     * deçà, personne ne fabriquerait — vendre brut irait aussi vite et
     * n'immobiliserait pas l'Atelier. Au-delà, vendre brut n'aurait plus jamais
     * de sens, et la moitié du commerce disparaîtrait.
     *
     * **À revérifier au lot 5.2**, qui réécrit les recettes du doc 08 à
     * plusieurs ingrédients (décision de la joueuse) : le coût de production
     * changera, la marge doit tenir malgré tout.
     */
    public const int MARGE_DE_TRANSFORMATION = 165;

    /**
     * Prix de vente unitaire, en deben. Une ressource absente de cette table ne
     * se vend pas au Marché local — c'est le cas du deben lui-même, qui est la
     * monnaie et non une marchandise.
     *
     * @return array<string, int>
     */
    public static function table(): array
    {
        return [
            // L'or n'est plus la monnaie mais un métal, extrait au désert
            // oriental et en Nubie (doc 08) : le plus cher du jeu. Le rapport
            // réel de l'or au cuivre sous le Nouvel Empire était bien plus
            // écrasant que ce ×2 sur le lapis-lazuli — la valeur est comprimée
            // pour rester jouable, comme le reste de cette table.
            Ressource::Or->value => 30,
            // Matériaux communs, ramassés sur les berges.
            Ressource::Argile->value => 1,
            Ressource::Roseaux->value => 1,
            // Deux deben : plus qu'un roseau, très loin du cèdre à dix (doc 08).
            Ressource::BoisLocal->value => 2,
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

            // Ressources fabriquées. Le doc 08 chiffre ce qu'elles coûtent à
            // produire, jamais ce qu'elles valent : les prix ci-dessous s'en
            // **déduisent**, à environ `MARGE_DE_TRANSFORMATION` du coût.
            // Transformer doit rapporter plus que vendre la matière brute,
            // sans quoi personne ne fabriquerait rien ; mais pas au point que
            // vendre brut n'ait plus jamais de sens.
            Ressource::Poterie->value => 12,     // 5 argile + 2 deben = 7
            Ressource::Pain->value => 18,        // 5 blé + 1 deben = 11
            Ressource::Biere->value => 20,       // 5 orge + 2 deben = 12
            Ressource::Vannerie->value => 10,    // 4 roseaux + 2 deben = 6
            Ressource::Papyrus->value => 15,     // 6 roseaux + 3 deben = 9
            Ressource::Sandales->value => 10,    // 4 roseaux + 2 deben = 6
            Ressource::Tissus->value => 60,      // 8 lin + 5 deben = 37

            // La Forge : le doc 08 ne chiffre ni ses recettes ni ses prix.
            // Comptés sur quatre à cinq cuivres, l'arme demandant plus de
            // travail que l'outil.
            Ressource::Outils->value => 60,
            Ressource::Armes->value => 80,

            // Craft de luxe : ce que l'Entrepôt de haut niveau finit par
            // ouvrir, et le sommet de la chaîne de valeur du jeu.
            Ressource::Bijoux->value => 200,     // 3 or + 2 turquoise + 10 deben = 124
            Ressource::Statuettes->value => 135, // 4 cèdre + 2 ivoire + 15 deben = 83
            Ressource::Vases->value => 70,       // 6 albâtre + 8 deben = 44
        ];
    }

    /**
     * Prix unitaire d'une ressource, ou null si elle ne se négocie pas. Le
     * deben ne s'y trouve pas : il est la monnaie, pas une marchandise.
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
