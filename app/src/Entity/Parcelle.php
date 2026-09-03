<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Culture;
use App\Game\CycleAgricoleTerrestre;
use App\Game\EffetDeFaveur;
use App\Game\EtapeDeChamp;
use App\Game\RendementDesChamps;
use App\Game\Saison;
use App\Game\TypeDeTerrain;
use App\Repository\ParcelleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un champ, sur une case qui en porte jusqu'à quatre (décision de la joueuse
 * au playtest).
 *
 * **Une case est une terre, pas un champ.** Sur une grille de neuf cases
 * figurant tout un Delta, un seul champ par case était une limite d'affichage
 * déguisée en règle : deux cases cultivables plafonnaient la ville à deux
 * champs, quelle que soit sa population.
 *
 * **Chaque parcelle est indépendante**, et c'est tout l'objet de cette entité :
 * elle a **sa culture** et **son cycle agricole**. On peut semer du blé, de
 * l'orge et du lin sur la même terre, chacun à son rythme. Un compteur de
 * champs sur la case aurait imposé une culture unique et un semis commun —
 * plus simple, mais ce n'est pas ce qu'on veut jouer.
 *
 * **Le rang n'est qu'une place**, de 1 à `Zone::CHAMPS_MAX` : il ordonne
 * l'affichage et rend l'unicité vérifiable en base, rien de plus. Arracher la
 * parcelle 2 ne décale pas la 3.
 *
 * **Chaque parcelle réclame son homme** (`Effectifs`), et c'est ce qui fait de
 * l'élargissement une décision : plus de grain, mais pris sur les bras
 * d'ailleurs.
 */
#[ORM\Entity(repositoryClass: ParcelleRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_PARCELLE_PAR_RANG', columns: ['zone_id', 'rang'])]
class Parcelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'parcelles')]
    #[ORM\JoinColumn(nullable: false)]
    private Zone $zone;

    #[ORM\Column]
    private int $rang;

    #[ORM\Column(enumType: Culture::class)]
    private Culture $culture;

    /**
     * Quinzaines écoulées depuis le semis, pour le **cycle terrestre**
     * seulement. `null` sur une berge du Nil, où la saison situe le champ sans
     * qu'il ait d'état propre — deux façons de compter le temps agricole, et
     * une seule des deux se persiste.
     */
    #[ORM\Column(nullable: true)]
    private ?int $quinzainesDepuisSemis = null;

    public function __construct(Zone $zone, int $rang, Culture $culture)
    {
        $this->zone = $zone;
        $this->rang = $rang;
        $this->semer($culture);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getZone(): Zone
    {
        return $this->zone;
    }

    public function getRang(): int
    {
        return $this->rang;
    }

    public function getCulture(): Culture
    {
        return $this->culture;
    }

    public function getQuinzainesDepuisSemis(): ?int
    {
        return $this->quinzainesDepuisSemis;
    }

    /**
     * (Re)sème la parcelle : le cycle agricole repart de zéro.
     */
    public function semer(Culture $culture): static
    {
        $this->culture = $culture;
        $this->quinzainesDepuisSemis = TypeDeTerrain::Nil === $this->zone->getTerrain() ? null : 0;

        return $this;
    }

    /**
     * Fait avancer le cycle terrestre d'une quinzaine. Sans effet sur une
     * parcelle du Nil, que la saison suffit à situer.
     */
    public function avancerLeCycleAgricole(): static
    {
        if (null !== $this->quinzainesDepuisSemis) {
            ++$this->quinzainesDepuisSemis;
        }

        return $this;
    }

    /**
     * Où en est cette parcelle, pour l'affichage.
     */
    public function etape(?Saison $saison, ?int $rangDansLaSaison): EtapeDeChamp
    {
        return TypeDeTerrain::Nil === $this->zone->getTerrain()
            ? RendementDesChamps::etape($saison, $rangDansLaSaison)
            : CycleAgricoleTerrestre::etape(
                $this->quinzainesDepuisSemis ?? 0,
                EffetDeFaveur::jachereRaccourcie($this->zone->getVille()),
            );
    }
}
