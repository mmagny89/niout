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
        private Recoltes $recoltes,
        private Subsistance $subsistance,
        private TirageDeLaCrue $crues,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function passer(GameSave $partie): array
    {
        // La saison du cycle qu'on vient de vivre, pas celle du suivant : les
        // travaux, les trajets et les récoltes ont eu lieu pendant l'ancien.
        $saison = $partie->dateDeJeu()->saison;

        $evenements = [
            ...$this->explorations->avancerDUnCycle($partie),
            ...$this->chantiers->avancerDUnCycle($partie, $saison),
            ...$this->recoltes->avancerDUnCycle($partie),
            // Après la récolte, jamais avant : la ville mange ce que la
            // quinzaine vient d'apporter.
            ...$this->subsistance->avancerDUnCycle($partie),
        ];

        $partie->avancerDUnCycle();

        // L'année qui s'ouvre apporte sa crue, annoncée avant qu'on ait à
        // semer : c'est un aléa qu'on subit, pas une surprise de moisson.
        if ($partie->dateDeJeu()->ouvreUneAnnee()) {
            $crue = $this->crues->tirer();
            $partie->annoncerLaCrue($crue);
            $evenements[] = \sprintf('La crue de cette année est %s. %s', $crue->libelle(), $crue->presage());
        }

        $this->entityManager->flush();

        return $evenements;
    }
}
