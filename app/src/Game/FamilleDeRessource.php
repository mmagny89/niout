<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les familles sous lesquelles la barre de jeu range les compteurs.
 *
 * **Un regroupement d'affichage, jamais un regroupement de jeu.** Chaque
 * ressource reste distincte partout ailleurs : il n'existe ni compteur « bois »
 * ni compteur « pierre », et un coût nomme toujours le matériau réel qu'il
 * réclame. Ce que ces familles font, et rien de plus : éviter au joueur une
 * barre de quarante nombres alignés, où il ne trouvait plus rien.
 *
 * Le total d'une famille est **indicatif** — il dit s'il y a de quoi voir, pas
 * ce qu'on peut payer avec. Seul le détail permet de décider, et c'est
 * pourquoi il s'ouvre d'un geste.
 */
enum FamilleDeRessource: string
{
    case Vivres = 'vivres';
    case Materiaux = 'materiaux';
    case Precieux = 'precieux';
    case Ouvrages = 'ouvrages';

    public function libelle(): string
    {
        return match ($this) {
            self::Vivres => 'Vivres',
            self::Materiaux => 'Matériaux',
            self::Precieux => 'Matières rares',
            self::Ouvrages => 'Ouvrages',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Vivres => 'Ce que la ville mange. Le Grenier en fixe le plafond.',
            self::Materiaux => 'Ce dont on bâtit. L\'Entrepôt en fixe le plafond, avec les ouvrages.',
            self::Precieux => 'Métaux, pierres et matières d\'échange, extraits ou importés.',
            self::Ouvrages => 'Ce qui sort de l\'Atelier et de la Forge — rien de tout cela ne se trouve sur une carte.',
        };
    }

    /**
     * L'ordre d'affichage : ce qui décide d'une quinzaine d'abord, ce qui
     * s'échange ensuite.
     *
     * @return list<self>
     */
    public static function ordreDAffichage(): array
    {
        return [self::Vivres, self::Materiaux, self::Precieux, self::Ouvrages];
    }
}
