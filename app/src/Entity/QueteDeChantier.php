<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\ChantierRoyal;
use App\Game\Ressource;
use App\Repository\QueteDeChantierRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une requête du pharaon pour l'un de ses chantiers (doc 09).
 *
 * **Jamais obligatoire** : refuser coûte deux points de renommée et rien
 * d'autre. Le joueur reste libre de prioriser sa propre stratégie, ce qui est
 * exactement ce que le document demande.
 *
 * Une seule à la fois, et un délai : la laisser filer revient à la refuser.
 */
#[ORM\Entity(repositoryClass: QueteDeChantierRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_QUETE_PAR_VILLE', columns: ['ville_id'])]
class QueteDeChantier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'queteDeChantier')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(length: 40, enumType: ChantierRoyal::class)]
    private ChantierRoyal $chantier;

    #[ORM\Column(length: 40, enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column]
    private int $quantite;

    #[ORM\Column]
    private int $quinzainesRestantes;

    public function __construct(
        City $ville,
        ChantierRoyal $chantier,
        Ressource $ressource,
        int $quantite,
        int $quinzaines,
    ) {
        $this->ville = $ville;
        $this->chantier = $chantier;
        $this->ressource = $ressource;
        $this->quantite = $quantite;
        $this->quinzainesRestantes = $quinzaines;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChantier(): ChantierRoyal
    {
        return $this->chantier;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getQuinzainesRestantes(): int
    {
        return $this->quinzainesRestantes;
    }

    /**
     * Rend vrai le jour où le délai expire.
     */
    public function avancerDUnCycle(): bool
    {
        $this->quinzainesRestantes = max(0, $this->quinzainesRestantes - 1);

        return 0 === $this->quinzainesRestantes;
    }
}
