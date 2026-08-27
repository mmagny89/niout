<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;

/**
 * Ce que la ville peut faire d'un type de bâtiment, à un instant donné :
 * le construire, le monter d'un niveau, ou rien — et pourquoi.
 */
final readonly class OffreDeConstruction
{
    private function __construct(
        public TypeDeBatiment $type,
        public ?Building $existant,
        public ?CoutDeConstruction $cout,
        /** Motif du blocage, null si l'action est possible. */
        public ?string $empechement,
    ) {
    }

    public static function possible(TypeDeBatiment $type, ?Building $existant, CoutDeConstruction $cout): self
    {
        return new self($type, $existant, $cout, null);
    }

    public static function empechee(TypeDeBatiment $type, ?Building $existant, ?CoutDeConstruction $cout, string $motif): self
    {
        return new self($type, $existant, $cout, $motif);
    }

    public function estDejaDressé(): bool
    {
        return null !== $this->existant;
    }

    public function estRealisable(): bool
    {
        return null === $this->empechement;
    }

    /**
     * Libellé de l'action proposée, du point de vue du joueur.
     */
    public function libelleDeLAction(): string
    {
        return $this->estDejaDressé() ? 'Améliorer' : 'Construire';
    }
}
