<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les huit traits qu'un candidat peut porter (doc 03).
 *
 * Chaque trait module la compétence, le salaire ou l'ancienneté — en
 * pourcentages, tous chiffrés par le document. Le joueur n'en voit que le nom :
 * c'est le principe qui structure toute la phase, **chiffré en interne,
 * qualitatif à l'affichage**.
 *
 * Deux d'entre eux ne font encore rien : « Pieux » attend la faveur divine
 * (Phase 6) et « Bagarreur » le combat (Phase 10). Ils sont tirés dès
 * maintenant — un candidat est un profil complet, doc 03 — mais l'interface
 * doit dire qu'ils dorment, plutôt que de laisser croire à un bonus actif.
 */
enum TraitDeCandidat: string
{
    case TravailleurAcharne = 'travailleur_acharne';
    case Econome = 'econome';
    case Fidele = 'fidele';
    case Ambitieux = 'ambitieux';
    case Croyant = 'croyant';
    case Bagarreur = 'bagarreur';
    case Experimente = 'experimente';
    case Novice = 'novice';

    /**
     * Ce que le joueur lit. « Novice » s'affiche « Débutant prometteur » : le
     * document tient à ce que le défaut se présente comme une promesse.
     */
    public function libelle(): string
    {
        return match ($this) {
            self::TravailleurAcharne => 'Travailleur acharné',
            self::Econome => 'Économe',
            self::Fidele => 'Fidèle',
            self::Ambitieux => 'Ambitieux',
            self::Croyant => 'Pieux',
            self::Bagarreur => 'Bagarreur',
            self::Experimente => 'Expérimenté',
            self::Novice => 'Débutant prometteur',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TravailleurAcharne => 'Abat plus d\'ouvrage que les autres, et le fait payer un peu.',
            self::Econome => 'Se contente de peu, et en donne un peu moins.',
            self::Fidele => 'Restera longtemps en poste.',
            self::Ambitieux => 'Doué, mais regarde déjà ailleurs.',
            self::Croyant => 'Honore les dieux — sans effet tant que le Temple n\'ouvre pas ses cultes.',
            self::Bagarreur => 'A le poing leste — sans effet tant qu\'aucun Medjaÿ n\'est recruté.',
            self::Experimente => 'Sait déjà tout faire, se vend cher et ne restera pas.',
            self::Novice => 'Malhabile et bon marché, mais il s\'accrochera.',
        };
    }

    /**
     * Modificateurs en pourcentage (doc 03). Tous entiers : aucune valeur de
     * jeu ne se compare en flottants.
     */
    public function effetSurCompetence(): int
    {
        return match ($this) {
            self::TravailleurAcharne => 15,
            self::Econome => -10,
            self::Ambitieux => 10,
            self::Experimente => 25,
            self::Novice => -20,
            self::Fidele, self::Croyant, self::Bagarreur => 0,
        };
    }

    public function effetSurSalaire(): int
    {
        return match ($this) {
            self::TravailleurAcharne => 10,
            self::Econome => -15,
            self::Experimente => 20,
            self::Novice => -15,
            self::Fidele, self::Ambitieux, self::Croyant, self::Bagarreur => 0,
        };
    }

    public function effetSurAnciennete(): int
    {
        return match ($this) {
            self::Fidele => 30,
            self::Ambitieux => -30,
            self::Experimente => -20,
            self::Novice => 20,
            self::TravailleurAcharne, self::Econome, self::Croyant, self::Bagarreur => 0,
        };
    }

    /**
     * Deux traits aux effets opposés ne peuvent jamais coexister (doc 03) :
     * Ambitieux et Fidèle se contredisent sur l'ancienneté, Travailleur acharné
     * et Économe sur le salaire.
     */
    public function estIncompatibleAvec(self $autre): bool
    {
        return match ($this) {
            self::Ambitieux => self::Fidele === $autre,
            self::Fidele => self::Ambitieux === $autre,
            self::TravailleurAcharne => self::Econome === $autre,
            self::Econome => self::TravailleurAcharne === $autre,
            default => false,
        };
    }

    /**
     * Traits dont le système d'accueil n'existe pas encore. L'affichage doit le
     * signaler : promettre un bonus qui ne s'applique nulle part serait mentir
     * au joueur au moment précis où il compare des candidats.
     */
    public function dortEnAttendantSaPhase(): bool
    {
        return \in_array($this, [self::Croyant, self::Bagarreur], true);
    }
}
