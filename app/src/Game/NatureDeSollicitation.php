<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce qu'une cité demande ou offre (`docs/plan-pnj-palier-a.md` § 7).
 *
 * *L'occasion* du plan (« une caravane part pour Pount, il reste de la place ») n'existe pas encore :
 * elle réclame de lire la capacité d'un convoi, et reste à écrire.
 */
enum NatureDeSollicitation: string
{
    /** La cité cède une marchandise à prix de faveur : la famille **achète**. */
    case Offre = 'offre';

    /** La cité réclame une marchandise et la paie bien : la famille **vend**. */
    case Demande = 'demande';

    public function libelle(): string
    {
        return match ($this) {
            self::Offre => 'Offre',
            self::Demande => 'Demande',
        };
    }
}
