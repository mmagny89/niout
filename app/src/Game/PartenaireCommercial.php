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
     * Quarante pour cent de plus que le Marché local : c'est ce qui rend
     * l'export intéressant, et ce qui laisse au joueur de quoi se tromper en
     * demandant trop.
     */
    public const int PRIX_MAXIMUM_A_LA_VENTE = 140;

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
     * Ce qu'un convoi porte par voyage, selon le niveau du bâtiment qui
     * l'arme (doc 12).
     */
    public function volumeParConvoi(int $niveauDuBatiment): int
    {
        return $this->route->volumeParNiveau() * max(1, $niveauDuBatiment);
    }
}
