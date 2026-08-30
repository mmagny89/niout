<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Game\SensDEchange;
use App\Repository\ConvoiRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une caravane ou un navire en chemin, chargé d'un échange déjà engagé.
 *
 * **Un convoi parti est un engagement pris.** Ce qu'il emporte est débité au
 * départ — la marchandise pour une vente, les deben pour un achat —, et ce
 * qu'il rapporte n'arrive qu'à son retour. Débiter à l'arrivée permettrait de
 * vendre deux fois la même chose : il suffirait de partir, puis de tout écouler
 * au Marché avant que la caravane n'atteigne son but.
 *
 * Le convoi porte **sa propre copie** de l'échange — ressource, sens, prix,
 * quantité — plutôt qu'un lien vers l'ordre qui l'a lancé. Retirer une annonce
 * n'annule donc pas ce qui est déjà en route : on ne rappelle pas une caravane
 * partie il y a trois quinzaines.
 *
 * **Un seul convoi en chemin par ressource et par route** : la caravane doit
 * revenir avant que la suivante ne parte. C'est ce qui donne son poids à la
 * distance — une cité lointaine commerce rarement, quelle que soit sa
 * générosité.
 */
#[ORM\Entity(repositoryClass: ConvoiRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CONVOI_PAR_ROUTE', columns: ['route_id', 'ressource'])]
class Convoi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'convois')]
    #[ORM\JoinColumn(nullable: false)]
    private RouteCommerciale $route;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column(enumType: SensDEchange::class)]
    private SensDEchange $sens;

    #[ORM\Column]
    private int $quantite;

    /**
     * Le prix convenu au départ. Il ne bouge plus : c'est ce qui fait de
     * l'aller un engagement et non une intention.
     */
    #[ORM\Column]
    private int $prix;

    #[ORM\Column]
    private int $quinzainesAvantRetour;

    public function __construct(
        RouteCommerciale $route,
        Ressource $ressource,
        SensDEchange $sens,
        int $quantite,
        int $prix,
        int $quinzainesDeTrajet,
    ) {
        $this->route = $route;
        $this->ressource = $ressource;
        $this->sens = $sens;
        $this->quantite = $quantite;
        $this->prix = $prix;
        // L'aller et le retour : une caravane ne revient pas par magie.
        $this->quinzainesAvantRetour = 2 * $quinzainesDeTrajet;
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

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getPrix(): int
    {
        return $this->prix;
    }

    public function getQuinzainesAvantRetour(): int
    {
        return $this->quinzainesAvantRetour;
    }

    /**
     * Ce que l'échange rapportera : des deben pour une vente, des unités pour
     * un achat.
     */
    public function valeur(): int
    {
        return $this->quantite * $this->prix;
    }

    public function estRentre(): bool
    {
        return 0 === $this->quinzainesAvantRetour;
    }

    /**
     * Recharge la même caravane et la renvoie en chemin.
     *
     * **On réemploie l'objet plutôt que d'en créer un autre**, et ce n'est pas
     * qu'une commodité : supprimer un convoi rentré pour en insérer un neuf
     * dans la même quinzaine fait insérer Doctrine avant de supprimer, et la
     * contrainte d'unicité saute — le même piège que celui déjà payé sur les
     * gisements. Le fait que ce soit aussi ce qui se passe vraiment — la
     * caravane décharge et repart — rend la parade honnête plutôt que
     * commode.
     */
    public function repartir(int $quantite, int $prix, int $quinzainesDeTrajet): static
    {
        $this->quantite = $quantite;
        $this->prix = $prix;
        $this->quinzainesAvantRetour = 2 * $quinzainesDeTrajet;

        return $this;
    }

    /**
     * Rapproche le convoi d'une quinzaine. Rend vrai **au moment précis où il
     * rentre**, et une seule fois.
     */
    public function avancerDUnCycle(): bool
    {
        if ($this->quinzainesAvantRetour <= 0) {
            return false;
        }

        --$this->quinzainesAvantRetour;

        return 0 === $this->quinzainesAvantRetour;
    }
}
