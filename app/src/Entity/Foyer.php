<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Population;
use App\Repository\FoyerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une maisonnée installée dans la ville (doc 01, doc 02).
 *
 * Ce que la ville héberge, ce ne sont pas des employés isolés mais des
 * **familles** : le doc 02 compte son vivier en familles, le doc 01 chiffre la
 * capacité du Quartier d'habitation en familles. Embaucher, c'est installer un
 * foyer — un salaire versé, plusieurs bouches à nourrir.
 *
 * **Seuls les enfants portent un âge.** Les adultes sont comptés, pas datés :
 * rien dans cette phase ne dépend de leur âge — ni vieillissement, ni retraite,
 * ni mortalité, tous hors périmètre. Leur donner une date de naissance ne
 * produirait que de la donnée morte. Les enfants, eux, grandissent, et c'est
 * précisément ce que le joueur regarde en choisissant un candidat.
 */
#[ORM\Entity(repositoryClass: FoyerRepository::class)]
class Foyer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'foyers')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column]
    private int $adultes;

    /**
     * Âges des enfants, **en quinzaines** et non en années.
     *
     * En quinzaines parce que c'est l'unité dans laquelle le temps avance : une
     * année de jeu en compte 25, et compter en années obligerait à traîner un
     * reste d'une quinzaine à l'autre. La conversion se fait à l'affichage,
     * jamais dans le calcul.
     *
     * @var list<int>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $agesDesEnfants;

    /**
     * @param list<int> $agesDesEnfants en quinzaines
     */
    public function __construct(City $ville, int $adultes, array $agesDesEnfants = [])
    {
        $this->ville = $ville;
        $this->adultes = $adultes;
        $this->agesDesEnfants = $agesDesEnfants;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    /**
     * Les bras que ce foyer met à disposition. **Tous les adultes travaillent,
     * sans distinction de sexe** : les Égyptiennes filaient et tissaient — le
     * textile était massivement féminin —, moulaient le grain, brassaient,
     * moissonnaient, et exerçaient des métiers attestés, avec une autonomie
     * juridique inhabituelle pour l'époque.
     */
    public function getAdultes(): int
    {
        return $this->adultes;
    }

    public function getEnfants(): int
    {
        return \count($this->agesDesEnfants);
    }

    public function personnes(): int
    {
        return $this->adultes + $this->getEnfants();
    }

    /**
     * @return list<int> âges des enfants, en années révolues, du plus grand au
     *                   plus jeune — l'ordre qui intéresse le joueur, puisque
     *                   c'est l'aîné qui deviendra un bras le premier
     */
    public function agesDesEnfantsEnAnnees(): array
    {
        $annees = array_map(Population::enAnnees(...), $this->agesDesEnfants);
        rsort($annees);

        return $annees;
    }

    /**
     * Ce que le foyer mange, **compté en demi-rations** : deux pour un adulte,
     * une pour un enfant.
     *
     * En demi-rations et non en vivres, pour ne jamais manipuler de 0,5 — le
     * jeu ne compare aucune valeur en flottants. La conversion en vivres se
     * fait une seule fois, à l'échelle de la ville.
     */
    public function demiRations(): int
    {
        return $this->adultes * 2 + $this->getEnfants();
    }

    /**
     * Fait vieillir le foyer d'une quinzaine et renvoie le nombre d'enfants
     * qui viennent d'atteindre l'âge de travailler — ceux-là passent adultes.
     */
    public function vieillirDUneQuinzaine(): int
    {
        $restants = [];
        $majeurs = 0;

        foreach ($this->agesDesEnfants as $age) {
            ++$age;

            if ($age >= Population::AGE_ADULTE_EN_QUINZAINES) {
                ++$majeurs;
                continue;
            }

            $restants[] = $age;
        }

        $this->agesDesEnfants = $restants;
        $this->adultes += $majeurs;

        return $majeurs;
    }
}
