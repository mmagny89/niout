<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'un champ du Nil donne à une quinzaine donnée (doc 05).
 *
 * Le cycle agricole égyptien, tel quel : rien pendant la crue (Akhèt, le champ
 * est submergé), rien non plus pendant la pousse (Perèt — avoir un champ ne
 * nourrit personne, seule la récolte le fait), la moisson d'un coup à Chémou.
 * Un champ n'est donc pas une rente régulière — il faut avoir semé au bon
 * moment et tenu jusqu'à la récolte.
 *
 * Un champ terrestre (Fertile, Oasis) suit un cycle différent, indépendant de
 * la saison : voir `CycleAgricoleTerrestre`.
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
            // Semis puis pousse : la terre travaille, rien n'entre encore au
            // Grenier — seule la récolte de Chémou le fait.
            Saison::Peret => 0,
            // La moisson, modulée par le limon que la crue a déposé.
            Saison::Chemou => intdiv(
                self::RECOLTE_DE_REFERENCE * $crue->modificateurEnDixiemes(),
                10,
            ),
        };
    }

    /**
     * L'étape d'un champ du Nil, pour l'affichage — le même vocabulaire que
     * `CycleAgricoleTerrestre::etape()`, mais piloté par la saison plutôt que
     * par un compteur : Akhèt submerge le champ (repos forcé), le premier
     * quart de Perèt est le semis, le reste sa pousse, Chémou sa récolte.
     */
    public static function etape(?Saison $saison, ?int $rangDansLaSaison): EtapeDeChamp
    {
        return match (true) {
            null === $saison || null === $rangDansLaSaison => EtapeDeChamp::Repos,
            Saison::Akhet === $saison => EtapeDeChamp::Repos,
            Saison::Peret === $saison && $rangDansLaSaison <= intdiv(DateDeJeu::CYCLES_PAR_SAISON, 4) => EtapeDeChamp::Semis,
            Saison::Peret === $saison => EtapeDeChamp::Pousse,
            default => EtapeDeChamp::Recolte,
        };
    }
}
