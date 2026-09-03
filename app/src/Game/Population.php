<?php

declare(strict_types=1);

namespace App\Game;

use Random\Randomizer;

/**
 * Les règles de peuplement d'une ville (doc 01, doc 02).
 *
 * **La ville se compte en trois nombres, jamais en individus** (décision de la
 * joueuse) : des enfants, des actifs, des anciens. Le joueur n'a besoin de
 * savoir que combien d'habitants il a, combien travaillent et combien sont à
 * charge — pas qui est qui ni de quel âge.
 *
 * Ces trois nombres bougent **une fois l'an**, pas à chaque quinzaine : les
 * enfants entrent dans la vie active, les actifs vieillissent, et la mort
 * prend sa part. Une année de jeu compte 25 quinzaines, ce qui laisse au
 * joueur le temps de voir venir.
 *
 * Deux valeurs viennent des documents et ne s'inventent pas : la capacité du
 * Quartier d'habitation (`20 × niveau` familles, doc 01) et le vivier régional
 * (`20 - 1,5 × difficulté`, doc 02).
 */
final readonly class Population
{
    /**
     * Taille moyenne d'une maisonnée, qui sert à convertir des habitants en
     * logements — l'ordre de grandeur des listes de maisonnées des papyrus de
     * Kahun et du village de Deir el-Médineh : un couple, deux à quatre
     * enfants survivants, parfois un aïeul.
     */
    public const int PERSONNES_PAR_FOYER = 5;

    /**
     * Capacité d'accueil d'un niveau de Quartier d'habitation, en familles
     * (doc 01). La ville en loge toujours une de plus : celle du joueur, que
     * la Résidence familiale abrite d'emblée.
     */
    public const int FAMILLES_PAR_NIVEAU_DE_QUARTIER = 20;

    /**
     * Le convoi que le pharaon envoie fonder la ville : la famille du joueur et
     * les volontaires qui l'accompagnent (décision de la joueuse au playtest).
     *
     * **Dix-sept personnes, dont huit bras.** On en comptait dix, dont quatre
     * bras, et c'était trop peu pour deux raisons qui se rejoignaient :
     *
     * - **Les bras n'arrivaient pas jusqu'à la terre.** Un chef embauché sort
     *   du vivier d'actifs, et les bâtiments se servent avant le territoire
     *   (`Effectifs::repartir()`) : trois bâtiments dirigés absorbaient les
     *   quatre actifs, les champs n'en recevaient aucun, et la ville tombait
     *   en famine pour avoir bâti. Mesuré.
     * - **Le premier acte n'était pas lisible.** Une ville de dix habitants
     *   tient dans deux maisonnées : la pression du logement existait — la
     *   capacité sans Quartier est d'un seul foyer — sans jamais se faire
     *   sentir. À dix-sept, il en faut quatre, et **bâtir des maisons devient
     *   le premier geste**, avant même les champs.
     *
     * **Rien n'est offert au passage** : la dotation royale se taille sur la
     * consommation réelle du convoi et sur une année de ses salaires, donc
     * elle suit. Un convoi plus nombreux mange plus, et il faut le loger.
     *
     * Historiquement, un pharaon qui fonde une ville n'envoie pas un couple et
     * ses enfants : les expéditions de fondation comptaient des dizaines
     * d'hommes, et celle du Ouadi Hammamat plus de huit mille (doc 09).
     */
    public const int ACTIFS_AU_DEPART = 8;
    public const int ENFANTS_AU_DEPART = 7;
    public const int ANCIENS_AU_DEPART = 2;

    /**
     * Ce que devient la population chaque année, en pourcentage de chaque
     * groupe. **Valeurs inventées** : aucun document ne les chiffre.
     *
     * Elles sont calibrées sur des durées de vie plausibles plutôt que sur un
     * effet de jeu — une enfance d'une douzaine d'années donne à peu près un
     * enfant sur douze qui entre chaque année dans la vie active, une vie
     * active d'une trentaine d'années à peu près un actif sur trente qui passe
     * la main.
     */
    public const int CHANCE_ENFANT_DEVIENT_ACTIF = 8;
    public const int CHANCE_ACTIF_DEVIENT_ANCIEN = 3;

    /**
     * Chance qu'un actif donne un enfant dans l'année. **Valeur inventée**,
     * calibrée pour que les naissances compensent à peu près les décès : une
     * ville bien tenue se maintient seule, mais ne grandit qu'en faisant venir
     * du monde. C'est ce qui garde au logement et à la renommée un intérêt
     * permanent, sans condamner pour autant une ville qu'on laisse tranquille.
     *
     * **Aucune naissance quand les maisons sont pleines** : la ville ne
     * déborde jamais de son logement. Simplification assumée — elle rend le
     * plafond du Quartier lisible plutôt que théorique.
     */
    public const int CHANCE_NAISSANCE_PAR_ACTIF = 10;

    /**
     * La mort frappe surtout les anciens.
     */
    public const int CHANCE_DECES_ANCIEN = 15;
    public const int CHANCE_DECES_ACTIF = 2;
    public const int CHANCE_DECES_ENFANT = 3;

    /**
     * Une maisonnée qui s'installe : deux bras et une charge variable — de
     * personne à six bouches de plus. C'est le même profil qu'un candidat
     * amène (doc 03), et la même variance : à prix égal, on ne sait pas
     * d'avance si l'on gagne des travailleurs ou des dépendants.
     *
     * Le hasard est passé plutôt que tenu, pour que chaque appelant tire avec
     * le sien — c'est ce qui rend une année de démographie reproductible sous
     * graine, tirages de migration compris.
     *
     * @return array{actifs: int, inactifs: int}
     */
    public static function maisonneeQuiArrive(Randomizer $hasard): array
    {
        return [
            'actifs' => GenerateurDeCandidat::ACTIFS_AMENES,
            'inactifs' => $hasard->getInt(0, GenerateurDeCandidat::INACTIFS_AMENES_MAX),
        ];
    }

    /**
     * Vivier de main-d'œuvre de la région : `20 - 1,5 × difficulté` (doc 02),
     * soit 20 familles au Delta et 6 ou 7 au Sinaï. Une région difficile n'est
     * pas seulement plus pauvre, elle est aussi moins peuplée.
     */
    public static function famillesDisponibles(int $difficulte): int
    {
        return 20 - intdiv(3 * $difficulte, 2);
    }

    /**
     * Combien de logements occupent ces habitants, à raison d'une maisonnée
     * par tranche entamée. C'est ce nombre que la capacité du Quartier borne.
     */
    public static function foyersPour(int $habitants): int
    {
        return intdiv($habitants + self::PERSONNES_PAR_FOYER - 1, self::PERSONNES_PAR_FOYER);
    }

    /**
     * Convertit en vivres une consommation exprimée en demi-rations — deux
     * pour un actif, une pour un inactif —, **arrondie au supérieur**.
     *
     * Au supérieur, et à l'échelle de la ville seulement : arrondir groupe par
     * groupe ferait manger un enfant isolé gratuitement, et arrondir vers le
     * bas laisserait nourrir une partie des habitants pour rien. La conversion
     * n'a lieu qu'ici — c'est ce qui permet de ne jamais manipuler de 0,5.
     */
    public static function vivresPourDemiRations(int $demiRations): int
    {
        return intdiv($demiRations + 1, 2);
    }
}
