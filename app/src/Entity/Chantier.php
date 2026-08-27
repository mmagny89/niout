<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\EtapeDeChantier;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use App\Repository\ChantierRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un chantier en cours dans une ville.
 *
 * Construire n'est jamais immédiat (doc 01) : les ressources sont payées au
 * lancement, puis les travaux avancent d'un cycle à l'autre — et seulement
 * quand le joueur en déclenche un.
 */
#[ORM\Entity(repositoryClass: ChantierRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CHANTIER_PAR_VILLE', columns: ['ville_id', 'type'])]
class Chantier
{
    /**
     * L'avancement se compte en dixièmes de cycle, pas en flottants : la crue
     * d'Akhèt fait progresser d'1,5 cycle, et une comparaison de flottants
     * finirait par laisser un chantier bloqué à un cheveu de son terme.
     */
    private const int DIXIEMES_PAR_CYCLE = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'chantiers')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $type;

    /**
     * Niveau qu'atteindra le bâtiment une fois le chantier achevé : 1 pour une
     * construction neuve, N+1 pour une amélioration.
     */
    #[ORM\Column]
    private int $niveauVise;

    #[ORM\Column]
    private int $dureeEnCycles;

    #[ORM\Column]
    private int $avancementEnDixiemes = 0;

    public function __construct(City $ville, TypeDeBatiment $type, int $niveauVise)
    {
        $this->ville = $ville;
        $this->type = $type;
        $this->niveauVise = $niveauVise;
        $this->dureeEnCycles = $type->dureeDeChantier($niveauVise);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getType(): TypeDeBatiment
    {
        return $this->type;
    }

    public function getNiveauVise(): int
    {
        return $this->niveauVise;
    }

    public function getDureeEnCycles(): int
    {
        return $this->dureeEnCycles;
    }

    public function estUneAmelioration(): bool
    {
        return $this->niveauVise > 1;
    }

    /**
     * Fait avancer les travaux d'un cycle, accéléré si la crue mobilise la
     * main-d'œuvre paysanne.
     */
    public function avancerDUnCycle(?Saison $saison): static
    {
        $facteur = $saison?->facteurDAvancementDesChantiers() ?? 1.0;
        $this->avancementEnDixiemes += (int) round(self::DIXIEMES_PAR_CYCLE * $facteur);

        return $this;
    }

    public function estAcheve(): bool
    {
        return $this->avancementEnDixiemes >= $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;
    }

    /**
     * Cycles restants, arrondis au supérieur — un chantier à moitié entamé
     * réclame encore un cycle entier.
     */
    public function cyclesRestants(): int
    {
        $restant = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE - $this->avancementEnDixiemes;

        return max(0, (int) ceil($restant / self::DIXIEMES_PAR_CYCLE));
    }

    /**
     * Avancement en pourcentage, pour la barre de progression.
     */
    public function pourcentageDAvancement(): int
    {
        $total = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;

        return min(100, (int) round($this->avancementEnDixiemes / $total * 100));
    }

    /**
     * L'étape en cours : les cycles du chantier se répartissent entre les
     * quatre étapes du matériau (doc 01).
     */
    public function etapeEnCours(): EtapeDeChantier
    {
        $etapes = $this->type->etapesDeChantier();
        $rang = (int) floor($this->pourcentageDAvancement() / 100 * \count($etapes));

        return $etapes[min($rang, \count($etapes) - 1)];
    }

    public function numeroDEtape(): int
    {
        $etapes = $this->type->etapesDeChantier();

        return min((int) floor($this->pourcentageDAvancement() / 100 * \count($etapes)) + 1, \count($etapes));
    }

    public function nombreDEtapes(): int
    {
        return \count($this->type->etapesDeChantier());
    }
}
