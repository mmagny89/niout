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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class MotDePasseCommandTest extends KernelTestCase
{
    private const string ANCIEN = 'Ouadi-Hammamat-1194';
    private const string NOUVEAU = 'Deir-el-Medineh-1279';

    public function testLaSaisieConfirmeeChangeLeMotDePasse(): void
    {
        self::bootKernel();
        $compte = $this->creerCompte('change@example.com');

        $this->lancer(['email' => 'change@example.com'], [self::NOUVEAU, self::NOUVEAU]);

        $compte = $this->recharger('change@example.com');
        self::assertTrue($this->verifie($compte, self::NOUVEAU));
        self::assertFalse($this->verifie($compte, self::ANCIEN));
    }

    /**
     * Une faute de frappe invisible enfermerait dehors le titulaire du compte :
     * la confirmation discordante ne change rien du tout.
     */
    public function testDeuxSaisiesDiscordantesNeChangentRien(): void
    {
        self::bootKernel();
        $this->creerCompte('discordant@example.com');

        $testeur = $this->lancer(
            ['email' => 'discordant@example.com'],
            [self::NOUVEAU, 'autre-chose-entierement'],
            reussite: false,
        );

        self::assertSame(Command::FAILURE, $testeur->getStatusCode());
        self::assertTrue($this->verifie($this->recharger('discordant@example.com'), self::ANCIEN));
    }

    /**
     * Les exigences sont celles de l'inscription : une porte de service qui
     * accepterait un mot de passe faible rendrait ces règles décoratives.
     */
    public function testUnMotDePasseTropFaibleEstRefuse(): void
    {
        self::bootKernel();
        $this->creerCompte('faible@example.com');

        $testeur = $this->lancer(['email' => 'faible@example.com'], ['court', 'court'], reussite: false);

        self::assertSame(Command::FAILURE, $testeur->getStatusCode());
        self::assertTrue($this->verifie($this->recharger('faible@example.com'), self::ANCIEN));
    }

    public function testLOptionGenererProduitUnMotDePasseUtilisable(): void
    {
        self::bootKernel();
        $this->creerCompte('engendre@example.com');

        $testeur = $this->lancer(['email' => 'engendre@example.com', '--generer' => true], []);

        // Le mot de passe n'est affiché qu'une fois : c'est la seule occasion
        // de le relire, seul le haché étant conservé.
        preg_match('/Mot de passe engendré : (\S+)/u', $testeur->getDisplay(), $trouve);
        $engendre = $trouve[1] ?? null;
        self::assertNotNull($engendre);
        self::assertTrue($this->verifie($this->recharger('engendre@example.com'), $engendre));
    }

    public function testUneAdresseInconnueEchoue(): void
    {
        self::bootKernel();

        $testeur = $this->lancer(['email' => 'fantome@example.com'], [], reussite: false);

        self::assertSame(Command::FAILURE, $testeur->getStatusCode());
    }

    /**
     * @param array<string, bool|string> $entrees
     * @param list<string>               $saisies
     */
    private function lancer(array $entrees, array $saisies, bool $reussite = true): CommandTester
    {
        $application = new Application(self::bootKernel());
        $testeur = new CommandTester($application->find('app:users:password'));

        if ([] !== $saisies) {
            $testeur->setInputs($saisies);
        }

        $testeur->execute($entrees);

        if ($reussite) {
            $testeur->assertCommandIsSuccessful();
        }

        return $testeur;
    }

    private function creerCompte(string $email): User
    {
        $compte = new User();
        $compte->setEmail($email);
        $compte->setPassword($this->hacheur()->hashPassword($compte, self::ANCIEN));

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($compte);
        $gestionnaire->flush();

        return $compte;
    }

    private function verifie(User $compte, string $motDePasse): bool
    {
        return $this->hacheur()->isPasswordValid($compte, $motDePasse);
    }

    private function recharger(string $email): User
    {
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $compte = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $compte);

        return $compte;
    }

    private function hacheur(): UserPasswordHasherInterface
    {
        return static::getContainer()->get(UserPasswordHasherInterface::class);
    }
}
