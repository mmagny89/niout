<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le battement du jeu : ce qui se résout quand le joueur avance d'une quinzaine.
 *
 * Réunit ici tout ce qui progresse dans le même cycle — chantiers, expéditions,
 * et demain récoltes et événements. Un seul endroit décide de l'ordre des
 * résolutions et n'écrit qu'une fois.
 */
final readonly class PassageDeCycle
{
    public function __construct(
        private Chantiers $chantiers,
        private Explorations $explorations,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function passer(GameSave $partie): array
    {
        // La saison du cycle qu'on vient de vivre, pas celle du suivant : les
        // travaux et les trajets ont eu lieu pendant l'ancien.
        $saison = $partie->dateDeJeu()->saison;

        $evenements = [
            ...$this->explorations->avancerDUnCycle($partie),
            ...$this->chantiers->avancerDUnCycle($partie, $saison),
        ];

        $partie->avancerDUnCycle();
        $this->entityManager->flush();

        return $evenements;
    }
}
