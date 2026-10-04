<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\City;
use App\Entity\GameSave;
use App\Game\Marche;
use App\Game\Mecontentement;
use App\Game\TypeDeBatiment;

/**
 * Le panneau du Marché : ce qu'on y vend, ce que la place absorbe, et le prix
 * qu'on fait payer au peuple.
 */
final readonly class PanneauDuMarche implements FournisseurDePanneau
{
    public function __construct(
        private Marche $marche,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Marche->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            'etal' => $this->marche->etalPour($partie),
            // Le débouché de la quinzaine se lit **avant** la vente : découvrir
            // la borne par un refus serait la subir au lieu de la jouer.
            'plafondDuMarche' => Marche::plafondDeLaQuinzaine($partie),
            'venteRestante' => $this->marche->venteRestante($partie),
            'jaugeDuMarche' => $this->marche->jauge($partie),
            'niveauDuMarche' => $ville->batimentDeType(TypeDeBatiment::Marche)?->getNiveau() ?? 0,
            // Le prix qu'on fait payer au peuple, et ce qu'il en coûte.
            'margeQuiFache' => Mecontentement::MARGE_QUI_FACHE,
            'margeMinimale' => City::MARGE_MINIMALE,
            'margeMaximale' => City::MARGE_MAXIMALE,
            'prixAbusif' => Mecontentement::prixAbusif($ville),
        ] + $this->direction->donnees($partie);
    }
}
