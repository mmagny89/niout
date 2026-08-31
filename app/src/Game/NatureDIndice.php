<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que vaut un indice dans son enquête (doc 10).
 */
enum NatureDIndice: string
{
    case Concordant = 'concordant';
    case Optionnel = 'optionnel';
    case Trompeur = 'trompeur';

    public function libelle(): string
    {
        return match ($this) {
            self::Concordant => 'Concordant',
            self::Optionnel => 'De contexte',
            self::Trompeur => 'À vérifier',
        };
    }

    /**
     * **Le joueur ne sait pas qu'un indice est trompeur.** L'écran ne l'écrit
     * donc jamais : « à vérifier » vaut pour un indice de contexte comme pour
     * une fausse piste, et c'est au joueur de trancher. Afficher la nature
     * réelle résoudrait l'enquête à sa place.
     */
    public function libelleAffiche(): string
    {
        return self::Concordant === $this ? 'Concordant' : 'À vérifier';
    }
}
