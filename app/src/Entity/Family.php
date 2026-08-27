<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\FamilyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La lignée incarnée par le joueur sur une partie donnée (doc 09, doc 13).
 *
 * Le joueur ne dirige pas un personnage unique mais une famille : c'est elle
 * qui porte la renommée, et c'est son nom qui apparaît dans les textes.
 */
#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    /**
     * Nom proposé par défaut : réellement attesté dans l'Égypte antique, porté
     * par des particuliers — scribes, artisans, fonctionnaires — jamais par un
     * pharaon (doc 09).
     */
    public const string NOM_PAR_DEFAUT = 'Nakht';

    public const int RENOMMEE_MIN = 0;
    public const int RENOMMEE_MAX = 100;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private string $nom;

    /**
     * Jauge 0-100 déterminant l'attractivité de la ville pour les travailleurs
     * et les marchands (doc 13). Persistante d'une mission à l'autre.
     */
    #[ORM\Column]
    private int $renommee = self::RENOMMEE_MIN;

    public function __construct(string $nom)
    {
        $this->nom = $nom;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getRenommee(): int
    {
        return $this->renommee;
    }

    /**
     * La renommée reste bornée à l'échelle 0-100 : les gains et pertes
     * ponctuels ne doivent jamais la faire sortir de ses paliers.
     */
    public function ajusterRenommee(int $variation): static
    {
        $this->renommee = max(
            self::RENOMMEE_MIN,
            min(self::RENOMMEE_MAX, $this->renommee + $variation),
        );

        return $this;
    }

    /**
     * Palier de renommée, tel que défini par le doc 13.
     */
    public function palierDeRenommee(): string
    {
        return match (true) {
            $this->renommee < 20 => 'Inconnue',
            $this->renommee < 40 => 'Modeste',
            $this->renommee < 60 => 'Reconnue',
            $this->renommee < 80 => 'Respectée',
            default => 'Illustre',
        };
    }
}
