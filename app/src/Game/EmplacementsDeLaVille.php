<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Où chaque bâtiment se dresse sur le visuel de la ville.
 *
 * Les deux visuels (`ville`, `ville_port`) portent le même plan : une dalle de
 * terre, des chemins, et **quinze lots** — des clairières de terre nue sur
 * lesquelles viennent se poser les sprites. Les coordonnées sont en pixels du
 * visuel (1376 × 768) ; la vue les convertit en pourcentages, pour que tout suive
 * la taille de l'écran.
 *
 * **Chaque sprite porte son propre lot** — le même, à tous ses paliers — et c'est
 * le lot qui fait l'échelle : on pose le sprite pour que son lot ait la largeur de
 * la clairière du plan (`AncragesDesSprites`, généré). Un petit bâtiment du palier
 * un n'est donc jamais gonflé à la taille d'un temple : il occupe une partie d'un
 * lot qui, lui, a toujours la bonne taille.
 *
 * Douze lots reçoivent un bâtiment, trois restent libres : de la place que le jeu
 * n'emploie pas encore, rendue par un lot vide. **Chaque bâtiment a son lot, fixe**
 * : on ne choisit pas où bâtir, on choisit quoi — la ville se gère, elle ne se
 * dessine pas (doc 15).
 */
final class EmplacementsDeLaVille
{
    public const int LARGEUR = 1376;
    public const int HAUTEUR = 768;

    /**
     * Largeur, sur le plan, de la clairière de chaque classe de lot : grand
     * (4 × 4 unités du plan guide), moyen (3 × 3), petit (2 × 2).
     */
    public const array LARGEUR_DES_LOTS = ['l' => 305, 'm' => 222, 's' => 148];

    /**
     * Part de la clairière que le lot du sprite occupe. **Moins que 1** : la clairière
     * du plan est mesurée au bord de ses chemins, et un lot à sa pleine largeur
     * collait ses voisins et mangeait les chemins — la ville ne respirait plus.
     */
    public const float FACTEUR_DE_LARGEUR = 0.86;

    /**
     * Les planches livrent quatre paliers de sprite ; les bâtiments montent au
     * niveau cinq, qui garde le dernier.
     */
    public const int PALIERS_DE_SPRITE = 4;

    /**
     * Le centre du lot, sa classe (`l`, `m`, `s`) et son bâtiment — null pour un
     * lot libre.
     *
     * @return list<array{x: int, y: int, classe: string, type: ?TypeDeBatiment}>
     */
    public static function tous(): array
    {
        return [
            ['x' => 697, 'y' => 160, 'classe' => 'l', 'type' => TypeDeBatiment::Temple],
            ['x' => 885, 'y' => 251, 'classe' => 'l', 'type' => TypeDeBatiment::ResidenceFamiliale],
            ['x' => 515, 'y' => 251, 'classe' => 'l', 'type' => TypeDeBatiment::Caserne],
            ['x' => 1068, 'y' => 343, 'classe' => 'l', 'type' => TypeDeBatiment::Marche],
            // Le Port a son lot au bord de l'eau, à côté du ponton de `ville_port` :
            // il n'a d'existence que là où la ville jouxte un point d'eau (doc 01).
            ['x' => 803, 'y' => 548, 'classe' => 'l', 'type' => TypeDeBatiment::Port],
            ['x' => 696, 'y' => 326, 'classe' => 'm', 'type' => TypeDeBatiment::MaisonDesScribes],
            ['x' => 844, 'y' => 399, 'classe' => 'm', 'type' => TypeDeBatiment::QuartierDHabitation],
            ['x' => 327, 'y' => 326, 'classe' => 'm', 'type' => TypeDeBatiment::Entrepot],
            ['x' => 512, 'y' => 382, 'classe' => 'm', 'type' => TypeDeBatiment::Grenier],
            ['x' => 654, 'y' => 440, 'classe' => 's', 'type' => TypeDeBatiment::Atelier],
            ['x' => 547, 'y' => 494, 'classe' => 's', 'type' => TypeDeBatiment::Forge],
            ['x' => 360, 'y' => 440, 'classe' => 's', 'type' => TypeDeBatiment::Auberge],
            ['x' => 172, 'y' => 384, 'classe' => 's', 'type' => null],
            ['x' => 990, 'y' => 455, 'classe' => 's', 'type' => null],
            ['x' => 915, 'y' => 492, 'classe' => 's', 'type' => null],
        ];
    }

    /**
     * Le palier de sprite d'un niveau de bâtiment : 1 à 4.
     */
    public static function palierDeSprite(int $niveau): int
    {
        return max(1, min(self::PALIERS_DE_SPRITE, $niveau));
    }
}
