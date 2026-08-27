<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeUnverifiedUsersCommandTest extends KernelTestCase
{
    public function testUnCompteNonVerifieHorsDelaiEstSupprime(): void
    {
        self::bootKernel();
        $this->creerUtilisateur('perime@example.com', verifie: false, ancienneteEnJours: User::DELAI_VERIFICATION_JOURS + 1);

        $this->lancerPurge();

        self::assertNull($this->depotUtilisateurs()->findOneBy(['email' => 'perime@example.com']));
    }

    public function testUnCompteNonVerifieDansLeDelaiEstConserve(): void
    {
        self::bootKernel();
        $this->creerUtilisateur('recent@example.com', verifie: false, ancienneteEnJours: 1);

        $this->lancerPurge();

        self::assertNotNull($this->depotUtilisateurs()->findOneBy(['email' => 'recent@example.com']));
    }

    public function testUnCompteVerifieNEstJamaisSupprime(): void
    {
        self::bootKernel();
        $this->creerUtilisateur('fidele@example.com', verifie: true, ancienneteEnJours: 365);

        $this->lancerPurge();

        self::assertNotNull($this->depotUtilisateurs()->findOneBy(['email' => 'fidele@example.com']));
    }

    public function testLeModeSimulationNeSupprimeRien(): void
    {
        self::bootKernel();
        $this->creerUtilisateur('simulation@example.com', verifie: false, ancienneteEnJours: User::DELAI_VERIFICATION_JOURS + 1);

        $testeur = $this->lancerPurge(['--dry-run' => true]);

        self::assertNotNull($this->depotUtilisateurs()->findOneBy(['email' => 'simulation@example.com']));
        self::assertStringContainsString('Aucune suppression effectuée', $testeur->getDisplay());
    }

    /**
     * @param array<string, bool|string> $options
     */
    private function lancerPurge(array $options = []): CommandTester
    {
        $application = new Application(self::bootKernel());
        $testeur = new CommandTester($application->find('app:users:purge-unverified'));
        $testeur->execute($options);
        $testeur->assertCommandIsSuccessful();

        return $testeur;
    }

    private function creerUtilisateur(string $email, bool $verifie, int $ancienneteEnJours): void
    {
        $conteneur = static::getContainer();
        $gestionnaire = $conteneur->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $user->setVerified($verifie);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        // createdAt est fixée au constructeur : on la recule en base pour
        // simuler l'ancienneté sans exposer de setter qui n'a pas lieu d'être.
        $gestionnaire->getConnection()->executeStatement(
            'UPDATE "user" SET created_at = :date WHERE email = :email',
            [
                'date' => (new \DateTimeImmutable())->modify(\sprintf('-%d days', $ancienneteEnJours))->format('Y-m-d H:i:s'),
                'email' => $email,
            ],
        );
        $gestionnaire->clear();
    }

    private function depotUtilisateurs(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
