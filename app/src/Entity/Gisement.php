<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\GisementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un filon sur une case de la carte (doc 02).
 *
 * Une entité à part, et non trois colonnes de `Zone` : **une case porte
 * plusieurs ressources**, jusqu'à Zone::GISEMENTS_MAX. Sans quoi l'argile et
 * les roseaux — les deux matériaux dont rien ne tient lieu, tous deux nés de
 * l'eau — se disputaient les rares berges d'une petite carte, et la partie
 * pouvait se figer faute de l'un des deux.
 *
 * Chaque filon s'exploite pour lui-même : ouvrir une carrière d'argile
 * n'engage pas la roselière voisine.
 */
#[ORM\Entity(repositoryClass: GisementRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_GISEMENT_PAR_ZONE', columns: ['zone_id', 'ressource'])]
class Gisement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'gisements')]
    #[ORM\JoinColumn(nullable: false)]
    private Zone $zone;

    #[ORM\Column(enumType: Ressource::class)]
    private Ressource $ressource;

    /**
     * Ce qu'il reste à extraire. Les régions faciles ne s'épuisent pas
     * (doc 02) — le compteur descend quand même, mais rien n'en dépend encore :
     * l'épuisement viendra avec les régions difficiles.
     */
    #[ORM\Column]
    private int $quantiteRestante;

    #[ORM\Column]
    private bool $exploitee = false;

    public function __construct(Zone $zone, Ressource $ressource, int $quantite)
    {
        $this->zone = $zone;
        $this->ressource = $ressource;
        $this->quantiteRestante = $quantite;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZone(): Zone
    {
        return $this->zone;
    }

    public function getRessource(): Ressource
    {
        return $this->ressource;
    }

    public function getQuantiteRestante(): int
    {
        return $this->quantiteRestante;
    }

    /**
     * Une ressource renouvelable ne s'épuise jamais : un banc de poisson se
     * reconstitue, une carrière non (`Ressource::estRenouvelable()`).
     */
    public function estEpuise(): bool
    {
        return !$this->ressource->estRenouvelable() && 0 === $this->quantiteRestante;
    }

    public function estExploitee(): bool
    {
        return $this->exploitee;
    }

    public function exploiter(): static
    {
        $this->exploitee = true;

        return $this;
    }

    /**
     * Ferme l'exploitation. **Un filon épuisé se ferme de lui-même** : sans
     * cela, la carrière restait « en activité » sur un vide — elle retenait son
     * équipage, qui manquait ailleurs, et le passage de cycle répétait
     * « le gisement est épuisé » à chaque quinzaine, indéfiniment.
     *
     * Le filon reste sur la carte : il se rouvre par une prospection, puis se
     * remet en exploitation comme au premier jour. On ne rappelle pas des
     * équipes qui sont parties.
     */
    public function fermer(): static
    {
        $this->exploitee = false;

        return $this;
    }

    /**
     * Prélève sur le filon, sans jamais descendre sous zéro. Renvoie ce qui a
     * effectivement été extrait, qui peut être moindre que demandé sur la fin.
     *
     * Une ressource renouvelable rend toujours son plein : le banc de poisson
     * se reconstitue d'une quinzaine à l'autre, son compteur ne bouge pas.
     */
    public function extraire(int $quantite): int
    {
        if ($this->ressource->estRenouvelable()) {
            return $quantite;
        }

        $extrait = min($quantite, $this->quantiteRestante);
        $this->quantiteRestante -= $extrait;

        return $extrait;
    }

    /**
     * Rouvre le filon de ce qu'une prospection y a retrouvé.
     *
     * **Le seul chemin par lequel un gisement regagne de la matière.** Sans
     * lui, l'épuisement d'un matériau vital condamnait la région : plus rien à
     * extraire, et rien à faire pour y remédier.
     *
     * **Le filon reste fermé** : `fermer()` l'a soldé au moment de
     * l'épuisement, et l'on ne rappelle pas des équipes qui sont parties. Il
     * faut rouvrir la carrière, comme au premier jour.
     */
    public function rouvrir(int $quantite): static
    {
        $this->quantiteRestante += max(0, $quantite);

        return $this;
    }

    /**
     * Désignation courte, pour les messages destinés au joueur. On ne creuse
     * pas un banc de poisson : le mot suit la chose.
     */
    public function libelle(): string
    {
        return \sprintf(
            '%s de %s',
            Ressource::Poisson === $this->ressource ? 'banc' : 'gisement',
            $this->ressource->libelle(),
        );
    }
}
