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
     * Ce que le monde peut retirer au plus : l'amplitude de la conjoncture
     * (`ConjonctureDeMarche::AMPLITUDE`). Un marché défavorable **rogne** l'avantage acquis,
     * jusqu'à rendre la fourchette moins large que le cours — jamais davantage.
     */
    public const int PLANCHER_TOTAL = -ConjonctureDeMarche::AMPLITUDE;

    /**
     * Ce que vaut une renommée donnée, en points de pourcentage. Vingt au plus,
     * la jauge étant bornée à cent.
     */
    public static function deLaRenommee(int $renommee): int
    {
        return intdiv(max(0, $renommee) * self::PAR_DIX_POINTS_DE_RENOMMEE, 10);
    }

    /**
     * L'avantage total, borné des deux côtés. `$autresSources` porte ce que le Négociateur
     * arrache et le carnet de contacts : jamais négatif. **`$monde` est la conjoncture du
     * marché** (palier A) : **signée**, de −20 à +20, elle s'ajoute comme une source de
     * plus et passe sous le même plafond de quarante — un monde qui s'ajouterait au plafond
     * le rendrait caduc.
     */
    public static function total(int $renommee, int $autresSources = 0, int $monde = 0): int
    {
        return max(
            self::PLANCHER_TOTAL,
            min(
                self::PLAFOND_TOTAL,
                self::deLaRenommee($renommee) + max(0, $autresSources) + $monde,
            ),
        );
    }
}
