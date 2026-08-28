<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Combien d'habitants une ville porte, et ce qu'ils mangent (amorce de la
 * Phase 4 — recrutement, chefs et travailleurs restent à venir).
 *
 * Une famille fondatrice s'installe dès l'arrivée, avant tout bâtiment : la
 * Résidence familiale est offerte avec la ville (doc 01). Le Quartier
 * d'habitation « détermine combien de familles peuvent s'installer dans la
 * ville » (TypeDeBatiment::QuartierDHabitation::fonction()) — c'est donc lui
 * qui fait croître la population au-delà de cette famille de départ.
 *
 * Valeurs inventées, comme le reste du cycle agricole (`RendementDesChamps`,
 * `Recoltes`) : aucun document ne les chiffre encore.
 */
final readonly class Population
{
    /**
     * La famille fondatrice, présente dès l'arrivée — exposée pour que
     * `DotationRoyale` calibre les provisions de départ sur elle, avant même
     * qu'un Quartier d'habitation existe.
     */
    public const int HABITANTS_DE_BASE = 5;

    private const int HABITANTS_PAR_NIVEAU_DE_QUARTIER = 10;

    /**
     * Nourriture consommée par habitant et par quinzaine.
     */
    public const int RATION_PAR_HABITANT = 1;

    public static function pour(City $ville): int
    {
        $quartier = $ville->batimentDeType(TypeDeBatiment::QuartierDHabitation);
        $niveau = null === $quartier ? 0 : $quartier->getNiveau();

        return self::HABITANTS_DE_BASE + self::HABITANTS_PAR_NIVEAU_DE_QUARTIER * $niveau;
    }

    public static function consommationParQuinzaine(City $ville): int
    {
        return self::pour($ville) * self::RATION_PAR_HABITANT;
    }
}
