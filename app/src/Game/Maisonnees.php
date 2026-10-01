<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * La ville rangée en maisonnées, pour qu'on la voie au lieu de la lire.
 *
 * **Une représentation, pas un état.** Le jeu ne tient que trois nombres —
 * actifs, enfants, anciens (`Population`) —, et nulle part qui habite avec qui.
 * Ce service en tire une répartition **déterministe** : la même ville donne
 * toujours les mêmes maisons, sans rien persister ni rien tirer au sort, ce qui
 * éviterait qu'un rechargement de page recompose le quartier sous les yeux du
 * joueur.
 *
 * Elle respecte les deux invariants du jeu : il y a autant de maisons occupées
 * que `Population::foyersPour()`, et aucune ne dépasse
 * `Population::PERSONNES_PAR_FOYER`. Distribuer chaque âge à tour de rôle
 * plutôt que de remplir une maison après l'autre est ce qui les garantit — les
 * tailles ne diffèrent jamais de plus d'une personne, et les âges se mêlent
 * comme dans une vraie rue.
 *
 * Les malades sont des actifs (`City::malades()` en retranche l'effectif
 * valide) : on les marque parmi eux.
 */
final readonly class Maisonnees
{
    public const string ACTIF = 'actif';
    public const string MALADE = 'malade';
    public const string ENFANT = 'enfant';
    public const string ANCIEN = 'ancien';

    /**
     * @return list<list<string>> une entrée par maison occupée, chacune la liste de ses habitants
     */
    public static function repartir(City $ville): array
    {
        $maisons = Population::foyersPour($ville->population());

        if (0 === $maisons) {
            return [];
        }

        $malades = min($ville->malades(), $ville->getActifs());

        // Dans cet ordre : les adultes d'abord, que l'on veut dans chaque
        // maison, puis ceux qui sont à charge.
        $habitants = [
            ...array_fill(0, $ville->getActifs() - $malades, self::ACTIF),
            ...array_fill(0, $malades, self::MALADE),
            ...array_fill(0, $ville->getEnfants(), self::ENFANT),
            ...array_fill(0, $ville->getAnciens(), self::ANCIEN),
        ];

        $repartition = array_fill(0, $maisons, []);

        // Chaque âge à tour de rôle, en continuant là où le précédent s'est
        // arrêté : c'est ce qui équilibre les tailles.
        foreach ($habitants as $rang => $habitant) {
            $repartition[$rang % $maisons][] = $habitant;
        }

        return $repartition;
    }

    /**
     * Ce qu'une maison abrite, en toutes lettres, pour un lecteur d'écran.
     *
     * @param list<string> $habitants
     */
    public static function decrire(array $habitants): string
    {
        $comptes = array_count_values($habitants);
        $libelles = [
            self::ACTIF => ['adulte qui travaille', 'adultes qui travaillent'],
            self::MALADE => ['adulte alité par la fièvre', 'adultes alités par la fièvre'],
            self::ENFANT => ['enfant', 'enfants'],
            self::ANCIEN => ['ancien', 'anciens'],
        ];

        $morceaux = [];

        foreach ($libelles as $genre => [$singulier, $pluriel]) {
            $nombre = $comptes[$genre] ?? 0;

            if ($nombre > 0) {
                $morceaux[] = \sprintf('%d %s', $nombre, 1 === $nombre ? $singulier : $pluriel);
            }
        }

        return implode(', ', $morceaux);
    }
}
