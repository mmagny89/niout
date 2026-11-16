<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\RoleDePnj;
use App\Game\TraitDeCandidat;
use App\Repository\NpcRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une personne du monde que la famille a rencontrée (`docs/plan-pnj-palier-b.md` § 6).
 *
 * **Créée paresseusement** : une personne n'existe en base qu'à sa première intention qui change
 * quelque chose pour le joueur — ici, l'installation d'un rival. Avant cela elle se **déduit** de la
 * graine (`identite`), comme un héritier.
 *
 * Ce qu'on y garde est ce qui ne se déduit pas : **ce qui lui est arrivé avec la famille**. Sa mémoire
 * a un **plafond** (§ 3) : elle infléchit un prix et un délai dans des bornes, elle ne bâtit pas un
 * ennemi qui grandit. Aucune rancune : un rival payé revient plus sensible aux accords, jamais plus dur.
 */
#[ORM\Entity(repositoryClass: NpcRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_NPC_PAR_IDENTITE', columns: ['partie_id', 'role', 'identite'])]
class Npc
{
    /** Combien d'accords au plus comptent dans sa mémoire. */
    public const int ACCORDS_RETENUS_MAX = 3;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSave $partie;

    /** Sa graine : ce qui le fait reconnaître d'une quinzaine à l'autre, et d'une partie à l'autre. */
    #[ORM\Column]
    private int $identite;

    #[ORM\Column(length: 20, enumType: RoleDePnj::class)]
    private RoleDePnj $role;

    #[ORM\Column(length: 60)]
    private string $nom;

    #[ORM\Column(length: 30, enumType: TraitDeCandidat::class)]
    private TraitDeCandidat $caractere;

    /** Combien de fois la famille a payé pour s'en défaire. Borné par `ACCORDS_RETENUS_MAX`. */
    #[ORM\Column]
    private int $accords = 0;

    /** La première quinzaine où il peut reparaître. Avant, il est ailleurs, lassé, payé ou démonté. */
    #[ORM\Column]
    private int $indisponibleJusqua = 0;

    /** Combien de faits une personne retient dans sa chronique : les plus récents. */
    public const int HISTOIRE_MAX = 12;

    /**
     * Ce qui lui est arrivé avec la famille, **en faits** (`{q: quinzaine, t: type, r: route}`), jamais en
     * phrases : le texte se rend à l'affichage. C'est la chronique filtrée par personne
     * (`docs/plan-pnj-palier-b.md` § 8). Bornée, comme sa mémoire.
     *
     * @var list<array{q: int, t: string, r: string|null}>
     */
    #[ORM\Column(type: 'json', options: ['default' => '[]'])]
    private array $histoire = [];

    /**
     * Ce qui ne tient pas dans les colonnes communes, propre à son rôle : pour un chef voyageur, la candidature
     * qu'il était (`Candidat::enTableau()`), son bâtiment, sa destination, ce qu'il y a gagné.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $profil = [];

    public function __construct(GameSave $partie, int $identite, RoleDePnj $role, string $nom, TraitDeCandidat $caractere)
    {
        $this->partie = $partie;
        $this->identite = $identite;
        $this->role = $role;
        $this->nom = $nom;
        $this->caractere = $caractere;
    }

    public function getPartie(): GameSave
    {
        return $this->partie;
    }

    public function getIdentite(): int
    {
        return $this->identite;
    }

    public function getRole(): RoleDePnj
    {
        return $this->role;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getCaractere(): TraitDeCandidat
    {
        return $this->caractere;
    }

    public function getAccords(): int
    {
        return $this->accords;
    }

    public function getIndisponibleJusqua(): int
    {
        return $this->indisponibleJusqua;
    }

    /**
     * @return list<array{q: int, t: string, r: string|null}> du plus récent au plus ancien
     */
    public function getHistoire(): array
    {
        return array_reverse($this->histoire);
    }

    public function noter(int $quinzaine, string $type, ?string $route = null): void
    {
        $this->histoire[] = ['q' => $quinzaine, 't' => $type, 'r' => $route];
        $this->histoire = \array_slice($this->histoire, -self::HISTOIRE_MAX);
    }

    /**
     * @return array<string, mixed>
     */
    public function getProfil(): array
    {
        return $this->profil;
    }

    /**
     * @param array<string, mixed> $profil
     */
    public function definirLeProfil(array $profil): void
    {
        $this->profil = $profil;
    }

    public function peutParaitre(int $quinzaine): bool
    {
        return $quinzaine >= $this->indisponibleJusqua;
    }

    /** Il se lasse et s'en va : il reparaîtra, mais pas tout de suite. */
    public function seLasser(int $quinzaine, int $delai): void
    {
        $this->indisponibleJusqua = max($this->indisponibleJusqua, $quinzaine + $delai);
    }

    /** Payé : il s'en va, et revient plus sensible à un accord. */
    public function etrePaye(int $quinzaine, int $delai): void
    {
        $this->accords = min(self::ACCORDS_RETENUS_MAX, $this->accords + 1);
        $this->seLasser($quinzaine, $delai);
    }

    /** Démonté par l'enquête : il disparaît pour longtemps — pas pour toujours, il n'y a pas de morts. */
    public function etreDemonte(int $quinzaine, int $delai): void
    {
        $this->seLasser($quinzaine, $delai);
    }
}
