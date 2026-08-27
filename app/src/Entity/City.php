<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CityRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La ville confiée au joueur pour une partie (doc 09).
 *
 * « Niout » (niwt) signifie « la ville » en égyptien ancien : c'est l'objet
 * central du jeu, pas un simple décor. Le stock de ressources et les bâtiments
 * viendront s'y rattacher aux lots suivants.
 */
#[ORM\Entity(repositoryClass: CityRepository::class)]
class City
{
    /**
     * Bornes du niveau de difficulté régionale (doc 02, doc 11) : 0 pour le
     * Delta, région d'apprentissage, jusqu'à 9 pour le Sinaï.
     */
    public const int DIFFICULTE_MIN = 0;
    public const int DIFFICULTE_MAX = 9;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $nom;

    #[ORM\Column]
    private int $difficulte;

    /**
     * Côté de la grille d'exploration. Dérivé de la difficulté en campagne,
     * choisi par le joueur en mode Aventure (doc 11, doc 14). La carte
     * elle-même viendra en Phase 3 ; seule sa dimension est fixée ici.
     */
    #[ORM\Column]
    private int $tailleGrille;

    /**
     * Colonne nommée explicitement : « or » est un mot réservé du SQL. La
     * création de table passait (Doctrine l'échappe), mais les SELECT générés
     * ensuite ne l'échappaient pas et provoquaient une erreur de syntaxe.
     */
    #[ORM\Column(name: 'stock_or')]
    private int $or = 0;

    #[ORM\Column]
    private int $bois = 0;

    #[ORM\Column]
    private int $pierre = 0;

    public function __construct(string $nom, int $difficulte, int $tailleGrille)
    {
        $this->nom = $nom;
        $this->difficulte = $difficulte;
        $this->tailleGrille = $tailleGrille;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getDifficulte(): int
    {
        return $this->difficulte;
    }

    /**
     * Plafond de niveau des bâtiments dans cette région (doc 01) : une région
     * plus difficile autorise des bâtiments plus hauts, ce qui compense la
     * rareté de ses ressources et allonge la partie.
     */
    public function niveauMaxRegional(): int
    {
        return 5 + $this->difficulte;
    }

    public function getTailleGrille(): int
    {
        return $this->tailleGrille;
    }

    public function getOr(): int
    {
        return $this->or;
    }

    public function getBois(): int
    {
        return $this->bois;
    }

    public function getPierre(): int
    {
        return $this->pierre;
    }

    /**
     * Crédite le stock. Sert à la dotation royale du départ (doc 13), puis aux
     * récoltes et aux achats.
     */
    public function crediter(int $or = 0, int $bois = 0, int $pierre = 0): static
    {
        $this->or += $or;
        $this->bois += $bois;
        $this->pierre += $pierre;

        return $this;
    }
}
