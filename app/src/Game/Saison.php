<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les trois saisons du calendrier égyptien (doc 05), de quatre mois chacune.
 *
 * Elles ne sont pas décoratives : la crue d'Akhèt libère la main-d'œuvre
 * paysanne et accélère les chantiers, tout en noyant les champs.
 */
enum Saison: string
{
    case Akhet = 'akhet';
    case Peret = 'peret';
    case Chemou = 'chemou';

    public function libelle(): string
    {
        return match ($this) {
            self::Akhet => 'Akhèt',
            self::Peret => 'Perèt',
            self::Chemou => 'Chémou',
        };
    }

    public function sens(): string
    {
        return match ($this) {
            self::Akhet => 'l\'inondation',
            self::Peret => 'l\'émergence',
            self::Chemou => 'la récolte',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Akhet => 'Le Nil déborde. Les champs sont sous l\'eau, mais les paysans libérés grossissent les chantiers.',
            self::Peret => 'Les eaux se retirent. On sème sur le limon, les cultures poussent.',
            self::Chemou => 'La moisson. Le fleuve est au plus bas, la navigation plus difficile.',
        };
    }

    /**
     * Accélération des chantiers pendant la crue : la corvée mobilisait
     * réellement les paysans désœuvrés sur les grands travaux (doc 05).
     * Uniforme quel que soit le matériau, l'écart entre brique et pierre étant
     * déjà porté par la durée de base.
     */
    public function facteurDAvancementDesChantiers(): float
    {
        return self::Akhet === $this ? 1.5 : 1.0;
    }
}
