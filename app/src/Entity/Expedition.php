<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\RoleDExploration;
use App\Game\Saison;
use App\Game\TypeDeTerrain;
use App\Repository\ExpeditionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une expédition en route vers une case de la carte (doc 04).
 *
 * Elle part au clic, avance quinzaine après quinzaine, et ne bloque rien : le
 * joueur continue de bâtir, de commercer et d'en lancer d'autres pendant ce
 * temps. Même mécanique que les chantiers, dont elle reprend le décompte en
 * dixièmes de cycle pour les mêmes raisons.
 */
#[ORM\Entity(repositoryClass: ExpeditionRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_EXPEDITION_PAR_ZONE', columns: ['destination_id'])]
class Expedition
{
    private const int DIXIEMES_PAR_CYCLE = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'expeditions')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Zone $destination;

    #[ORM\Column(enumType: RoleDExploration::class)]
    private RoleDExploration $role;

    #[ORM\Column]
    private int $dureeEnCycles;

    #[ORM\Column]
    private int $avancementEnDixiemes = 0;

    public function __construct(City $ville, Zone $destination, RoleDExploration $role, int $dureeEnCycles)
    {
        $this->ville = $ville;
        $this->destination = $destination;
        $this->role = $role;
        $this->dureeEnCycles = max(1, $dureeEnCycles);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getDestination(): Zone
    {
        return $this->destination;
    }

    public function getRole(): RoleDExploration
    {
        return $this->role;
    }

    public function getDureeEnCycles(): int
    {
        return $this->dureeEnCycles;
    }

    public function avancerDUnCycle(): static
    {
        $this->avancementEnDixiemes += self::DIXIEMES_PAR_CYCLE;

        return $this;
    }

    public function estArrivee(): bool
    {
        return $this->avancementEnDixiemes >= $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;
    }

    public function cyclesRestants(): int
    {
        $restant = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE - $this->avancementEnDixiemes;

        return max(0, (int) ceil($restant / self::DIXIEMES_PAR_CYCLE));
    }

    public function pourcentageDAvancement(): int
    {
        return min(100, (int) round($this->avancementEnDixiemes / ($this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE) * 100));
    }

    /**
     * Durée d'un trajet, en cycles (doc 04).
     *
     * Une case par cycle, jamais moins d'un. Le fleuve module ce coût : la crue
     * d'Akhèt gonfle le Nil et facilite la navigation, Chémou le laisse au plus
     * bas et la rend pénible.
     *
     * **Interprétation retenue** : une expédition « emprunte le Nil » quand sa
     * destination est une case du fleuve. Le document parle du bonus sans dire
     * comment reconnaître un trajet fluvial ; c'est la lecture la plus simple
     * qui garde au qualificatif un sens.
     */
    public static function dureeDuTrajet(int $distance, TypeDeTerrain $destination, ?Saison $saison): int
    {
        $cycles = max(1, $distance);

        if (TypeDeTerrain::Nil !== $destination) {
            return $cycles;
        }

        return match ($saison) {
            Saison::Akhet => max(1, (int) round($cycles * 0.7)),
            Saison::Chemou => (int) ceil($cycles * 1.3),
            default => $cycles,
        };
    }
}
