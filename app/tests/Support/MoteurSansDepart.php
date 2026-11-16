<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Random\Engine;

/**
 * Un moteur qui rend toujours 99, donc un `getInt(1, 100)` qui rend toujours 100.
 *
 * Branché sur `DepartsNaturels` en test : un chef ne part jamais « au hasard » pendant un test qui
 * fait passer des quinzaines. Sans lui, un chef d'ancienneté 20 avait 5 % de chances de partir à
 * chaque passage, et un test qui comptait sur lui échouait une fois de temps en temps, sans rapport
 * avec ce qu'il vérifiait. 99 plutôt qu'un maximum : une valeur trop haute serait rejetée par
 * l'échantillonnage de `Randomizer`, qui tournerait alors sans fin.
 */
final class MoteurSansDepart implements Engine
{
    /**
     * Vrai le temps d'un test qui **veut** les départs (`MecontentementTest`) : à remettre à faux dans
     * un `finally`, l'état étant partagé par tout le processus.
     */
    public static bool $hasardReel = false;

    public function generate(): string
    {
        return self::$hasardReel ? random_bytes(8) : pack('P', 99);
    }
}
