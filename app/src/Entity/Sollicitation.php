<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\NatureDeSollicitation;
use App\Game\Ressource;
use App\Game\StatutDeSollicitation;
use App\Repository\SollicitationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une offre ou une demande d'une cité, **datée, bornée, ignorable**
 * (`docs/plan-pnj-palier-a.md` § 7).
 *
 * C'est un fait **stocké** parce qu'elle a un état (proposée, acceptée, refusée, échue) que la
 * conjoncture ne retrouve pas. Le prix est figé à la proposition : ce que l'écran annonce est ce
 * qu'on paie, même si le marché bouge d'ici l'échéance.
 */
#[ORM\Entity(repositoryClass: SollicitationRepository::class)]
#[ORM\Index(name: 'IDX_SOLLICITATION_PARTIE', columns: ['partie_id', 'statut'])]
class Sollicitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSave $partie;

    /** La clé de la route (`PartenaireCommercial::$cle`), pour retrouver le nom. */
    #[ORM\Column(length: 40)]
    private string $partenaire;

    #[ORM\Column(length: 40)]
    private string $cite;

    #[ORM\Column(length: 20, enumType: NatureDeSollicitation::class)]
    private NatureDeSollicitation $nature;

    #[ORM\Column(length: 40, enumType: Ressource::class)]
    private Ressource $ressource;

    #[ORM\Column]
    private int $quantite;

    /** Le prix unitaire, en deben, figé à la proposition. */
    #[ORM\Column]
    private int $prix;

    #[ORM\Column]
    private int $quinzaine;

    /** La première quinzaine où elle n'est plus valable. */
    #[ORM\Column]
    private int $echeance;

    #[ORM\Column(length: 20, enumType: StatutDeSollicitation::class)]
    private StatutDeSollicitation $statut = StatutDeSollicitation::Proposee;

    public function __construct(
        GameSave $partie,
        string $partenaire,
        string $cite,
        NatureDeSollicitation $nature,
        Ressource $ressource,
        int $quantite,
        int $prix,
        int $quinzaine,
        int $echeance,
    ) {
        $this->partie = $partie;
        $this->partenaire = $partenaire;
        $this->cite = $cite;
        $this->nature = $nature;
        $this->ressource = $ressource;
        $this->quantite = $quantite;
        $this->prix = $prix;
        $this->quinzaine = $quinzaine;
        $this->echeance = $echeance;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartie(): GameSave
    {
        return $this->partie;
    }

    public function getPartenaire(): string
    {
        return $this->partenaire;
    }

    public function getCite(): string
    {
        return $this->cite;
    }

    public function getNature(): NatureDeSollicitation
    {
        return $this->nature;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function getPrix(): int
    {
        return $this->prix;
    }

    public function total(): int
    {
        return $this->quantite * $this->prix;
    }

    public function getQuinzaine(): int
    {
        return $this->quinzaine;
    }

    public function getEcheance(): int
    {
        return $this->echeance;
    }

    public function getStatut(): StatutDeSollicitation
    {
        return $this->statut;
    }

    public function estEnAttente(): bool
    {
        return StatutDeSollicitation::Proposee === $this->statut;
    }

    public function marquer(StatutDeSollicitation $statut): void
    {
        $this->statut = $statut;
    }
}
