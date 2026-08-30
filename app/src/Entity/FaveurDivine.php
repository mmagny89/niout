<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Divinite;
use App\Game\PalierDeFaveur;
use App\Repository\FaveurDivineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce qu'une divinité pense de la ville (doc 07).
 *
 * **Une ligne n'existe qu'à partir du moment où l'on s'occupe du dieu.** Une
 * divinité jamais honorée n'a pas de ligne en base : sa faveur vaut
 * `Divinite::FAVEUR_DE_DEPART`, et `City::faveurEnvers()` le répond sans
 * rien créer. Écrire huit lignes au lancement de chaque partie stockerait
 * huit fois la même constante, et il faudrait ensuite les migrer à chaque
 * divinité ajoutée.
 *
 * Seule la **clé** de la divinité est persistée ; son nom, son domaine et son
 * effet sont du contenu (`Divinite`).
 *
 * Le compteur de quinzaines sans offrande vit ici plutôt que dans la ville :
 * la négligence se compte dieu par dieu, et l'on peut très bien couvrir Ptah
 * d'offrandes en laissant Sekhmet s'éloigner.
 */
#[ORM\Entity(repositoryClass: FaveurDivineRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_FAVEUR_PAR_VILLE', columns: ['ville_id', 'divinite'])]
class FaveurDivine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'faveurs')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(length: 20, enumType: Divinite::class)]
    private Divinite $divinite;

    #[ORM\Column]
    private int $faveur;

    #[ORM\Column]
    private int $quinzainesSansOffrande = 0;

    public function __construct(City $ville, Divinite $divinite)
    {
        $this->ville = $ville;
        $this->divinite = $divinite;
        $this->faveur = Divinite::FAVEUR_DE_DEPART;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getDivinite(): Divinite
    {
        return $this->divinite;
    }

    public function getFaveur(): int
    {
        return $this->faveur;
    }

    public function getPalier(): PalierDeFaveur
    {
        return PalierDeFaveur::pour($this->faveur);
    }

    public function getQuinzainesSansOffrande(): int
    {
        return $this->quinzainesSansOffrande;
    }

    /**
     * Fait monter ou descendre la faveur, **toujours dans les bornes**. Un
     * gain qui déborderait 100 est simplement rogné : c'est la seule façon de
     * garantir qu'aucun chemin — offrande, fête, bénédiction, malédiction —
     * ne sorte de l'échelle sans avoir à le vérifier partout.
     */
    public function ajuster(int $points): static
    {
        $this->faveur = max(
            Divinite::FAVEUR_MINIMALE,
            min(Divinite::FAVEUR_MAXIMALE, $this->faveur + $points),
        );

        return $this;
    }

    /**
     * Une offrande reçue : la faveur monte, et le dieu cesse de compter les
     * quinzaines depuis la dernière.
     */
    public function recevoirUneOffrande(int $points): static
    {
        $this->quinzainesSansOffrande = 0;

        return $this->ajuster($points);
    }

    public function attendreUneQuinzaine(): static
    {
        ++$this->quinzainesSansOffrande;

        return $this;
    }
}
