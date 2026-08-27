<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les poids du tirage de contenu, par paliers de difficulté (doc 02).
 *
 * Par paliers et non en progression continue : une variation trop fine serait
 * impossible à calibrer en playtest.
 */
final readonly class PoidsDeTirage
{
    public function __construct(
        public int $ressource,
        public int $champ,
        public int $evenement,
        public int $vide,
    ) {
    }

    public static function pourDifficulte(int $difficulte): self
    {
        return match (true) {
            $difficulte <= 2 => new self(ressource: 35, champ: 25, evenement: 20, vide: 20),
            $difficulte <= 5 => new self(ressource: 30, champ: 20, evenement: 20, vide: 22),
            default => new self(ressource: 25, champ: 15, evenement: 18, vide: 27),
        };
    }

    /**
     * Contenu d'un gisement : `200 - (difficulté × 15)` (doc 02). Les régions
     * rudes donnent moins, ce qui pousse à explorer plus loin.
     */
    public static function quantiteParGisement(int $difficulte): int
    {
        return 200 - $difficulte * 15;
    }

    /**
     * Les gisements ne s'épuisent qu'à partir de la difficulté 4 (doc 02).
     * L'épuisement lui-même viendra avec les régions concernées.
     */
    public static function gisementsEpuisables(int $difficulte): bool
    {
        return $difficulte >= 4;
    }
}
