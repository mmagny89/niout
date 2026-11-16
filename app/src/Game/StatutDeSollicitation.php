<?php

declare(strict_types=1);

namespace App\Game;

enum StatutDeSollicitation: string
{
    case Proposee = 'proposee';
    case Acceptee = 'acceptee';
    case Refusee = 'refusee';
    /** Personne n'a répondu avant l'échéance : **ignorer est une réponse valide** et ne coûte rien. */
    case Echue = 'echue';
}
