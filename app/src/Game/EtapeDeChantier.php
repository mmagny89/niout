<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une étape nommée d'un chantier, avec son explication (doc 01).
 *
 * L'affichage est informatif, jamais une mécanique de plus à gérer : le joueur
 * voit où en sont les travaux et apprend au passage comment on bâtissait.
 */
final readonly class EtapeDeChantier
{
    public function __construct(
        public string $nom,
        public string $explication,
    ) {
    }

    /**
     * Chantier en brique crue — la majorité des bâtiments.
     *
     * @return list<self>
     */
    public static function enBriqueCrue(): array
    {
        return [
            new self(
                'Préparation du terrain',
                'Nivellement du sol et traçage des fondations.',
            ),
            new self(
                'Fabrication et séchage des briques',
                'Limon du Nil, sable et paille, moulés puis séchés au soleil une à deux semaines. Un bon ouvrier en façonne près d\'un millier par jour — ce séchage est la raison pour laquelle aucun chantier ne dure moins d\'une quinzaine.',
            ),
            new self(
                'Élévation des murs',
                'Montage des briques séchées, assemblage de la structure.',
            ),
            new self(
                'Finitions',
                'Enduit de limon, toiture en troncs de palmier et nattes, parfois une fine couche de plâtre.',
            ),
        ];
    }

    /**
     * Chantier en pierre — nettement plus long et plus technique.
     *
     * @return list<self>
     */
    public static function enPierre(): array
    {
        return [
            new self(
                'Extraction et transport de la pierre',
                'Les blocs viennent des carrières de la région, parfois lointaines.',
            ),
            new self(
                'Taille des blocs',
                'Travail à l\'outil de cuivre et au maillet, selon les techniques de l\'époque.',
            ),
            new self(
                'Assemblage',
                'Montage des blocs, rampes et échafaudages.',
            ),
            new self(
                'Finitions',
                'Décor, reliefs gravés, enduit.',
            ),
        ];
    }
}
