<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'on sème dans un champ (doc 08).
 *
 * Le blé et l'orge nourrissent, le lin habille. Les trois poussent au même
 * rythme saisonnier : le choix n'est donc pas un arbitrage de rendement mais
 * de destination — manger, brasser, ou tisser et honorer les dieux.
 */
enum Culture: string
{
    case Ble = 'ble';
    case Orge = 'orge';
    case Lin = 'lin';

    public function libelle(): string
    {
        return match ($this) {
            self::Ble => 'blé',
            self::Orge => 'orge',
            self::Lin => 'lin',
        };
    }

    public function usage(): string
    {
        return match ($this) {
            self::Ble => 'Nourriture, et le pain de l\'Atelier.',
            self::Orge => 'Nourriture, et la bière de l\'Atelier.',
            self::Lin => 'Textile — et la seule offrande que le Temple accepte.',
        };
    }

    public function ressource(): Ressource
    {
        return match ($this) {
            self::Ble => Ressource::Ble,
            self::Orge => Ressource::Orge,
            self::Lin => Ressource::Lin,
        };
    }
}
