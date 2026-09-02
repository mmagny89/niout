<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La renommée traverse la campagne (doc 13, lot 9.1).
 *
 * Le document veut « une seule jauge de renommée par famille, persistante d'une
 * mission à l'autre […] elle ne fait que croître ». Ces tests portent les trois
 * invariants qui en découlent, et qui se contredisaient en apparence : elle
 * passe, elle ne redescend pas, et deux parties menées de front ne se la volent
 * pas.
 */
final class RenommeeDeLigneeTest extends WebTestCase
{
    /**
     * **Ce qu'on a gagné se retrouve au lancement suivant.** C'est tout le
     * lot : avant lui, la renommée repartait de zéro à chaque mission, plus
     * quatre points de legs au mieux.
     */
    public function testLaRenommeeGagneePasseALaMissionSuivante(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-transmission@example.com');

        $premiere = $this->lanceur()->lancerCampagne($joueur, 'Nakht');
        $premiere->getFamille()->ajusterRenommee(35);
        $premiere->achever(100);
        $this->lignees()->encaisser($premiere);
        $this->gestionnaire()->flush();

        $seconde = $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 2);

        self::assertSame(35, $seconde->getFamille()->getRenommee());
    }

    /**
     * **Le plancher est la règle qui compte** : les pertes ponctuelles — refus
     * d'une requête, mécontentement — jouent *dans* la mission, jamais en
     * travers de la campagne. Même discipline que le plancher du neutre de la
     * négligence divine.
     */
    public function testUneChuteEnCoursDeMissionNeRabaissePasLacquis(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-plancher@example.com');

        $premiere = $this->lanceur()->lancerCampagne($joueur, 'Nakht');
        $premiere->getFamille()->ajusterRenommee(40);
        $premiere->achever(100);
        $this->lignees()->encaisser($premiere);
        $this->gestionnaire()->flush();

        // La mission suivante se passe mal : la ville gronde, la jauge tombe.
        $seconde = $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 2);
        $seconde->getFamille()->ajusterRenommee(-25);
        $seconde->achever(20);
        $this->lignees()->encaisser($seconde);
        $this->gestionnaire()->flush();

        self::assertSame(15, $seconde->getFamille()->getRenommee());
        self::assertSame(40, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    /**
     * **Deux parties menées de front ne se volent pas leur renommée.** Elles
     * lisent le même acquis au lancement, mais chacune a sa propre jauge : ce
     * que l'une perd, l'autre ne le sent pas.
     */
    public function testDeuxPartiesDeFrontNeSeVolentPasLeurRenommee(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-de-front@example.com');

        $premiere = $this->lanceur()->lancerCampagne($joueur, 'Nakht');
        $premiere->getFamille()->ajusterRenommee(30);
        $premiere->achever(100);
        $this->lignees()->encaisser($premiere);
        $this->gestionnaire()->flush();

        $uneAutre = $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 2);
        $encoreUne = $this->lanceur()->lancerCampagne($joueur, 'Nakht', numeroDeMission: 2);

        $uneAutre->getFamille()->ajusterRenommee(-20);
        $encoreUne->getFamille()->ajusterRenommee(10);
        $this->gestionnaire()->flush();

        self::assertSame(10, $uneAutre->getFamille()->getRenommee());
        self::assertSame(40, $encoreUne->getFamille()->getRenommee());
        self::assertSame(30, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    /**
     * Une première partie part de rien : la lignée naît vide, et le document
     * ne promet aucune faveur d'entrée.
     */
    public function testUnePremierePartiePartDeRien(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-premiere@example.com');

        $partie = $this->lanceur()->lancerCampagne($joueur, 'Nakht');

        self::assertSame(0, $partie->getFamille()->getRenommee());
        self::assertSame(0, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    /**
     * **Le mode Aventure lit l'acquis mais ne l'alimente pas** : il ne s'achève
     * pas, ses règnes se succèdent dans la même partie (doc 14). Y verser de la
     * renommée reviendrait à faire monter la campagne depuis un mode qui n'en
     * fait pas partie.
     */
    public function testLeModeAventureNalimentePasLaLignee(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-aventure@example.com');

        $aventure = $this->lanceur()->lancerAventure($joueur, 'Nakht', difficulte: 0, tailleGrille: 3);
        $aventure->getFamille()->ajusterRenommee(50);
        $this->lignees()->encaisser($aventure);
        $this->gestionnaire()->flush();

        self::assertSame(0, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    /**
     * L'acquis reste borné à l'échelle du document : cent points, pas un de
     * plus, quelle que soit la partie qui l'alimente.
     */
    public function testLacquisResteBorneACentPoints(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('lignee-plafond@example.com');

        $partie = GameSave::pourCampagne($joueur, new Family('Nakht', 100), new City('Ville', 0, 3));
        $partie->achever(100);
        $this->gestionnaire()->persist($partie);

        $this->lignees()->pour($joueur)->relever(500);
        $this->gestionnaire()->flush();

        self::assertSame(Family::RENOMMEE_MAX, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    private function creerJoueur(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $user->setRoles([User::ROLE_DIVIN]);

        $this->gestionnaire()->persist($user);
        $this->gestionnaire()->flush();

        return $user;
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function lignees(): Lignees
    {
        return static::getContainer()->get(Lignees::class);
    }

    private function lanceur(): LanceurDePartie
    {
        return static::getContainer()->get(LanceurDePartie::class);
    }
}
