<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\EmplacementsDeLaVille;
use App\Game\ReseauDeRues;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Les rues de la ville vue d'en haut : un réseau écrit à la main sur le visuel, dont
 * on garde ici ce qu'un test peut voir. Qu'une rue ne traverse pas un bâtiment, lui,
 * se regarde sur la page de contrôle de développement.
 */
#[CoversClass(ReseauDeRues::class)]
final class ReseauDeRuesTest extends TestCase
{
    public function testChaqueCarrefourEstDansLeVisuel(): void
    {
        foreach (ReseauDeRues::NOEUDS as $id => [$x, $y]) {
            self::assertGreaterThanOrEqual(0, $x, $id);
            self::assertLessThanOrEqual(EmplacementsDeLaVille::LARGEUR, $x, $id);
            self::assertGreaterThanOrEqual(0, $y, $id);
            self::assertLessThanOrEqual(EmplacementsDeLaVille::HAUTEUR, $y, $id);
        }
    }

    /**
     * Le réseau est d'un seul tenant : on peut aller de n'importe quel carrefour à
     * n'importe quel autre, et le chemin commence et finit où on le demande.
     */
    public function testOnVaDeNImporteQuelCarrefourANImporteLequel(): void
    {
        foreach (ReseauDeRues::NOEUDS as $de => $depart) {
            foreach (ReseauDeRues::NOEUDS as $vers => $arrivee) {
                $chemin = ReseauDeRues::chemin($de, $vers);

                self::assertNotSame([], $chemin, \sprintf('%s → %s', $de, $vers));
                self::assertSame($depart, $chemin[0]);
                self::assertSame($arrivee, $chemin[array_key_last($chemin)]);
            }
        }
    }

    /**
     * Un chemin est aussi long dans un sens que dans l'autre, et jamais plus court
     * que la ligne droite.
     */
    public function testLeCheminEstSymetriqueEtJamaisPlusCourtQueLaLigneDroite(): void
    {
        $longueur = static function (array $chemin): float {
            $total = 0.0;

            for ($i = 1; $i < \count($chemin); ++$i) {
                $total += hypot($chemin[$i][0] - $chemin[$i - 1][0], $chemin[$i][1] - $chemin[$i - 1][1]);
            }

            return $total;
        };

        $aller = ReseauDeRues::chemin('r2c0', 'r0c3');
        $retour = ReseauDeRues::chemin('r0c3', 'r2c0');

        self::assertEqualsWithDelta($longueur($aller), $longueur($retour), 0.001);
        self::assertGreaterThanOrEqual(
            hypot(ReseauDeRues::NOEUDS['r2c0'][0] - ReseauDeRues::NOEUDS['r0c3'][0], ReseauDeRues::NOEUDS['r2c0'][1] - ReseauDeRues::NOEUDS['r0c3'][1]),
            $longueur($aller),
        );
        self::assertSame([ReseauDeRues::NOEUDS['r1c1']], ReseauDeRues::chemin('r1c1', 'r1c1'), 'Aller nulle part, c\'est y être.');
    }

    /**
     * Deux carrefours voisins se rejoignent d'un trait : les rues suivent le
     * treillis, elles ne font pas de détour.
     */
    public function testDeuxCarrefoursVoisinsSeRejoignentDUnTrait(): void
    {
        foreach (ReseauDeRues::aretes() as [$a, $b]) {
            self::assertCount(2, ReseauDeRues::chemin($a, $b), \sprintf('%s — %s', $a, $b));
        }
    }

    /**
     * Chaque lot a une porte, et elle est près de lui : on ne sort pas par l'autre
     * bout de la ville.
     */
    public function testChaqueLotAUnePorteProche(): void
    {
        foreach (EmplacementsDeLaVille::tous() as $lot) {
            $porte = ReseauDeRues::porteDuLot($lot);

            self::assertArrayHasKey($porte, ReseauDeRues::NOEUDS);
            self::assertLessThan(
                260,
                hypot(ReseauDeRues::NOEUDS[$porte][0] - $lot['x'], ReseauDeRues::NOEUDS[$porte][1] - $lot['y']),
                \sprintf('La porte du lot %d,%d est trop loin.', $lot['x'], $lot['y']),
            );
        }
    }

    public function testUnCarrefourInconnuNeDonneAucunChemin(): void
    {
        self::assertSame([], ReseauDeRues::chemin('r0c0', 'nulle-part'));
        self::assertSame([], ReseauDeRues::chemin('nulle-part', 'r0c0'));
    }
}
