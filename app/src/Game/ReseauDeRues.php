<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les rues de la ville vue d'en haut : où l'on peut marcher entre deux bâtiments
 * (`docs/plan-pnj-ville-vivante.md` § 5, lot V3).
 *
 * **Une donnée qui manquait** : les chemins sont dans l'image, pas dans le jeu.
 * Les deux visuels (`ville`, `ville_port`) portent le même plan — des clairières de
 * terre nue séparées par des rues qui suivent les deux axes de l'isométrie —, donc
 * **un seul réseau** : douze carrefours, écrits ici à la main, dans le même repère
 * que `EmplacementsDeLaVille` (pixels du visuel de 1376 × 768).
 *
 * **Comment il a été posé.** Les rues de l'image forment un treillis régulier : deux
 * familles de droites, de pente +½ et −½, espacées d'environ 181 et 183 pixels
 * (`y = x/2 + c`, `y = −x/2 + d`). Les carrefours en sont les croisements ; on ne
 * garde que ceux qui sont à terre. La page de contrôle de développement
 * (`ReseauDeRuesController`) dessine ce réseau **par-dessus** le visuel, pour qu'on
 * voie une rue qui traverse un bâtiment — un test ne le verrait pas.
 *
 * **Chaque lot a une porte** : le carrefour le plus proche de son devant. Une
 * silhouette sort de son lot, rejoint la porte, suit les rues jusqu'à la porte de
 * l'autre lot, et y entre. **Le chemin se calcule ici, côté serveur** : le
 * navigateur reçoit des trajets, il n'en cherche pas.
 */
final class ReseauDeRues
{
    /**
     * Les carrefours, par identifiant `r<rang>c<colonne>` : la ligne du treillis
     * (de haut en bas) et sa colonne (de gauche à droite).
     *
     * @var array<string, array{int, int}>
     */
    public const array NOEUDS = [
        'r0c0' => [519, 142], 'r0c1' => [702, 234], 'r0c2' => [885, 326], 'r0c3' => [1068, 417],
        'r1c0' => [338, 233], 'r1c1' => [521, 324], 'r1c2' => [704, 416], 'r1c3' => [887, 507],
        'r2c0' => [157, 323], 'r2c1' => [340, 415], 'r2c2' => [523, 506], 'r2c3' => [706, 598],
    ];

    /**
     * Où le devant d'un lot se tient, en fraction de sa largeur : de là se mesure
     * la porte la plus proche. Le lot est un losange posé sur le sol ; son devant
     * est sa pointe basse.
     */
    private const float DEVANT_DU_LOT = 0.22;

    /**
     * Les rues : deux carrefours voisins sur une même ligne du treillis sont reliés.
     *
     * @return list<array{string, string}>
     */
    public static function aretes(): array
    {
        $aretes = [];

        foreach (range(0, 2) as $rang) {
            foreach (range(0, 3) as $colonne) {
                $ici = \sprintf('r%dc%d', $rang, $colonne);

                if ($colonne < 3) {
                    $aretes[] = [$ici, \sprintf('r%dc%d', $rang, $colonne + 1)];
                }

                if ($rang < 2) {
                    $aretes[] = [$ici, \sprintf('r%dc%d', $rang + 1, $colonne)];
                }
            }
        }

        return $aretes;
    }

    /**
     * La porte d'un lot : le carrefour le plus proche de son devant.
     *
     * @param array{x: int, y: int, classe: string} $lot
     */
    public static function porteDuLot(array $lot): string
    {
        $largeur = EmplacementsDeLaVille::LARGEUR_DES_LOTS[$lot['classe']];
        $devantX = $lot['x'];
        $devantY = $lot['y'] + self::DEVANT_DU_LOT * $largeur;
        $meilleure = 'r0c1';
        $distance = \INF;

        foreach (self::NOEUDS as $id => [$x, $y]) {
            $d = hypot($x - $devantX, $y - $devantY);

            if ($d < $distance) {
                $distance = $d;
                $meilleure = $id;
            }
        }

        return $meilleure;
    }

    /**
     * Le plus court chemin entre deux carrefours, extrémités comprises.
     *
     * @return list<array{int, int}> les points à suivre, dans l'ordre
     */
    public static function chemin(string $de, string $vers): array
    {
        if (!isset(self::NOEUDS[$de], self::NOEUDS[$vers])) {
            return [];
        }

        $voisins = [];

        foreach (self::aretes() as [$a, $b]) {
            $voisins[$a][] = $b;
            $voisins[$b][] = $a;
        }

        $distance = array_fill_keys(array_keys(self::NOEUDS), \INF);
        $avant = [];
        $distance[$de] = 0.0;
        $restants = array_keys(self::NOEUDS);

        while ([] !== $restants) {
            usort($restants, static fn (string $a, string $b): int => $distance[$a] <=> $distance[$b]);
            $courant = array_shift($restants);

            if ($courant === $vers || \INF === $distance[$courant]) {
                break;
            }

            foreach ($voisins[$courant] ?? [] as $voisin) {
                $alt = $distance[$courant] + hypot(
                    self::NOEUDS[$courant][0] - self::NOEUDS[$voisin][0],
                    self::NOEUDS[$courant][1] - self::NOEUDS[$voisin][1],
                );

                if ($alt < $distance[$voisin]) {
                    $distance[$voisin] = $alt;
                    $avant[$voisin] = $courant;
                }
            }
        }

        if (\INF === $distance[$vers]) {
            return [];
        }

        $chemin = [];

        for ($noeud = $vers;; $noeud = $avant[$noeud]) {
            array_unshift($chemin, self::NOEUDS[$noeud]);

            if ($noeud === $de) {
                break;
            }
        }

        return $chemin;
    }
}
