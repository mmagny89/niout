<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Entity\GameSave;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\TypeDeMission;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MissionCatalogue::class)]
#[CoversClass(Mission::class)]
final class MissionCatalogueTest extends TestCase
{
    public function testLaCampagneCompteDixMissions(): void
    {
        self::assertCount(GameSave::DERNIERE_MISSION, (new MissionCatalogue())->toutes());
    }

    public function testLaPremiereMissionEstAvarisSousAhmosis(): void
    {
        $mission = (new MissionCatalogue())->get(GameSave::PREMIERE_MISSION);

        self::assertSame('Avaris', $mission->ville);
        self::assertSame('Ahmôsis Ier', $mission->pharaon);
        self::assertSame(0, $mission->difficulte, 'La première mission est la région d\'apprentissage.');
    }

    public function testLaDifficulteCroitStrictementAvecLOrdreDesMissions(): void
    {
        $precedente = -1;

        foreach ((new MissionCatalogue())->toutes() as $mission) {
            self::assertGreaterThan(
                $precedente,
                $mission->difficulte,
                \sprintf('La mission %d doit être plus difficile que la précédente.', $mission->numero),
            );
            $precedente = $mission->difficulte;
        }
    }

    public function testLaGrilleSAgranditParPaliersDeDeuxNiveaux(): void
    {
        $catalogue = new MissionCatalogue();

        // taille = 3 + partieEntiere(difficulté / 2), doc 11.
        self::assertSame(3, $catalogue->get(1)->tailleDeGrille());
        self::assertSame(3, $catalogue->get(2)->tailleDeGrille());
        self::assertSame(4, $catalogue->get(3)->tailleDeGrille());
        self::assertSame(7, $catalogue->get(10)->tailleDeGrille());
    }

    public function testChaqueMissionCiteUnPharaonEtUnContexte(): void
    {
        foreach ((new MissionCatalogue())->toutes() as $mission) {
            self::assertNotSame('', $mission->pharaon);
            self::assertNotSame('', $mission->contexte);
        }
    }

    /**
     * **Le camp minier n'est pas un développement.** Le doc 09 se contredit —
     * sa section n'annonce que trois types, son tableau des missions en donne
     * un quatrième à l'Ouadi Hammamat. Le tableau, plus précis, l'emporte
     * (décision de la joueuse) : on y vient prendre ce que le sol donne, et
     * repartir.
     */
    public function testLaMissionDuOuadiHammamatEstUneExploitation(): void
    {
        $mission = (new MissionCatalogue())->get(9);

        self::assertSame(TypeDeMission::Exploiter, $mission->type);
        self::assertSame('Ouadi Hammamat', $mission->ville);
    }

    /**
     * Le type **nomme** ce qu'on vient faire, il ne change aucune règle : les
     * quatre servent, et aucune mission n'en porte un que le catalogue
     * ignorerait.
     */
    public function testChaqueTypeDeMissionSertAuMoinsUneFois(): void
    {
        $types = array_map(
            static fn (Mission $m): TypeDeMission => $m->type,
            (new MissionCatalogue())->toutes(),
        );

        foreach (TypeDeMission::cases() as $type) {
            self::assertContains($type, $types, \sprintf('Aucune mission n\'est de type « %s ».', $type->libelle()));
        }
    }

    public function testUnNumeroInconnuEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new MissionCatalogue())->get(11);
    }
}
