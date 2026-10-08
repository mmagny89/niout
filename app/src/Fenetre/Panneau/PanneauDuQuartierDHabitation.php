<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\AppelDHabitants;
use App\Game\Effectifs;
use App\Game\EffetDeChef;
use App\Game\Maisonnees;
use App\Game\PalierDeRenommee;
use App\Game\Salaires;
use App\Game\TypeDeBatiment;

/**
 * Le panneau du Quartier d'habitation : les habitants rangés en maisonnées, pour
 * qu'on les voie — une représentation déterministe, rien n'en est persisté.
 */
final readonly class PanneauDuQuartierDHabitation implements FournisseurDePanneau
{
    public function __construct(
        private AppelDHabitants $appels,
        private Salaires $salaires,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::QuartierDHabitation->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $maisons = Maisonnees::repartir($ville);

        return [
            'maisons' => $maisons,
            'descriptions' => array_map(Maisonnees::decrire(...), $maisons),
            'libres' => $ville->foyersLibres(),
            'brasDisponibles' => Effectifs::brasDisponibles($ville, $partie->getCycle()),
            'effectifs' => Effectifs::repartir($ville, $partie->getCycle()),
            // Le rendement total : travailleurs, plafond du chef et compétence ensemble (l'en-tête le montre à part).
            'rendementsTotaux' => EffetDeChef::qualitesDeDirection($ville, $partie->getCycle()),
            // Et celui de la quinzaine suivante : un chef embauché prend son poste à ce moment-là, et le plafond monte.
            'rendementsProchains' => EffetDeChef::qualitesDeDirection($ville, $partie->getCycle() + 1),
            'masseSalariale' => $this->salaires->masseSalariale($ville, $partie->getCycle()),
            'coutDUnAppel' => $this->appels->cout($partie),
            // La renommée décide du prix d'un appel : elle se lit ici aussi.
            'palier' => $partie->getFamille()->palier(),
            'paliers' => PalierDeRenommee::cases(),
        ];
    }
}
