<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Les deux modes de jeu (doc 00).
 */
enum GameMode: string
{
    /**
     * Dix missions jouées dans l'ordre imposé, chacune liée à un pharaon réel
     * et à une ville attestée.
     */
    case Campagne = 'campagne';

    /**
     * Une seule ville, Memphis, développée sur la durée à travers une
     * succession de règnes. Pas d'objectif de fin.
     */
    case Aventure = 'aventure';

    public function libelle(): string
    {
        return match ($this) {
            self::Campagne => 'Campagne',
            self::Aventure => 'Aventure',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Campagne => 'Dix missions, dix villes, un pharaon commanditaire à chaque fois. Difficulté croissante.',
            self::Aventure => 'Memphis, sur la longue durée, à travers plusieurs règnes. Sans fin imposée.',
        };
    }
}
