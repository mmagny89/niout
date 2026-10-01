<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\PalierDErudition;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PalierDErudition::class)]
final class PalierDEruditionTest extends TestCase
{
    #[DataProvider('signesEtDegres')]
    public function testLeDegreSuitLeNombreDeSignes(int $signes, PalierDErudition $attendu): void
    {
        self::assertSame($attendu, PalierDErudition::pourUnNombreDeSignes($signes));
    }

    /**
     * @return iterable<string, array{int, PalierDErudition}>
     */
    public static function signesEtDegres(): iterable
    {
        yield 'les huit signes d\'emblée' => [8, PalierDErudition::Neophyte];
        yield 'juste avant le premier degré' => [13, PalierDErudition::Neophyte];
        yield 'le premier degré' => [14, PalierDErudition::Lettre];
        yield 'le second' => [26, PalierDErudition::Confirme];
        yield 'le sommet' => [40, PalierDErudition::Maitre];
        yield 'tout appris' => [PalierDErudition::signesEnTout(), PalierDErudition::Maitre];
    }

    public function testLeBonusMonteAvecLeDegre(): void
    {
        $bonus = array_map(static fn (PalierDErudition $p): int => $p->bonusDImpot(), PalierDErudition::cases());

        self::assertSame([0, 10, 20, 30], $bonus);
    }

    public function testLeSommetEstAtteignableEtPasUneIllusion(): void
    {
        self::assertGreaterThanOrEqual(
            PalierDErudition::Maitre->seuilDEntree(),
            PalierDErudition::signesEnTout(),
            'Le dernier degré doit pouvoir s\'atteindre en apprenant tout ce que le jeu enseigne.',
        );
    }

    public function testChaqueDegreDitCeQuIlRapporte(): void
    {
        foreach (PalierDErudition::cases() as $degre) {
            self::assertNotSame('', $degre->description());
            self::assertNotSame('', $degre->libelle());
        }

        self::assertNull(PalierDErudition::Maitre->suivant());
        self::assertSame(PalierDErudition::Lettre, PalierDErudition::Neophyte->suivant());
    }
}
