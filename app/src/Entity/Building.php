<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\CoutDeConstruction;
use App\Game\TypeDeBatiment;
use App\Repository\BuildingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un bâtiment effectivement dressé dans une ville, à son niveau courant.
 *
 * Ne porte que l'état : tout ce qui relève des règles (coûts, plafonds, durées)
 * vit dans TypeDeBatiment.
 */
#[ORM\Entity(repositoryClass: BuildingRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_BATIMENT_PAR_VILLE', columns: ['ville_id', 'type'])]
class Building
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'batiments')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $type;

    #[ORM\Column]
    private int $niveau = 1;

    public function __construct(City $ville, TypeDeBatiment $type, int $niveau = 1)
    {
        $this->ville = $ville;
        $this->type = $type;
        $this->niveau = $niveau;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getType(): TypeDeBatiment
    {
        return $this->type;
    }

    public function getNiveau(): int
    {
        return $this->niveau;
    }

    /**
     * Plafond réellement atteignable ici : le moindre du plafond propre au
     * bâtiment et du plafond régional (doc 01).
     */
    public function niveauMaxAtteignable(): int
    {
        return min($this->type->niveauMax(), $this->ville->niveauMaxRegional());
    }

    public function estAuMaximum(): bool
    {
        return $this->niveau >= $this->niveauMaxAtteignable();
    }

    /**
     * Coût de la montée au niveau suivant, ou null si le plafond est atteint.
     */
    public function coutDeLaMonteeDeNiveau(): ?CoutDeConstruction
    {
        if ($this->estAuMaximum()) {
            return null;
        }

        return $this->type->coutDeBase()->pourNiveau($this->niveau + 1);
    }

    public function monterDUnNiveau(): static
    {
        if ($this->estAuMaximum()) {
            throw new \LogicException(\sprintf('Le %s est déjà au niveau maximal atteignable ici (%d).', $this->type->libelle(), $this->niveauMaxAtteignable()));
        }

        ++$this->niveau;

        return $this;
    }

    /**
     * Combien de chefs ce bâtiment peut employer : `arrondiSupérieur(niveau / 3)`
     * (doc 01), soit un aux niveaux 1 à 3, deux aux niveaux 4 à 6, trois
     * au-delà.
     *
     * Cette formule appartient au lot 4.4 ; elle est avancée ici parce que
     * sans plafond, une offre d'emploi pourrait être postée indéfiniment sur
     * le même bâtiment.
     */
    public function nombreDeChefs(): int
    {
        return intdiv($this->niveau + 2, 3);
    }

    /**
     * Palier d'illustration, de 1 à 4 (doc 15). Plutôt qu'une image par niveau,
     * chaque bâtiment n'en a que quatre, proportionnels à son propre plafond.
     */
    public function palierVisuel(): int
    {
        $max = $this->type->niveauMax();

        return max(1, min(4, (int) ceil($this->niveau / ($max / 4))));
    }
}
