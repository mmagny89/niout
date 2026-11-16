<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\InstantaneDeQuinzaineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La photographie de la ville au début d'une quinzaine : ce qu'il faut garder pour
 * pouvoir la rejouer (`docs/plan-pnj-ville-vivante.md` § 7).
 *
 * Le jeu ne garde rien d'une quinzaine passée : les écarts du récapitulatif vivent
 * un instant dans un message flash. Sans cet instantané — qui est à quel poste,
 * quels chefs, quels Medjaÿ debout —, la reconstitution n'aurait rien à partir
 * de quoi se faire. **On ne stocke aucune scène** : la chorégraphie se recalcule à
 * partir de lui et des faits (`FaitDeMonde`).
 *
 * Une ligne par partie et par quinzaine, **les trois dernières seulement**
 * (`Game\TraceDeQuinzaine`) : rejouer plus loin n'est pas possible, et on le dit.
 * Rattaché à la partie par une suppression en cascade de la base — rien ne
 * référence `User`, la purge d'un compte n'a donc rien à ajouter.
 */
#[ORM\Entity(repositoryClass: InstantaneDeQuinzaineRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_INSTANTANE_PAR_QUINZAINE', columns: ['partie_id', 'cycle'])]
class InstantaneDeQuinzaine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSave $partie;

    /**
     * Le numéro de la quinzaine **qui s'ouvre** sur cette photographie : celle
     * qu'on rejoue.
     */
    #[ORM\Column]
    private int $cycle;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $donnees = [];

    /**
     * @param array<string, mixed> $donnees
     */
    public function __construct(GameSave $partie, int $cycle, array $donnees)
    {
        $this->partie = $partie;
        $this->cycle = $cycle;
        $this->donnees = $donnees;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCycle(): int
    {
        return $this->cycle;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDonnees(): array
    {
        return $this->donnees;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    public function remplacer(array $donnees): static
    {
        $this->donnees = $donnees;

        return $this;
    }
}
