<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\Ressource;
use App\Repository\EtatDePartenaireRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ce que la famille a fait à une cité, et ce que la cité en retient
 * (`docs/plan-pnj-palier-a.md` § 4.3) : la seule couche de la conjoncture qui se **stocke**,
 * parce qu'elle vient du joueur.
 *
 * Une ligne par **cité** (pas par route : `byblos` et `byblos-levant` sont la même), créée à la
 * première route qui y mène, rattachée à la partie par une suppression en cascade de la base.
 *
 *  - la **saturation**, par marchandise : plus on vend de lin, moins la cité en veut. Elle monte
 *    quand un convoi rentre, et s'érode d'un tiers par quinzaine ;
 *  - la **relation**, de 0 à 100 : elle monte avec les convois menés à bien et redescend vers un
 *    **plancher** — l'acquis de la lignée —, jamais en dessous. Ne rien faire est une décision.
 */
#[ORM\Entity(repositoryClass: EtatDePartenaireRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_ETAT_PAR_CITE', columns: ['partie_id', 'cite'])]
class EtatDePartenaire
{
    public const int RELATION_MAX = 100;

    /** Ce qu'un convoi mené à bien ajoute à la relation. **Valeur inventée.** */
    public const int GAIN_PAR_CONVOI = 2;

    /** Ce qu'un convoi qui rentre ajoute à la saturation de la marchandise. **Valeur inventée.** */
    public const int SATURATION_PAR_CONVOI = 4;

    /** Le niveau au-delà duquel la saturation n'ajoute plus rien : elle ne pèse jamais plus que l'amplitude du monde. */
    public const int SATURATION_MAX = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private GameSave $partie;

    #[ORM\Column(length: 40)]
    private string $cite;

    #[ORM\Column]
    private int $relation;

    /** Le plancher de la relation : l'acquis hérité de la lignée, qui ne descend pas. */
    #[ORM\Column]
    private int $plancher;

    /**
     * @var array<string, int> niveau de saturation par valeur de `Ressource`
     */
    #[ORM\Column(type: 'json', options: ['default' => '{}'])]
    private array $saturations = [];

    public function __construct(GameSave $partie, string $cite, int $plancher = 0)
    {
        $this->partie = $partie;
        $this->cite = $cite;
        $this->plancher = max(0, min(self::RELATION_MAX, $plancher));
        $this->relation = $this->plancher;
    }

    public function getCite(): string
    {
        return $this->cite;
    }

    public function getRelation(): int
    {
        return $this->relation;
    }

    public function getPlancher(): int
    {
        return $this->plancher;
    }

    public function saturationDe(Ressource $ressource): int
    {
        return $this->saturations[$ressource->value] ?? 0;
    }

    /**
     * Un convoi est rentré : la cité en a assez de cette marchandise, et connaît un peu mieux la famille.
     */
    public function enregistrerUnRetour(Ressource $ressource): void
    {
        $this->saturations[$ressource->value] = min(self::SATURATION_MAX, $this->saturationDe($ressource) + self::SATURATION_PAR_CONVOI);
        $this->relation = min(self::RELATION_MAX, $this->relation + self::GAIN_PAR_CONVOI);
    }

    /**
     * Un geste qui n'est pas un convoi — une offre acceptée, une demande servie : la cité vous connaît mieux,
     * sans que sa saturation bouge.
     */
    public function gagnerLaRelation(int $points): void
    {
        $this->relation = min(self::RELATION_MAX, $this->relation + max(0, $points));
    }

    /**
     * Une quinzaine passe : la saturation s'érode d'un tiers (arrondi au-dessus, pour qu'elle finisse
     * par s'éteindre), la relation redescend d'un point vers son plancher.
     */
    public function laisserPasserUneQuinzaine(): void
    {
        foreach ($this->saturations as $ressource => $niveau) {
            $reste = $niveau - (int) ceil($niveau / 3);

            if ($reste <= 0) {
                unset($this->saturations[$ressource]);
            } else {
                $this->saturations[$ressource] = $reste;
            }
        }

        $this->relation = max($this->plancher, $this->relation - 1);
    }
}
