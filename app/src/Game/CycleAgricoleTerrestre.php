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
     * Le cycle sous le regard d'Osiris : la jachère saute, le champ revient
     * plus tôt (lot 6.3). **Le dieu du grain qui meurt et renaît agit sur le
     * cycle, jamais sur la gerbe** — une récolte ne rend pas davantage, elle
     * revient plus souvent, ce qui évite d'empiler un multiplicateur de plus
     * sur une chaîne qui en porte déjà.
     */
    public const int DUREE_SANS_JACHERE = self::DUREE_TOTALE - self::DUREE_REPOS;

    public static function duree(bool $jachereRaccourcie): int
    {
        return $jachereRaccourcie ? self::DUREE_SANS_JACHERE : self::DUREE_TOTALE;
    }

    /**
     * L'étape courante, à partir du nombre de quinzaines écoulées depuis le
     * semis. Reboucle d'elle-même : pas de remise à zéro à gérer.
     */
    public static function etape(int $quinzainesDepuisSemis, bool $jachereRaccourcie = false): EtapeDeChamp
    {
        $rang = $quinzainesDepuisSemis % self::duree($jachereRaccourcie);

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
    public static function pourUneQuinzaine(int $quinzainesDepuisSemis, bool $jachereRaccourcie = false): int
    {
        return EtapeDeChamp::Recolte === self::etape($quinzainesDepuisSemis, $jachereRaccourcie)
            ? RendementDesChamps::RECOLTE_DE_REFERENCE
            : 0;
    }
}
