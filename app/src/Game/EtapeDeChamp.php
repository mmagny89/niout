<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les quatre étapes d'un champ, sur le modèle d'`EtapeDeChantier` : le joueur
 * voit où en est sa terre plutôt qu'un simple pourcentage.
 *
 * Un champ du Nil les traverse au rythme de la saison (`RendementDesChamps`),
 * un champ terrestre à son propre rythme, indépendant de la crue
 * (`CycleAgricoleTerrestre`) : les deux se lisent avec le même vocabulaire.
 */
enum EtapeDeChamp: string
{
    case Semis = 'semis';
    case Pousse = 'pousse';
    case Recolte = 'recolte';
    case Repos = 'repos';

    public function libelle(): string
    {
        return match ($this) {
            self::Semis => 'Semis',
            self::Pousse => 'Pousse',
            self::Recolte => 'Récolte',
            self::Repos => 'Repos',
        };
    }

    public function explication(): string
    {
        return match ($this) {
            self::Semis => 'La graine vient d\'être mise en terre : rien à récolter encore.',
            self::Pousse => 'La culture grandit. Avoir un champ ne nourrit personne — seule la récolte le fait.',
            self::Recolte => 'La récolte tombe, prête à rejoindre le Grenier.',
            self::Repos => 'La terre se repose avant le prochain semis — un champ ne produit pas en continu.',
        };
    }
}
