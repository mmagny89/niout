<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Où chaque bâtiment se dresse sur le visuel de la ville.
 *
 * Les deux visuels (`ville`, `ville_port`) portent le même plan : quinze enclos
 * vides, que les bâtiments viennent remplir. Les coordonnées sont en pixels du
 * visuel — 1408 × 768 — et la vue les convertit en pourcentages, pour que tout
 * suive la taille de l'écran.
 *
 * Douze enclos reçoivent un bâtiment, trois restent libres : ce sont de la
 * place que le jeu n'emploie pas encore. **Chaque bâtiment a son enclos, fixe** :
 * on ne choisit pas où bâtir, on choisit quoi — la ville se gère, elle ne se
 * dessine pas (doc 15).
 *
 * Un enclos est un losange : son centre, sa demi-largeur et sa demi-hauteur.
 */
final class EmplacementsDeLaVille
{
    public const int LARGEUR = 1408;
    public const int HAUTEUR = 768;

    /**
     * Le sprite d'un bâtiment est un peu plus large que son enclos : il en
     * recouvre les murets.
     */
    public const float FACTEUR_DE_LARGEUR = 1.15;

    /**
     * Le bas du sprite descend sous le centre de l'enclos, de ce multiple de sa
     * demi-hauteur : le bâtiment pose son pied sur le sol, pas en son milieu.
     */
    public const float DECALAGE_DU_PIED = 1.25;

    /**
     * Les planches livrent quatre paliers de sprite ; les bâtiments montent au
     * niveau cinq, qui garde le dernier.
     */
    public const int PALIERS_DE_SPRITE = 4;

    /**
     * @return list<array{x: int, y: int, demiLargeur: int, demiHauteur: int, type: ?TypeDeBatiment}>
     */
    public static function tous(): array
    {
        return [
            ['x' => 565, 'y' => 200, 'demiLargeur' => 115, 'demiHauteur' => 62, 'type' => TypeDeBatiment::ResidenceFamiliale],
            ['x' => 857, 'y' => 188, 'demiLargeur' => 90, 'demiHauteur' => 48, 'type' => TypeDeBatiment::MaisonDesScribes],
            ['x' => 995, 'y' => 268, 'demiLargeur' => 105, 'demiHauteur' => 55, 'type' => TypeDeBatiment::Marche],
            ['x' => 1135, 'y' => 357, 'demiLargeur' => 105, 'demiHauteur' => 55, 'type' => TypeDeBatiment::Caserne],
            ['x' => 390, 'y' => 310, 'demiLargeur' => 115, 'demiHauteur' => 62, 'type' => TypeDeBatiment::Temple],
            ['x' => 240, 'y' => 386, 'demiLargeur' => 90, 'demiHauteur' => 48, 'type' => TypeDeBatiment::QuartierDHabitation],
            ['x' => 725, 'y' => 281, 'demiLargeur' => 65, 'demiHauteur' => 35, 'type' => null],
            ['x' => 556, 'y' => 382, 'demiLargeur' => 65, 'demiHauteur' => 32, 'type' => null],
            ['x' => 858, 'y' => 369, 'demiLargeur' => 90, 'demiHauteur' => 48, 'type' => TypeDeBatiment::Entrepot],
            ['x' => 440, 'y' => 456, 'demiLargeur' => 88, 'demiHauteur' => 46, 'type' => TypeDeBatiment::Grenier],
            ['x' => 712, 'y' => 451, 'demiLargeur' => 80, 'demiHauteur' => 42, 'type' => TypeDeBatiment::Atelier],
            ['x' => 972, 'y' => 446, 'demiLargeur' => 82, 'demiHauteur' => 42, 'type' => TypeDeBatiment::Forge],
            ['x' => 575, 'y' => 533, 'demiLargeur' => 80, 'demiHauteur' => 42, 'type' => TypeDeBatiment::Auberge],
            // Le Port a son enclos au bord de l'eau, à côté du ponton de
            // `ville_port` : il n'a d'existence que là où la ville jouxte un
            // point d'eau (doc 01).
            ['x' => 875, 'y' => 535, 'demiLargeur' => 120, 'demiHauteur' => 62, 'type' => TypeDeBatiment::Port],
            ['x' => 675, 'y' => 600, 'demiLargeur' => 65, 'demiHauteur' => 32, 'type' => null],
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
