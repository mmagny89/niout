<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Commerce;
use App\Game\Ressource;
use App\Game\SensDEchange;

/**
 * L'étal de chaque route ouverte.
 */
final readonly class EtalsDesRoutes
{
    public function __construct(private Commerce $commerce)
    {
    }

    /**
     * L'étal de chaque route ouverte : ce qui peut s'y annoncer, dans quelle
     * fourchette, et l'empressement que le prix posé produit.
     *
     * @return array<string, list<array{ressource: Ressource, sens: SensDEchange, ordre: ?\App\Entity\OrdreCommercial, plancher: int, plafond: int, empressement: int}>>
     */
    public function pour(GameSave $partie): array
    {
        $etals = [];

        foreach ($partie->getVille()->getRoutesCommerciales() as $route) {
            if ($route->estOuverte()) {
                $etals[$route->getPartenaire()] = $this->commerce->etalDe($partie, $route);
            }
        }

        return $etals;
    }
}
