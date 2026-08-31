<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que la faveur des dieux change réellement (doc 07).
 *
 * C'est le lot exposé de la phase, et il obéit à une discipline stricte, héritée
 * du double comptage retiré au lot 4.5 : **la faveur n'ajoute jamais un
 * multiplicateur à une chaîne qui en a déjà un.** Là où un facteur existe, elle
 * déplace la valeur qui l'alimente ; là où il n'en existe aucun, elle agit
 * directement.
 *
 * D'où le partage, dieu par dieu :
 *
 * - **Hâpi** ne multiplie pas la récolte : il **infléchit le tirage de la
 *   crue**, d'un cran dans un sens ou dans l'autre. La récolte garde son unique
 *   modificateur de crue.
 * - **Ptah** ne multiplie pas l'avancement d'un chantier : il **s'ajoute au
 *   facteur de saison** déjà en place, dans la même unité.
 * - **Osiris** ne touche pas au rendement d'un champ : il **raccourcit la
 *   jachère** d'un champ terrestre, qui revient donc plus souvent. Le dieu du
 *   grain qui meurt et renaît agit sur le cycle, pas sur la gerbe.
 * - **Amon-Rê** et **Sobek** agissent sur des chaînes qui n'ont aucun
 *   multiplicateur : l'attractivité de la ville et la durée d'un trajet par
 *   eau.
 *
 * **Un dieu favorable ne pénalise jamais une production.** L'hostilité se paie
 * autrement — une crue moins généreuse, et la fièvre au lot 6.6 —, jamais par
 * un malus de rendement : deux malus qui se multiplient sont exactement ce qui
 * a fait tomber la chaîne alimentaire à 25 % au lot 4.4.
 *
 * **Valeurs inventées** : le doc 07 chiffre les paliers, jamais leurs effets.
 */
final readonly class EffetDeFaveur
{
    /**
     * Ce que Ptah ajoute au facteur d'avancement des chantiers, en centièmes —
     * la même unité que le facteur de saison, pour qu'ils s'additionnent au
     * lieu de se multiplier.
     */
    public const int CHANTIERS_FAVORABLE = 15;
    public const int CHANTIERS_DEVOUE = 30;

    /**
     * Ce qu'Amon-Rê retire au coût d'un appel d'habitants, en centièmes, et ce
     * qu'il ajoute à la chance qu'une maisonnée vienne d'elle-même.
     */
    public const int APPEL_MOINS_CHER_FAVORABLE = 20;
    public const int APPEL_MOINS_CHER_DEVOUE = 35;
    public const int MIGRATION_FAVORABLE = 10;
    public const int MIGRATION_DEVOUE = 20;

    /**
     * Ce que Sobek retire à un trajet par eau, en centièmes. Comme pour le
     * Logisticien, **jamais sous une quinzaine** : une route reste une route,
     * et c'est la distance qui décide de la fréquence des convois.
     */
    public const int TRAJET_FAVORABLE = 15;
    public const int TRAJET_DEVOUE = 25;

    /**
     * **La crue infléchie par Hâpi**, d'un cran et pas davantage.
     *
     * Le hasard reste le hasard : un dieu dévoué ne garantit pas une crue
     * forte, il rend la faible moins probable. Une crue déjà forte ne monte
     * pas plus haut — il n'y a pas de cran au-dessus.
     */
    public static function crueInflechie(City $ville, QualiteDeCrue $tiree): QualiteDeCrue
    {
        $palier = $ville->palierDe(Divinite::Hapi);

        if ($palier->estAuDessusDuNeutre()) {
            return $tiree->cranAuDessus();
        }

        if ($palier->nuit()) {
            return $tiree->cranEnDessous();
        }

        return $tiree;
    }

    /**
     * Ce que Ptah ajoute au facteur d'avancement d'un chantier, en centièmes.
     * Zéro sous le palier Favorable : la faveur accélère, elle ne freine pas.
     */
    public static function bonusDeChantier(City $ville): int
    {
        return self::selonLePalier(
            $ville->palierDe(Divinite::Ptah),
            self::CHANTIERS_FAVORABLE,
            self::CHANTIERS_DEVOUE,
        );
    }

    /**
     * La jachère d'un champ terrestre sous le regard d'Osiris : le champ
     * revient plus vite, il ne rend pas davantage.
     */
    public static function jachereRaccourcie(City $ville): bool
    {
        return $ville->palierDe(Divinite::Osiris)->estAuDessusDuNeutre();
    }

    /**
     * Ce qu'Amon-Rê retire au coût d'un appel d'habitants, en centièmes.
     */
    public static function remiseSurLAppel(City $ville): int
    {
        return self::selonLePalier(
            $ville->palierDe(Divinite::AmonRe),
            self::APPEL_MOINS_CHER_FAVORABLE,
            self::APPEL_MOINS_CHER_DEVOUE,
        );
    }

    /**
     * Ce qu'Amon-Rê ajoute à la chance annuelle qu'une maisonnée s'installe
     * d'elle-même — nulle tant que la renommée ne l'a pas ouverte : un dieu
     * fait parler de vous, il ne vous rend pas célèbre à votre place.
     */
    public static function bonusDeMigration(City $ville): int
    {
        return self::selonLePalier(
            $ville->palierDe(Divinite::AmonRe),
            self::MIGRATION_FAVORABLE,
            self::MIGRATION_DEVOUE,
        );
    }

    /**
     * Ce que Sobek retire à un trajet, en centièmes — sur l'eau seulement. Une
     * piste caravanière ne le regarde pas.
     */
    public static function raccourciDeSobek(City $ville, TypeDeRoute $route): int
    {
        if (TypeDeRoute::Terrestre === $route) {
            return 0;
        }

        return self::selonLePalier(
            $ville->palierDe(Divinite::Sobek),
            self::TRAJET_FAVORABLE,
            self::TRAJET_DEVOUE,
        );
    }

    private static function selonLePalier(PalierDeFaveur $palier, int $favorable, int $devoue): int
    {
        return match ($palier) {
            PalierDeFaveur::Devoue => $devoue,
            PalierDeFaveur::Favorable => $favorable,
            PalierDeFaveur::Neutre, PalierDeFaveur::Hostile => 0,
        };
    }
}
