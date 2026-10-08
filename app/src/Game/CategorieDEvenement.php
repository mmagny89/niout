<?php

declare(strict_types=1);

namespace App\Game;

/**
 * D'où vient une ligne du journal d'une quinzaine — pour la ranger et lui donner son
 * pictogramme dans le récapitulatif.
 *
 * **Un regroupement d'affichage, comme `FamilleDeRessource`** : aucune règle du jeu ne
 * s'y adosse. Le texte de chaque événement reste écrit par le service qui le produit.
 */
enum CategorieDEvenement: string
{
    case Sante = 'sante';
    case Bourse = 'bourse';
    case Expeditions = 'expeditions';
    case Chantiers = 'chantiers';
    case Ateliers = 'ateliers';
    case Commerce = 'commerce';
    case Vivres = 'vivres';
    case Habitants = 'habitants';
    case Dieux = 'dieux';
    case Rivaux = 'rivaux';
    case Royaume = 'royaume';
    case Calendrier = 'calendrier';

    public function libelle(): string
    {
        return match ($this) {
            self::Sante => 'Santé',
            self::Bourse => 'Bourse',
            self::Expeditions => 'Expéditions',
            self::Chantiers => 'Chantiers',
            self::Ateliers => 'Ateliers',
            self::Commerce => 'Commerce',
            self::Vivres => 'Récoltes et vivres',
            self::Habitants => 'Habitants',
            self::Dieux => 'Les dieux',
            self::Rivaux => 'Rivaux',
            self::Royaume => 'Le royaume',
            self::Calendrier => 'Le temps',
        };
    }

    /**
     * Le nom d'un pictogramme de `app/assets/images/interface/` — un usage, pas un dessin.
     */
    public function icone(): string
    {
        return match ($this) {
            self::Sante => 'fievre',
            self::Bourse => 'deben',
            self::Expeditions => 'expedition',
            self::Chantiers => 'chantier',
            self::Ateliers => 'amelioration',
            self::Commerce => 'echange',
            self::Vivres => 'prestige',
            self::Habitants => 'habitants',
            self::Dieux => 'faveur',
            self::Rivaux => 'danger',
            self::Royaume => 'pharaon',
            self::Calendrier => 'quinzaine',
        };
    }
}
