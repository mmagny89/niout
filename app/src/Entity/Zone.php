<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\ContenuDeZone;
use App\Game\Ressource;
use App\Game\TypeDeTerrain;
use App\Repository\ZoneRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une case de la carte d'exploration (doc 02).
 *
 * Tant qu'un éclaireur n'y est pas passé, elle reste sous le brouillard : son
 * terrain et son contenu existent en base, mais le joueur n'en sait rien.
 */
#[ORM\Entity(repositoryClass: ZoneRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ZONE_PAR_VILLE', columns: ['ville_id', 'x', 'y'])]
class Zone
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'zones')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\Column(enumType: TypeDeTerrain::class)]
    private TypeDeTerrain $terrain;

    #[ORM\Column(enumType: ContenuDeZone::class)]
    private ContenuDeZone $contenu = ContenuDeZone::Rien;

    #[ORM\Column(nullable: true, enumType: Ressource::class)]
    private ?Ressource $ressource = null;

    /**
     * Ce qu'il reste à extraire du gisement. Les régions faciles ne s'épuisent
     * pas (doc 02) — le compteur descend quand même, mais rien n'en dépend
     * encore : l'épuisement viendra avec les régions difficiles.
     */
    #[ORM\Column]
    private int $quantiteRestante = 0;

    #[ORM\Column]
    private bool $decouverte = false;

    /**
     * La case où se dresse la ville. Découverte d'emblée, évidemment.
     */
    #[ORM\Column]
    private bool $porteLaVille = false;

    public function __construct(City $ville, int $x, int $y, TypeDeTerrain $terrain)
    {
        $this->ville = $ville;
        $this->x = $x;
        $this->y = $y;
        $this->terrain = $terrain;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getTerrain(): TypeDeTerrain
    {
        return $this->terrain;
    }

    public function definirTerrain(TypeDeTerrain $terrain): static
    {
        $this->terrain = $terrain;

        return $this;
    }

    public function getContenu(): ContenuDeZone
    {
        return $this->contenu;
    }

    public function getRessource(): ?Ressource
    {
        return $this->ressource;
    }

    public function getQuantiteRestante(): int
    {
        return $this->quantiteRestante;
    }

    public function poserUnGisement(Ressource $ressource, int $quantite): static
    {
        $this->contenu = ContenuDeZone::Ressource;
        $this->ressource = $ressource;
        $this->quantiteRestante = $quantite;

        return $this;
    }

    public function poserUnContenu(ContenuDeZone $contenu): static
    {
        $this->contenu = $contenu;
        $this->ressource = null;
        $this->quantiteRestante = 0;

        return $this;
    }

    public function estDecouverte(): bool
    {
        return $this->decouverte;
    }

    public function decouvrir(): static
    {
        $this->decouverte = true;

        return $this;
    }

    public function porteLaVille(): bool
    {
        return $this->porteLaVille;
    }

    public function yPlacerLaVille(): static
    {
        $this->porteLaVille = true;
        // La ville n'est pas à découvrir : on y est.
        $this->decouverte = true;

        return $this;
    }

    /**
     * Distance en cases depuis la ville, au sens des déplacements en grille :
     * le plus grand des deux écarts, une diagonale valant un pas.
     */
    public function distanceDepuis(self $autre): int
    {
        return max(abs($this->x - $autre->x), abs($this->y - $autre->y));
    }

    public function estAdjacenteA(self $autre): bool
    {
        return 1 === $this->distanceDepuis($autre);
    }
}
