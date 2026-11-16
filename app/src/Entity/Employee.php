<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Candidat;
use App\Game\SpecialiteDeChef;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use App\Repository\EmployeeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un chef embauché pour diriger un bâtiment (doc 03, doc 05).
 *
 * **Seuls les chefs sont suivis un par un.** Les travailleurs se puisent dans
 * le vivier d'actifs de la ville (doc 05, lot 4.4) et n'ont pas d'existence
 * propre — c'est la même règle que pour la population, qui se compte en
 * nombres et non en individus.
 *
 * Il porte un prénom (`Game\Prenoms`), que la désignation par bâtiment et
 * spécialité suit — « Nakhtmin, chef du Grenier ». Il n'en avait pas : la
 * décision a été levée pour que la ville habitée ait des gens qu'on retienne.
 *
 * **Chiffré en interne, qualitatif à l'affichage** : `competence` sert aux
 * calculs de production (lot 4.8), les gabarits n'impriment que des étoiles.
 */
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'employes')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $type;

    #[ORM\Column]
    private int $competence;

    #[ORM\Column]
    private int $salaire;

    #[ORM\Column]
    private int $ancienneteProbable;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $traits = [];

    #[ORM\Column(nullable: true, enumType: SpecialiteDeChef::class)]
    private ?SpecialiteDeChef $specialite = null;

    /**
     * Son prénom, tiré **une fois** et gardé (`Game\Prenoms`). Nul pour un chef
     * embauché avant que le jeu en distribue : `Prenoms::completer()` le lui
     * donne à la quinzaine suivante, et le gabarit se rabat d'ici là sur sa
     * désignation par bâtiment.
     */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $prenom = null;

    /**
     * La maisonnée qu'il a amenée, retenue telle quelle : c'est elle qui
     * repart s'il est renvoyé. Sans cette mémoire, embaucher puis renvoyer
     * serait un moyen gratuit de peupler la ville.
     */
    #[ORM\Column]
    private int $actifsAmenes;

    #[ORM\Column]
    private int $inactifsAmenes;

    /**
     * La quinzaine à partir de laquelle il tient réellement son poste
     * (doc 05 : « durée d'un recrutement une fois le candidat choisi :
     * 1 cycle »). Avant elle, il est embauché mais pas encore à l'ouvrage.
     */
    #[ORM\Column]
    private int $prendPosteAuCycle;

    public function __construct(City $ville, TypeDeBatiment $type, Candidat $candidat, int $prendPosteAuCycle)
    {
        $this->ville = $ville;
        $this->type = $type;
        $this->competence = $candidat->competence;
        $this->salaire = $candidat->salaire;
        $this->ancienneteProbable = $candidat->ancienneteProbable;
        $this->traits = array_map(
            static fn (TraitDeCandidat $trait): string => $trait->value,
            $candidat->traits,
        );
        $this->specialite = $candidat->specialite;
        $this->prenom = $candidat->prenom;
        $this->actifsAmenes = $candidat->actifsAmenes;
        $this->inactifsAmenes = $candidat->inactifsAmenes;
        $this->prendPosteAuCycle = $prendPosteAuCycle;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function donnerUnPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getCompetence(): int
    {
        return $this->competence;
    }

    public function getSalaire(): int
    {
        return $this->salaire;
    }

    public function getAncienneteProbable(): int
    {
        return $this->ancienneteProbable;
    }

    public function getSpecialite(): ?SpecialiteDeChef
    {
        return $this->specialite;
    }

    public function getActifsAmenes(): int
    {
        return $this->actifsAmenes;
    }

    public function getInactifsAmenes(): int
    {
        return $this->inactifsAmenes;
    }

    public function getPrendPosteAuCycle(): int
    {
        return $this->prendPosteAuCycle;
    }

    /**
     * @return list<TraitDeCandidat>
     */
    public function traits(): array
    {
        $traits = [];

        foreach ($this->traits as $valeur) {
            $trait = TraitDeCandidat::tryFrom($valeur);

            if (null !== $trait) {
                $traits[] = $trait;
            }
        }

        return $traits;
    }

    public function estEnPoste(int $cycle): bool
    {
        return $cycle >= $this->prendPosteAuCycle;
    }

    /**
     * Le même barème que celui d'un candidat — un chef embauché ne doit pas
     * s'afficher autrement que l'annonce qui l'a fait venir.
     */
    public function etoiles(): int
    {
        return $this->enCandidat()->etoiles();
    }

    /**
     * @return list<TraitDeCandidat>
     */
    public function traitsEndormis(): array
    {
        return $this->enCandidat()->traitsEndormis();
    }

    private function enCandidat(): Candidat
    {
        return new Candidat(
            competence: $this->competence,
            salaire: $this->salaire,
            ancienneteProbable: $this->ancienneteProbable,
            traits: $this->traits(),
            specialite: $this->specialite,
            actifsAmenes: $this->actifsAmenes,
            inactifsAmenes: $this->inactifsAmenes,
            prenom: $this->prenom,
        );
    }
}
