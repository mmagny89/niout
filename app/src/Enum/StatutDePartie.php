<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * L'état d'une partie, au sens de sa progression — distinct du mode de jeu.
 *
 * Une partie abandonnée est supprimée (`PartieController`) ; une partie
 * échouée, elle, reste consultable : « chaque partie est une run complète »
 * (doc 00), y compris quand elle se termine mal.
 */
enum StatutDePartie: string
{
    case EnCours = 'en_cours';

    /**
     * La ville n'a plus nourri ses habitants assez longtemps (`Subsistance`).
     * Écrans de jeu fermés, partie conservée en lecture seule.
     */
    case Echouee = 'echouee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Echouee => 'Échouée',
        };
    }
}
