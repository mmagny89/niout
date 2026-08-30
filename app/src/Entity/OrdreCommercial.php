<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Game\SensDEchange;
use App\Repository\OrdreCommercialRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que la ville annonce vendre ou acheter sur une route, et à quel prix.
 *
 * **C'est un étal, pas un bouton d'échange** (décision de la joueuse) : le
 * joueur ne clique pas « commercer », il affiche des conditions et attend que
 * les convois viennent. Un ordre reste posé jusqu'à ce qu'on le retire.
 *
 * **Le prix est le levier, et le seul.** Trop gourmand à la vente, personne
 * n'achète ; trop pingre à l'achat, rien n'arrive. Entre les deux, il décide
 * de l'empressement du partenaire — donc du volume qui bouge à chaque convoi
 * (`PartenaireCommercial::empressement()`).
 *
 * **La quantité par convoi est un garde-fou, pas un objectif** : elle existe
 * pour qu'un ordre permanent ne vide jamais la ville sans prévenir.
 */
#[ORM\Entity(repositoryClass: OrdreCommercialRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDRE_PAR_ROUTE', columns: ['route_id', 'ressource'])]
class OrdreCommercial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ordres')]
    #[ORM\JoinColumn(nullable: false)]
    private RouteCommerciale $route;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column(enumType: SensDEchange::class)]
    private SensDEchange $sens;

    /**
     * Ce que la ville demande à la vente, ou consent à payer à l'achat, par
     * unité et en deben.
     */
    #[ORM\Column]
    private int $prix;

    #[ORM\Column]
    private int $quantiteParConvoi;

    public function __construct(
        RouteCommerciale $route,
        Ressource $ressource,
        SensDEchange $sens,
        int $prix,
        int $quantiteParConvoi,
    ) {
        $this->route = $route;
        $this->ressource = $ressource;
        $this->sens = $sens;
        $this->prix = $prix;
        $this->quantiteParConvoi = $quantiteParConvoi;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoute(): RouteCommerciale
    {
        return $this->route;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getSens(): SensDEchange
    {
        return $this->sens;
    }

    public function getPrix(): int
    {
        return $this->prix;
    }

    public function getQuantiteParConvoi(): int
    {
        return $this->quantiteParConvoi;
    }
}
