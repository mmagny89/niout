<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\User;
use App\Game\Equipement;
use App\Game\LanceurDePartie;
use App\Game\MedjayImpossible;
use App\Game\Medjays;
use App\Game\Ressource;
use App\Game\Salaires;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Lever et tenir une troupe (doc 03, doc 01, lot 10.2).
 *
 * Le frein est double, et ces tests portent les deux : l'effectif est borné par
 * la Caserne — un bâtiment tient une garnison, pas une armée — et l'entretien
 * rejoint la masse salariale, si bien qu'une troupe qu'on ne peut plus payer
 * mécontente la ville comme des chefs impayés.
 */
final class MedjaysTest extends KernelTestCase
{
    /**
     * L'effectif du doc 01, à la lettre : `3 + 2 × niveau`. Zéro sans Caserne —
     * on ne loge pas une troupe dans une ville qui n'a pas de quoi la caserner.
     */
    public function testLeffectifSuitLeNiveauDeCaserne(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('effectif@example.com');
        $ville = $partie->getVille();

        self::assertSame(0, $this->medjays()->effectifMaximum($ville));

        $caserne = new Building($ville, TypeDeBatiment::Caserne);
        $ville->ajouterBatiment($caserne);

        self::assertSame(5, $this->medjays()->effectifMaximum($ville));
    }

    /**
     * **L'archer demande une Caserne de niveau 4** (doc 01), et le contrôle
     * vit dans le domaine, pas seulement dans le gabarit : un POST forgé ne
     * doit pas lever un archer dans une caserne de fortune.
     */
    public function testLarcherDemandeUneCaserneDeNiveauQuatre(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('archer@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        // Le fantassin passe dès le premier niveau.
        self::assertInstanceOf(
            Medjay::class,
            $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin),
        );

        $this->expectException(MedjayImpossible::class);
        $this->medjays()->lever($partie, SpecialisationMedjay::Archer);
    }

    /**
     * **Une Caserne pleine refuse**, et le refus ne coûte rien : c'est la même
     * discipline que partout ailleurs dans le jeu, où un empêchement ne prélève
     * jamais.
     */
    public function testUneCasernePleineRefuseSansRienPrelever(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('pleine@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        foreach (range(1, $this->medjays()->effectifMaximum($ville)) as $ignore) {
            $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
        }

        $deben = $ville->getDeben();

        try {
            $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
            self::fail('La levée aurait dû être refusée.');
        } catch (MedjayImpossible) {
            self::assertSame($deben, $ville->getDeben(), 'Un refus ne doit rien prélever.');
        }
    }

    /**
     * **L'entretien rejoint la masse salariale** (doc 03) : c'est lui, et non
     * un plafond arbitraire, qui décide de la troupe qu'une famille peut
     * réellement tenir.
     */
    public function testLentretienEntreDansLaMasseSalariale(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('entretien@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        $salaires = static::getContainer()->get(Salaires::class);
        $avant = $salaires->masseSalariale($ville, $partie->getCycle());

        $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);

        self::assertSame(
            $avant + SpecialisationMedjay::Fantassin->entretienParQuinzaine(),
            $salaires->masseSalariale($ville, $partie->getCycle()),
        );
    }

    /**
     * **Un blessé garde son expérience**, et c'est ce qui le distingue d'un
     * mort : la mort permanente du doc 03 fait mal parce qu'elle efface ce que
     * les combats ont appris, pas parce qu'elle coûte un recrutement.
     */
    public function testUnBlesseGardeSonExperience(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('blesse@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        $medjay = $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
        $medjay->gagnerDeLexperience();
        $medjay->blesser($partie->getCycle(), 2);

        self::assertFalse($medjay->estDisponible($partie->getCycle()));
        self::assertTrue($medjay->estDisponible($partie->getCycle() + 2));
        self::assertSame(Medjay::EXPERIENCE_PAR_VICTOIRE, $medjay->getExperience());
        self::assertSame([], $this->medjays()->disponibles($partie));
    }

    /**
     * L'expérience plafonne à cinquante points (doc 03) : un vétéran vaut une
     * fois et demie un homme neuf, jamais davantage.
     */
    public function testLexperiencePlafonne(): void
    {
        $medjay = new Medjay(new City('Ville', 0, 3), SpecialisationMedjay::Fantassin);

        foreach (range(1, 30) as $ignore) {
            $medjay->gagnerDeLexperience();
        }

        self::assertSame(Medjay::EXPERIENCE_MAX, $medjay->getExperience());

        // Trois facteurs — force de base, expérience, qualité de l'arme — et
        // **une seule division** : deux divisions entières enchaînées
        // perdraient de la force à chaque étape (discipline du lot 6.3).
        self::assertSame(
            intdiv(
                SpecialisationMedjay::Fantassin->force()
                    * (100 + Medjay::EXPERIENCE_MAX)
                    * Equipement::QUALITE_SANS_ARME,
                100 * 100,
            ),
            $medjay->force(),
        );
    }

    private function lancerUnePartie(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function medjays(): Medjays
    {
        return static::getContainer()->get(Medjays::class);
    }
}
