<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\SteleHistorique;
use PHPUnit\Framework\TestCase;

/**
 * Les stèles réelles du doc 09 : chaque pharaon commanditaire en a laissé une,
 * et le déchiffrage y fait écho plutôt que d'inventer de toutes pièces.
 */
final class StelesHistoriquesTest extends TestCase
{
    /**
     * **Aucune mission ne s'ouvre sans sa stèle.** C'est l'ancrage pédagogique
     * que le doc 09 demande : une pierre qu'on peut aller voir, pas une
     * invention.
     */
    public function testChaqueMissionADroitASaStele(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            self::assertNotNull(
                SteleHistorique::pourLePharaon($mission->pharaon),
                \sprintf('La mission %d (%s) n\'a pas de stèle.', $mission->numero, $mission->ville),
            );
        }

        self::assertNull(SteleHistorique::pourLePharaon('Un pharaon qui n\'existe pas'));
    }

    /**
     * Chaque entrée est complète : sans son lieu, la stèle cesse d'être une
     * pierre qu'on peut aller voir, et le doc 09 y tient.
     */
    public function testChaqueSteleDitSonNomSonLieuEtSonContenu(): void
    {
        foreach (SteleHistorique::cases() as $stele) {
            self::assertNotSame('', $stele->nom());
            self::assertNotSame('', $stele->lieu());
            self::assertNotSame('', $stele->contenu());
        }
    }

    /**
     * **Un papyrus n'est pas une stèle**, et l'écran ne doit pas le dire : le
     * doc 09 le signale lui-même pour le grand papyrus Harris.
     */
    public function testLePapyrusHarrisNEstPasAppeleUneStele(): void
    {
        self::assertFalse(SteleHistorique::PapyrusHarris->estUneStele());
        self::assertSame('papyrus', SteleHistorique::PapyrusHarris->nature());

        foreach (SteleHistorique::cases() as $stele) {
            if (SteleHistorique::PapyrusHarris !== $stele) {
                self::assertSame('stèle', $stele->nature(), $stele->nom());
            }
        }
    }

    /**
     * **Un résumé, jamais une citation** — la contrainte est de droits autant
     * que d'honnêteté, et le doc 09 la répète deux fois. Un texte entre
     * guillemets serait le signe qu'on a cité plutôt que résumé.
     */
    public function testAucuneSteleNeCiteSonTexte(): void
    {
        foreach (SteleHistorique::cases() as $stele) {
            self::assertStringNotContainsString('«', $stele->contenu(), $stele->nom());
            self::assertStringNotContainsString('"', $stele->contenu(), $stele->nom());
        }
    }

    /**
     * Deux missions partagent Ramsès IV, et donc sa stèle : c'est la même
     * expédition de l'an 3 qui les commandite toutes deux.
     */
    public function testLesDeuxMissionsDeRamsesQuatrePartagentSaStele(): void
    {
        $missions = array_values(array_filter(
            (new MissionCatalogue())->toutes(),
            static fn (Mission $m): bool => 'Ramsès IV' === $m->pharaon,
        ));

        self::assertCount(2, $missions);
        self::assertSame(
            SteleHistorique::pourLePharaon($missions[0]->pharaon),
            SteleHistorique::pourLePharaon($missions[1]->pharaon),
        );
    }
}
