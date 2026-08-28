<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le cycle d'un champ établi sur une terre fertile ou une oasis — jamais sur
 * le Nil, dont le rythme reste celui de la crue (`RendementDesChamps`).
 *
 * Un champ terrestre ne dépend ni de la saison ni de la crue : il suit son
 * propre calendrier, semis puis pousse puis récolte puis repos, qui reboucle
 * indéfiniment. C'est ce qui le distingue d'un champ du Nil — la terre, pas le
 * fleuve, en commande le rythme.
 *
 * Durées inventées, comme le reste du cycle agricole (`RendementDesChamps`) :
 * aucun document ne les chiffre. Calibrées sur le même ordre de grandeur que
 * la saison du Nil (8 quinzaines) pour ne pas déséquilibrer l'agriculture
 * terrestre par rapport à celle du fleuve.
 */
final readonly class CycleAgricoleTerrestre
{
    private const int DUREE_SEMIS = 1;
    private const int DUREE_POUSSE = 3;
    public const int DUREE_RECOLTE = 2;
    private const int DUREE_REPOS = 1;

    public const int DUREE_TOTALE = self::DUREE_SEMIS + self::DUREE_POUSSE + self::DUREE_RECOLTE + self::DUREE_REPOS;

    /**
     * L'étape courante, à partir du nombre de quinzaines écoulées depuis le
     * semis. Reboucle d'elle-même : pas de remise à zéro à gérer.
     */
    public static function etape(int $quinzainesDepuisSemis): EtapeDeChamp
    {
        $rang = $quinzainesDepuisSemis % self::DUREE_TOTALE;

        return match (true) {
            $rang < self::DUREE_SEMIS => EtapeDeChamp::Semis,
            $rang < self::DUREE_SEMIS + self::DUREE_POUSSE => EtapeDeChamp::Pousse,
            $rang < self::DUREE_SEMIS + self::DUREE_POUSSE + self::DUREE_RECOLTE => EtapeDeChamp::Recolte,
            default => EtapeDeChamp::Repos,
        };
    }

    /**
     * Rendement d'une quinzaine : rien hors de l'étape « récolte », la pleine
     * récolte de référence pendant celle-ci. Avoir un champ ne nourrit
     * personne — seule la récolte le fait, comme pour un champ du Nil.
     */
    public static function pourUneQuinzaine(int $quinzainesDepuisSemis): int
    {
        return EtapeDeChamp::Recolte === self::etape($quinzainesDepuisSemis)
            ? RendementDesChamps::RECOLTE_DE_REFERENCE
            : 0;
    }
}
