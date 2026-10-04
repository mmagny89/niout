<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Game\Inscription;
use App\Game\LeconDeNiout;
use App\Game\SigneAlphabetique;
use App\Game\SymboleHieroglyphique;

/**
 * Ce qu'on mêle **au rendu** : dans l'ordre du catalogue, la bonne réponse se
 * lirait dans la source de la page. Le tirage n'a aucune conséquence de jeu.
 */
final class Melange
{
    /**
     * Une liste de propositions : la bonne est toujours la première du
     * catalogue.
     *
     * @param list<string> $propositions
     *
     * @return list<string>
     */
    public static function liste(array $propositions): array
    {
        shuffle($propositions);

        return $propositions;
    }

    /**
     * Les jetons d'une inscription, mêlés : dans l'ordre gravé, la réponse se
     * lirait dans la source.
     *
     * @return list<SymboleHieroglyphique>
     */
    public static function signesDe(Inscription $inscription): array
    {
        $signes = $inscription->signes();
        shuffle($signes);

        return $signes;
    }

    /**
     * Les quatre signes de la leçon fondatrice. Même parade que pour les
     * jetons du déchiffrage et les propositions d'une énigme.
     *
     * @return list<SigneAlphabetique>
     */
    public static function leconDeNiout(): array
    {
        $signes = LeconDeNiout::SIGNES;
        shuffle($signes);

        return $signes;
    }
}
