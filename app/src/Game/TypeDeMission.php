<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les trois natures d'objectif d'une mission (doc 09). Elles évitent la
 * répétition d'une mission à l'autre tout en gardant les mêmes mécaniques.
 */
enum TypeDeMission: string
{
    /** La ville n'existe pas encore : on bâtit sur un site vierge. */
    case Fonder = 'fonder';

    /** Une ville existe, affaiblie ou sous-développée : on la fait revivre. */
    case Developper = 'developper';

    /** Ville frontalière récemment conquise : l'enjeu est aussi militaire. */
    case Securiser = 'securiser';

    public function libelle(): string
    {
        return match ($this) {
            self::Fonder => 'Fonder',
            self::Developper => 'Restaurer et développer',
            self::Securiser => 'Sécuriser',
        };
    }
}
