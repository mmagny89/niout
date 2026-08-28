<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que le temps fait aux maisonnées : les enfants grandissent.
 *
 * Aucune compression du temps (décision de la joueuse) — un enfant met une
 * douzaine d'années à devenir adulte, soit près de trois cents quinzaines. Ça
 * ne condamne pas le mécanisme à l'invisibilité pour autant, parce que les
 * foyers arrivent avec des enfants de **tous les âges** : celui qui a onze ans
 * donne un bras dans l'année, celui qui vient de naître n'en donnera qu'en mode
 * Aventure, joué sur plusieurs règnes.
 *
 * Ne persiste rien, comme les autres résolutions de cycle : `PassageDeCycle`
 * réunit tout en une seule écriture.
 */
final readonly class Foyers
{
    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $majeurs = 0;

        foreach ($partie->getVille()->getFoyers() as $foyer) {
            $majeurs += $foyer->vieillirDUneQuinzaine();
        }

        if (0 === $majeurs) {
            return [];
        }

        return [1 === $majeurs
            ? 'Un enfant de la ville est en âge de travailler.'
            : \sprintf('%d enfants de la ville sont en âge de travailler.', $majeurs),
        ];
    }
}
