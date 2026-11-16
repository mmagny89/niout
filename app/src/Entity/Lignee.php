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

    /**
     * Ce que la famille a bâti avec chaque cité, par identité de cité (`PartenaireCommercial::cite()`),
     * au plus haut atteint — plafonné à `RELATION_ACQUISE_MAX`. Le même modèle que la renommée : une
     * jauge vivante dans la partie (`EtatDePartenaire::relation`), un acquis ici qui ne descend jamais.
     *
     * @var array<string, int>
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $relations = [];

    /**
     * Les personnes que la famille a rencontrées, d'une partie à l'autre (`docs/plan-pnj-palier-b.md` § 7) : jusqu'à
     * `VISAGES_MAX`, les plus récemment vus. Clé `v<identité>` (jamais un entier nu : un JSON dont les clés seraient
     * 0, 1, 2… se relirait comme une liste). Chacun porte son rôle, son nom, son caractère, ce qu'on a fait avec lui
     * et un rang de rencontre — le plus petit cède le premier.
     *
     * **Aucune rancune ne se transmet** : on garde ce qu'on a fait *avec* lui (des accords), jamais un grief.
     *
     * @var array<string, array{identite: int, role: string, nom: string, caractere: string, accords: int, vu: int, profil: array<string, mixed>}>
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $visagesConnus = [];

    /** Combien de visages une lignée garde : au-delà, le plus ancien s'efface. */
    public const int VISAGES_MAX = 12;

    /**
     * Ce qu'une nouvelle partie reçoit d'emblée : les cinquante points suivants se gagnent en
     * jouant. Un plafond plutôt qu'un cloisonnement, comme le plan de bataille le prévoit.
     * **Valeur inventée.**.
     */
    public const int RELATION_ACQUISE_MAX = 50;

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

    public function relationAcquise(string $cite): int
    {
        return $this->relations[$cite] ?? 0;
    }

    /**
     * Relève l'acquis d'une cité, **jamais l'inverse**.
     */
    public function releverLaRelation(string $cite, int $relation): static
    {
        $this->relations[$cite] = max($this->relationAcquise($cite), min(self::RELATION_ACQUISE_MAX, $relation));

        return $this;
    }

    /**
     * @return list<array{identite: int, role: string, nom: string, caractere: string, accords: int, vu: int, profil: array<string, mixed>}> du plus récemment vu au plus ancien
     */
    public function getVisagesConnus(): array
    {
        $visages = array_values($this->visagesConnus);
        usort($visages, static fn (array $a, array $b): int => $b['vu'] <=> $a['vu']);

        return $visages;
    }

    /**
     * Retient une personne rencontrée, ou rafraîchit celle qu'on connaît déjà. Au-delà de `VISAGES_MAX`, le
     * plus anciennement vu s'efface : une mémoire sans limite deviendrait un obstacle à chaque partie.
     *
     * @param array<string, mixed> $profil
     */
    public function retenirUnVisage(int $identite, string $role, string $nom, string $caractere, int $accords, array $profil = []): static
    {
        $rang = 1 + max(0, ...array_column($this->visagesConnus, 'vu') ?: [0]);
        $this->visagesConnus['v'.$identite] = [
            'identite' => $identite,
            'role' => $role,
            'nom' => $nom,
            'caractere' => $caractere,
            'accords' => max(0, min(3, $accords)),
            'vu' => $rang,
            'profil' => $profil,
        ];

        while (\count($this->visagesConnus) > self::VISAGES_MAX) {
            uasort($this->visagesConnus, static fn (array $a, array $b): int => $a['vu'] <=> $b['vu']);
            array_shift($this->visagesConnus);
        }

        return $this;
    }

    public function palier(): PalierDeRenommee
    {
        return PalierDeRenommee::pour($this->renommeeAcquise);
    }
}
