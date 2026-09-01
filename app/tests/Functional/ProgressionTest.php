<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Legs;
use App\Game\MissionFermee;
use App\Game\Progression;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Enchaîner les missions, et ce qu'on emporte (lots 8.5 et 8.6).
 */
final class ProgressionTest extends WebTestCase
{
    /**
     * **L'ordre est imposé** : on n'ouvre la mission suivante qu'en ayant
     * accompli la précédente. Le contrôle vit dans le lanceur, pas seulement
     * dans le formulaire — un POST forgé n'ouvre pas le Sinaï à qui sort du
     * Delta.
     */
    public function testOnNouvrePasUneMissionQuOnNaPasMeritee(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('debutante@example.com');

        self::assertSame(1, $this->progression()->prochaineMission($joueur));
        self::assertSame([1], $this->progression()->missionsOuvertes($joueur));

        $this->expectException(MissionFermee::class);
        $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 5);
    }

    /**
     * **Une mission accomplie ouvre la suivante** — et l'on peut toujours
     * rejouer celles qu'on a faites, plutôt que d'enfermer un joueur qui
     * voudrait refaire Avaris autrement.
     */
    public function testUneMissionAccomplieOuvreLaSuivante(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('avancee@example.com');

        $this->acheverLaMission($joueur, 1, score: 100);

        self::assertSame(2, $this->progression()->prochaineMission($joueur));
        self::assertSame([1, 2], $this->progression()->missionsOuvertes($joueur));

        $suivante = $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 2);
        self::assertSame(2, $suivante->getMission());
    }

    /**
     * **Une réussite partielle ouvre la suite comme une réussite pleine**
     * (doc 09) : ce serait la punir deux fois que de bloquer la campagne
     * dessus.
     */
    public function testUneReussitePartielleOuvreLaSuiteAussi(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('partielle-suite@example.com');

        $this->acheverLaMission($joueur, 1, score: 50);

        self::assertSame(2, $this->progression()->prochaineMission($joueur));
    }

    /**
     * Une partie **échouée** n'ouvre rien : c'est l'accomplissement qui fait
     * avancer, pas le fait d'avoir essayé.
     */
    public function testUnePartieEchoueeNouvreRien(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('echouee-progression@example.com');

        $partie = $this->lanceur()->lancerCampagne($joueur, 'Nakht');
        $partie->echouer();
        $this->gestionnaire()->flush();

        self::assertSame(1, $this->progression()->prochaineMission($joueur));
    }

    /**
     * La campagne s'arrête à dix : on ne va pas au-delà.
     */
    public function testLaCampagneSArreteADix(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('finisseuse@example.com');

        $this->acheverLaMission($joueur, Progression::DERNIERE_MISSION, score: 100);

        self::assertTrue($this->progression()->campagneAchevee($joueur));
        self::assertSame(Progression::DERNIERE_MISSION, $this->progression()->prochaineMission($joueur));
    }

    /**
     * **Le legs suit le score, proportionnellement** : une mission accomplie à
     * moitié lègue la moitié. C'est ce qui donne un sens aux objectifs
     * chiffrés au-delà du chiffre lui-même.
     */
    public function testLeLegsSuitLeScore(): void
    {
        self::bootKernel();
        $pleine = $this->creerJoueur('legs-plein@example.com');
        $partielle = $this->creerJoueur('legs-partiel@example.com');

        $this->acheverLaMission($pleine, 1, score: 100);
        $this->acheverLaMission($partielle, 1, score: 50);

        $legs = $this->legs();

        self::assertSame(Legs::DEBEN_POUR_UNE_REUSSITE_PLEINE, $legs->debenPour($pleine, 2));
        self::assertSame(intdiv(Legs::DEBEN_POUR_UNE_REUSSITE_PLEINE, 2), $legs->debenPour($partielle, 2));
        self::assertGreaterThan($legs->renommeePour($partielle, 2), $legs->renommeePour($pleine, 2));
    }

    /**
     * **Le legs s'ajoute à la dotation, il ne la remplace pas** : une première
     * mission et une cinquième démarrent sur le même socle, et c'est ce qui
     * garde chaque mission jouable seule.
     */
    public function testLeLegsSAjouteALaDotation(): void
    {
        self::bootKernel();
        $sansLegs = $this->creerJoueur('sans-legs@example.com');
        $avecLegs = $this->creerJoueur('avec-legs@example.com');

        $premiere = $this->lanceur()->lancerCampagne($sansLegs, 'Nakht');
        $dotation = $premiere->getVille()->quantite(Ressource::Deben);

        $this->acheverLaMission($avecLegs, 1, score: 100);
        $seconde = $this->lanceur()->lancerCampagne($avecLegs, 'Nakht', numeroDeMission: 2);

        self::assertSame(Legs::DEBEN_POUR_UNE_REUSSITE_PLEINE, $seconde->getLegsEnDeben());
        self::assertGreaterThan($dotation, $seconde->getVille()->quantite(Ressource::Deben));
        self::assertGreaterThan(0, $seconde->getFamille()->getRenommee(), 'Le pharaon parle de vous à son successeur.');
    }

    /**
     * La première mission ne lègue rien : il n'y a rien avant elle.
     */
    public function testLaPremiereMissionNeLegueRien(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('premiere@example.com');

        self::assertSame(0, $this->legs()->debenPour($joueur, 1));

        $partie = $this->lanceur()->lancerCampagne($joueur, 'Nakht');
        self::assertSame(0, $partie->getLegsEnDeben());
    }

    /**
     * **Le mode d'essai ouvre les dix**, et c'est à cela qu'il sert : éprouver
     * une région sans jouer les heures qui y mènent.
     */
    public function testLeModeDessaiOuvreLesDix(): void
    {
        self::bootKernel();
        $divinite = $this->creerJoueur('essai-progression@example.com', divinite: true);

        self::assertCount(Progression::DERNIERE_MISSION, $this->progression()->missionsOuvertes($divinite));
        self::assertTrue($this->progression()->peutLancer($divinite, 9));
    }

    /**
     * Mène une partie à son terme sans la jouer : c'est le score qui nous
     * intéresse ici, pas le chemin.
     */
    private function acheverLaMission(User $joueur, int $mission, int $score): GameSave
    {
        $partie = GameSave::pourCampagne(
            $joueur,
            new \App\Entity\Family('Nakht'),
            new \App\Entity\City('Ville', 0, 3),
        );
        $partie->commencerALaMission($mission);
        $partie->achever($score);

        $this->gestionnaire()->persist($partie);
        $this->gestionnaire()->flush();

        return $partie;
    }

    private function creerJoueur(string $email, bool $divinite = false): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        if ($divinite) {
            $user->setRoles([User::ROLE_DIVIN]);
        }

        $this->gestionnaire()->persist($user);
        $this->gestionnaire()->flush();

        return $user;
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function progression(): Progression
    {
        return static::getContainer()->get(Progression::class);
    }

    private function legs(): Legs
    {
        return static::getContainer()->get(Legs::class);
    }

    private function lanceur(): LanceurDePartie
    {
        return static::getContainer()->get(LanceurDePartie::class);
    }
}
