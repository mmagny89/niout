<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\TypeDeFait;
use App\Repository\FaitDeMondeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un fait d'une quinzaine : un chantier qui s'achève, un convoi qui part, une
 * fièvre qui éclate (`docs/plan-pnj-ville-vivante.md` § 7).
 *
 * **Structuré, jamais un texte** : le type, le lieu, l'acteur, une quantité — la
 * phrase se compose à la lecture (`TypeDeFait::phrase()`). Un texte stocké
 * divergerait du fait qu'il raconte.
 *
 * Les faits se **déduisent de deux photographies** de la ville
 * (`Game\DifferenceDeQuinzaine`), pas des textes du journal du cycle. Ils n'ont
 * **pas de jour** : le jour est une mise en scène que la chorégraphie choisit,
 * sans valeur de règle, et qu'on ne persiste donc pas.
 *
 * Les trois dernières quinzaines seulement, avec leur instantané. Rattaché à la
 * partie par une suppression en cascade de la base.
 */
#[ORM\Entity(repositoryClass: FaitDeMondeRepository::class)]
#[ORM\Index(name: 'IDX_FAIT_PAR_QUINZAINE', columns: ['partie_id', 'quinzaine'])]
class FaitDeMonde
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSave $partie;

    /**
     * La quinzaine vécue, par le numéro du cycle qui s'est ouvert sur elle.
     */
    #[ORM\Column]
    private int $quinzaine;

    #[ORM\Column(length: 40, enumType: TypeDeFait::class)]
    private TypeDeFait $type;

    /**
     * Le bâtiment concerné, par la valeur de `TypeDeBatiment`.
     */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $lieu;

    /**
     * Qui est concerné, par la clé stable d'une figure : `chef-12`, `rival`, `convoi-3`.
     */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $acteur;

    #[ORM\Column(nullable: true)]
    private ?int $quantite;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $details = [];

    /**
     * @param array<string, mixed> $details
     */
    public function __construct(GameSave $partie, int $quinzaine, TypeDeFait $type, ?string $lieu = null, ?string $acteur = null, ?int $quantite = null, array $details = [])
    {
        $this->partie = $partie;
        $this->quinzaine = $quinzaine;
        $this->type = $type;
        $this->lieu = $lieu;
        $this->acteur = $acteur;
        $this->quantite = $quantite;
        $this->details = $details;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuinzaine(): int
    {
        return $this->quinzaine;
    }

    public function getType(): TypeDeFait
    {
        return $this->type;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function getActeur(): ?string
    {
        return $this->acteur;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetails(): array
    {
        return $this->details;
    }
}
