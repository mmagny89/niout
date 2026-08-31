<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\ActeDuFilRouge;
use App\Game\Dechiffrage;
use App\Game\DechiffrageImpossible;
use App\Game\Enquetes;
use App\Game\FilRouge;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\NatureDIndice;
use App\Game\Ressource;
use App\Game\SymboleHieroglyphique;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le fil rouge de la mission 1 (lot 7.6).
 */
final class FilRougeTest extends WebTestCase
{
    /**
     * **L'acte I doit être jouable avant d'avoir rien bâti.** La tablette
     * d'Ahmôsis n'emploie que des signes connus d'emblée : c'est le tutoriel
     * du système, il ne peut pas demander un bâtiment.
     */
    public function testLaTabletteDouvertureSeLitDesLePremierJour(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('acte-un@example.com');

        self::assertTrue(FilRouge::court($partie));
        self::assertSame(ActeDuFilRouge::Commande, FilRouge::acte($partie));
        self::assertTrue(Inscription::CommandeDAhmosis->estLisiblePar($partie->getVille()));

        foreach (Inscription::CommandeDAhmosis->signes() as $signe) {
            self::assertContains($signe, \array_slice(SymboleHieroglyphique::ordreDApprentissage(), 0, 4));
        }
    }

    /**
     * **Ce que le roi attend passe avant le reste** : tant que la tablette
     * n'est pas lue, c'est elle qu'on propose.
     */
    public function testLaTablettePasseAvantLesAutresInscriptions(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('priorite@example.com');

        self::assertSame(Inscription::CommandeDAhmosis, $this->dechiffrage()->proposition($partie));
    }

    /**
     * **On ne lit pas la conclusion avant l'obstacle.** La stèle finale est
     * réservée à son acte, sinon le fil rouge se raconterait à l'envers.
     */
    public function testLaSteleFinaleNeSeLitPasAvantSonHeure(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('trop-tot@example.com');
        $ville = $partie->getVille();

        // Même en sachant lire tous ses signes, elle reste fermée.
        foreach (Inscription::LaRouteEstRouverte->signes() as $signe) {
            $ville->apprendreUnSymbole($signe);
        }

        self::assertTrue(Inscription::LaRouteEstRouverte->estLisiblePar($ville));
        self::assertFalse(FilRouge::inscriptionOuverte($partie, Inscription::LaRouteEstRouverte));

        $this->expectException(DechiffrageImpossible::class);
        $this->expectExceptionMessage('pas le moment');
        $this->dechiffrage()->verifier($partie, Inscription::LaRouteEstRouverte, array_map(
            static fn (SymboleHieroglyphique $s): string => $s->value,
            Inscription::LaRouteEstRouverte->signes(),
        ));
    }

    /**
     * Les trois actes, dans l'ordre, jusqu'à l'accomplissement — le parcours
     * que la Phase 8 généralisera aux dix missions.
     */
    public function testLesTroisActesSEnchainent(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('trois-actes@example.com');
        $ville = $partie->getVille();

        // Acte I : la tablette.
        $this->lire($partie, Inscription::CommandeDAhmosis);
        self::assertSame(ActeDuFilRouge::Obstacle, FilRouge::acte($partie));

        // Acte II : l'enquête, qu'il faut résoudre — et elle seule fait avancer.
        $dossier = $ville->ouvrirLeDossierDe(FilRouge::enquete());

        foreach (FilRouge::enquete()->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                $dossier->verser($indice);
            }
        }

        self::assertSame(ActeDuFilRouge::Obstacle, FilRouge::acte($partie), 'Réunir les indices ne suffit pas.');

        static::getContainer()->get(Enquetes::class)->conclure(
            $partie,
            FilRouge::enquete(),
            FilRouge::enquete()->bonneConclusion(),
        );
        self::assertSame(ActeDuFilRouge::Accomplissement, FilRouge::acte($partie));

        // Acte III : la stèle, désormais ouverte — mais encore illisible, ses
        // cinq signes demandant une Maison des scribes déjà montée. Tant
        // qu'elle l'est, on propose autre chose plutôt qu'un mur.
        self::assertTrue(FilRouge::inscriptionOuverte($partie, Inscription::LaRouteEstRouverte));
        self::assertNotSame(Inscription::LaRouteEstRouverte, $this->dechiffrage()->proposition($partie));

        foreach (Inscription::LaRouteEstRouverte->signes() as $signe) {
            $ville->apprendreUnSymbole($signe);
        }

        self::assertSame(Inscription::LaRouteEstRouverte, $this->dechiffrage()->proposition($partie));

        $this->lire($partie, Inscription::LaRouteEstRouverte);
        self::assertSame(ActeDuFilRouge::Accompli, FilRouge::acte($partie));
    }

    /**
     * **L'acte se déduit, il ne se stocke pas.** Il découle de trois faits déjà
     * vrais ; une colonne à tenir à jour finirait par diverger d'eux, et cela
     * ne se verrait qu'en partie.
     */
    public function testLActeSeDeduitDeCeQuiEstDejaVrai(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('deduit@example.com');
        $this->lire($partie, Inscription::CommandeDAhmosis);

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();
        $gestionnaire->clear();

        $rechargee = $gestionnaire->find(GameSave::class, $partie->getId());
        self::assertNotNull($rechargee);
        self::assertSame(ActeDuFilRouge::Obstacle, FilRouge::acte($rechargee));
    }

    /**
     * **Le fil rouge ne court que sur la mission qu'il raconte.** Ailleurs, ses
     * inscriptions redeviennent ordinaires plutôt que de rester inaccessibles.
     */
    public function testAilleursLesInscriptionsDuFilRougeSontOrdinaires(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('aventure@example.com');
        $partie->commencerALaMission(3);

        self::assertFalse(FilRouge::court($partie));
        self::assertTrue(FilRouge::inscriptionOuverte($partie, Inscription::LaRouteEstRouverte));
        self::assertNull(FilRouge::inscriptionDeLActe($partie));
    }

    /**
     * L'écran dit à quel acte on en est, et ce qu'il attend : un fil rouge dont
     * on ignore la consigne n'est qu'une décoration.
     */
    public function testLEcranDitOuLonEnEstEtCeQuIlAttend(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('ecran-fil@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $gestionnaire->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('#panneau-scribes', 'Acte I');
        self::assertSelectorTextContains('#panneau-scribes', 'Ahmôsis');
    }

    private function lire(GameSave $partie, Inscription $inscription): void
    {
        $lecture = $this->dechiffrage()->verifier($partie, $inscription, array_map(
            static fn (SymboleHieroglyphique $signe): string => $signe->value,
            $inscription->signes(),
        ));

        self::assertTrue($lecture['juste']);
    }

    private function villeAvecScribes(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $ville->crediterRessources([Ressource::Deben->value => 200]);

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancerPartie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function dechiffrage(): Dechiffrage
    {
        return static::getContainer()->get(Dechiffrage::class);
    }
}
