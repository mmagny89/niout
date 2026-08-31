<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Enquete;
use App\Game\Indice;
use App\Game\NatureDIndice;
use App\Game\StatutDEnquete;
use App\Repository\DossierDEnqueteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le dossier d'une enquête en cours (doc 10).
 *
 * Persisté, contrairement aux indices eux-mêmes : une enquête traverse des
 * dizaines de quinzaines, et ce qu'on y a versé doit y rester. Seules les
 * **clés** des indices sont stockées ; leur texte et leur nature sont du
 * contenu.
 *
 * Le dossier ne juge de rien — il collecte. C'est la déduction (lot 7.4) qui
 * conclut, et le dossier qui garde la trace de ce qu'elle a décidé.
 */
#[ORM\Entity(repositoryClass: DossierDEnqueteRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_DOSSIER_PAR_VILLE', columns: ['ville_id', 'enquete'])]
class DossierDEnquete
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dossiers')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(length: 40, enumType: Enquete::class)]
    private Enquete $enquete;

    #[ORM\Column(length: 20, enumType: StatutDEnquete::class)]
    private StatutDEnquete $statut = StatutDEnquete::EnCours;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $indices = [];

    public function __construct(City $ville, Enquete $enquete)
    {
        $this->ville = $ville;
        $this->enquete = $enquete;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEnquete(): Enquete
    {
        return $this->enquete;
    }

    public function getStatut(): StatutDEnquete
    {
        return $this->statut;
    }

    public function conclure(StatutDEnquete $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return list<Indice>
     */
    public function indices(): array
    {
        $trouves = [];

        foreach ($this->indices as $cle) {
            $indice = Indice::tryFrom($cle);

            if (null !== $indice) {
                $trouves[] = $indice;
            }
        }

        return $trouves;
    }

    public function contient(Indice $indice): bool
    {
        return \in_array($indice->value, $this->indices, true);
    }

    /**
     * Verse un indice au dossier. Rend vrai s'il apporte quelque chose : le
     * même indice trouvé deux fois n'est pas une découverte.
     */
    public function verser(Indice $indice): bool
    {
        if ($this->contient($indice)) {
            return false;
        }

        $this->indices[] = $indice->value;

        return true;
    }

    /**
     * Les indices **concordants** réunis. Les fausses pistes n'y comptent
     * pas — c'est précisément ce que le joueur doit démêler, et ce qui
     * l'empêche de conclure en comptant plutôt qu'en lisant.
     */
    public function concordantsReunis(): int
    {
        $concordants = 0;

        foreach ($this->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                ++$concordants;
            }
        }

        return $concordants;
    }

    public function peutConclure(): bool
    {
        return StatutDEnquete::EnCours === $this->statut
            && $this->concordantsReunis() >= $this->enquete->indicesRequis();
    }
}
