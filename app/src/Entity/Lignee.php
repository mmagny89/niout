<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\PalierDeRenommee;
use App\Repository\LigneeRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce qu'un joueur a acquis au fil de ses parties, et qui ne se perd pas
 * (doc 13).
 *
 * Le document veut « une seule jauge de renommée par famille, persistante d'une
 * mission à l'autre […] elle ne fait que croître au fil de la campagne ». La
 * `Family`, elle, naît et meurt avec sa partie : elle ne pouvait donc pas la
 * porter.
 *
 * **Deux choses que le mot « renommée » confondait** (arbitrage 9.0) :
 *
 * - l'**acquis**, ici — le plancher, qui ne descend jamais et que chaque
 *   nouvelle partie reçoit au lancement ;
 * - la **jauge de la mission**, sur `Family` — celle qui bouge, que le
 *   mécontentement fait baisser, et qui reste propre à sa partie.
 *
 * C'est ce qui permet à deux parties menées de front de coexister sans se
 * contaminer : elles lisent le même acquis, mais chacune a sa jauge.
 *
 * Une lignée par joueur, créée à la première partie qui en a besoin. Elle
 * accueillera le carnet de contacts au lot 9.4.
 */
#[ORM\Entity(repositoryClass: LigneeRepository::class)]
class Lignee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private User $joueur;

    /**
     * La plus haute renommée atteinte à la fin d'une mission accomplie. Elle
     * ne fait que croître : les pertes ponctuelles — refus d'une requête,
     * mécontentement — jouent **dans** la mission, jamais en travers de la
     * campagne. Même discipline que le plancher du neutre de la négligence
     * divine.
     */
    #[ORM\Column]
    private int $renommeeAcquise = Family::RENOMMEE_MIN;

    public function __construct(User $joueur)
    {
        $this->joueur = $joueur;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoueur(): User
    {
        return $this->joueur;
    }

    public function getRenommeeAcquise(): int
    {
        return $this->renommeeAcquise;
    }

    /**
     * Relève l'acquis si la mission qui s'achève a fait mieux. **Jamais
     * l'inverse** : une mission mal finie ne rabaisse pas ce qu'on avait déjà
     * gagné, sinon la renommée cesserait d'être un acquis pour redevenir un
     * solde.
     */
    public function relever(int $renommee): static
    {
        $this->renommeeAcquise = max(
            $this->renommeeAcquise,
            min(Family::RENOMMEE_MAX, $renommee),
        );

        return $this;
    }

    public function palier(): PalierDeRenommee
    {
        return PalierDeRenommee::pour($this->renommeeAcquise);
    }
}
