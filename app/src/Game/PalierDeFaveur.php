<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les quatre paliers de faveur d'une divinité (doc 07).
 *
 * Les plages viennent du document. Deux propriétés en découlent, et l'une
 * comme l'autre est délibérée :
 *
 * **Ne pas honorer un dieu ne coûte rien.** La bande Neutre est large, et
 * c'est là qu'on démarre : il faut avoir fait descendre une divinité sous 25
 * pour être puni, ce qui demande une négligence prolongée ou une quête ratée —
 * jamais la simple inaction d'un début de partie, où le Temple n'est même pas
 * bâti.
 *
 * **Le palier Dévoué n'est pas qu'un bonus plus gros** : il ouvre la
 * possibilité d'une bénédiction ponctuelle, comme le palier Hostile prolongé
 * ouvre celle d'une malédiction. La symétrie est du doc 07.
 */
enum PalierDeFaveur: string
{
    case Hostile = 'hostile';
    case Neutre = 'neutre';
    case Favorable = 'favorable';
    case Devoue = 'devoue';

    public static function pour(int $faveur): self
    {
        return match (true) {
            $faveur < 25 => self::Hostile,
            $faveur < 50 => self::Neutre,
            $faveur < 80 => self::Favorable,
            default => self::Devoue,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Hostile => 'Hostile',
            self::Neutre => 'Neutre',
            self::Favorable => 'Favorable',
            self::Devoue => 'Dévoué',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Hostile => 'Le dieu se détourne, et son domaine s\'en ressent.',
            self::Neutre => 'Le dieu ne vous doit rien, et ne vous veut rien.',
            self::Favorable => 'Le dieu vous est acquis : son domaine vous favorise.',
            self::Devoue => 'Le dieu vous tient pour siens : sa faveur est pleine, et il lui arrive de la manifester.',
        };
    }

    /**
     * Au-dessus du neutre, un dieu compte dans ce que le Temple peut porter
     * (doc 01 : « le nombre de dieux honorés croît avec le niveau »).
     */
    public function estAuDessusDuNeutre(): bool
    {
        return self::Favorable === $this || self::Devoue === $this;
    }

    /**
     * Un palier hostile agit contre la ville. Les paliers Neutre et au-dessus
     * ne font jamais de mal.
     */
    public function nuit(): bool
    {
        return self::Hostile === $this;
    }
}
