<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    public function testUnCompteEstNonVerifieALaCreation(): void
    {
        $user = new User();

        self::assertFalse($user->isVerified());
    }

    public function testUnCompteVerifieNEstJamaisPurgeable(): void
    {
        $user = new User();
        $user->setVerified(true);

        $bienApresLeDelai = $user->getCreatedAt()->modify('+1 year');

        self::assertFalse($user->isPurgeable($bienApresLeDelai));
    }

    public function testUnCompteNonVerifieNEstPasPurgeableAvantLeDelai(): void
    {
        $user = new User();

        $veilleDeLEcheance = $user->getCreatedAt()
            ->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS))
            ->modify('-1 second');

        self::assertFalse($user->isPurgeable($veilleDeLEcheance));
    }

    public function testUnCompteNonVerifieEstPurgeableUneFoisLeDelaiEcoule(): void
    {
        $user = new User();

        $echeance = $user->getCreatedAt()
            ->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS));

        self::assertTrue($user->isPurgeable($echeance));
    }

    public function testUnUtilisateurATaujoursLeRoleUser(): void
    {
        $user = new User();

        self::assertContains('ROLE_USER', $user->getRoles());
    }
}
