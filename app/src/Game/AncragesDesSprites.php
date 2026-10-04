<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ou se trouve le lot dans chaque sprite, et quelle largeur il y fait.
 *
 * **GENERE** par `outils/decouper-batiments.py` — ne pas editer a la main. Les
 * valeurs sont en pixels du sprite : `largeur` et `hauteur` sont celles du cadre
 * (communes aux quatre paliers d'un batiment), `centreX` et `centreY` celles du
 * centre du losange du lot, `largeurDuLot` sa largeur. La vue de la ville pose
 * le sprite pour que ce centre tombe sur le centre de l'enclos, a l'echelle qui
 * donne au lot la largeur de l'enclos.
 */
final class AncragesDesSprites
{
    /**
     * @var array<string, array{largeur: int, hauteur: int, centreX: float, centreY: float, largeurDuLot: float}>
     */
    public const array BATIMENTS = [
        'atelier' => ['largeur' => 487, 'hauteur' => 423, 'centreX' => 252.5, 'centreY' => 201.5, 'largeurDuLot' => 479.0],
        'auberge' => ['largeur' => 756, 'hauteur' => 500, 'centreX' => 389.5, 'centreY' => 257.5, 'largeurDuLot' => 539.0],
        'caserne' => ['largeur' => 486, 'hauteur' => 396, 'centreX' => 235.0, 'centreY' => 237.5, 'largeurDuLot' => 468.0],
        'entrepot' => ['largeur' => 487, 'hauteur' => 423, 'centreX' => 252.5, 'centreY' => 201.5, 'largeurDuLot' => 479.0],
        'forge' => ['largeur' => 485, 'hauteur' => 386, 'centreX' => 241.5, 'centreY' => 217.0, 'largeurDuLot' => 473.0],
        'grenier' => ['largeur' => 487, 'hauteur' => 404, 'centreX' => 244.0, 'centreY' => 182.0, 'largeurDuLot' => 462.0],
        'quartier_habitation' => ['largeur' => 492, 'hauteur' => 406, 'centreX' => 244.5, 'centreY' => 207.5, 'largeurDuLot' => 477.0],
        'marche' => ['largeur' => 565, 'hauteur' => 372, 'centreX' => 307.5, 'centreY' => 192.0, 'largeurDuLot' => 507.0],
        'port' => ['largeur' => 497, 'hauteur' => 421, 'centreX' => 255.0, 'centreY' => 245.0, 'largeurDuLot' => 498.0],
        'residence_familiale' => ['largeur' => 565, 'hauteur' => 354, 'centreX' => 307.5, 'centreY' => 192.0, 'largeurDuLot' => 507.0],
        'maison_des_scribes' => ['largeur' => 495, 'hauteur' => 420, 'centreX' => 251.5, 'centreY' => 246.5, 'largeurDuLot' => 483.0],
        'temple' => ['largeur' => 565, 'hauteur' => 354, 'centreX' => 307.5, 'centreY' => 192.0, 'largeurDuLot' => 507.0],
    ];

    /**
     * Les lots vides, par classe : `s`, `m`, `l`.
     *
     * @var array<string, array{largeur: int, hauteur: int, centreX: float, centreY: float, largeurDuLot: float}>
     */
    public const array LOTS = [
        's' => ['largeur' => 321, 'hauteur' => 202, 'centreX' => 159.5, 'centreY' => 100.5, 'largeurDuLot' => 319.0],
        'm' => ['largeur' => 447, 'hauteur' => 234, 'centreX' => 184.5, 'centreY' => 110.6, 'largeurDuLot' => 369.0],
        'l' => ['largeur' => 413, 'hauteur' => 271, 'centreX' => 193.5, 'centreY' => 133.1, 'largeurDuLot' => 387.0],
    ];
}
