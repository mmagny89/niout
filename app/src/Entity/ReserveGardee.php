<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\ReserveGardeeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que la ville garde en réserve d'une ressource, et en deçà de quoi elle ne
 * vend jamais (décision de la joueuse au playtest).
 *
 * **Le problème qu'elle résout.** Le Marché ne rapportait un deben que si le
 * joueur venait cliquer, ressource par ressource, à chaque quinzaine. Une
 * partie menée normalement — on explore, on bâtit, on avance le temps — ne
 * produisait donc **aucune monnaie**, alors que les salaires, eux, tombaient à
 * chaque cycle. La caisse ne pouvait que descendre.
 *
 * **Le principe, et son sens de lecture.** Le joueur ne déclare pas ce qu'il
 * vend, il déclare **ce qu'il garde** : « sur mes cent argiles, j'en veux
 * soixante en réserve ». Tout ce qui dépasse ce seuil part à l'étal, jour de
 * marché après jour de marché, jusqu'à ce que le stock retombe à soixante.
 *
 * C'est le sens qui compte : un plafond de vente aurait obligé à recalculer sa
 * consigne à chaque fois que la production change. Un **plancher de garde** se
 * pose une fois — il dit ce dont on a besoin pour ses chantiers et ses
 * caravanes —, et le reste se règle tout seul.
 *
 * **Rien n'est prélevé au moment où on le pose** : la marchandise ne quitte la
 * réserve qu'au moment d'être vendue. Une resserre hors plafond viderait
 * `Stockage` de son sens.
 *
 * **Ce que ça ne change pas** : le débouché de la quinzaine
 * (`Marche::plafondDeLaQuinzaine()`) borne toujours l'ensemble, ventes à la
 * main comprises. Un gros surplus met donc plusieurs quinzaines à s'écouler —
 * c'est ce qui garde au Marché sa nature de place de ville, et à la caravane
 * sa raison d'être.
 */
#[ORM\Entity(repositoryClass: ReserveGardeeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_RESERVE_GARDEE_PAR_VILLE', columns: ['ville_id', 'ressource'])]
class ReserveGardee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservesGardees')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    /**
     * Ce qu'on garde. Le surplus au-delà part au Marché.
     *
     * **Zéro est une valeur légitime** — « vends-moi tout » —, contrairement
     * aux quantités du reste du jeu : c'est un seuil, pas un lot.
     */
    #[ORM\Column]
    private int $quantiteGardee;

    public function __construct(City $ville, Ressource $ressource, int $quantiteGardee)
    {
        $this->ville = $ville;
        $this->ressource = $ressource;
        $this->quantiteGardee = max(0, $quantiteGardee);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantiteGardee(): int
    {
        return $this->quantiteGardee;
    }

    public function fixerLaQuantite(int $quantite): static
    {
        $this->quantiteGardee = max(0, $quantite);

        return $this;
    }

    /**
     * Ce qui dépasse le seuil dans la réserve, et peut donc partir au Marché.
     */
    public function surplusDans(City $ville): int
    {
        return max(0, $ville->quantite($this->ressource) - $this->quantiteGardee);
    }
}
