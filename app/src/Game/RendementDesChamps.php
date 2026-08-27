<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'un champ donne à une quinzaine donnée (doc 05).
 *
 * Le cycle agricole égyptien, tel quel : rien pendant la crue, une croissance
 * progressive à l'émergence, la moisson d'un coup à la récolte. Un champ n'est
 * donc pas une rente régulière — il faut avoir semé au bon moment et tenu
 * jusqu'à Chémou.
 *
 * Règles pures, sans état ni dépendance : c'est là que se vérifie qu'un champ
 * ne produit rien en Akhèt.
 */
final readonly class RendementDesChamps
{
    /**
     * Récolte d'un champ sur une quinzaine, au sommet de la saison et pour une
     * crue normale.
     *
     * Valeur inventée : aucun document ne chiffre le rendement d'un champ. Elle
     * est calibrée pour qu'une moisson de Chémou (8 quinzaines) nourrisse
     * confortablement quelques expéditions, sans rendre le Grenier superflu.
     */
    public const int RECOLTE_DE_REFERENCE = 10;

    /**
     * @param ?int $rangDansLaSaison de 1 à 8 ; null pendant les jours épagomènes
     */
    public static function pourUneQuinzaine(
        ?Saison $saison,
        ?int $rangDansLaSaison,
        QualiteDeCrue $crue,
    ): int {
        if (null === $saison || null === $rangDansLaSaison) {
            // Les cinq jours épagomènes ne sont d'aucune saison : on ne moissonne pas.
            return 0;
        }

        return match ($saison) {
            // Les champs sont sous l'eau. C'est le prix de la fertilité.
            Saison::Akhet => 0,
            // Semis puis croissance : le rendement monte au fil des huit quinzaines.
            Saison::Peret => intdiv(
                self::RECOLTE_DE_REFERENCE * $rangDansLaSaison,
                DateDeJeu::CYCLES_PAR_SAISON,
            ),
            // La moisson, modulée par le limon que la crue a déposé.
            Saison::Chemou => intdiv(
                self::RECOLTE_DE_REFERENCE * $crue->modificateurEnDixiemes(),
                10,
            ),
        };
    }
}
