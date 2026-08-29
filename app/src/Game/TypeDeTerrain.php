<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les terrains d'une case de la carte (doc 02).
 *
 * Leur placement suit la géographie réelle de l'Égypte : la Méditerranée borde
 * le nord, la mer Rouge l'est, le Nil descend en colonne, le désert occupe ce
 * qui reste des bords, et le centre est fertile par défaut.
 */
enum TypeDeTerrain: string
{
    case Nil = 'nil';
    case Mediterranee = 'mediterranee';
    case MerRouge = 'mer_rouge';
    case Desert = 'desert';
    case Oasis = 'oasis';
    case Fertile = 'fertile';
    /**
     * La « terre classique » du doc 02 : ni cultivée ni désert, ces
     * broussailles et taillis où poussent d'eux-mêmes acacias et sycomores.
     *
     * Elle remplace l'ancienne « terre fertile non cultivable », qui n'était
     * qu'une case fertile que le tirage n'avait pas retenue — un manque plutôt
     * qu'un lieu. C'est désormais un terrain à part entière, avec sa ressource
     * caractéristique : le bois local.
     */
    case TerreClassique = 'terre_classique';
    case Foret = 'foret';

    public function libelle(): string
    {
        return match ($this) {
            self::Nil => 'Le Nil',
            self::Mediterranee => 'La Méditerranée',
            self::MerRouge => 'La mer Rouge',
            self::Desert => 'Désert',
            self::Oasis => 'Oasis',
            self::Fertile => 'Terre fertile',
            // Le doc 02 la nomme « terre classique » ; le joueur, lui, doit
            // voir de quoi il s'agit.
            self::TerreClassique => 'Terre broussailleuse',
            self::Foret => 'Forêt de cèdres',
        };
    }

    /**
     * Une case d'eau : pêchable une fois le Port dressé, et elle rend la ville
     * constructible à côté d'elle (doc 01, doc 02).
     */
    public function estUnPointDEau(): bool
    {
        return \in_array($this, [self::Nil, self::Mediterranee, self::MerRouge], true);
    }

    /**
     * Terrain où un champ peut être établi (doc 02). Le Nil en fait partie :
     * la crue y laisse le limon sur lequel on cultivait réellement.
     *
     * **La terre classique n'en est jamais** — c'est ce qui la distingue
     * structurellement de la terre fertile, et non un simple degré de moins.
     */
    public function accepteUnChamp(): bool
    {
        return \in_array($this, [self::Fertile, self::Nil, self::Oasis], true);
    }

    /**
     * Nom du fichier de tuile, sans extension (planche « tuiles » du Drive).
     */
    public function tuile(): string
    {
        return match ($this) {
            self::Nil => 'nil',
            self::Mediterranee, self::MerRouge => 'mer',
            self::Desert => 'desert',
            self::Oasis => 'oasis',
            self::Fertile => 'fertile',
            self::TerreClassique => 'terre_classique',
            self::Foret => 'foret',
        };
    }
}
