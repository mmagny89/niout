<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Effectifs;
use App\Game\Recette;
use App\Game\TypeDeBatiment;
use App\Repository\OrdreDeFabricationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un ordre passé à l'Atelier, en cours de fabrication.
 *
 * Même forme qu'un chantier, et pour les mêmes raisons : les matières sont
 * **payées à l'engagement** — on ne réserve pas, on paie —, l'ouvrage prend
 * des quinzaines, et **les pièces n'entrent au stock qu'à l'achèvement**. C'est
 * la règle des champs : rien ne rentre hors de la récolte.
 *
 * **Un seul ordre à la fois et par bâtiment** : un atelier est un lieu, pas une
 * file d'attente. C'est ce qui donne son coût d'opportunité à la fabrication —
 * immobiliser l'Atelier pour des sandales, c'est ne pas tisser. La Forge, elle,
 * est un autre lieu : les deux travaillent de front.
 */
#[ORM\Entity(repositoryClass: OrdreDeFabricationRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ORDRE_PAR_BATIMENT', columns: ['ville_id', 'batiment'])]
class OrdreDeFabrication
{
    /**
     * L'avancement se compte en dixièmes de quinzaine, jamais en flottants :
     * la qualité de direction de l'Atelier module le rythme, et une
     * comparaison de flottants finirait par laisser un ordre bloqué à un
     * cheveu de son terme (même piège que `Chantier`).
     */
    private const int DIXIEMES_PAR_CYCLE = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ordresDeFabrication')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: Recette::class)]
    private Recette $recette;

    /**
     * Où l'ouvrage se fait. Redondant avec `Recette::batiment()`, et c'est
     * assumé : la contrainte d'unicité « un ordre par bâtiment » a besoin
     * d'une colonne, pas d'une méthode.
     */
    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $batiment;

    #[ORM\Column]
    private int $lots;

    #[ORM\Column]
    private int $dureeEnCycles;

    #[ORM\Column]
    private int $avancementEnDixiemes = 0;

    public function __construct(City $ville, Recette $recette, int $lots)
    {
        $this->ville = $ville;
        $this->recette = $recette;
        $this->batiment = $recette->batiment();
        $this->lots = $lots;
        $this->dureeEnCycles = $recette->quinzainesDunLot() * $lots;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getRecette(): Recette
    {
        return $this->recette;
    }

    public function getBatiment(): TypeDeBatiment
    {
        return $this->batiment;
    }

    public function getLots(): int
    {
        return $this->lots;
    }

    public function getDureeEnCycles(): int
    {
        return $this->dureeEnCycles;
    }

    /**
     * Ce que l'ordre rendra une fois achevé.
     */
    public function piecesAttendues(): int
    {
        return $this->recette->piecesDunLot() * $this->lots;
    }

    /**
     * Avance d'une quinzaine, au rythme que l'Atelier peut tenir.
     *
     * `$qualiteDeDirection` est la qualité de direction du bâtiment
     * (`EffetDeChef`) : un Atelier désert tourne au plancher de 50 % et met
     * donc deux fois plus longtemps, un Atelier bien tenu par un bon chef va
     * plus vite que la durée nominale.
     */
    public function avancerDUnCycle(int $qualiteDeDirection): static
    {
        $this->avancementEnDixiemes += max(
            1,
            intdiv(self::DIXIEMES_PAR_CYCLE * $qualiteDeDirection, Effectifs::RENDEMENT_PLEIN),
        );

        return $this;
    }

    public function estAcheve(): bool
    {
        return $this->avancementEnDixiemes >= $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;
    }

    /**
     * Quinzaines restantes, arrondies au supérieur — ce que l'écran annonce.
     */
    public function cyclesRestants(): int
    {
        $restant = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE - $this->avancementEnDixiemes;

        return max(0, (int) ceil($restant / self::DIXIEMES_PAR_CYCLE));
    }

    public function pourcentageDAvancement(): int
    {
        $total = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;

        if ($total <= 0) {
            return 100;
        }

        return min(100, (int) round($this->avancementEnDixiemes / $total * 100));
    }
}
