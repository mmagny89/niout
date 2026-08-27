<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Où en est une étape de chantier vis-à-vis de la quinzaine qui vient.
 *
 * Trois états et non deux : une étape « à venir » n'est pas la même chose
 * qu'une étape en cours, et le joueur doit voir les quatre étapes du doc 01 en
 * permanence — sinon les plus courtes défilent sans jamais s'afficher.
 */
enum EtatDEtape: string
{
    case Terminee = 'terminee';
    case EnCours = 'en_cours';
    case AVenir = 'a_venir';
}
