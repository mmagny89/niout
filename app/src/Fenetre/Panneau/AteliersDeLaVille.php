<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Fabrication;
use App\Game\Recette;
use App\Game\TypeDeBatiment;

/**
 * Ce que chaque bâtiment qui fabrique sait faire.
 */
final readonly class AteliersDeLaVille
{
    public function __construct(private Fabrication $fabrication)
    {
    }

    /**
     * Ce que chaque bâtiment qui fabrique sait faire, ce qu'il fait déjà, et
     * ce qu'il refera de lui-même.
     *
     * L'Atelier et la Forge partagent tout : un seul gabarit les rend, une
     * seule boucle les prépare.
     *
     * @return array<string, array{type: TypeDeBatiment, niveau: int, lotsMaximum: int, recettes: list<array{recette: Recette, matieres: array<string, int>, realisable: bool, empechement: ?string}>, ordre: ?\App\Entity\OrdreDeFabrication, consigne: ?\App\Entity\ConsigneDeFabrication}>
     */
    public function pour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $ateliers = [];

        foreach (Recette::batimentsQuiFabriquent() as $type) {
            $batiment = $ville->batimentDeType($type);

            if (null === $batiment) {
                continue;
            }

            $ateliers[$type->value] = [
                'type' => $type,
                'niveau' => $batiment->getNiveau(),
                'lotsMaximum' => Fabrication::lotsMaximum($batiment->getNiveau()),
                'recettes' => $this->fabrication->offrePour($partie, $type),
                'ordre' => $ville->ordreDeFabricationDe($type),
                'consigne' => $ville->consigneDeFabricationDe($type),
            ];
        }

        return $ateliers;
    }
}
