<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Game\EmplacementsDeLaVille;
use App\Game\GesteDeLaVille;
use App\Game\MomentDeLaJournee;
use App\Game\ReseauDeRues;
use App\Game\RoleDeFigure;
use App\Game\TypeDeBatiment;

/**
 * Le film de la quinzaine passée : pour chaque silhouette, les points par où elle
 * passe, dans le temps (`docs/plan-pnj-ville-vivante.md` §§ 5, 8, 9, lot V3).
 *
 * **Le serveur décide de tout, le navigateur joue.** La chorégraphie (`Choregraphie`)
 * dit où est chacun à chaque moment ; ce service y ajoute **les chemins** — de la porte
 * d'un lot à celle d'un autre, par les rues (`ReseauDeRues`) — et en fait une liste de
 * points datés. Le contrôleur Stimulus n'en fait que les interpoler : il ne calcule ni
 * lieu, ni geste, ni chemin.
 *
 * **Le temps se compte en moments.** Une quinzaine de 15 jours compte 60 moments (quatre
 * par jour) ; un point porte son instant `t` dans cette échelle. Entre deux moments où
 * l'on change de lieu, la silhouette **marche pendant les `MARCHE` premiers centièmes**
 * du moment, puis reste là : elle arrive vite, elle n'erre pas.
 *
 * **Un point** : `[t, gauche, haut, visible, couchée, tourné]`. Les positions sont celles
 * de l'angle supérieur gauche de la silhouette debout, **en pourcentages du visuel** —
 * comme les sprites. `tourné` dit vers où l'on regarde pendant le segment qui commence
 * là : 1, vers la gauche.
 *
 * **Chaque silhouette garde sa place dans un lot** pour toute la quinzaine : sans cela,
 * l'arrivée de l'une décalerait les autres. Les silhouettes d'un même lot se partagent
 * les huit places de l'éventail ; au-delà, elles se superposent (un lot n'en reçoit
 * pas davantage en pratique).
 *
 * Le résultat n'est jamais stocké : il se recalcule, identique, à partir de la trace.
 *
 * @phpstan-type Point array{float, float, float, int, int, int}
 * @phpstan-type FigureDuFilm array{
 *     i: int,
 *     cle: string,
 *     libelle: string,
 *     role: string,
 *     symbole: string,
 *     points: list<Point>,
 * }
 * @phpstan-type Bulle array{i: int, x: float, y: float}
 * @phpstan-type ParoleDuFilm array{t: float, nature: string, texteA: string, texteB: string, a: Bulle, b: Bulle}
 * @phpstan-type Film array{
 *     jours: int,
 *     moments: int,
 *     marche: float,
 *     largeur: float,
 *     libellesDesMoments: list<string>,
 *     figures: list<FigureDuFilm>,
 *     paroles: list<ParoleDuFilm>,
 * }
 */
final readonly class FilmDeLaVille
{
    /**
     * La part d'un moment passée à marcher quand on change de lieu. **Valeur
     * inventée** : assez pour qu'on voie un trajet, pas assez pour qu'il dure
     * plus qu'un moment.
     */
    public const float MARCHE = 0.4;

    public function __construct(
        private GensDeLaVille $gens,
    ) {
    }

    /**
     * Le film de la quinzaine passée, ou null si la trace n'en garde aucune.
     *
     * @return Film|null
     */
    public function pour(GameSave $partie): ?array
    {
        $reconstitution = $this->gens->reconstitution($partie);

        if (null === $reconstitution) {
            return null;
        }

        /** @var array<string, array<int, array<int, array{lieu: ?string, geste: GesteDeLaVille, fait: mixed, couchee: bool}>>> $pas */
        $pas = $reconstitution['film']['pas'];
        /** @var list<array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool}> $candidats */
        $candidats = $reconstitution['photo']['candidats'];
        /** @var list<array{jour: int, moment: int, lieu: string, a: int, b: int, situation: string, nature: string, libelleA: string, libelleB: string, texteA: string, texteB: string}> $paroles */
        $paroles = $reconstitution['film']['paroles'];

        return self::construire(
            $candidats,
            $pas,
            (int) $reconstitution['film']['jours'],
            $this->gens->lotsDresses($partie),
            (int) $partie->getId(),
            $paroles,
        );
    }

    /**
     * @param list<array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool}>                                                                                    $candidats
     * @param array<string, array<int, array<int, array{lieu: ?string, geste: GesteDeLaVille, fait: mixed, couchee: bool}>>>                                                           $pas
     * @param array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}>                                                                                              $lots
     * @param list<array{jour: int, moment: int, lieu: string, a: int, b: int, situation: string, nature: string, libelleA: string, libelleB: string, texteA: string, texteB: string}> $paroles
     *
     * @return Film
     */
    public static function construire(array $candidats, array $pas, int $jours, array $lots, int $graine, array $paroles = []): array
    {
        $moments = $jours * 4;
        $occupation = [];
        $figures = [];

        foreach (\array_slice($candidats, 0, GensDeLaVille::PLAFOND_DE_FIGURES) as $i => $candidat) {
            $cle = $candidat['cle'];
            $hachage = crc32($graine.'|'.$cle.'|'.$i);

            // Où est la silhouette à chaque moment : un lot, ou nulle part.
            $etats = [];

            for ($s = 0; $s < $moments; ++$s) {
                $pasDuMoment = $pas[$cle][intdiv($s, 4) + 1][$s % 4] ?? null;
                $etats[$s] = ['lieu' => $pasDuMoment['lieu'] ?? null, 'couchee' => $pasDuMoment['couchee'] ?? false];
            }

            // Une place par lot visité, gardée toute la quinzaine.
            $places = [];

            foreach ($etats as $etat) {
                if (null === $etat['lieu']) {
                    continue;
                }

                $lot = $lots[$etat['lieu']] ?? $lots[TypeDeBatiment::ResidenceFamiliale->value];
                $cleDuLot = $lot['x'].'-'.$lot['y'];

                if (!isset($places[$cleDuLot])) {
                    $rang = $occupation[$cleDuLot] ?? 0;
                    $occupation[$cleDuLot] = $rang + 1;
                    $places[$cleDuLot] = self::position($lot, min($rang, \count(GensDeLaVille::ANCRAGES) - 1), $hachage);
                }
            }

            $figures[] = [
                'i' => $i,
                'cle' => $cle,
                'libelle' => $candidat['libelle'],
                'role' => $candidat['role'],
                'symbole' => RoleDeFigure::from($candidat['role'])->symbole(),
                'points' => self::points($etats, $lots, $places, 1 === ($hachage & 1) ? 1 : 0),
            ];
        }

        return [
            'jours' => $jours,
            'moments' => $moments,
            'marche' => self::MARCHE,
            'largeur' => round(GensDeLaVille::LARGEUR_DEBOUT / EmplacementsDeLaVille::LARGEUR * 100, 3),
            'libellesDesMoments' => array_map(static fn (MomentDeLaJournee $m): string => $m->libelle(), MomentDeLaJournee::cases()),
            'figures' => $figures,
            'paroles' => self::bulles($figures, $paroles),
        ];
    }

    /**
     * Les conversations, avec **où se tient chacun** quand il parle : à l'instant où l'on
     * a fini de marcher, sa dernière position connue. La bulle se pose au-dessus.
     *
     * @param list<FigureDuFilm>                                                                                                                                                       $figures
     * @param list<array{jour: int, moment: int, lieu: string, a: int, b: int, situation: string, nature: string, libelleA: string, libelleB: string, texteA: string, texteB: string}> $paroles
     *
     * @return list<ParoleDuFilm>
     */
    private static function bulles(array $figures, array $paroles): array
    {
        $parIndice = [];

        foreach ($figures as $figure) {
            $parIndice[$figure['i']] = $figure['points'];
        }

        $bulles = [];

        foreach ($paroles as $parole) {
            if (!isset($parIndice[$parole['a']], $parIndice[$parole['b']])) {
                continue;
            }

            $debut = (float) (($parole['jour'] - 1) * 4 + $parole['moment']);
            $instant = $debut + self::MARCHE + 0.05;
            $bulles[] = [
                't' => $debut,
                'nature' => $parole['nature'],
                'texteA' => $parole['texteA'],
                'texteB' => $parole['texteB'],
                'a' => ['i' => $parole['a']] + self::positionAu($parIndice[$parole['a']], $instant),
                'b' => ['i' => $parole['b']] + self::positionAu($parIndice[$parole['b']], $instant),
            ];
        }

        return $bulles;
    }

    /**
     * Où est une silhouette à un instant : le dernier de ses points qui n'est pas dans le futur.
     *
     * @param list<Point> $points
     *
     * @return array{x: float, y: float}
     */
    private static function positionAu(array $points, float $instant): array
    {
        $dernier = $points[0];

        foreach ($points as $point) {
            if ($point[0] > $instant) {
                break;
            }

            $dernier = $point;
        }

        return ['x' => $dernier[1], 'y' => $dernier[2]];
    }

    /**
     * Les points d'une silhouette : l'instant où elle part, les rues qu'elle suit,
     * l'instant où elle arrive ; l'instant où elle apparaît ou disparaît ; celui où
     * elle se couche ou se relève.
     *
     * @param array<int, array{lieu: ?string, couchee: bool}>                             $etats
     * @param array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}> $lots
     * @param array<string, array{float, float, float, float}>                            $places [gauche, haut, piedsX, piedsY] par lot
     *
     * @return list<Point>
     */
    private static function points(array $etats, array $lots, array $places, int $tourneAuDepart): array
    {
        $points = [];
        $courant = null;
        $tourne = $tourneAuDepart;
        $dernier = \count($etats);

        foreach ($etats as $s => $etat) {
            $cleDuLot = null === $etat['lieu'] ? null : self::cleDuLot($lots, $etat['lieu']);
            $place = null === $cleDuLot ? null : $places[$cleDuLot];
            $visible = null !== $place;
            $couchee = $etat['couchee'] && $visible ? 1 : 0;

            if (null === $courant) {
                $courant = ['lot' => $cleDuLot, 'gauche' => $place[0] ?? 0.0, 'haut' => $place[1] ?? 0.0, 'visible' => $visible, 'couchee' => $couchee];
                $points[] = [0.0, $courant['gauche'], $courant['haut'], (int) $visible, $couchee, $tourne];

                continue;
            }

            if ($visible && $courant['visible'] && $cleDuLot !== $courant['lot']) {
                // On marche d'un lot à l'autre, par les rues, pendant le début du moment.
                $trajet = self::trajet($lots, $courant, $place, $etat['lieu']);
                $depart = (float) $s;
                $longueur = 0.0;

                for ($k = 1; $k < \count($trajet); ++$k) {
                    $longueur += hypot($trajet[$k][0] - $trajet[$k - 1][0], $trajet[$k][1] - $trajet[$k - 1][1]);
                }

                $parcouru = 0.0;

                for ($k = 0; $k < \count($trajet); ++$k) {
                    if ($k > 0) {
                        $parcouru += hypot($trajet[$k][0] - $trajet[$k - 1][0], $trajet[$k][1] - $trajet[$k - 1][1]);
                    }

                    if ($k < \count($trajet) - 1) {
                        $dx = $trajet[$k + 1][0] - $trajet[$k][0];
                        $tourne = abs($dx) < 0.05 ? $tourne : ($dx < 0 ? 1 : 0);
                    }

                    $points[] = [
                        round($depart + self::MARCHE * ($longueur > 0 ? $parcouru / $longueur : 1.0), 3),
                        round($trajet[$k][0], 2),
                        round($trajet[$k][1], 2),
                        1,
                        $courant['couchee'],
                        $tourne,
                    ];
                }

                $courant = ['lot' => $cleDuLot, 'gauche' => $place[0], 'haut' => $place[1], 'visible' => true, 'couchee' => $couchee];

                if ($couchee !== $points[array_key_last($points)][4]) {
                    $points[] = [(float) $s + self::MARCHE, $place[0], $place[1], 1, $couchee, $tourne];
                }

                continue;
            }

            if ($visible !== $courant['visible'] || $couchee !== $courant['couchee']) {
                // Elle apparaît, disparaît, se couche ou se relève : un changement net.
                $gauche = $visible ? $place[0] : $courant['gauche'];
                $haut = $visible ? $place[1] : $courant['haut'];
                $points[] = [(float) $s, round($gauche, 2), round($haut, 2), (int) $visible, $couchee, $tourne];
                $courant = ['lot' => $cleDuLot, 'gauche' => $gauche, 'haut' => $haut, 'visible' => $visible, 'couchee' => $couchee];
            }
        }

        $points[] = [(float) $dernier, round($courant['gauche'] ?? 0.0, 2), round($courant['haut'] ?? 0.0, 2), (int) ($courant['visible'] ?? false), $courant['couchee'] ?? 0, $tourne];

        return $points;
    }

    /**
     * Le trajet entre la place qu'on quitte et celle qu'on rejoint : de la place à la
     * porte de son lot, par les rues jusqu'à la porte de l'autre, puis jusqu'à la
     * place. En pourcentages du visuel, angle supérieur gauche de la silhouette.
     *
     * @param array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}>  $lots
     * @param array{lot: ?string, gauche: float, haut: float, visible: bool, couchee: int} $courant
     * @param array{float, float, float, float}                                            $arrivee
     *
     * @return list<array{float, float}>
     */
    private static function trajet(array $lots, array $courant, array $arrivee, string $lieu): array
    {
        $lotDepart = null === $courant['lot'] ? null : self::lotParCle($lots, $courant['lot']);
        $lotArrivee = $lots[$lieu] ?? $lots[TypeDeBatiment::ResidenceFamiliale->value];
        $rues = null === $lotDepart ? [] : ReseauDeRues::chemin(ReseauDeRues::porteDuLot($lotDepart), ReseauDeRues::porteDuLot($lotArrivee));
        $versPieds = static fn (array $noeud): array => [
            ($noeud[0] - GensDeLaVille::LARGEUR_DEBOUT / 2) / EmplacementsDeLaVille::LARGEUR * 100,
            ($noeud[1] - GensDeLaVille::HAUTEUR_DEBOUT) / EmplacementsDeLaVille::HAUTEUR * 100,
        ];

        $trajet = [[$courant['gauche'], $courant['haut']]];

        foreach ($rues as $noeud) {
            $trajet[] = $versPieds($noeud);
        }

        $trajet[] = [$arrivee[0], $arrivee[1]];

        return $trajet;
    }

    /**
     * @param array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}> $lots
     */
    private static function cleDuLot(array $lots, string $lieu): string
    {
        $lot = $lots[$lieu] ?? $lots[TypeDeBatiment::ResidenceFamiliale->value];

        return $lot['x'].'-'.$lot['y'];
    }

    /**
     * @param array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}> $lots
     *
     * @return array{x: int, y: int, classe: string, type: ?TypeDeBatiment}|null
     */
    private static function lotParCle(array $lots, string $cleDuLot): ?array
    {
        foreach ($lots as $lot) {
            if ($lot['x'].'-'.$lot['y'] === $cleDuLot) {
                return $lot;
            }
        }

        return null;
    }

    /**
     * La place d'une silhouette dans un lot : ses pieds, puis l'angle supérieur
     * gauche de la silhouette debout, en pourcentages du visuel.
     *
     * @param array{x: int, y: int, classe: string, type: ?TypeDeBatiment} $lot
     *
     * @return array{float, float, float, float}
     */
    private static function position(array $lot, int $place, int $hachage): array
    {
        $largeurDuLot = EmplacementsDeLaVille::LARGEUR_DES_LOTS[$lot['classe']];
        [$dx, $dy] = GensDeLaVille::ANCRAGES[$place];
        $piedsX = $lot['x'] + $dx * $largeurDuLot + (($hachage >> 1) % 7 - 3);
        $piedsY = $lot['y'] + $dy * $largeurDuLot;
        $gauche = ($piedsX - GensDeLaVille::LARGEUR_DEBOUT / 2) / EmplacementsDeLaVille::LARGEUR * 100;
        $haut = ($piedsY - GensDeLaVille::HAUTEUR_DEBOUT) / EmplacementsDeLaVille::HAUTEUR * 100;

        return [
            round(max(0.0, min(100 - GensDeLaVille::LARGEUR_DEBOUT / EmplacementsDeLaVille::LARGEUR * 100, $gauche)), 2),
            round(max(0.0, min(100 - GensDeLaVille::HAUTEUR_DEBOUT / EmplacementsDeLaVille::HAUTEUR * 100, $haut)), 2),
            $piedsX,
            $piedsY,
        ];
    }
}
