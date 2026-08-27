<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameMode;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une partie : l'état complet d'une run, du premier cycle à la fin de mission.
 *
 * Chaque partie est indépendante et rejouable — le jeu ne connaît pas la
 * sauvegarde unique et infinie (doc 00). Un compte peut en mener plusieurs de
 * front, dans la limite de self::MAX_PAR_COMPTE.
 */
#[ORM\Entity(repositoryClass: GameSaveRepository::class)]
class GameSave
{
    /**
     * Plafond de parties simultanées par compte. Au-delà, il faut en abandonner
     * une — la suppression étant définitive.
     */
    public const int MAX_PAR_COMPTE = 5;

    /**
     * La campagne se joue dans l'ordre imposé, de la première à la dixième
     * mission (doc 09, doc 11). Aucun choix de région.
     */
    public const int PREMIERE_MISSION = 1;
    public const int DERNIERE_MISSION = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $joueur;

    #[ORM\Column(enumType: GameMode::class)]
    private GameMode $mode;

    /**
     * Numéro de la mission en cours, de 1 à 10. Renseigné en campagne
     * uniquement : le mode Aventure ne suit pas de missions mais une
     * succession de règnes (doc 14).
     */
    #[ORM\Column(nullable: true)]
    private ?int $mission = null;

    /**
     * Cycle courant, l'unité de temps du jeu : une « quinzaine », soit deux
     * semaines (doc 05). Il n'avance que sur action du joueur.
     */
    #[ORM\Column]
    private int $cycle = 1;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Family $famille;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastOpenedAt;

    private function __construct(User $joueur, GameMode $mode, Family $famille, City $ville)
    {
        $this->joueur = $joueur;
        $this->mode = $mode;
        $this->famille = $famille;
        $this->ville = $ville;
        $this->createdAt = new \DateTimeImmutable();
        $this->lastOpenedAt = $this->createdAt;
    }

    /**
     * Une campagne démarre toujours à la première mission : l'ordre est imposé,
     * il n'y a pas de sélection de région.
     */
    public static function pourCampagne(User $joueur, Family $famille, City $ville): self
    {
        $partie = new self($joueur, GameMode::Campagne, $famille, $ville);
        $partie->mission = self::PREMIERE_MISSION;

        return $partie;
    }

    public static function pourAventure(User $joueur, Family $famille, City $ville): self
    {
        return new self($joueur, GameMode::Aventure, $famille, $ville);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoueur(): User
    {
        return $this->joueur;
    }

    public function getMode(): GameMode
    {
        return $this->mode;
    }

    public function getMission(): ?int
    {
        return $this->mission;
    }

    public function getCycle(): int
    {
        return $this->cycle;
    }

    public function getFamille(): Family
    {
        return $this->famille;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastOpenedAt(): \DateTimeImmutable
    {
        return $this->lastOpenedAt;
    }

    /**
     * Enregistre la reprise de la partie, pour trier les parties de la plus
     * récemment jouée à la plus ancienne.
     */
    public function marquerOuverte(): static
    {
        $this->lastOpenedAt = new \DateTimeImmutable();

        return $this;
    }

    public function estCampagne(): bool
    {
        return GameMode::Campagne === $this->mode;
    }

    /**
     * Vrai lorsque la campagne est arrivée à sa dernière mission. Sans objet en
     * mode Aventure, qui n'a pas de fin scriptée.
     */
    public function estALaDerniereMission(): bool
    {
        return $this->estCampagne() && self::DERNIERE_MISSION === $this->mission;
    }
}
