<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Un héritier proposé à la succession familiale (doc 13, lot 11.5).
 *
 * **Il n'est ni persisté ni tiré au moment où l'écran s'affiche** : il se
 * déduit d'une graine gardée sur la famille, comme l'offre d'emploi fige son
 * tirage. Deux visites du même écran montrent donc les mêmes héritiers — ce
 * qui compte, puisqu'on choisit entre eux.
 */
final readonly class Heritier
{
    /**
     * @param list<TraitDeCandidat> $traits un ou deux, comme pour un candidat
     */
    public function __construct(
        public string $prenom,
        public array $traits,
    ) {
    }

    /**
     * Ce que le joueur lit de lui : ses traits, ou le fait qu'il n'en ait
     * aucun — ce qui se dit plutôt que se tait.
     */
    public function description(): string
    {
        if ([] === $this->traits) {
            return 'Rien ne le distingue encore : ni défaut, ni promesse.';
        }

        return implode(' ', array_map(
            static fn (TraitDeCandidat $trait): string => $trait->description(),
            $this->traits,
        ));
    }
}
