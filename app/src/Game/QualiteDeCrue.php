<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La crue de l'année, tirée au sort à chaque changement d'année (doc 05).
 *
 * Une forte crue dépose plus de limon, donc fertilise mieux : elle se paie à la
 * moisson de Chémou, pas au moment où elle survient. C'est le seul aléa que le
 * joueur subit sans pouvoir l'infléchir, ce qui justifie qu'il en soit averti
 * dès le début d'Akhèt plutôt que de le découvrir à la récolte.
 */
enum QualiteDeCrue: string
{
    case Faible = 'faible';
    case Normale = 'normale';
    case Forte = 'forte';

    public function libelle(): string
    {
        return match ($this) {
            self::Faible => 'faible',
            self::Normale => 'normale',
            self::Forte => 'forte',
        };
    }

    public function presage(): string
    {
        return match ($this) {
            self::Faible => 'Le fleuve est monté sans conviction. Le limon manquera, la moisson sera maigre.',
            self::Normale => 'La crue est venue comme elle vient d\'ordinaire. La moisson tiendra ses promesses.',
            self::Forte => 'Le Nil a débordé largement. Le limon est épais : la moisson sera généreuse.',
        };
    }

    /**
     * Le modificateur de récolte, en dixièmes — ×0,7 / ×1,0 / ×1,3 (doc 05).
     *
     * En dixièmes et non en flottants : aucune valeur de jeu ne se compare en
     * virgule flottante, et un rendement s'additionne cycle après cycle.
     */
    public function modificateurEnDixiemes(): int
    {
        return match ($this) {
            self::Faible => 7,
            self::Normale => 10,
            self::Forte => 13,
        };
    }

    /**
     * Poids du tirage annuel : faible 20 %, normale 60 %, forte 20 % (doc 05).
     *
     * @return array<string, int>
     */
    public static function poids(): array
    {
        return [
            self::Faible->value => 20,
            self::Normale->value => 60,
            self::Forte->value => 20,
        ];
    }

    /**
     * Le cran au-dessus, s'il en existe un. Sert à Hâpi (lot 6.3) : un dieu
     * dévoué rend la crue faible moins probable, il ne crée pas une crue que
     * le Nil ne connaît pas.
     */
    public function cranAuDessus(): self
    {
        return match ($this) {
            self::Faible => self::Normale,
            self::Normale, self::Forte => self::Forte,
        };
    }

    public function cranEnDessous(): self
    {
        return match ($this) {
            self::Forte => self::Normale,
            self::Normale, self::Faible => self::Faible,
        };
    }
}
