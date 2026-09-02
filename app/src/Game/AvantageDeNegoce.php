<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que la réputation d'une famille lui vaut sur les prix (doc 13, lot 9.3).
 *
 * Le document est chiffré : « −0,2 % par point de renommée à l'achat, plafonné
 * à −20 % », et la majoration symétrique à la vente. Cent points de renommée
 * valent donc vingt points de pourcentage.
 *
 * **Elle entre dans un facteur qui existe, elle n'en ajoute pas un troisième.**
 * C'est la discipline du lot 6.3, et elle vaut ici plus qu'ailleurs : la vente
 * au Marché porte déjà la qualité de direction du bâtiment, l'ordre commercial
 * porte déjà l'avantage du Négociateur. Trois coefficients enchaînés se
 * composeraient — et trois divisions entières de suite perdent des deben à
 * chaque étape, d'une façon que personne ne sait plus expliquer six mois après.
 * La renommée s'**ajoute** donc au coefficient de la chaîne, qui reste
 * appliqué une seule fois.
 *
 * Tout se compte en **points de pourcentage entiers**, jamais en flottants —
 * comme les rendements et la qualité de direction.
 */
final readonly class AvantageDeNegoce
{
    /**
     * Ce que dix points de renommée valent, en points de pourcentage. Deux
     * pour dix, soit les 0,2 % par point du doc 13 — exprimés de façon à ne
     * jamais faire apparaître de virgule.
     */
    public const int PAR_DIX_POINTS_DE_RENOMMEE = 2;

    /**
     * Le plafond **unique**, posé sur l'avantage total plutôt que sur chaque
     * source (arbitrage 9.0). Trois plafonds séparés se cumulent et n'en
     * plafonnent aucun : le Négociateur en vaut vingt-cinq, la renommée pleine
     * vingt, et le carnet de contacts du lot 9.4 s'y ajoutera.
     *
     * **Quarante, et la raison est dans `PRIX_MINIMUM_A_LACHAT`** : le prix
     * plancher d'un partenaire vaut 150 % du cours local moins l'avantage. À
     * cinquante, ce plancher rejoint le cours local — importer ne coûterait
     * plus rien de plus que produire sur place, et la distance, les routes et
     * les convois cesseraient de peser quoi que ce soit. Quarante laisse dix
     * points de marge à cet effondrement.
     */
    public const int PLAFOND_TOTAL = 40;

    /**
     * Ce que vaut une renommée donnée, en points de pourcentage. Vingt au plus,
     * la jauge étant bornée à cent.
     */
    public static function deLaRenommee(int $renommee): int
    {
        return intdiv(max(0, $renommee) * self::PAR_DIX_POINTS_DE_RENOMMEE, 10);
    }

    /**
     * L'avantage total, plafonné. `$autresSources` porte ce que le Négociateur
     * arrache, et accueillera le carnet de contacts.
     */
    public static function total(int $renommee, int $autresSources = 0): int
    {
        return min(
            self::PLAFOND_TOTAL,
            self::deLaRenommee($renommee) + max(0, $autresSources),
        );
    }
}
