<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\GameMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GameSave::class)]
final class GameSaveTest extends TestCase
{
    public function testUneCampagneDemarreALaPremiereMission(): void
    {
        $partie = $this->creerPartie(GameMode::Campagne);

        self::assertSame(GameSave::PREMIERE_MISSION, $partie->getMission());
    }

    public function testUneAventureNaPasDeMission(): void
    {
        $partie = $this->creerPartie(GameMode::Aventure);

        self::assertNull($partie->getMission(), 'Le mode Aventure suit des règnes, pas des missions.');
    }

    public function testUnePartieDemarreAuPremierCycle(): void
    {
        $partie = $this->creerPartie(GameMode::Campagne);

        self::assertSame(1, $partie->getCycle());
    }

    public function testUneAventureNEstJamaisALaDerniereMission(): void
    {
        $partie = $this->creerPartie(GameMode::Aventure);

        self::assertFalse(
            $partie->estALaDerniereMission(),
            'Le mode Aventure n\'a pas de fin scriptée.',
        );
    }

    public function testLaRepriseMetAJourLaDateDOuverture(): void
    {
        $partie = $this->creerPartie(GameMode::Campagne);
        $avant = $partie->getLastOpenedAt();

        $partie->marquerOuverte();

        self::assertGreaterThanOrEqual($avant, $partie->getLastOpenedAt());
    }

    private function creerPartie(GameMode $mode): GameSave
    {
        $joueur = new User();
        $joueur->setEmail('joueur@example.com');
        $famille = new Family(Family::NOM_PAR_DEFAUT);
        $ville = new City('Avaris', 0);

        return GameMode::Campagne === $mode
            ? GameSave::pourCampagne($joueur, $famille, $ville)
            : GameSave::pourAventure($joueur, $famille, $ville);
    }
}
