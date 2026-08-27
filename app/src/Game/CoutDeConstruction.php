<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le coût d'un niveau de bâtiment (doc 01).
 *
 * Le lin apparaît dans le seul coût du Temple, sous forme d'offrande. C'est une
 * ressource agricole, qui n'existera qu'avec les champs en Phase 3 : d'ici là,
 * un bâtiment qui en réclame reste inconstructible.
 */
final readonly class CoutDeConstruction
{
    /**
     * Progression du coût par niveau (doc 01) : modérée, jamais exponentielle.
     */
    private const float FACTEUR_DE_PROGRESSION = 0.4;

    public function __construct(
        public int $bois = 0,
        public int $pierre = 0,
        public int $or = 0,
        public int $lin = 0,
    ) {
    }

    /**
     * coutNiveau(N) = coutBase × (1 + (N - 1) × 0,4).
     */
    public function pourNiveau(int $niveau): self
    {
        $multiplicateur = 1 + ($niveau - 1) * self::FACTEUR_DE_PROGRESSION;

        return new self(
            bois: (int) ceil($this->bois * $multiplicateur),
            pierre: (int) ceil($this->pierre * $multiplicateur),
            or: (int) ceil($this->or * $multiplicateur),
            lin: (int) ceil($this->lin * $multiplicateur),
        );
    }

    public function estGratuit(): bool
    {
        return 0 === $this->bois && 0 === $this->pierre && 0 === $this->or && 0 === $this->lin;
    }

    /**
     * Le coût exprimé en ressources, prêt à être débité d'un stock. Les lignes
     * nulles sont exclues : inutile de vérifier ce qu'on ne réclame pas.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function enRessources(): array
    {
        return array_filter([
            Ressource::Bois->value => $this->bois,
            Ressource::Pierre->value => $this->pierre,
            Ressource::Or->value => $this->or,
            Ressource::Lin->value => $this->lin,
        ]);
    }

    /**
     * Détail affichable, libellés en toutes lettres.
     *
     * @return array<string, int> libellé => quantité
     */
    public function detail(): array
    {
        $detail = [];

        foreach ($this->enRessources() as $valeur => $quantite) {
            $detail[Ressource::from($valeur)->libelle()] = $quantite;
        }

        return $detail;
    }
}
