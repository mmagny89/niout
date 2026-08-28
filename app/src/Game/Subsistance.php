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
     * Quinzaines consécutives de famine avant l'échec de la partie. Valeur
     * inventée, comme le reste des rythmes de jeu (`RendementDesChamps`,
     * `Recoltes`) — le besoin de la calibrer reste ouvert, signalé comme tel
     * plutôt que tranché sans playtest. Deux mois (quatre quinzaines) laissent
     * le temps de réagir — envoyer un éclaireur, vendre au Marché — sans
     * rendre la famine anodine.
     */
    public const int SEUIL_DE_FAMINE = 4;

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $besoin = $ville->consommationDeNourriture();

        if (0 === $besoin) {
            return [];
        }

        $disponible = $ville->getNourriture();

        if ($disponible >= $besoin) {
            $ville->debiterNourriture($besoin);
            $partie->reinitialiserLaFamine();

            return [];
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

        if ($partie->getQuinzainesDeFamine() >= self::SEUIL_DE_FAMINE) {
            $partie->echouer();
            $evenements[] = 'Faute de vivres, la ville est abandonnée. La partie s\'achève en échec.';
        }

        return $evenements;
    }
}
