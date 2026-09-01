<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RivalCommercialRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un marchand rival, installé sur une de vos routes (doc 08).
 *
 * **Il ne détruit rien** : il prend une part du volume qui passe, et s'en va
 * de lui-même si on le laisse faire assez longtemps. C'est une gêne qu'on
 * choisit de subir, de payer ou de démonter — les trois issues du doc 08.
 *
 * Seule la **clé** du partenaire visé est stockée, comme pour la route
 * elle-même : le rival concurrence sur une route, pas sur un objet de base.
 */
#[ORM\Entity(repositoryClass: RivalCommercialRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_RIVAL_PAR_VILLE', columns: ['ville_id'])]
class RivalCommercial
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'rival')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(length: 40)]
    private string $partenaire;

    #[ORM\Column(length: 60)]
    private string $nom;

    /**
     * Ce qu'il prend sur le volume d'un convoi, en centièmes (doc 08 : 10 à
     * 20 %).
     */
    #[ORM\Column]
    private int $malusEnCentiemes;

    /**
     * Quinzaines avant qu'il ne se lasse et parte de lui-même. C'est la
     * troisième issue du doc 08 : ignorer coûte, mais n'est pas une impasse.
     */
    #[ORM\Column]
    private int $quinzainesRestantes;

    public function __construct(City $ville, string $partenaire, string $nom, int $malusEnCentiemes, int $quinzaines)
    {
        $this->ville = $ville;
        $this->partenaire = $partenaire;
        $this->nom = $nom;
        $this->malusEnCentiemes = $malusEnCentiemes;
        $this->quinzainesRestantes = $quinzaines;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPartenaire(): string
    {
        return $this->partenaire;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getMalusEnCentiemes(): int
    {
        return $this->malusEnCentiemes;
    }

    public function getQuinzainesRestantes(): int
    {
        return $this->quinzainesRestantes;
    }

    /**
     * Rend vrai le jour où il s'en va de lui-même.
     */
    public function avancerDUnCycle(): bool
    {
        $this->quinzainesRestantes = max(0, $this->quinzainesRestantes - 1);

        return 0 === $this->quinzainesRestantes;
    }
}
