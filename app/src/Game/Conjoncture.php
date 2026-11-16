<?php

declare(strict_types=1);

namespace App\Game;

/**
 * L'état du marché d'une cité pour une marchandise, une quinzaine donnée : un nombre signé
 * et ce qui l'explique (`docs/plan-pnj-palier-a.md` § 3).
 *
 * Valeur dérivée, **jamais persistée** : `ConjonctureDeMarche` la recalcule à la demande.
 * Positive, elle avantage la famille ; négative, elle rogne ce qu'elle a acquis.
 */
final readonly class Conjoncture
{
    public function __construct(
        /** Le total, borné à ± `ConjonctureDeMarche::AMPLITUDE`. */
        public int $points,
        /** Le bruit de fond, lissé d'une quinzaine à l'autre. */
        public int $meteo,
        /** Ce que la saison et la crue ajoutent aux vivres. */
        public int $saisonEtCrue,
        public ?EvenementDeMarche $evenement,
        public int $pointsDeLEvenement,
    ) {
    }
}
