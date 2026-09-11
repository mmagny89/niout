<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * L'accès à l'administration ne s'accorde qu'ici : aucun écran ne le donne, et
 * c'est la seule barrière de l'écran qui supprime les comptes.
 */
final class AdministrateurCommandTest extends KernelTestCase
{
    public function testLaCommandeAccordeLAcces(): void
    {
        self::bootKernel();
        $this->creerCompte('promue@example.com');

        $this->lancer(['email' => 'promue@example.com']);

        self::assertTrue($this->recharger('promue@example.com')->estAdministratrice());
    }

    public function testLaCommandeRetireLAcces(): void
    {
        self::bootKernel();
        $this->creerCompte('destituee@example.com', [User::ROLE_ADMIN]);

        $this->lancer(['email' => 'destituee@example.com', '--retirer' => true]);

        self::assertFalse($this->recharger('destituee@example.com')->estAdministratrice());
    }

    /**
     * Les deux rôles sont indépendants : retirer l'un ne doit pas emporter
     * l'autre, la reconstruction de la liste étant le moment où c'est facile.
     */
    public function testRetirerLAccesLaisseLeModeDivinIntact(): void
    {
        self::bootKernel();
        $this->creerCompte('deesse@example.com', [User::ROLE_ADMIN, User::ROLE_DIVIN]);

        $this->lancer(['email' => 'deesse@example.com', '--retirer' => true]);

        $compte = $this->recharger('deesse@example.com');
        self::assertFalse($compte->estAdministratrice());
        self::assertTrue($compte->estDivinite());
    }

    public function testUneAdresseInconnueEchoue(): void
    {
        self::bootKernel();

        $testeur = $this->lancer(['email' => 'fantome@example.com'], reussite: false);

        self::assertSame(Command::FAILURE, $testeur->getStatusCode());
    }

    /**
     * @param array<string, bool|string> $entrees
     */
    private function lancer(array $entrees, bool $reussite = true): CommandTester
    {
        $application = new Application(self::bootKernel());
        $testeur = new CommandTester($application->find('app:users:admin'));
        $testeur->execute($entrees);

        if ($reussite) {
            $testeur->assertCommandIsSuccessful();
        }

        return $testeur;
    }

    /**
     * @param list<string> $roles
     */
    private function creerCompte(string $email, array $roles = []): void
    {
        $compte = new User();
        $compte->setEmail($email);
        $compte->setPassword('peu-importe-ici');
        $compte->setRoles($roles);

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($compte);
        $gestionnaire->flush();
    }

    private function recharger(string $email): User
    {
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $compte = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $compte);

        return $compte;
    }
}
