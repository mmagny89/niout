<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\SpecialisationMedjay;
use App\Repository\MedjayRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un Medjaÿ levé à la Caserne (doc 03).
 *
 * **Ce n'est pas un `Employee`.** Un chef de bâtiment a une compétence, un
 * salaire négocié, une spécialité tirée et une maisonnée qui le suit ; un
 * Medjaÿ a une force, une spécialisation et une expérience gagnée au combat.
 * Les deux n'ont en commun qu'un salaire — les confondre aurait fait porter à
 * `Employee` deux modèles qui ne se ressemblent pas.
 *
 * Comme le chef, il **n'a pas de nom** : on le désigne par sa spécialisation.
 *
 * **L'expérience est ce qui donne son poids à la mort permanente** (doc 03) :
 * ce n'est pas le coût de recrutement qui fait mal, c'est de repartir à zéro.
 */
#[ORM\Entity(repositoryClass: MedjayRepository::class)]
class Medjay
{
    /**
     * Ce qu'un combat gagné ajoute, en points de pourcentage d'efficacité, et
     * ce que l'expérience ne dépasse jamais (doc 03).
     */
    public const int EXPERIENCE_PAR_VICTOIRE = 5;
    public const int EXPERIENCE_MAX = 50;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'medjays')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: SpecialisationMedjay::class)]
    private SpecialisationMedjay $specialisation;

    /**
     * Ce que les combats gagnés lui ont appris, en points de pourcentage
     * cumulés sur sa force.
     */
    #[ORM\Column]
    private int $experience = 0;

    /**
     * Le cycle à partir duquel il repart au combat. Nul tant qu'il est
     * valide : une blessure est temporaire, et il **garde son expérience**
     * pendant qu'il se remet — c'est ce qui la distingue de la mort.
     */
    #[ORM\Column(nullable: true)]
    private ?int $retabliAuCycle = null;

    public function __construct(City $ville, SpecialisationMedjay $specialisation)
    {
        $this->ville = $ville;
        $this->specialisation = $specialisation;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getSpecialisation(): SpecialisationMedjay
    {
        return $this->specialisation;
    }

    public function getExperience(): int
    {
        return $this->experience;
    }

    /**
     * Sa force réelle : celle de sa spécialisation, augmentée de ce qu'il a
     * appris. **En centièmes entiers**, comme tous les facteurs du jeu.
     *
     * L'équipement de la Forge s'y ajoutera au lot 10.3 — il entrera dans ce
     * calcul, il n'en posera pas un second à côté.
     */
    public function force(): int
    {
        return intdiv($this->specialisation->force() * (100 + $this->experience), 100);
    }

    public function gagnerDeLexperience(): static
    {
        $this->experience = min(
            self::EXPERIENCE_MAX,
            $this->experience + self::EXPERIENCE_PAR_VICTOIRE,
        );

        return $this;
    }

    public function estDisponible(int $cycle): bool
    {
        return null === $this->retabliAuCycle || $cycle >= $this->retabliAuCycle;
    }

    public function getRetabliAuCycle(): ?int
    {
        return $this->retabliAuCycle;
    }

    /**
     * Le met hors de combat pour un temps. Il continue d'être payé : on ne
     * renvoie pas un homme parce qu'il s'est fait blesser à son service.
     */
    public function blesser(int $cycle, int $quinzaines): static
    {
        $this->retabliAuCycle = $cycle + max(1, $quinzaines);

        return $this;
    }
}
