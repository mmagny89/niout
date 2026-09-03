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

    /**
     * **Une partie close n'occupe pas de place.**.
     *
     * Défaut réel, payé : le plafond comptait toutes les parties, closes
     * comprises. Un joueur qui accomplissait cinq missions ne pouvait plus en
     * lancer une sixième — **la campagne de dix missions était donc
     * infinissable** sans supprimer des parties, alors qu'une partie close est
     * précisément ce qu'on ne supprime jamais (décision actée).
     */
    public function testUnePartieAcheveeNoccupePasDePlace(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('campagne-longue@example.com');

        foreach (range(1, GameSave::MAX_PAR_COMPTE) as $rang) {
            $this->creerPartie($joueur, 'Ville '.$rang)->achever(100);
        }

        $this->gestionnaire()->flush();

        self::assertFalse(
            $this->depot()->plafondAtteintPour($joueur),
            'Cinq missions accomplies ne doivent pas interdire la sixième.',
        );
        self::assertSame(0, $this->depot()->compterEnCoursPourJoueur($joueur));
        self::assertSame(
            GameSave::MAX_PAR_COMPTE,
            $this->depot()->compterPourJoueur($joueur),
            'Elles restent consultables : on ne les supprime jamais.',
        );
    }

    /**
     * **Une partie échouée non plus** : elle est close au même titre, et le
     * doc 02 tient à ce qu'elle reste consultable — « chaque partie est une
     * run complète, y compris quand elle finit mal ».
     */
    public function testUnePartieEchoueeNoccupePasDePlace(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('campagne-echouee@example.com');

        foreach (range(1, GameSave::MAX_PAR_COMPTE) as $rang) {
            $this->creerPartie($joueur, 'Ville '.$rang)->echouer();
        }

        $this->gestionnaire()->flush();

        self::assertFalse($this->depot()->plafondAtteintPour($joueur));
    }

    /**
     * Et le plafond mord toujours sur ce qu'il doit borner : cinq parties **en
     * cours** ferment la porte, quel que soit le nombre de parties closes à
     * côté.
     */
    public function testLePlafondMordSurLesPartiesEnCoursMemeAvecDesClosesACote(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('melange@example.com');

        foreach (range(1, 3) as $rang) {
            $this->creerPartie($joueur, 'Close '.$rang)->achever(100);
        }

        foreach (range(1, GameSave::MAX_PAR_COMPTE) as $rang) {
            $this->creerPartie($joueur, 'En cours '.$rang);
        }

        $this->gestionnaire()->flush();

        self::assertTrue($this->depot()->plafondAtteintPour($joueur));
        self::assertSame(GameSave::MAX_PAR_COMPTE, $this->depot()->compterEnCoursPourJoueur($joueur));
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
