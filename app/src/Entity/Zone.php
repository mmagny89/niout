<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\Ressource;
use App\Game\TypeDeTerrain;
use App\Repository\ZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    /**
     * Une case porte au plus deux gisements (décision de la joueuse).
     *
     * Deux, et non un : l'argile et les roseaux sont les deux matériaux dont
     * rien ne tient lieu, et tous deux naissent de l'eau. À un gisement par
     * case, ils se disputaient les rares berges d'une petite carte, au point
     * qu'une partie pouvait se figer faute de l'un des deux.
     *
     * Deux, et non davantage : on ne trouve jamais tout au même endroit, sans
     * quoi explorer cesserait d'avoir un intérêt.
     */
    public const int GISEMENTS_MAX = 2;

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

    /**
     * @var Collection<int, Gisement>
     */
    #[ORM\OneToMany(targetEntity: Gisement::class, mappedBy: 'zone', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $gisements;

    /**
     * Ce qui est semé sur cette case, si un champ y est établi (doc 01, doc 02).
     */
    #[ORM\Column(nullable: true, enumType: Culture::class)]
    private ?Culture $culture = null;

    #[ORM\Column]
    private bool $decouverte = false;

    /**
     * La case où se dresse la ville. Découverte d'emblée, évidemment.
     */
    #[ORM\Column]
    private bool $porteLaVille = false;

    public function __construct(City $ville, int $x, int $y, TypeDeTerrain $terrain)
    {
        $this->gisements = new ArrayCollection();
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

    /**
     * @return Collection<int, Gisement>
     */
    public function getGisements(): Collection
    {
        return $this->gisements;
    }

    public function porteUnGisement(): bool
    {
        return !$this->gisements->isEmpty();
    }

    public function gisementDe(Ressource $ressource): ?Gisement
    {
        foreach ($this->gisements as $gisement) {
            if ($gisement->getRessource() === $ressource) {
                return $gisement;
            }
        }

        return null;
    }

    /**
     * Ajoute un filon. Sans effet si la case en porte déjà autant qu'elle peut,
     * ou si elle porte déjà ce matériau-là — on ne trouve jamais tout au même
     * endroit, c'est ce qui fait qu'explorer garde un intérêt.
     */
    public function poserUnGisement(Ressource $ressource, int $quantite): static
    {
        if (null !== $this->gisementDe($ressource) || $this->gisements->count() >= self::GISEMENTS_MAX) {
            return $this;
        }

        $this->contenu = ContenuDeZone::Ressource;
        $this->gisements->add(new Gisement($this, $ressource, $quantite));

        return $this;
    }

    public function peutPorterUnGisementDePlus(): bool
    {
        return $this->gisements->count() < self::GISEMENTS_MAX;
    }

    public function poserUnContenu(ContenuDeZone $contenu): static
    {
        $this->contenu = $contenu;
        $this->gisements->clear();

        return $this;
    }

    public function estDecouverte(): bool
    {
        return $this->decouverte;
    }

    public function getCulture(): ?Culture
    {
        return $this->culture;
    }

    public function porteUnChamp(): bool
    {
        return null !== $this->culture;
    }

    /**
     * Un champ ne s'établit que sur une terre qui l'accepte — terre fertile,
     * oasis, ou berge du Nil que la crue limone (doc 02) — et seulement là où
     * la génération a vu une terre cultivable.
     */
    public function accepteUnChamp(): bool
    {
        return $this->terrain->accepteUnChamp()
            && ContenuDeZone::ChampEligible === $this->contenu;
    }

    public function semer(Culture $culture): static
    {
        $this->culture = $culture;

        return $this;
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
