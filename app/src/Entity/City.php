<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * La ville confiée au joueur pour une partie (doc 09).
 *
 * « Niout » (niwt) signifie « la ville » en égyptien ancien : c'est l'objet
 * central du jeu, pas un simple décor. Elle porte son stock et ses bâtiments ;
 * la carte d'exploration s'y rattachera en Phase 3.
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
     * @var Collection<int, StockDeRessource>
     */
    #[ORM\OneToMany(targetEntity: StockDeRessource::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'ressource')]
    private Collection $stock;

    /**
     * @var Collection<int, Building>
     */
    #[ORM\OneToMany(targetEntity: Building::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $batiments;

    /**
     * @var Collection<int, Chantier>
     */
    #[ORM\OneToMany(targetEntity: Chantier::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $chantiers;

    public function __construct(string $nom, int $difficulte, int $tailleGrille)
    {
        $this->nom = $nom;
        $this->difficulte = $difficulte;
        $this->tailleGrille = $tailleGrille;
        $this->stock = new ArrayCollection();
        $this->batiments = new ArrayCollection();
        $this->chantiers = new ArrayCollection();
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

    /**
     * @return Collection<int, StockDeRessource>
     */
    public function getStock(): Collection
    {
        return $this->stock;
    }

    public function quantite(Ressource $ressource): int
    {
        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource() === $ressource) {
                return $ligne->getQuantite();
            }
        }

        return 0;
    }

    /**
     * Raccourcis vers les trois matériaux affichés en permanence dans la barre
     * de jeu. Ils évitent aux gabarits d'appeler quantite() avec une énumération.
     */
    public function getOr(): int
    {
        return $this->quantite(Ressource::Or);
    }

    public function getBois(): int
    {
        return $this->quantite(Ressource::Bois);
    }

    public function getPierre(): int
    {
        return $this->quantite(Ressource::Pierre);
    }

    /**
     * Crédite le stock. Sert à la dotation royale du départ (doc 13), puis aux
     * récoltes, à la pêche et aux achats.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     */
    public function crediterRessources(array $ressources): static
    {
        foreach ($ressources as $valeur => $quantite) {
            if ($quantite <= 0) {
                continue;
            }

            $this->ligneDe(Ressource::from($valeur))->ajouter($quantite);
        }

        return $this;
    }

    /**
     * Débite le stock. Renvoie false **sans rien modifier** si les moyens ne
     * suffisent pas : un chantier ne doit jamais démarrer à découvert.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     */
    public function debiterRessources(array $ressources): bool
    {
        foreach ($ressources as $valeur => $quantite) {
            if ($this->quantite(Ressource::from($valeur)) < $quantite) {
                return false;
            }
        }

        foreach ($ressources as $valeur => $quantite) {
            $this->ligneDe(Ressource::from($valeur))->retirer($quantite);
        }

        return true;
    }

    private function ligneDe(Ressource $ressource): StockDeRessource
    {
        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource() === $ressource) {
                return $ligne;
            }
        }

        $ligne = new StockDeRessource($this, $ressource);
        $this->stock->add($ligne);

        return $ligne;
    }

    /**
     * @return Collection<int, Building>
     */
    public function getBatiments(): Collection
    {
        return $this->batiments;
    }

    public function ajouterBatiment(Building $batiment): static
    {
        if (!$this->batiments->contains($batiment)) {
            $this->batiments->add($batiment);
        }

        return $this;
    }

    /**
     * Le bâtiment de ce type s'il est dressé, null sinon. Un type ne peut
     * exister qu'une fois par ville — c'est une contrainte d'unicité en base,
     * pas seulement une convention.
     */
    public function batimentDeType(TypeDeBatiment $type): ?Building
    {
        foreach ($this->batiments as $batiment) {
            if ($batiment->getType() === $type) {
                return $batiment;
            }
        }

        return null;
    }

    public function possede(TypeDeBatiment $type): bool
    {
        return null !== $this->batimentDeType($type);
    }

    /**
     * @return Collection<int, Chantier>
     */
    public function getChantiers(): Collection
    {
        return $this->chantiers;
    }

    public function ajouterChantier(Chantier $chantier): static
    {
        if (!$this->chantiers->contains($chantier)) {
            $this->chantiers->add($chantier);
        }

        return $this;
    }

    public function retirerChantier(Chantier $chantier): static
    {
        $this->chantiers->removeElement($chantier);

        return $this;
    }

    /**
     * Un même bâtiment ne peut faire l'objet que d'un chantier à la fois.
     */
    public function aUnChantierPour(TypeDeBatiment $type): bool
    {
        foreach ($this->chantiers as $chantier) {
            if ($chantier->getType() === $type) {
                return true;
            }
        }

        return false;
    }
}
