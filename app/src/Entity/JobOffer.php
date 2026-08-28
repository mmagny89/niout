<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Candidat;
use App\Game\TypeDeBatiment;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une offre d'emploi affichée pour diriger un bâtiment (doc 03, doc 05).
 *
 * **Poster une offre est libre** : elle ne consomme pas de quinzaine et ne
 * coûte rien (doc 05). Ce qui coûte, c'est le chef qu'on finit par embaucher.
 *
 * L'offre **fige son tirage**. C'est la raison même de sa persistance : sans
 * elle, chaque rechargement de page relancerait les dés jusqu'à ce que le
 * cinq étoiles sorte, et le choix entre deux ou trois candidats — le cœur du
 * doc 03 — n'aurait plus aucun sens.
 *
 * Une seule offre à la fois par bâtiment : le joueur tranche l'annonce en
 * cours avant d'en poster une autre.
 */
#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_OFFRE_PAR_BATIMENT', columns: ['ville_id', 'type'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'offres')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $type;

    /**
     * Les candidatures sérialisées, dans l'ordre du tirage.
     *
     * @var list<array<string, mixed>>
     */
    #[ORM\Column]
    private array $candidatures = [];

    /**
     * @param list<Candidat> $candidats
     */
    public function __construct(City $ville, TypeDeBatiment $type, array $candidats)
    {
        $this->ville = $ville;
        $this->type = $type;
        $this->candidatures = array_map(
            static fn (Candidat $candidat): array => $candidat->enTableau(),
            $candidats,
        );
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

    /**
     * Les candidats de cette offre, relus depuis la ligne persistée.
     *
     * @return list<Candidat>
     */
    public function candidats(): array
    {
        return array_map(
            static fn (array $ligne): Candidat => Candidat::depuisTableau($ligne),
            $this->candidatures,
        );
    }

    /**
     * Le candidat de rang `$rang`, ou null s'il n'existe pas — un rang venu
     * d'un formulaire ne se croit jamais sur parole.
     */
    public function candidat(int $rang): ?Candidat
    {
        $candidats = $this->candidats();

        return $candidats[$rang] ?? null;
    }
}
