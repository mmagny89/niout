<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les quatre natures d'objectif d'une mission (doc 09). Elles évitent la
 * répétition d'une mission à l'autre tout en gardant les mêmes mécaniques :
 * le type **nomme** ce qu'on vient faire, il ne change aucune règle.
 *
 * **Le document se contredit**, et la contradiction est tranchée ici
 * (décision de la joueuse) : sa section n'annonce que trois types, mais son
 * tableau des missions donne « Exploiter » à l'Ouadi Hammamat. Le tableau, plus
 * précis, l'emporte — un camp minier temporaire n'est ni une fondation ni un
 * développement, et l'annoncer comme un développement décrirait mal ce qu'on y
 * fait.
 */
enum TypeDeMission: string
{
    /** La ville n'existe pas encore : on bâtit sur un site vierge. */
    case Fonder = 'fonder';

    /** Une ville existe, affaiblie ou sous-développée : on la fait revivre. */
    case Developper = 'developper';

    /** Ville frontalière récemment conquise : l'enjeu est aussi militaire. */
    case Securiser = 'securiser';

    /**
     * Un camp, pas une ville : on vient prendre ce que le sol donne, et
     * repartir. Les règles restent les mêmes — la mission 9 se joue comme les
     * autres —, c'est la géographie qui la distingue : ni fleuve ni mer.
     */
    case Exploiter = 'exploiter';

    public function libelle(): string
    {
        return match ($this) {
            self::Fonder => 'Fonder',
            self::Developper => 'Restaurer et développer',
            self::Securiser => 'Sécuriser',
            self::Exploiter => 'Exploiter',
        };
    }
}
