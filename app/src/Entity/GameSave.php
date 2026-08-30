<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\GameMode;
use App\Enum\StatutDePartie;
use App\Game\DateDeJeu;
use App\Game\QualiteDeCrue;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une partie : l'état complet d'une run, du premier cycle à la fin de mission.
 *
 * Chaque partie est indépendante et rejouable — le jeu ne connaît pas la
 * sauvegarde unique et infinie (doc 00). Un compte peut en mener plusieurs de
 * front, dans la limite de self::MAX_PAR_COMPTE.
 */
#[ORM\Entity(repositoryClass: GameSaveRepository::class)]
class GameSave
{
    /**
     * Plafond de parties simultanées par compte. Au-delà, il faut en abandonner
     * une — la suppression étant définitive.
     */
    public const int MAX_PAR_COMPTE = 5;

    /**
     * La campagne se joue dans l'ordre imposé, de la première à la dixième
     * mission (doc 09, doc 11). Aucun choix de région.
     */
    public const int PREMIERE_MISSION = 1;
    public const int DERNIERE_MISSION = 10;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $joueur;

    #[ORM\Column(enumType: GameMode::class)]
    private GameMode $mode;

    /**
     * Numéro de la mission en cours, de 1 à 10. Renseigné en campagne
     * uniquement : le mode Aventure ne suit pas de missions mais une
     * succession de règnes (doc 14).
     */
    #[ORM\Column(nullable: true)]
    private ?int $mission = null;

    /**
     * Cycle courant, l'unité de temps du jeu : une « quinzaine », soit deux
     * semaines (doc 05). Il n'avance que sur action du joueur.
     */
    #[ORM\Column]
    private int $cycle = 1;

    /**
     * La crue de l'année en cours (doc 05), retirée à chaque nouvelle année.
     * Elle module la moisson de Chémou, bien après être survenue.
     */
    #[ORM\Column(enumType: QualiteDeCrue::class)]
    private QualiteDeCrue $crue = QualiteDeCrue::Normale;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private Family $famille;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private City $ville;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $lastOpenedAt;

    #[ORM\Column(enumType: StatutDePartie::class)]
    private StatutDePartie $statut = StatutDePartie::EnCours;

    /**
     * Quinzaines consécutives où la ville n'a pas pu nourrir tous ses
     * habitants (`Subsistance`). Remis à zéro dès qu'une quinzaine est payée
     * intégralement.
     */
    #[ORM\Column]
    private int $quinzainesDeFamine = 0;

    /**
     * Quinzaines de mécontentement accumulées (`Mecontentement`). **Deux
     * causes, un seul compteur** : la faim et les salaires impayés mènent à la
     * même colère, et il n'y a aucune raison de la compter deux fois.
     *
     * Il se résorbe **au même rythme qu'il monte**, un cran par quinzaine :
     * une ville qu'on affame huit quinzaines met huit quinzaines à se calmer.
     * Assez lent pour interdire le yo-yo, assez rapide pour qu'une ville
     * redressée s'en sorte.
     */
    #[ORM\Column]
    private int $quinzainesDeMecontentement = 0;

    private function __construct(User $joueur, GameMode $mode, Family $famille, City $ville)
    {
        $this->joueur = $joueur;
        $this->mode = $mode;
        $this->famille = $famille;
        $this->ville = $ville;
        $this->createdAt = new \DateTimeImmutable();
        $this->lastOpenedAt = $this->createdAt;
    }

    /**
     * Une campagne démarre toujours à la première mission : l'ordre est imposé,
     * il n'y a pas de sélection de région.
     */
    public static function pourCampagne(User $joueur, Family $famille, City $ville): self
    {
        $partie = new self($joueur, GameMode::Campagne, $famille, $ville);
        $partie->mission = self::PREMIERE_MISSION;

        return $partie;
    }

    public static function pourAventure(User $joueur, Family $famille, City $ville): self
    {
        return new self($joueur, GameMode::Aventure, $famille, $ville);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJoueur(): User
    {
        return $this->joueur;
    }

    public function getMode(): GameMode
    {
        return $this->mode;
    }

    public function getMission(): ?int
    {
        return $this->mission;
    }

    public function getCycle(): int
    {
        return $this->cycle;
    }

    public function getCrue(): QualiteDeCrue
    {
        return $this->crue;
    }

    public function annoncerLaCrue(QualiteDeCrue $crue): static
    {
        $this->crue = $crue;

        return $this;
    }

    public function getFamille(): Family
    {
        return $this->famille;
    }

    public function getVille(): City
    {
        return $this->ville;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastOpenedAt(): \DateTimeImmutable
    {
        return $this->lastOpenedAt;
    }

    /**
     * Enregistre la reprise de la partie, pour trier les parties de la plus
     * récemment jouée à la plus ancienne.
     */
    public function marquerOuverte(): static
    {
        $this->lastOpenedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Date du calendrier pharaonique correspondant au cycle courant.
     *
     * Dérivée, jamais persistée : le cycle suffit à la reconstituer. Exposée
     * ici pour que les gabarits n'aient pas à la recevoir de chaque contrôleur.
     */
    public function dateDeJeu(): DateDeJeu
    {
        return DateDeJeu::pourCycle($this->cycle);
    }

    /**
     * Fait avancer le temps d'une quinzaine. C'est la seule façon dont il
     * avance : aucune horloge réelle ne tourne hors du jeu (doc 05).
     */
    public function avancerDUnCycle(): static
    {
        ++$this->cycle;

        return $this;
    }

    public function estCampagne(): bool
    {
        return GameMode::Campagne === $this->mode;
    }

    public function getStatut(): StatutDePartie
    {
        return $this->statut;
    }

    public function estEnCours(): bool
    {
        return StatutDePartie::EnCours === $this->statut;
    }

    public function getQuinzainesDeFamine(): int
    {
        return $this->quinzainesDeFamine;
    }

    /**
     * Une quinzaine de plus sans nourriture suffisante. Bascule la partie en
     * échec une fois le seuil atteint (`Subsistance::SEUIL_DE_FAMINE`).
     */
    public function enregistrerUneQuinzaineDeFamine(): static
    {
        ++$this->quinzainesDeFamine;

        return $this;
    }

    /**
     * Partie d'essai (`City::estEnModeDivin()`) : à afficher clairement, une
     * run truquée ne se confond pas avec une vraie.
     */
    /**
     * Fait démarrer une campagne à une autre mission que la première.
     *
     * **Réservé au mode divin** : l'ordre des missions est imposé (doc 09), et
     * une campagne ordinaire n'a aucun moyen d'appeler ceci. Sans quoi les
     * neuf autres régions resteraient hors d'atteinte tant que la Phase 8 n'a
     * pas écrit l'enchaînement — et invérifiables autrement qu'en script.
     */
    public function commencerALaMission(int $numero): static
    {
        if ($this->estCampagne()) {
            $this->mission = $numero;
        }

        return $this;
    }

    public function estEnModeDivin(): bool
    {
        return $this->ville->estEnModeDivin();
    }

    /**
     * Remet la partie d'aplomb : famine oubliée, colère retombée, échec levé.
     *
     * Réservé au mode divin, et c'est **la seule chose du jeu qui défait un
     * échec** — sans elle, une partie tombée en famine ne pourrait plus servir
     * à tester quoi que ce soit, alors que c'est souvent celle qu'on veut
     * examiner.
     */
    public function toutRemettreDAplomb(): static
    {
        $this->statut = StatutDePartie::EnCours;
        $this->quinzainesDeFamine = 0;
        $this->quinzainesDeMecontentement = 0;

        return $this;
    }

    public function getQuinzainesDeMecontentement(): int
    {
        return $this->quinzainesDeMecontentement;
    }

    public function aggraverLeMecontentement(int $plafond): static
    {
        $this->quinzainesDeMecontentement = min($plafond, $this->quinzainesDeMecontentement + 1);

        return $this;
    }

    /**
     * Une quinzaine qui se passe bien apaise d'un cran — le même que celui qui
     * aggrave, ce qui rend la remontée aussi longue que la descente une fois
     * la cause levée.
     */
    public function apaiserLeMecontentement(): static
    {
        $this->quinzainesDeMecontentement = max(0, $this->quinzainesDeMecontentement - 1);

        return $this;
    }

    public function reinitialiserLaFamine(): static
    {
        $this->quinzainesDeFamine = 0;

        return $this;
    }

    /**
     * La ville n'a pas pu nourrir ses habitants trop longtemps : la partie se
     * conclut en échec, conservée plutôt que supprimée — « chaque partie est
     * une run complète » (doc 00), y compris quand elle finit mal.
     */
    public function echouer(): static
    {
        $this->statut = StatutDePartie::Echouee;

        return $this;
    }

    /**
     * Vrai lorsque la campagne est arrivée à sa dernière mission. Sans objet en
     * mode Aventure, qui n'a pas de fin scriptée.
     */
    public function estALaDerniereMission(): bool
    {
        return $this->estCampagne() && self::DERNIERE_MISSION === $this->mission;
    }
}
