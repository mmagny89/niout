<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qui empêche de faire venir des habitants — plus de place, plus de deben.
 * Le message est destiné au joueur, il doit dire quoi faire.
 */
final class AppelImpossible extends \RuntimeException
{
}
