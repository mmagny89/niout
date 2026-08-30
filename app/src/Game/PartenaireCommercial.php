<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Une cité avec qui commercer (doc 12).
 *
 * Contenu de référence, jamais persisté : seule la clé du partenaire est
 * stockée sur une route ouverte.
 *
 * **Ce qu'un partenaire vend et achète n'est pas décoratif** : c'est ce qui
 * décide de ce qu'une route apporte. Byblos vend du cèdre et achète du lin ;
 * Pount vend l'encens et la myrrhe et n'achète presque rien, la région étant
 * un point de transit plutôt qu'un marché.
 *
 * **Les fourchettes de prix se déduisent** plutôt que de se tabuler : dix
 * partenaires par vingt-cinq ressources feraient cinq cents nombres à tenir à
 * jour, dont aucun ne dirait rien de plus que la règle qui les engendre.
 */
final readonly class PartenaireCommercial
{
    /**
     * Ce qu'un partenaire consent à payer au plus, en centièmes du prix local
     * (`PrixDuMarche`). **Valeur inventée** : le doc 08 chiffre la majoration
     * d'import, jamais la marge de négociation.
     *
     * **Le double du cours local.** Une première version plafonnait à 140 %,
     * ce qui ne laissait au lin — coté 4 — que deux prix entiers possibles :
     * le levier n'existait pas pour les matières bon marché. À 200 %, chaque
     * ressource a une vraie plage de négociation.
     *
     * C'est aussi ce qui rend l'export préférable au Marché local, lequel
     * plafonne à ~130 % du cours avec un bon chef. La contrepartie est réelle :
     * une route coûte 100 à 150 deben, prend le temps du trajet, et ne porte
     * qu'un convoi par quinzaine.
     */
    public const int PRIX_MAXIMUM_A_LA_VENTE = 200;

    /**
     * Ce qu'un partenaire réclame au moins pour ce qu'il vend, en centièmes du
     * prix local : la majoration d'import du doc 08, `prixLocal × 1,5`, qui
     * paie le transport.
     */
    public const int PRIX_MINIMUM_A_LACHAT = 150;

    /**
     * @param list<Ressource> $vend   ce qu'on peut lui acheter
     * @param list<Ressource> $achete ce qu'on peut lui vendre
     */
    public function __construct(
        public string $cle,
        public string $nom,
        public TypeDeRoute $route,
        public int $distanceEnQuinzaines,
        public array $vend,
        public array $achete,
        public string $description,
    ) {
    }

    public function vendDe(Ressource $ressource): bool
    {
        return \in_array($ressource, $this->vend, true);
    }

    public function acheteDe(Ressource $ressource): bool
    {
        return \in_array($ressource, $this->achete, true);
    }

    /**
     * Le prix le plus élevé auquel il achètera cette ressource. Au-dessus,
     * aucun convoi ne part : le joueur a demandé trop cher.
     */
    public function prixMaximumALaVente(Ressource $ressource): ?int
    {
        $local = PrixDuMarche::pour($ressource);

        if (null === $local || !$this->acheteDe($ressource)) {
            return null;
        }

        return max(1, intdiv($local * self::PRIX_MAXIMUM_A_LA_VENTE, 100));
    }

    /**
     * Le prix le plus bas auquel il cédera cette ressource. En dessous, rien
     * n'arrive : le joueur a offert trop peu.
     */
    public function prixMinimumALAchat(Ressource $ressource): ?int
    {
        $local = PrixDuMarche::pour($ressource);

        if (null === $local || !$this->vendDe($ressource)) {
            return null;
        }

        return max(1, intdiv($local * self::PRIX_MINIMUM_A_LACHAT, 100));
    }

    /**
     * Ce prix bradé qui fait se presser les convois, en centièmes du cours
     * local. **Valeur inventée.**.
     *
     * Le double du cours pour ce qu'on lui achète : à ce prix-là, la cité
     * charge tout ce qu'elle peut.
     */
    public const int PRIX_GENEREUX_A_LACHAT = 200;

    /**
     * Ce qui reste d'empressement quand on négocie au plus juste, en
     * centièmes. Un quart : la cité traite encore, mais du bout des lèvres.
     */
    public const int EMPRESSEMENT_MINIMAL = 25;

    /**
     * Avec quel empressement ce partenaire prendra ce qu'on lui vend à ce
     * prix — de 0 (il refuse) à 100 (il charge tout ce qu'il peut).
     *
     * **C'est ici que le prix devient un levier, et le seul.** Trop gourmand,
     * personne n'achète ; au cours local, la cité prend tout mais on ne gagne
     * rien de plus qu'au Marché d'à côté. Entre les deux, le joueur arbitre
     * entre la marge et le volume — et c'est cet arbitrage qui fait du
     * commerce autre chose qu'un robinet.
     */
    public function empressementALaVente(Ressource $ressource, int $prix): int
    {
        $cours = PrixDuMarche::pour($ressource);
        $maximum = $this->prixMaximumALaVente($ressource);

        if (null === $cours || null === $maximum || $prix > $maximum) {
            return 0;
        }

        if ($prix <= $cours) {
            return 100;
        }

        return 100 - intdiv((100 - self::EMPRESSEMENT_MINIMAL) * ($prix - $cours), max(1, $maximum - $cours));
    }

    /**
     * Avec quel empressement il cédera ce qu'on lui achète à ce prix. En
     * dessous de ce qu'il réclame, rien n'arrive ; au double du cours, il
     * charge tout.
     */
    public function empressementALAchat(Ressource $ressource, int $prix): int
    {
        $cours = PrixDuMarche::pour($ressource);
        $minimum = $this->prixMinimumALAchat($ressource);

        if (null === $cours || null === $minimum || $prix < $minimum) {
            return 0;
        }

        $genereux = max($minimum + 1, intdiv($cours * self::PRIX_GENEREUX_A_LACHAT, 100));

        if ($prix >= $genereux) {
            return 100;
        }

        return self::EMPRESSEMENT_MINIMAL
            + intdiv((100 - self::EMPRESSEMENT_MINIMAL) * ($prix - $minimum), max(1, $genereux - $minimum));
    }

    /**
     * L'empressement dans le sens demandé — de quoi éviter aux appelants de
     * choisir entre les deux méthodes.
     */
    public function empressement(SensDEchange $sens, Ressource $ressource, int $prix): int
    {
        return SensDEchange::Vendre === $sens
            ? $this->empressementALaVente($ressource, $prix)
            : $this->empressementALAchat($ressource, $prix);
    }

    /**
     * Traite-t-il cette ressource dans ce sens ?
     */
    public function traite(SensDEchange $sens, Ressource $ressource): bool
    {
        return SensDEchange::Vendre === $sens
            ? $this->acheteDe($ressource)
            : $this->vendDe($ressource);
    }

    /**
     * Ce qu'un convoi porte par voyage, selon le niveau du bâtiment qui
     * l'arme (doc 12).
     */
    public function volumeParConvoi(int $niveauDuBatiment): int
    {
        return $this->route->volumeParNiveau() * max(1, $niveauDuBatiment);
    }
}
