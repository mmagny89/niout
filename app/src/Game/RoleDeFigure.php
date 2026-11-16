<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Qui l'on voit sur la ville vue d'en haut, et de quelle silhouette
 * (`docs/plan-pnj-ville-vivante.md` § 15).
 *
 * Chaque rôle porte le **symbole** SVG qui le dessine
 * (`partie/_symboles_de_figures.html.twig`) : de profil, à l'égyptienne, un
 * objet en main qui dit ce qu'il fait. La couleur est du ressort du gabarit :
 * ses classes doivent y figurer en toutes lettres pour que Tailwind les compile.
 */
enum RoleDeFigure: string
{
    case ChefDeFamille = 'chef_de_famille';
    case Chef = 'chef';
    case Medjay = 'medjay';
    case Rival = 'rival';
    case Travailleur = 'travailleur';
    case SansPoste = 'sans_poste';
    case Foyer = 'foyer';

    /**
     * L'identifiant du `<symbol>` qui le dessine.
     */
    public function symbole(bool $couchee = false): string
    {
        if ($couchee) {
            return 'fig-couchee';
        }

        return match ($this) {
            self::ChefDeFamille => 'fig-chef-de-famille',
            self::Chef => 'fig-chef',
            self::Medjay => 'fig-medjay',
            self::Rival => 'fig-rival',
            self::Travailleur => 'fig-travailleur',
            self::SansPoste, self::Foyer => 'fig-habitant',
        };
    }
}
