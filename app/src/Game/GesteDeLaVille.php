<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que fait une silhouette à un moment du rejeu (`Choregraphie`). Un geste dit
 * ce qui se passe ; il ne le décide pas — la logique est dans la chorégraphie, le
 * libellé seul est ici.
 */
enum GesteDeLaVille: string
{
    case Poste = 'poste';
    case Chez = 'chez';
    case Fabrique = 'fabrique';
    case Construit = 'construit';
    case Acheve = 'acheve';
    case ConvoiParti = 'convoi_parti';
    case ConvoiRentre = 'convoi_rentre';
    case Ronde = 'ronde';
    case Veille = 'veille';
    case Repos = 'repos';
    case Procession = 'procession';
    case Paie = 'paie';
    case Rode = 'rode';
    case Oisif = 'oisif';
    case Corvee = 'corvee';
    case Habite = 'habite';
    case Part = 'part';
    case Course = 'course';
    case Visite = 'visite';
    case Detente = 'detente';
    case Joue = 'joue';

    /**
     * La famille du geste, pour qu'un journal se lise d'un coup d'œil : le travail, le foyer, les sorties, le
     * repos de qui est alité, ou le départ. Aucune règle : c'est une couleur.
     */
    public function famille(): string
    {
        return match ($this) {
            self::Poste, self::Fabrique, self::Construit, self::Acheve, self::ConvoiParti, self::ConvoiRentre,
            self::Ronde, self::Veille, self::Paie, self::Corvee => 'travail',
            self::Chez, self::Habite => 'foyer',
            self::Repos => 'repos',
            self::Part => 'depart',
            self::Procession, self::Course, self::Visite, self::Detente, self::Rode, self::Oisif, self::Joue => 'sortie',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Poste => 'À son poste',
            self::Chez => 'Chez soi',
            self::Fabrique => 'Fabrique',
            self::Construit => 'Travaille au chantier',
            self::Acheve => 'Achève le chantier',
            self::ConvoiParti => 'Charge un convoi qui part',
            self::ConvoiRentre => 'Accueille un convoi',
            self::Ronde => 'Fait sa ronde',
            self::Veille => 'Veille',
            self::Repos => 'Alité, au repos',
            self::Procession => 'Suit la procession',
            self::Paie => 'Verse la paie',
            self::Rode => 'Rôde sur la place',
            self::Oisif => 'Sans ouvrage',
            self::Corvee => 'Va à la corvée',
            self::Habite => 'Autour des maisons',
            self::Part => 'Quitte la ville',
            self::Course => 'Fait une course',
            self::Visite => 'Rend visite',
            self::Detente => 'Se détend',
            self::Joue => 'Joue dans les ruelles',
        };
    }
}
