<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le profil géographique d'une région : quels bords elle possède, et quelles
 * ressources brutes on peut y trouver (doc 02, doc 08, doc 11).
 *
 * Données de référence, jamais persistées.
 */
final readonly class GeographieDeRegion
{
    /**
     * @param list<Ressource> $ressourcesDeZone ce que la région peut porter en gisement
     */
    public function __construct(
        public bool $nil = false,
        public bool $mediterranee = false,
        public bool $merRouge = false,
        public bool $desert = false,
        /**
         * Régions où le sable l'emporte sur la terre cultivable (doc 11) :
         * Haute-Nubie, désert oriental, Sinaï. Le doc 02 ne pose le désert que
         * sur un bord, ce qui laisserait ces régions majoritairement fertiles —
         * un camp minier entouré de champs, contraire à leur description.
         */
        public bool $desertDominant = false,
        public bool $oasis = false,
        public bool $foret = false,
        public array $ressourcesDeZone = [],
    ) {
    }

    public function aUnPointDEau(): bool
    {
        return $this->nil || $this->mediterranee || $this->merRouge;
    }

    /**
     * Sans Nil, il n'y a ni crue ni zone inondable : le système des saisons ne
     * s'applique pas à ces régions (doc 02, doc 05).
     */
    public function connaitLaCrue(): bool
    {
        return $this->nil;
    }
}
