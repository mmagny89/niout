<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Game\TypeDeBatiment;

/**
 * Une silhouette posée sur la ville vue d'en haut.
 *
 * Une donnée, pas un dessin : `GensDeLaVille` la compose, le gabarit
 * l'affiche. **Tout est en pourcentages du visuel**, comme les sprites — le
 * gabarit n'a ni pixel ni échelle à connaître.
 */
final readonly class FigureDeLaVille
{
    public function __construct(
        /** Clé stable, qui est aussi celle de la fiche : `chef-12`, `maison-3`, `poste-grenier`… */
        public string $cle,
        public RoleDeFigure $role,
        public string $libelle,
        public ?TypeDeBatiment $lieu,
        public float $gauche,
        public float $haut,
        public float $largeur,
        public int $profondeur,
        public bool $miroir,
        public bool $couchee,
    ) {
    }

    /**
     * Le rapport largeur / hauteur de la silhouette : une personne debout est
     * deux fois plus haute que large, une personne couchée deux fois plus
     * large que haute.
     */
    public function rapport(): string
    {
        return $this->couchee ? '2 / 1' : '1 / 2';
    }
}
