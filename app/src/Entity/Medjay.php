<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Equipement;
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

    /**
     * Ce que vaut son arme, en centièmes (doc 03, lot 10.3). Un homme jamais
     * armé garde `Equipement::QUALITE_SANS_ARME` : il part quand même, moins
     * bien — rien ne bloque une expédition.
     *
     * **La qualité se fige à la remise de l'arme** : monter la Forge ensuite
     * n'améliore pas rétroactivement ce qu'on a déjà donné, il faut réarmer.
     */
    #[ORM\Column]
    private int $qualiteDeLequipement = Equipement::QUALITE_SANS_ARME;

    public function getQualiteDeLequipement(): int
    {
        return $this->qualiteDeLequipement;
    }

    public function estArme(): bool
    {
        return $this->qualiteDeLequipement > Equipement::QUALITE_SANS_ARME;
    }

    public function recevoirUneArme(int $qualite): static
    {
        $this->qualiteDeLequipement = max(Equipement::QUALITE_SANS_ARME, $qualite);

        return $this;
    }

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
     * Sa force réelle : celle de sa spécialisation, ce qu'il a appris, et ce
     * que vaut son arme. **En centièmes entiers**, comme tous les facteurs du
     * jeu.
     *
     * **Une seule division**, bien que trois facteurs s'y croisent : deux
     * divisions entières enchaînées perdraient de la force à chaque étape,
     * d'une façon que personne ne saurait plus expliquer ensuite. C'est la
     * discipline du lot 6.3, et elle vaut ici comme sur les prix.
     */
    public function force(): int
    {
        return intdiv(
            $this->specialisation->force() * (100 + $this->experience) * $this->qualiteDeLequipement,
            100 * 100,
        );
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
