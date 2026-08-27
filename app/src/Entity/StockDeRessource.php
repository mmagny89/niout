<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\StockDeRessourceRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La quantité d'une ressource détenue par une ville.
 *
 * Une ligne par ressource effectivement possédée, plutôt que des colonnes
 * fixes : le doc 08 en compte une vingtaine, et la liste continuera de croître
 * avec les objets fabriqués. Une ville ne porte de ligne que pour ce qu'elle a
 * réellement eu entre les mains.
 */
#[ORM\Entity(repositoryClass: StockDeRessourceRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_RESSOURCE_PAR_VILLE', columns: ['ville_id', 'ressource'])]
class StockDeRessource
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'stock')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column]
    private int $quantite = 0;

    public function __construct(City $ville, Ressource $ressource, int $quantite = 0)
    {
        $this->ville = $ville;
        $this->ressource = $ressource;
        $this->quantite = $quantite;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function ajouter(int $quantite): static
    {
        $this->quantite += $quantite;

        return $this;
    }

    /**
     * Retire une quantité. Le stock ne descend jamais sous zéro : la
     * vérification des moyens appartient à l'appelant, pas à cette ligne.
     */
    public function retirer(int $quantite): static
    {
        $this->quantite = max(0, $this->quantite - $quantite);

        return $this;
    }
}
