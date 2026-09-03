<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use App\Game\Ressource;
use App\Game\ScoreDAventure;
use App\Game\SuccessionDesRegnes;
use App\Game\Successions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le score et la fin d'une partie Aventure (doc 14, lot 11.4).
 *
 * **Pas d'objectif fermé** : rien à atteindre, seulement quelque chose à
 * regarder monter. Mais **une fin tout de même** (arbitrage 11.0) : le mode est
 * un bac à sable *long*, il n'est pas *sans fin*.
 */
final class ScoreEtFinDAventureTest extends KernelTestCase
{
    /**
     * **Le score lit ce que le jeu sait déjà compter** — les mêmes grandeurs
     * que les objectifs de mission. Rien n'y réinvente une mesure existante.
     */
    public function testLeScoreLitLesGrandeursQueLeJeuMesureDeja(): void
    {
        self::bootKernel();
        $partie = $this->lancerAventure('score-mesure@example.com');
        $ville = $partie->getVille();

        $avant = $this->score()->total($partie);

        $ville->crediterRessources([Ressource::Deben->value => 500]);
        $partie->getFamille()->ajusterRenommee(20);

        $apres = $this->score()->total($partie);

        self::assertGreaterThan($avant, $apres);
        self::assertSame(
            500 * ScoreDAventure::PAR_DEBEN + 20 * ScoreDAventure::PAR_POINT_DE_RENOMMEE,
            $apres - $avant,
        );
    }

    /**
     * **Le détail dit d'où vient le score** : un total nu ne se joue pas, on ne
     * sait pas quoi faire pour le faire monter.
     */
    public function testLeDetailDitDouVientLeScore(): void
    {
        self::bootKernel();
        $partie = $this->lancerAventure('score-detail@example.com');

        $detail = $this->score()->detail($partie);

        self::assertCount(4, $detail);
        self::assertSame(
            $this->score()->total($partie),
            array_sum(array_column($detail, 'points')),
        );
    }

    /**
     * **La campagne n'a pas de score cumulatif** : elle a des objectifs, et un
     * score de mission qui se fige à l'achèvement.
     */
    public function testLeScoreCumulatifNestQuePourLaventure(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('score-campagne@example.com');
        $campagne = $this->lanceur()->lancerCampagne($joueur, 'Nakht');

        // Le service sait compter n'importe quelle partie ; c'est l'écran qui
        // ne le montre qu'en Aventure. Ce que ce test fixe, c'est qu'aucune
        // fin de règne ne vient clore une partie de campagne.
        self::assertSame([], $this->successions()->avenementAuCycle($campagne));
        self::assertTrue($campagne->estEnCours());
    }

    /**
     * **La succession épuisée clôt la partie** (arbitrage 11.0), et le dernier
     * règne compte comme les autres : son acquis rejoint la lignée avant que
     * la partie ne se ferme.
     */
    public function testLaSuccessionEpuiseeCloutLaPartie(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('fin-aventure@example.com');
        $aventure = $this->lanceur()->lancerAventure($joueur, 'Nakht', difficulte: 0, tailleGrille: 3);
        $aventure->getFamille()->ajusterRenommee(40);

        $fin = (new SuccessionDesRegnes())->dernierCycle() + 1;

        while ($aventure->getCycle() < $fin) {
            $aventure->avancerDUnCycle();
        }

        self::assertTrue($aventure->estEnCours(), 'La partie court encore avant qu\'on résolve le cycle.');

        $annonces = $this->successions()->avenementAuCycle($aventure);

        self::assertCount(1, $annonces);
        self::assertStringContainsString('dernier règne', $annonces[0]);
        self::assertTrue($aventure->estAchevee(), 'La succession épuisée clôt la partie.');
        self::assertSame(40, $this->lignees()->pour($joueur)->getRenommeeAcquise());

        // **Elle ne se clôt qu'une fois** : rejouer le cycle ne doit rien
        // réannoncer ni rien reverser.
        self::assertSame([], $this->successions()->avenementAuCycle($aventure));
    }

    private function lancerAventure(string $email): GameSave
    {
        return $this->lanceur()->lancerAventure(
            $this->creerJoueur($email),
            'Nakht',
            difficulte: 0,
            tailleGrille: 3,
        );
    }

    private function creerJoueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return $joueur;
    }

    private function lanceur(): LanceurDePartie
    {
        return static::getContainer()->get(LanceurDePartie::class);
    }

    private function successions(): Successions
    {
        return static::getContainer()->get(Successions::class);
    }

    private function score(): ScoreDAventure
    {
        return static::getContainer()->get(ScoreDAventure::class);
    }

    private function lignees(): Lignees
    {
        return static::getContainer()->get(Lignees::class);
    }
}
