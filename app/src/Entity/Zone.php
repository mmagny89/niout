<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\CycleAgricoleTerrestre;
use App\Game\EtapeDeChamp;
use App\Game\RendementDesChamps;
use App\Game\Ressource;
use App\Game\Saison;
use App\Game\TypeDeTerrain;
use App\Repository\ZoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une case de la carte d'exploration (doc 02).
 *
 * Tant qu'un éclaireur n'y est pas passé, elle reste sous le brouillard : son
 * terrain et son contenu existent en base, mais le joueur n'en sait rien.
 */
#[ORM\Entity(repositoryClass: ZoneRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ZONE_PAR_VILLE', columns: ['ville_id', 'x', 'y'])]
class Zone
{
    /**
     * Une case porte au plus deux gisements (décision de la joueuse).
     *
     * Deux, et non un : l'argile et les roseaux sont les deux matériaux dont
     * rien ne tient lieu, et tous deux naissent de l'eau. À un gisement par
     * case, ils se disputaient les rares berges d'une petite carte, au point
     * qu'une partie pouvait se figer faute de l'un des deux.
     *
     * Deux, et non davantage : on ne trouve jamais tout au même endroit, sans
     * quoi explorer cesserait d'avoir un intérêt.
     */
    public const int GISEMENTS_MAX = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'zones')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\Column(enumType: TypeDeTerrain::class)]
    private TypeDeTerrain $terrain;

    #[ORM\Column(enumType: ContenuDeZone::class)]
    private ContenuDeZone $contenu = ContenuDeZone::Rien;

    /**
     * @var Collection<int, Gisement>
     */
    #[ORM\OneToMany(targetEntity: Gisement::class, mappedBy: 'zone', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $gisements;

    /**
     * Ce qui est semé sur cette case, si un champ y est établi (doc 01, doc 02).
     */
    #[ORM\Column(nullable: true, enumType: Culture::class)]
    private ?Culture $culture = null;

    /**
     * Quinzaines écoulées depuis le semis — nul hors culture, et hors sujet
     * sur le Nil, dont le rythme reste celui de la crue
     * (`RendementDesChamps`) plutôt que d'un compteur propre à la case.
     */
    #[ORM\Column(nullable: true)]
    private ?int $quinzainesDepuisSemis = null;

    #[ORM\Column]
    private bool $decouverte = false;

    /**
     * La case où se dresse la ville. Découverte d'emblée, évidemment.
     */
    #[ORM\Column]
    private bool $porteLaVille = false;

    public function __construct(City $ville, int $x, int $y, TypeDeTerrain $terrain)
    {
        $this->gisements = new ArrayCollection();
        $this->ville = $ville;
        $this->x = $x;
        $this->y = $y;
        $this->terrain = $terrain;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getTerrain(): TypeDeTerrain
    {
        return $this->terrain;
    }

    public function definirTerrain(TypeDeTerrain $terrain): static
    {
        $this->terrain = $terrain;

        return $this;
    }

    public function getContenu(): ContenuDeZone
    {
        return $this->contenu;
    }

    /**
     * @return Collection<int, Gisement>
     */
    public function getGisements(): Collection
    {
        return $this->gisements;
    }

    public function porteUnGisement(): bool
    {
        return !$this->gisements->isEmpty();
    }

    public function gisementDe(Ressource $ressource): ?Gisement
    {
        foreach ($this->gisements as $gisement) {
            if ($gisement->getRessource() === $ressource) {
                return $gisement;
            }
        }

        return null;
    }

    /**
     * Ajoute un filon. Sans effet si la case en porte déjà autant qu'elle peut,
     * ou si elle porte déjà ce matériau-là — on ne trouve jamais tout au même
     * endroit, c'est ce qui fait qu'explorer garde un intérêt.
     */
    public function poserUnGisement(Ressource $ressource, int $quantite): static
    {
        if (null !== $this->gisementDe($ressource) || $this->gisements->count() >= self::GISEMENTS_MAX) {
            return $this;
        }

        // N'écrase jamais un contenu déjà plus spécifique — une terre
        // cultivable ou un événement. Un gisement peut s'y ajouter sans
        // effacer ce que la génération y a posé en premier : c'est ce qui a
        // fait disparaître le seul champ garanti d'une carte, une carrière de
        // calcaire posée après coup ayant repris la case pour elle.
        if (ContenuDeZone::Rien === $this->contenu) {
            $this->contenu = ContenuDeZone::Ressource;
        }

        $this->gisements->add(new Gisement($this, $ressource, $quantite));

        return $this;
    }

    /**
     * Retire un gisement de cette case, s'il s'y trouve.
     *
     * Sert à la génération seule, et à un seul cas : faire de la place à un
     * matériau vital sur une carte saturée (voir
     * `GenerateurDeCarte::garantirDuBoisLocal()`). Rien dans le jeu ne fait
     * disparaître un filon — un gisement épuisé reste sur la carte.
     */
    public function retirerUnGisement(Ressource $ressource): static
    {
        $gisement = $this->gisementDe($ressource);

        if (null !== $gisement) {
            $this->gisements->removeElement($gisement);
        }

        return $this;
    }

    public function peutPorterUnGisementDePlus(): bool
    {
        return $this->gisements->count() < self::GISEMENTS_MAX;
    }

    public function poserUnContenu(ContenuDeZone $contenu): static
    {
        $this->contenu = $contenu;
        $this->gisements->clear();

        return $this;
    }

    /**
     * Marque la case cultivable **sans toucher à ses gisements**.
     *
     * Un champ et une carrière coexistent volontiers sur une même case — c'est
     * déjà ce que fait `poserUnGisement()`, qui n'écrase jamais une terre
     * cultivable. Cette méthode est l'autre sens du même principe, réservée à
     * la garantie de champs : sur une petite carte dont toutes les terres
     * fertiles ont tiré un gisement, `poserUnContenu()` les effacerait pour
     * poser le champ, et détruirait la garantie de matériau qui suit. Une ville
     * doit pouvoir semer, sans que ce soit au prix de son argile.
     */
    public function rendreCultivable(): static
    {
        if ($this->terrain->accepteUnChamp()) {
            $this->contenu = ContenuDeZone::ChampEligible;
        }

        return $this;
    }

    public function estDecouverte(): bool
    {
        return $this->decouverte;
    }

    public function getCulture(): ?Culture
    {
        return $this->culture;
    }

    public function porteUnChamp(): bool
    {
        return null !== $this->culture;
    }

    /**
     * Un champ ne s'établit que sur une terre qui l'accepte — terre fertile,
     * oasis, ou berge du Nil que la crue limone (doc 02) — et seulement là où
     * la génération a vu une terre cultivable.
     */
    public function accepteUnChamp(): bool
    {
        return $this->terrain->accepteUnChamp()
            && ContenuDeZone::ChampEligible === $this->contenu;
    }

    public function semer(Culture $culture): static
    {
        $this->culture = $culture;
        // Le compteur ne sert qu'au cycle terrestre — sur le Nil, la saison
        // suffit à situer le champ, sans état propre à la case.
        $this->quinzainesDepuisSemis = TypeDeTerrain::Nil === $this->terrain ? null : 0;

        return $this;
    }

    public function getQuinzainesDepuisSemis(): ?int
    {
        return $this->quinzainesDepuisSemis;
    }

    /**
     * Fait avancer le cycle agricole terrestre d'une quinzaine. Sans effet sur
     * un champ du Nil ou une case sans champ.
     */
    public function avancerLeCycleAgricole(): static
    {
        if (null !== $this->quinzainesDepuisSemis) {
            ++$this->quinzainesDepuisSemis;
        }

        return $this;
    }

    /**
     * L'étape du champ établi ici, pour l'affichage — null hors culture.
     */
    public function etapeDuChamp(?Saison $saisonActuelle, ?int $rangDansLaSaison): ?EtapeDeChamp
    {
        if (!$this->porteUnChamp()) {
            return null;
        }

        return TypeDeTerrain::Nil === $this->terrain
            ? RendementDesChamps::etape($saisonActuelle, $rangDansLaSaison)
            : CycleAgricoleTerrestre::etape($this->quinzainesDepuisSemis ?? 0);
    }

    public function decouvrir(): static
    {
        $this->decouverte = true;

        return $this;
    }

    public function porteLaVille(): bool
    {
        return $this->porteLaVille;
    }

    public function yPlacerLaVille(): static
    {
        $this->porteLaVille = true;
        // La ville n'est pas à découvrir : on y est.
        $this->decouverte = true;

        return $this;
    }

    /**
     * Distance en cases depuis la ville, au sens des déplacements en grille :
     * le plus grand des deux écarts, une diagonale valant un pas.
     */
    public function distanceDepuis(self $autre): int
    {
        return max(abs($this->x - $autre->x), abs($this->y - $autre->y));
    }

    public function estAdjacenteA(self $autre): bool
    {
        return 1 === $this->distanceDepuis($autre);
    }
}
