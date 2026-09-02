<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les deux spécialisations des Medjaÿ (doc 03, doc 01).
 *
 * **Deux, et le document explique pourquoi.** Les Medjaÿ étaient un corps de
 * sécurité intérieure d'origine nubienne, chargé de la protection des villes,
 * de l'escorte des caravanes et de la garde des sites sacrés. Leur armement
 * attesté était l'arc, les flèches et le bouclier — **jamais le char de
 * guerre**, qui appartenait à la *mesha*, l'armée professionnelle de l'État,
 * un corps entièrement distinct. Le Charrier n'est donc pas ici : il se
 * réquisitionne (lot 10.6), il ne se recrute pas.
 *
 * Le nom ne se traduit pas et ne s'anglicise pas — c'est du vocabulaire de
 * l'univers, au même titre qu'Akhèt ou quinzaine. La classe s'écrit sans son
 * tréma pour rester manipulable, le libellé le porte.
 */
enum SpecialisationMedjay: string
{
    case Fantassin = 'fantassin';
    case Archer = 'archer';

    public function libelle(): string
    {
        return match ($this) {
            self::Fantassin => 'Fantassin',
            self::Archer => 'Archer',
        };
    }

    /**
     * Le niveau de Caserne qui l'ouvre (doc 01 : fantassin dès le niveau 1,
     * archer à partir du quatrième).
     */
    public function niveauDeCaserneRequis(): int
    {
        return match ($this) {
            self::Fantassin => 1,
            self::Archer => 4,
        };
    }

    /**
     * Sa force au combat, avant équipement et expérience (doc 03).
     */
    public function force(): int
    {
        return match ($this) {
            self::Fantassin => 10,
            self::Archer => 15,
        };
    }

    public function coutDeRecrutement(): int
    {
        return match ($this) {
            self::Fantassin => 15,
            self::Archer => 25,
        };
    }

    /**
     * Ce qu'il coûte par quinzaine, une fois levé. Il rejoint la masse
     * salariale de la ville comme n'importe quel autre homme payé : une troupe
     * qu'on ne peut plus entretenir est le vrai frein à l'effectif.
     */
    public function entretienParQuinzaine(): int
    {
        return match ($this) {
            self::Fantassin => 1,
            self::Archer => 2,
        };
    }

    /**
     * Ce que le bouclier épargne à la troupe, en points de pourcentage de
     * pertes (doc 03). L'archer n'en réduit aucune : il frappe de loin, il ne
     * couvre personne.
     */
    public function reductionDesPertes(): int
    {
        return match ($this) {
            self::Fantassin => 30,
            self::Archer => 0,
        };
    }

    /**
     * Ce que l'archer gagne en terrain désertique (doc 03), en points de
     * pourcentage — le tir à découvert y porte, là où le corps à corps s'y
     * épuise.
     */
    public function bonusEnDesert(): int
    {
        return match ($this) {
            self::Fantassin => 0,
            self::Archer => 10,
        };
    }

    public function particularite(): string
    {
        return match ($this) {
            self::Fantassin => 'Son bouclier couvre la troupe : les pertes en sont réduites de 30 %.',
            self::Archer => 'Tire à découvert : 10 % plus efficace en terrain désertique.',
        };
    }

    /**
     * Celles qu'une Caserne de ce niveau permet de lever.
     *
     * @return list<self>
     */
    public static function ouvertesAuNiveau(int $niveauDeCaserne): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $specialisation): bool => $niveauDeCaserne >= $specialisation->niveauDeCaserneRequis(),
        ));
    }
}
