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

    /**
     * Le fil rouge est résolu : la mission est close (doc 09). **Pas de « game
     * over » à l'envers non plus** — une partie achevée ne redevient jamais
     * en cours, et ne peut plus échouer : elle est finie, et son score est
     * celui qu'elle avait au moment de l'être.
     */
    case Achevee = 'achevee';

    public function libelle(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Echouee => 'Échouée',
            self::Achevee => 'Accomplie',
        };
    }

    /**
     * Une partie close ne se joue plus, quelle que soit la façon dont elle
     * s'est terminée.
     */
    public function estClose(): bool
    {
        return self::EnCours !== $this;
    }
}
