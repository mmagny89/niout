<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GameSaveRepositoryTest extends KernelTestCase
{
    public function testLesPartiesRemontentDeLaPlusRecemmentOuverte(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('tri@example.com');

        $ancienne = $this->creerPartie($joueur, 'Avaris');
        $recente = $this->creerPartie($joueur, 'Memphis');

        // On recule explicitement l'ouverture de la première : les deux parties
        // sont créées dans la même seconde, l'ordre serait sinon indéterminé.
        $this->reculerDerniereOuverture($ancienne, 2);

        $parties = $this->depot()->findPourJoueur($joueur);

        self::assertCount(2, $parties);
        self::assertSame($recente->getId(), $parties[0]->getId());
    }

    public function testLesPartiesDUnAutreJoueurNeRemontentPas(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('mien@example.com');
        $autre = $this->creerJoueur('autre@example.com');
        $this->creerPartie($autre, 'Saï');

        self::assertSame(0, $this->depot()->compterPourJoueur($joueur));
        self::assertSame(1, $this->depot()->compterPourJoueur($autre));
    }

    public function testLePlafondNEstPasAtteintEnDessousDeLaLimite(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('marge@example.com');

        for ($i = 1; $i < GameSave::MAX_PAR_COMPTE; ++$i) {
            $this->creerPartie($joueur, 'Ville '.$i);
        }

        self::assertFalse($this->depot()->plafondAtteintPour($joueur));
    }

    public function testLePlafondEstAtteintALaLimite(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('plein@example.com');

        for ($i = 1; $i <= GameSave::MAX_PAR_COMPTE; ++$i) {
            $this->creerPartie($joueur, 'Ville '.$i);
        }

        self::assertTrue($this->depot()->plafondAtteintPour($joueur));
    }

    private function creerJoueur(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }

    private function creerPartie(User $joueur, string $nomDeVille): GameSave
    {
        $partie = GameSave::pourCampagne(
            $joueur,
            new Family(Family::NOM_PAR_DEFAUT),
            new City($nomDeVille, 0, 3),
        );

        $gestionnaire = $this->gestionnaire();
        $gestionnaire->persist($partie);
        $gestionnaire->flush();

        return $partie;
    }

    private function reculerDerniereOuverture(GameSave $partie, int $secondes): void
    {
        $this->gestionnaire()->getConnection()->executeStatement(
            'UPDATE game_save SET last_opened_at = last_opened_at - INTERVAL \'1 second\' * :secondes WHERE id = :id',
            ['secondes' => $secondes, 'id' => $partie->getId()],
        );
        $this->gestionnaire()->clear();
    }

    private function depot(): GameSaveRepository
    {
        return static::getContainer()->get(GameSaveRepository::class);
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
