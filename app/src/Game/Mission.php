<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une mission de campagne : un pharaon réel, une ville attestée, un type
 * d'objectif (doc 09, doc 11).
 *
 * Données de référence, jamais persistées : elles décrivent le contenu du jeu,
 * pas l'état d'une partie.
 */
final readonly class Mission
{
    public function __construct(
        public int $numero,
        public string $region,
        public string $ville,
        public string $pharaon,
        public TypeDeMission $type,
        public int $difficulte,
        /** Ancrage historique, affiché à la commande du pharaon. */
        public string $contexte,
        public GeographieDeRegion $geographie,
    ) {
    }

    /**
     * Taille de la grille d'exploration, par paliers de deux niveaux de
     * difficulté (doc 11) — de 3×3 dans le Delta à 7×7 au Sinaï.
     */
    public function tailleDeGrille(): int
    {
        return 3 + intdiv($this->difficulte, 2);
    }
}
