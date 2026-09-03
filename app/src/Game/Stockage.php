<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que la ville peut garder, et ce qui déborde (doc 01).
 *
 * Deux réserves, jamais une seule : le **Grenier** tient les vivres,
 * l'**Entrepôt** les matériaux et les objets. Chacune a un plafond, et le
 * niveau du bâtiment l'élève.
 *
 * **Le surplus est perdu à l'entrée, il ne se dégrade pas** (décision de la
 * joueuse). Le doc 01 prévoyait un grain laissé à l'air libre qui se périme en
 * trois quinzaines ; c'est un système d'inventaire à part entière, et le
 * plafond suffit à donner un effet aux niveaux. Ce qui est rangé y reste donc
 * indéfiniment ; ce qui ne rentre pas ne rentre pas.
 *
 * **Le deben n'est pas stocké** : la monnaie n'occupe ni grenier ni entrepôt,
 * et n'a donc aucun plafond. Un joueur qui vend son surplus le convertit ainsi
 * en une valeur qui ne déborde jamais — c'est l'issue que le plafond doit
 * pousser à prendre.
 *
 * Une **réserve de base** existe sans le moindre bâtiment : la Résidence
 * familiale a ses propres jarres et son propre cellier. Sans elle, la dotation
 * royale — une année de vivres et de quoi bâtir quatre bâtiments — s'évaporerait
 * à la première quinzaine, avant même que l'Entrepôt ne sorte de terre.
 */
final readonly class Stockage
{
    /**
     * Ce que la Résidence familiale tient à elle seule. **Valeurs inventées**,
     * calibrées sur la dotation royale : elle doit y tenir **avec de la
     * marge**, et `StockageTest` le vérifie plutôt que de s'y fier.
     *
     * La marge compte autant que le plafond. Une réserve qui contiendrait la
     * dotation au ras ferait démarrer la ville saturée : la première carrière
     * ouverte ne rapporterait rien, et le joueur perdrait sa récolte avant
     * d'avoir compris qu'il avait un plafond.
     *
     * **Les vivres sont passés de 250 à 450** avec le convoi de dix-sept
     * personnes (`Population::ACTIFS_AU_DEPART`) : la dotation royale porte une
     * année de nourriture, soit désormais 325 unités, et elle ne tenait plus
     * dans 250 — la ville en perdait une part au premier cycle, sans avoir rien
     * fait. C'est le couplage que `StockageTest` garde : **toute hausse de la
     * population de départ remonte la dotation, donc réclame cette marge.**
     */
    public const int RESERVE_DE_BASE_EN_VIVRES = 450;
    public const int RESERVE_DE_BASE_EN_MATERIAUX = 250;

    /**
     * Ce que chaque niveau ajoute. Les vivres viennent du doc 01
     * (« 100 × niveau unités de nourriture ») ; le chiffre de l'Entrepôt est
     * **inventé**, le document décrivant sa capacité sans jamais la chiffrer.
     *
     * Cent cinquante par niveau, soit le doublement de la réserve de base au
     * premier niveau : assez pour qu'un Entrepôt neuf change quelque chose,
     * assez peu pour qu'une ville qui exploite trois carrières doive écouler
     * son surplus plutôt que l'entasser.
     */
    public const int VIVRES_PAR_NIVEAU_DE_GRENIER = 100;
    public const int MATERIAUX_PAR_NIVEAU_DENTREPOT = 150;

    /**
     * À partir de quelle part du plafond l'écran prévient. **Le joueur doit
     * voir venir la saturation avant qu'elle ne lui coûte une moisson** : un
     * plafond qui fait disparaître une récolte en silence est le genre de règle
     * qu'on subit sans comprendre.
     */
    public const int SEUIL_DALERTE = 85;

    /**
     * Le plafond qui s'applique à cette ressource, ou `null` si elle n'en a
     * aucun — c'est le cas de la seule monnaie.
     */
    public static function plafondPour(City $ville, Ressource $ressource): ?int
    {
        // Une partie d'essai n'a pas de réserve à ménager : sans cette levée,
        // le million de ressources du mode divin serait refusé à l'entrée par
        // la règle même qu'on veut pouvoir mettre de côté pour tester.
        if ($ressource->estLaMonnaie() || $ville->estEnModeDivin()) {
            return null;
        }

        return $ressource->estNourriture()
            ? self::plafondDesVivres($ville)
            : self::plafondDesMateriaux($ville);
    }

    public static function plafondDesVivres(City $ville): int
    {
        return self::RESERVE_DE_BASE_EN_VIVRES
            + self::VIVRES_PAR_NIVEAU_DE_GRENIER * self::niveauDe($ville, TypeDeBatiment::Grenier);
    }

    public static function plafondDesMateriaux(City $ville): int
    {
        return self::RESERVE_DE_BASE_EN_MATERIAUX
            + self::MATERIAUX_PAR_NIVEAU_DENTREPOT * self::niveauDe($ville, TypeDeBatiment::Entrepot);
    }

    /**
     * Ce qui est déjà rangé dans la réserve dont relève cette ressource.
     */
    public static function occupationPour(City $ville, Ressource $ressource): int
    {
        return $ressource->estNourriture() ? $ville->getNourriture() : $ville->getMateriaux();
    }

    /**
     * Vrai quand une réserve approche de son plafond, au point que l'écran
     * doive le dire.
     */
    public static function saturationProche(int $occupation, int $plafond): bool
    {
        return $plafond > 0 && $occupation * 100 >= $plafond * self::SEUIL_DALERTE;
    }

    private static function niveauDe(City $ville, TypeDeBatiment $type): int
    {
        return $ville->batimentDeType($type)?->getNiveau() ?? 0;
    }
}
