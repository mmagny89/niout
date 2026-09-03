<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Recette;
use App\Game\TypeDeBatiment;
use App\Repository\ConsigneDeFabricationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La consigne permanente d'un atelier : ce qu'il refait de lui-même, tant
 * qu'on ne la lève pas (décision de la joueuse au playtest).
 *
 * **Le problème qu'elle résout.** Un ordre de fabrication tient une à trois
 * quinzaines. Passé ce délai, l'Atelier s'arrêtait et attendait qu'on revienne
 * le relancer à la main, ressource par ressource. Sur une partie où l'on passe
 * des quinzaines entières sur la carte, l'Atelier et la Forge dormaient
 * l'essentiel du temps — et c'est là que se fabrique tout ce qui a de la
 * valeur.
 *
 * **Ce qu'elle ne change pas.** Un seul ordre à la fois et par bâtiment : la
 * consigne relance, elle ne parallélise pas. C'est ce qui donne son coût
 * d'opportunité à la fabrication, et l'automatiser ne doit pas le lever.
 *
 * **Elle ne force rien.** À la relance, l'ordre passe par les mêmes
 * vérifications qu'à la main — niveau du bâtiment, second déblocage, matières
 * disponibles. Faute de matières, l'atelier s'arrête et le dit **une fois**,
 * puis retente à chaque quinzaine sans plus rien annoncer : un message répété
 * indéfiniment noierait le journal, comme le faisait le gisement épuisé avant
 * qu'on ne le ferme.
 *
 * **Elle se heurte volontairement au seuil de garde** (`ReserveGardee`) : si
 * le Marché écoule l'argile que l'Atelier réclame, c'est au joueur d'arbitrer
 * en remontant son seuil. Deux consignes qui se contredisent doivent se voir,
 * pas se résoudre en silence au profit de l'une des deux.
 */
#[ORM\Entity(repositoryClass: ConsigneDeFabricationRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CONSIGNE_PAR_BATIMENT', columns: ['ville_id', 'batiment'])]
class ConsigneDeFabrication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'consignesDeFabrication')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    /**
     * Le bâtiment que la consigne tient. Déduit de la recette à la pose, et
     * stocké pour que l'unicité par bâtiment se garde en base plutôt que dans
     * le code.
     */
    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $batiment;

    #[ORM\Column(enumType: Recette::class)]
    private Recette $recette;

    #[ORM\Column]
    private int $lots;

    /**
     * Vrai quand la dernière relance a échoué faute de matières. Sert
     * uniquement à **ne le dire qu'une fois** : sans ce drapeau, un atelier
     * privé de cuivre répéterait la même phrase à chaque quinzaine,
     * indéfiniment.
     */
    #[ORM\Column]
    private bool $enAttenteDeMatieres = false;

    public function __construct(City $ville, Recette $recette, int $lots)
    {
        $this->ville = $ville;
        $this->recette = $recette;
        $this->batiment = $recette->batiment();
        $this->lots = max(1, $lots);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getBatiment(): TypeDeBatiment
    {
        return $this->batiment;
    }

    public function getRecette(): Recette
    {
        return $this->recette;
    }

    public function getLots(): int
    {
        return $this->lots;
    }

    public function estEnAttenteDeMatieres(): bool
    {
        return $this->enAttenteDeMatieres;
    }

    /**
     * Corrige la consigne. La recette peut changer : c'est le même atelier
     * qu'on réoriente, pas une seconde consigne qu'on empile.
     */
    public function reorienter(Recette $recette, int $lots): static
    {
        $this->recette = $recette;
        $this->batiment = $recette->batiment();
        $this->lots = max(1, $lots);
        $this->enAttenteDeMatieres = false;

        return $this;
    }

    /**
     * @return bool vrai s'il faut le dire au joueur — c'est-à-dire la première
     *              fois seulement
     */
    public function signalerLAttente(): bool
    {
        if ($this->enAttenteDeMatieres) {
            return false;
        }

        $this->enAttenteDeMatieres = true;

        return true;
    }

    public function reprendre(): static
    {
        $this->enAttenteDeMatieres = false;

        return $this;
    }
}
