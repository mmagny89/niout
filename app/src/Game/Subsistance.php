<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que les habitants coûtent en nourriture, à chaque quinzaine — le prix de
 * la population (`Population`), après que les gisements et les champs ont
 * versé leur récolte (`Recoltes`).
 *
 * Ne persiste rien, comme les autres résolutions de cycle : `PassageDeCycle`
 * réunit tout en une seule écriture.
 */
final readonly class Subsistance
{
    /**
     * **La famine se lit à deux paliers** (lot 4.7), et non plus à un seul.
     *
     * Les quatre quinzaines du lot 3.7 ne mènent plus à l'échec mais au
     * mécontentement : on travaille moins bien, on parle de partir, la
     * renommée s'effrite. L'échec ne tombe qu'après une famine nettement plus
     * longue.
     *
     * C'est le compromis entre le « pas de game over brutal » du doc 02 et
     * l'échec demandé au lot 3.7 : la ville prévient longtemps avant de
     * mourir. Les deux valeurs restent inventées, à calibrer en playtest.
     */
    public const int SEUIL_DE_FAMINE = 4;
    public const int SEUIL_DECHEC = 12;

    /**
     * @return array{evenements: list<string>, famine: bool}
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $besoin = $ville->consommationDeNourriture();

        if (0 === $besoin) {
            return ['evenements' => [], 'famine' => false];
        }

        $disponible = $ville->getNourriture();

        if ($disponible >= $besoin) {
            $ville->debiterNourriture($besoin);
            $partie->reinitialiserLaFamine();

            return ['evenements' => [], 'famine' => false];
        }

        // La ville mange ce qu'elle a, rien de plus : le manque ne se reporte
        // pas sur la quinzaine suivante, il s'accumule dans le compteur de
        // famine.
        $ville->debiterNourriture($disponible);
        $partie->enregistrerUneQuinzaineDeFamine();

        $evenements = [\sprintf(
            'La ville ne compte plus assez de vivres pour ses %d habitants : la famine s\'installe.',
            $ville->population(),
        )];

        if ($partie->getQuinzainesDeFamine() >= self::SEUIL_DECHEC) {
            $partie->echouer();
            $evenements[] = 'Faute de vivres, la ville est abandonnée. La partie s\'achève en échec.';
        }

        return ['evenements' => $evenements, 'famine' => true];
    }
}
