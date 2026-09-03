<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\PresentRoyalRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que le pharaon renvoie après qu'on a honoré une de ses demandes de
 * chantier (doc 09, décision de la joueuse au playtest).
 *
 * **Pourquoi une contrepartie matérielle.** Livrer une quête ne rapportait
 * que de la renommée et de la faveur : la ville se dépouillait de vingt à
 * cinquante unités d'une ressource et n'en voyait jamais rien revenir. Sur une
 * partie où le deben ne rentrait presque pas, la seule décision rationnelle
 * était de refuser toutes les demandes — ce qui vidait de son sens un système
 * dont le doc 09 fait un pilier de l'ancrage historique.
 *
 * **Pourquoi différé.** Le présent n'arrive pas au clic : il remonte le fleuve
 * depuis les magasins royaux. Trois quinzaines, ce qui en fait un revenu qu'on
 * anticipe plutôt qu'un troc immédiat — et ce qui garde à la livraison son
 * caractère de service rendu au roi, pas de vente.
 *
 * **Pourquoi ça ne rembourse pas.** Le présent vaut une fraction de ce qui a
 * été donné : honorer une demande reste une dépense, dont la renommée et la
 * faveur sont le vrai gain. Un remboursement intégral en aurait fait une
 * transaction sans risque, donc sans choix.
 *
 * Une ligne par ressource : le roi renvoie du deben, et souvent quelque chose
 * que la région ne produit pas.
 */
#[ORM\Entity(repositoryClass: PresentRoyalRepository::class)]
class PresentRoyal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'presentsRoyaux')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column]
    private int $quantite;

    /**
     * Quinzaines restantes avant que le convoi royal n'entre en ville. On
     * compte à rebours plutôt que de retenir un numéro de cycle : c'est la
     * même mécanique que les chantiers et les convois, et elle survit à tout
     * ce qui pourrait décaler le calendrier.
     */
    #[ORM\Column]
    private int $quinzainesAvantArrivee;

    /**
     * Le monument au nom duquel le présent est envoyé, pour que le message
     * d'arrivée dise d'où il vient — un cadeau anonyme trois quinzaines plus
     * tard ne se rattache à rien.
     */
    #[ORM\Column(length: 255)]
    private string $chantier;

    public function __construct(
        City $ville,
        Ressource $ressource,
        int $quantite,
        int $quinzainesAvantArrivee,
        string $chantier,
    ) {
        $this->ville = $ville;
        $this->ressource = $ressource;
        $this->quantite = max(1, $quantite);
        $this->quinzainesAvantArrivee = max(1, $quinzainesAvantArrivee);
        $this->chantier = $chantier;
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

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getQuinzainesAvantArrivee(): int
    {
        return $this->quinzainesAvantArrivee;
    }

    public function getChantier(): string
    {
        return $this->chantier;
    }

    /**
     * @return bool vrai quand le convoi royal arrive à cette quinzaine
     */
    public function avancerDUnCycle(): bool
    {
        --$this->quinzainesAvantArrivee;

        return $this->quinzainesAvantArrivee <= 0;
    }
}
