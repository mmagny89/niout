<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La longueur d'un règne, par catégorie (doc 14).
 *
 * **Le document se garde d'une fausse précision, et c'est le point** : un règne
 * ne se convertit pas année pour année en cycles de jeu. Le temps du jeu est
 * abstrait — une quinzaine, un calendrier saisonnier — et prétendre simuler
 * soixante-six ans de Ramsès II en cycles reviendrait à donner l'illusion d'une
 * exactitude que le reste du jeu ne prétend pas avoir.
 *
 * Trois catégories suffisent : **l'ordre relatif est respecté** — un règne réel
 * plus long reste plus long en jeu — sans que personne puisse lire une durée de
 * jeu comme une donnée historique.
 */
enum LongueurDeRegne: string
{
    case Court = 'court';
    case Moyen = 'moyen';
    case Long = 'long';

    /**
     * La borne d'années réelles qui range un règne dans cette catégorie
     * (doc 14 : court < 15 ans, moyen 15-30, long > 30).
     */
    public static function pourDesAnnees(int $annees): self
    {
        return match (true) {
            $annees < 15 => self::Court,
            $annees <= 30 => self::Moyen,
            default => self::Long,
        };
    }

    /**
     * La fourchette de cycles de jeu correspondante (doc 14).
     *
     * @return array{0: int, 1: int}
     */
    public function fourchetteEnCycles(): array
    {
        return match ($this) {
            self::Court => [10, 15],
            self::Moyen => [16, 25],
            self::Long => [26, 35],
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Court => 'Règne bref',
            self::Moyen => 'Règne établi',
            self::Long => 'Long règne',
        };
    }
}
