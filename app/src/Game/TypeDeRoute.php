<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Par où passe une route commerciale (doc 12).
 *
 * Ce n'est pas un décor : le type décide **quel bâtiment l'ouvre** et **combien
 * elle peut porter**. Une caravane relève de l'Entrepôt, un navire du Port —
 * une ville sans quai ne commerce que par la piste.
 */
enum TypeDeRoute: string
{
    case Terrestre = 'terrestre';
    case Fluviale = 'fluviale';
    case Maritime = 'maritime';

    public function libelle(): string
    {
        return match ($this) {
            self::Terrestre => 'Piste caravanière',
            self::Fluviale => 'Voie fluviale',
            self::Maritime => 'Voie maritime',
        };
    }

    /**
     * Le bâtiment qui l'ouvre (doc 12) : l'Entrepôt pour les caravanes
     * terrestres, le Port pour tout ce qui flotte.
     */
    public function batiment(): TypeDeBatiment
    {
        return self::Terrestre === $this ? TypeDeBatiment::Entrepot : TypeDeBatiment::Port;
    }

    /**
     * Ce que coûte l'ouverture, en deben (doc 12, où l'or est devenu le
     * deben) : une piste demande des guides et des puits, un port des navires.
     */
    public function coutDOuverture(): int
    {
        return self::Terrestre === $this ? 100 : 150;
    }

    /**
     * Ce qu'un convoi peut porter par quinzaine, selon le niveau du bâtiment
     * qui l'arme (doc 12) : `10 × niveau` pour une caravane, `15 × niveau`
     * pour un navire — un bateau porte davantage qu'un âne.
     */
    public function volumeParNiveau(): int
    {
        return self::Terrestre === $this ? 10 : 15;
    }

    public function convoi(): string
    {
        return self::Terrestre === $this ? 'caravane' : 'navire';
    }
}
