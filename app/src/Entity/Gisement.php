<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\GisementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un filon sur une case de la carte (doc 02).
 *
 * Une entité à part, et non trois colonnes de `Zone` : **une case porte
 * plusieurs ressources**, jusqu'à Zone::GISEMENTS_MAX. Sans quoi l'argile et
 * les roseaux — les deux matériaux dont rien ne tient lieu, tous deux nés de
 * l'eau — se disputaient les rares berges d'une petite carte, et la partie
 * pouvait se figer faute de l'un des deux.
 *
 * Chaque filon s'exploite pour lui-même : ouvrir une carrière d'argile
 * n'engage pas la roselière voisine.
 */
#[ORM\Entity(repositoryClass: GisementRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GISEMENT_PAR_ZONE', columns: ['zone_id', 'ressource'])]
class Gisement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'gisements')]
    #[ORM\JoinColumn(nullable: false)]
    private Zone $zone;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    /**
     * Ce qu'il reste à extraire. Les régions faciles ne s'épuisent pas
     * (doc 02) — le compteur descend quand même, mais rien n'en dépend encore :
     * l'épuisement viendra avec les régions difficiles.
     */
    #[ORM\Column]
    private int $quantiteRestante;

    #[ORM\Column]
    private bool $exploitee = false;

    public function __construct(Zone $zone, Ressource $ressource, int $quantite)
    {
        $this->zone = $zone;
        $this->ressource = $ressource;
        $this->quantiteRestante = $quantite;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZone(): Zone
    {
        return $this->zone;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantiteRestante(): int
    {
        return $this->quantiteRestante;
    }

    public function estEpuise(): bool
    {
        return 0 === $this->quantiteRestante;
    }

    public function estExploitee(): bool
    {
        return $this->exploitee;
    }

    public function exploiter(): static
    {
        $this->exploitee = true;

        return $this;
    }

    /**
     * Prélève sur le filon, sans jamais descendre sous zéro. Renvoie ce qui a
     * effectivement été extrait, qui peut être moindre que demandé sur la fin.
     */
    public function extraire(int $quantite): int
    {
        $extrait = min($quantite, $this->quantiteRestante);
        $this->quantiteRestante -= $extrait;

        return $extrait;
    }

    /**
     * Désignation courte, pour les messages destinés au joueur.
     */
    public function libelle(): string
    {
        return \sprintf('gisement de %s', $this->ressource->libelle());
    }
}
