<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le don du pharaon au lancement d'une mission (doc 13).
 *
 * Toujours accordé, quel que soit le parcours du joueur : c'est la base de
 * départ, distincte de l'héritage familial qui viendra s'y superposer. Une
 * mission plus difficile appelle un soutien plus consistant, sans quoi elle ne
 * serait pas viable.
 */
final readonly class DotationRoyale
{
    private const int OR_DE_BASE = 50;
    private const int OR_PAR_NIVEAU_DE_DIFFICULTE = 10;

    /**
     * De quoi couvrir le premier bâtiment, quelle que soit la région.
     */
    private const int BOIS = 20;
    private const int PIERRE = 10;

    public function __construct(
        public int $or,
        public int $bois,
        public int $pierre,
    ) {
    }

    /**
     * La dotation exprimée en ressources, prête à créditer un stock.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function enRessources(): array
    {
        return [
            Ressource::Or->value => $this->or,
            Ressource::Bois->value => $this->bois,
            Ressource::Pierre->value => $this->pierre,
        ];
    }

    public static function pourDifficulte(int $difficulte): self
    {
        return new self(
            or: self::OR_DE_BASE + self::OR_PAR_NIVEAU_DE_DIFFICULTE * $difficulte,
            bois: self::BOIS,
            pierre: self::PIERRE,
        );
    }
}
