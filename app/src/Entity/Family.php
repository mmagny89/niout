<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\PalierDeRenommee;
use App\Repository\FamilyRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * La lignée incarnée par le joueur sur une partie donnée (doc 09, doc 13).
 *
 * Le joueur ne dirige pas un personnage unique mais une famille : c'est elle
 * qui porte la renommée, et c'est son nom qui apparaît dans les textes.
 */
#[ORM\Entity(repositoryClass: FamilyRepository::class)]
class Family
{
    /**
     * Nom proposé par défaut : réellement attesté dans l'Égypte antique, porté
     * par des particuliers — scribes, artisans, fonctionnaires — jamais par un
     * pharaon (doc 09).
     */
    public const string NOM_PAR_DEFAUT = 'Nakht';

    public const int RENOMMEE_MIN = 0;
    public const int RENOMMEE_MAX = 100;

    /**
     * Ce que les **affaires de l'esprit** — énigmes et enquêtes résolues —
     * peuvent rapporter au plus sur une mission (doc 13, lot 9.2).
     *
     * **Valeur inventée**, et le plafond est la moitié du sujet. Sans lui, une
     * campagne de dix missions où l'on résout tout verserait bien au-delà des
     * cent points de l'échelle : la renommée cesserait de mesurer une
     * réputation pour ne plus compter que l'assiduité à un mini-jeu. Huit
     * points laissent une mission bien menée s'approcher d'un demi-palier sans
     * jamais le franchir sur ce seul mérite.
     */
    public const int RENOMMEE_MAX_DES_AFFAIRES = 8;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 60)]
    private string $nom;

    /**
     * Jauge 0-100 déterminant l'attractivité de la ville pour les travailleurs
     * et les marchands (doc 13).
     *
     * **C'est la jauge de la mission, pas l'acquis de la campagne** : elle
     * démarre à l'acquis de la `Lignee` et bouge librement ensuite, à la
     * baisse comprise. Ce qu'elle vaut à la fin d'une mission accomplie relève
     * l'acquis ; elle ne le rabaisse jamais.
     */
    #[ORM\Column]
    private int $renommee = self::RENOMMEE_MIN;

    /**
     * `$renommeeDeDepart` est l'acquis de la lignée (`Lignees::renommeeDeDepart()`).
     * Nul par défaut : une première partie part de rien.
     */
    public function __construct(string $nom, int $renommeeDeDepart = self::RENOMMEE_MIN)
    {
        $this->nom = $nom;
        $this->renommee = max(
            self::RENOMMEE_MIN,
            min(self::RENOMMEE_MAX, $renommeeDeDepart),
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Ce que les énigmes et les enquêtes ont déjà rapporté sur cette mission,
     * pour ne pas dépasser self::RENOMMEE_MAX_DES_AFFAIRES. Compté à part de
     * la jauge : elle bouge pour six raisons, et un plafond qui la lirait
     * plafonnerait les cinq autres.
     */
    #[ORM\Column]
    private int $renommeeDesAffaires = 0;

    public function getRenommee(): int
    {
        return $this->renommee;
    }

    public function getRenommeeDesAffaires(): int
    {
        return $this->renommeeDesAffaires;
    }

    /**
     * Verse ce qu'une énigme ou une enquête résolue rapporte, dans la limite du
     * plafond de la mission.
     *
     * @return int ce qui a réellement été accordé — nul une fois le plafond
     *             atteint, et l'écran doit alors se taire plutôt qu'annoncer
     *             un gain de zéro
     */
    public function crediterUneAffaireResolue(int $points): int
    {
        $accorde = max(0, min($points, self::RENOMMEE_MAX_DES_AFFAIRES - $this->renommeeDesAffaires));

        if (0 === $accorde) {
            return 0;
        }

        $this->renommeeDesAffaires += $accorde;
        $this->ajusterRenommee($accorde);

        return $accorde;
    }

    /**
     * La renommée reste bornée à l'échelle 0-100 : les gains et pertes
     * ponctuels ne doivent jamais la faire sortir de ses paliers.
     */
    public function ajusterRenommee(int $variation): static
    {
        $this->renommee = max(
            self::RENOMMEE_MIN,
            min(self::RENOMMEE_MAX, $this->renommee + $variation),
        );

        return $this;
    }

    /**
     * Palier de renommée, tel que défini par le doc 13.
     */
    /**
     * Le palier atteint, qui décide de l'attractivité de la ville (doc 13).
     */
    public function palier(): PalierDeRenommee
    {
        return PalierDeRenommee::pour($this->renommee);
    }

    public function palierDeRenommee(): string
    {
        return $this->palier()->libelle();
    }
}
