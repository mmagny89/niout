<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\TypeDeRoute;
use App\Repository\RouteCommercialeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une route commerciale ouverte, ou en train de l'être (doc 12).
 *
 * **Ouvrir une route, c'est envoyer une première caravane** (décision de la
 * joueuse) : le geste déclare à une cité qu'on est prêt à commercer avec elle.
 * On paie, le convoi part, et la route n'est ouverte qu'à son arrivée — le
 * temps du trajet, ni plus ni moins.
 *
 * Seule la **clé** du partenaire est stockée : le reste — nom, distance, ce
 * qu'il vend et achète — est du contenu de référence
 * (`CataloguePartenaires`), et n'a rien à faire en base.
 *
 * Une fois ouverte, une route le reste. C'est un lien établi, pas un
 * abonnement.
 */
#[ORM\Entity(repositoryClass: RouteCommercialeRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ROUTE_PAR_VILLE', columns: ['ville_id', 'partenaire'])]
class RouteCommerciale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'routesCommerciales')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(length: 40)]
    private string $partenaire;

    #[ORM\Column(enumType: TypeDeRoute::class)]
    private TypeDeRoute $route;

    /**
     * Quinzaines avant que la première caravane n'arrive. À zéro, la route est
     * ouverte.
     */
    #[ORM\Column]
    private int $quinzainesAvantOuverture;

    /**
     * @var Collection<int, OrdreCommercial>
     */
    #[ORM\OneToMany(targetEntity: OrdreCommercial::class, mappedBy: 'route', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ordres;

    public function __construct(City $ville, string $partenaire, TypeDeRoute $route, int $distanceEnQuinzaines)
    {
        $this->ville = $ville;
        $this->partenaire = $partenaire;
        $this->route = $route;
        $this->quinzainesAvantOuverture = $distanceEnQuinzaines;
        $this->ordres = new ArrayCollection();
    }

    /**
     * @return Collection<int, OrdreCommercial>
     */
    public function getOrdres(): Collection
    {
        return $this->ordres;
    }

    public function ajouterOrdre(OrdreCommercial $ordre): static
    {
        if (!$this->ordres->contains($ordre)) {
            $this->ordres->add($ordre);
        }

        return $this;
    }

    public function retirerOrdre(OrdreCommercial $ordre): static
    {
        $this->ordres->removeElement($ordre);

        return $this;
    }

    public function ordrePour(\App\Game\Ressource $ressource): ?OrdreCommercial
    {
        foreach ($this->ordres as $ordre) {
            if ($ordre->getRessource() === $ressource) {
                return $ordre;
            }
        }

        return null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getPartenaire(): string
    {
        return $this->partenaire;
    }

    public function getRoute(): TypeDeRoute
    {
        return $this->route;
    }

    public function getQuinzainesAvantOuverture(): int
    {
        return $this->quinzainesAvantOuverture;
    }

    public function estOuverte(): bool
    {
        return 0 === $this->quinzainesAvantOuverture;
    }

    /**
     * Rapproche la première caravane d'une quinzaine.
     *
     * Rend vrai **au moment précis où la route s'ouvre**, et une seule fois :
     * c'est ce qui permet de l'annoncer sans avoir à comparer un avant et un
     * après.
     */
    public function avancerDUnCycle(): bool
    {
        if ($this->estOuverte()) {
            return false;
        }

        --$this->quinzainesAvantOuverture;

        return $this->estOuverte();
    }
}
