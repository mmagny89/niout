<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\EtapeDeChantier;
use App\Game\EtatDEtape;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use App\Repository\ChantierRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un chantier en cours dans une ville.
 *
 * Construire n'est jamais immédiat (doc 01) : les ressources sont payées au
 * lancement, puis les travaux avancent d'un cycle à l'autre — et seulement
 * quand le joueur en déclenche un.
 */
#[ORM\Entity(repositoryClass: ChantierRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CHANTIER_PAR_VILLE', columns: ['ville_id', 'type'])]
class Chantier
{
    /**
     * L'avancement se compte en dixièmes de cycle, pas en flottants : la crue
     * d'Akhèt fait progresser d'1,5 cycle, et une comparaison de flottants
     * finirait par laisser un chantier bloqué à un cheveu de son terme.
     */
    private const int DIXIEMES_PAR_CYCLE = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'chantiers')]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column(enumType: TypeDeBatiment::class)]
    private TypeDeBatiment $type;

    /**
     * Niveau qu'atteindra le bâtiment une fois le chantier achevé : 1 pour une
     * construction neuve, N+1 pour une amélioration.
     */
    #[ORM\Column]
    private int $niveauVise;

    #[ORM\Column]
    private int $dureeEnCycles;

    #[ORM\Column]
    private int $avancementEnDixiemes = 0;

    public function __construct(City $ville, TypeDeBatiment $type, int $niveauVise)
    {
        $this->ville = $ville;
        $this->type = $type;
        $this->niveauVise = $niveauVise;
        $this->dureeEnCycles = $type->dureeDeChantier($niveauVise);
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

    public function getNiveauVise(): int
    {
        return $this->niveauVise;
    }

    public function getDureeEnCycles(): int
    {
        return $this->dureeEnCycles;
    }

    public function estUneAmelioration(): bool
    {
        return $this->niveauVise > 1;
    }

    /**
     * Fait avancer les travaux d'un cycle, accéléré si la crue mobilise la
     * main-d'œuvre paysanne.
     */
    public function avancerDUnCycle(?Saison $saison, int $faveurEnCentiemes = 0): static
    {
        $this->avancementEnDixiemes += self::avancementDUneQuinzaine($saison, $faveurEnCentiemes);

        return $this;
    }

    /**
     * Ce qu'une quinzaine fait gagner, en dixièmes de cycle.
     *
     * Le facteur de saison et celui de Ptah **s'additionnent** dans la même
     * unité, au lieu de se multiplier : deux facteurs multipliés sur la même
     * chaîne sont précisément ce que le lot 4.5 a fait retirer. Le tout est
     * compté en centièmes, jamais en flottants — un chantier bloqué à un
     * cheveu de son terme est le défaut que la règle évite.
     */
    private static function avancementDUneQuinzaine(?Saison $saison, int $faveurEnCentiemes): int
    {
        $facteur = (int) round(100 * ($saison?->facteurDAvancementDesChantiers() ?? 1.0)) + $faveurEnCentiemes;

        return intdiv(self::DIXIEMES_PAR_CYCLE * $facteur, 100);
    }

    public function estAcheve(): bool
    {
        return $this->avancementEnDixiemes >= $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;
    }

    /**
     * Cycles restants, arrondis au supérieur — un chantier à moitié entamé
     * réclame encore un cycle entier.
     */
    public function cyclesRestants(): int
    {
        $restant = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE - $this->avancementEnDixiemes;

        return max(0, (int) ceil($restant / self::DIXIEMES_PAR_CYCLE));
    }

    /**
     * Avancement en pourcentage, pour la barre de progression.
     */
    public function pourcentageDAvancement(): int
    {
        $total = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;

        return min(100, (int) round($this->avancementEnDixiemes / $total * 100));
    }

    /**
     * Les quatre étapes du matériau (doc 01), chacune avec son état.
     *
     * Les cycles du chantier se répartissent proportionnellement entre les
     * étapes : un chantier de deux quinzaines en traverse donc deux par
     * quinzaine. Elles sont **toutes** rendues, et non la seule étape courante —
     * sinon la moitié d'entre elles défilerait sans jamais s'afficher, dont
     * celle du séchage des briques, qui porte l'explication du rythme même du
     * jeu.
     *
     * « En cours » désigne ce que la quinzaine qui vient va réellement
     * traverser, **à la vitesse de sa saison**. Sans cette précision, la corvée
     * d'Akhèt fait franchir une étape de plus que ce qui avait été annoncé, et
     * cette étape-là n'apparaît jamais.
     *
     * @return list<array{etape: EtapeDeChantier, etat: EtatDEtape, numero: int}>
     */
    public function etapes(?Saison $saison = null, int $faveurEnCentiemes = 0): array
    {
        $etapes = $this->type->etapesDeChantier();
        $total = $this->dureeEnCycles * self::DIXIEMES_PAR_CYCLE;
        $parEtape = $total / \count($etapes);

        $debutDeLaQuinzaine = $this->avancementEnDixiemes;
        $finDeLaQuinzaine = $debutDeLaQuinzaine + self::avancementDUneQuinzaine($saison, $faveurEnCentiemes);

        $rendu = [];

        foreach ($etapes as $rang => $etape) {
            $debut = $rang * $parEtape;
            $fin = ($rang + 1) * $parEtape;

            $rendu[] = [
                'etape' => $etape,
                'numero' => $rang + 1,
                'etat' => match (true) {
                    $fin <= $debutDeLaQuinzaine => EtatDEtape::Terminee,
                    $debut < $finDeLaQuinzaine => EtatDEtape::EnCours,
                    default => EtatDEtape::AVenir,
                },
            ];
        }

        return $rendu;
    }

    /**
     * Les étapes que la quinzaine qui vient va traverser. Jamais vide : un
     * chantier non achevé travaille forcément à quelque chose.
     *
     * @return list<EtapeDeChantier>
     */
    public function etapesEnCours(?Saison $saison = null): array
    {
        $enCours = [];

        foreach ($this->etapes($saison) as ['etape' => $etape, 'etat' => $etat]) {
            if (EtatDEtape::EnCours === $etat) {
                $enCours[] = $etape;
            }
        }

        return $enCours;
    }

    public function nombreDEtapes(): int
    {
        return \count($this->type->etapesDeChantier());
    }
}
