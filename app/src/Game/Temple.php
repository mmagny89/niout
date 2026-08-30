<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que le Temple autorise en matière de dévotion (doc 01, doc 07).
 *
 * Le doc 07 est explicite sur le principe : le plafond de faveur atteignable
 * et le nombre de divinités qu'on peut porter haut **dépendent du niveau du
 * Temple**, « sans plafond arbitraire indépendant ». C'est donc le bâtiment
 * qui limite, et rien d'autre — pas une règle posée à côté.
 *
 * Deux limites, et elles ne disent pas la même chose. **Combien de dieux** on
 * peut porter au-dessus du neutre fait de la répartition des offrandes une
 * stratégie : un Temple modeste oblige à choisir. **Jusqu'où** la faveur peut
 * monter fait du palier Dévoué une conquête plutôt qu'une formalité — il
 * demande un Temple déjà bien avancé.
 *
 * **Valeurs inventées** : le doc pose le principe, jamais les nombres.
 */
final readonly class Temple
{
    /**
     * Un Temple porte autant de divinités que son niveau, jamais plus que le
     * panthéon n'en compte.
     */
    public const int DIVINITES_PAR_NIVEAU = 1;

    /**
     * Le plafond de faveur : 55 au premier niveau — de quoi rendre un dieu
     * Favorable, pas davantage —, 100 au dixième. Le palier Dévoué s'ouvre
     * donc au niveau 6, ce qui en fait l'objectif d'une partie avancée plutôt
     * qu'un achat de début.
     */
    public const int PLAFOND_DE_BASE = 50;
    public const int PLAFOND_PAR_NIVEAU = 5;

    /**
     * Combien de dieux la ville peut porter au-dessus du neutre. Zéro sans
     * Temple : on n'honore pas un dieu sur un terrain vague.
     */
    public static function divinitesPortables(City $ville): int
    {
        if ($ville->estEnModeDivin()) {
            return \count(Divinite::pantheon());
        }

        $temple = $ville->batimentDeType(TypeDeBatiment::Temple);

        if (null === $temple) {
            return 0;
        }

        return min(
            \count(Divinite::pantheon()),
            $temple->getNiveau() * self::DIVINITES_PAR_NIVEAU,
        );
    }

    /**
     * Jusqu'où la faveur d'un dieu peut monter dans cette ville.
     *
     * Sans Temple, la valeur de départ : une ville sans sanctuaire ne fait
     * monter personne. Elle ne descend pas non plus — l'absence de Temple
     * n'est pas une faute, seulement un manque.
     */
    public static function plafondDeFaveur(City $ville): int
    {
        if ($ville->estEnModeDivin()) {
            return Divinite::FAVEUR_MAXIMALE;
        }

        $temple = $ville->batimentDeType(TypeDeBatiment::Temple);

        if (null === $temple) {
            return Divinite::FAVEUR_DE_DEPART;
        }

        return min(
            Divinite::FAVEUR_MAXIMALE,
            self::PLAFOND_DE_BASE + $temple->getNiveau() * self::PLAFOND_PAR_NIVEAU,
        );
    }

    /**
     * Reste-t-il une place pour porter ce dieu-ci au-dessus du neutre ?
     *
     * Un dieu déjà porté ne consomme pas une place de plus : c'est la
     * question « puis-je encore monter celui-là », pas « ai-je de la marge ».
     */
    public static function peutEncorePorter(City $ville, Divinite $divinite): bool
    {
        $honorees = $ville->divinitesHonorees();

        if (\in_array($divinite, $honorees, true)) {
            return true;
        }

        return \count($honorees) < self::divinitesPortables($ville);
    }
}
