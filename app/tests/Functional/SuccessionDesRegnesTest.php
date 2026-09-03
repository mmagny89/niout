<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Game\CartoucheRoyal;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use App\Game\LongueurDeRegne;
use App\Game\SuccessionDesRegnes;
use App\Game\Successions;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La succession des règnes du mode Aventure (doc 14, lot 11.1).
 *
 * C'est la différence structurelle avec la campagne : au lieu d'un pharaon
 * commanditaire unique, la partie traverse une succession réelle. Deux
 * invariants portent le lot — **la liste est une donnée**, si bien que
 * l'allonger ne doit toucher aucun code, et **le règne se déduit du cycle**,
 * donc rien ne se persiste.
 */
final class SuccessionDesRegnesTest extends KernelTestCase
{
    /**
     * **Rien ne suppose que la succession s'arrête à un pharaon nommé**
     * (arbitrage 11.0). La première livraison porte la XVIIIᵉ dynastie ; les
     * suivantes s'y ajouteront sans toucher au code.
     */
    public function testLaListeEstUneDonneeEtNonUneConstante(): void
    {
        $regnes = $this->succession()->tous();

        self::assertNotEmpty($regnes);

        foreach ($regnes as $regne) {
            self::assertSame(18, $regne->dynastie, 'La première livraison porte la XVIIIᵉ dynastie.');
            self::assertNotSame('', $regne->pharaon);
            self::assertNotSame('', $regne->nomDeTrone);
            self::assertNotSame('', $regne->avenement);
        }
    }

    /**
     * **La durée reste dans la fourchette de sa catégorie** (doc 14) : un règne
     * ne se convertit pas année pour année, il se range par longueur. C'est ce
     * qui évite de faire passer une durée de jeu pour une donnée historique.
     */
    public function testChaqueDureeTientDansSaCategorie(): void
    {
        foreach ($this->succession()->tous() as $regne) {
            [$minimum, $maximum] = $regne->longueur()->fourchetteEnCycles();

            self::assertGreaterThanOrEqual(
                $minimum,
                $regne->dureeEnCycles,
                \sprintf('%s : %d cycles, hors de sa catégorie.', $regne->pharaon, $regne->dureeEnCycles),
            );
            self::assertLessThanOrEqual($maximum, $regne->dureeEnCycles, $regne->pharaon);
        }
    }

    /**
     * L'ordre relatif du document est respecté : un règne réel plus long reste
     * plus long en jeu.
     */
    public function testUnRegneReelPlusLongResteLePlusLongEnJeu(): void
    {
        $regnes = $this->succession()->tous();

        $leplusLong = $regnes[0];
        $leplusCourt = $regnes[0];

        foreach ($regnes as $regne) {
            $leplusLong = $regne->anneesReelles > $leplusLong->anneesReelles ? $regne : $leplusLong;
            $leplusCourt = $regne->anneesReelles < $leplusCourt->anneesReelles ? $regne : $leplusCourt;
        }

        self::assertSame(LongueurDeRegne::Long, $leplusLong->longueur());
        self::assertSame(LongueurDeRegne::Court, $leplusCourt->longueur());
        self::assertGreaterThan($leplusCourt->dureeEnCycles, $leplusLong->dureeEnCycles);
    }

    /**
     * **Le règne se déduit du cycle** : la somme des durées est connue
     * d'avance, donc une colonne de plus n'aurait rien dit que la liste ne
     * sache déjà.
     */
    public function testLeRegneSeDeduitDuCycle(): void
    {
        $succession = $this->succession();
        $regnes = $succession->tous();

        // `tous()` rend des objets neufs à chaque appel — c'est du contenu,
        // pas un état : on compare donc les pharaons, jamais les instances.
        self::assertSame($regnes[0]->pharaon, $succession->auCycle(1)?->pharaon);
        self::assertSame($regnes[0]->pharaon, $succession->auCycle($regnes[0]->dureeEnCycles)?->pharaon);
        self::assertSame($regnes[1]->pharaon, $succession->auCycle($regnes[0]->dureeEnCycles + 1)?->pharaon);

        // Passé le dernier règne, la succession est épuisée : c'est la fin de
        // la partie, que le lot 11.4 portera.
        self::assertNull($succession->auCycle($succession->dernierCycle() + 1));
        self::assertTrue($succession->estEpuisee($succession->dernierCycle() + 1));
    }

    /**
     * **Le premier cycle n'est un avènement pour personne** : la ville existait
     * avant le roi qui l'ouvre, et le mode Aventure n'a pas de commanditaire.
     */
    public function testLePremierCycleNestPasUnAvenement(): void
    {
        $succession = $this->succession();

        self::assertFalse($succession->estUneAnneeDAvenement(1));
        self::assertTrue($succession->estUneAnneeDAvenement($succession->tous()[0]->dureeEnCycles + 1));
    }

    /**
     * **La campagne n'a pas de succession** : elle a un pharaon commanditaire
     * par mission, et un second maître pour le même règne n'aurait pas de sens.
     */
    public function testLaCampagneNaPasDeSuccession(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('succession-campagne@example.com');
        $campagne = $this->lanceur()->lancerCampagne($joueur, 'Nakht');

        self::assertNull($this->successions()->regneEnCours($campagne));
        self::assertNull($this->successions()->rangEnCours($campagne));
        self::assertSame([], $this->successions()->avenementAuCycle($campagne));
    }

    /**
     * **Une partie Aventure vit sous un règne**, et l'écran peut le nommer.
     */
    public function testUnePartieAventureVitSousUnRegne(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('succession-aventure@example.com');
        $aventure = $this->lanceur()->lancerAventure($joueur, 'Nakht', difficulte: 0, tailleGrille: 3);

        $regne = $this->successions()->regneEnCours($aventure);

        self::assertNotNull($regne);
        self::assertSame('Ahmôsis Ier', $regne->pharaon);
        self::assertSame(1, $this->successions()->rangEnCours($aventure));
    }

    /**
     * **Un règne achevé relève l'acquis de la lignée** (arbitrage 11.0) :
     * l'Aventure n'avait aucun jalon où verser sa renommée tant que les règnes
     * n'existaient pas.
     */
    public function testUnAvenementReleveLacquisDeLaLignee(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('succession-lignee@example.com');
        $aventure = $this->lanceur()->lancerAventure($joueur, 'Nakht', difficulte: 0, tailleGrille: 3);
        $aventure->getFamille()->ajusterRenommee(30);

        // On amène la partie au premier cycle du second règne.
        $bascule = $this->succession()->tous()[0]->dureeEnCycles + 1;

        while ($aventure->getCycle() < $bascule) {
            $aventure->avancerDUnCycle();
        }

        self::assertSame(0, $this->lignees()->pour($joueur)->getRenommeeAcquise());

        $annonces = $this->successions()->avenementAuCycle($aventure);

        self::assertCount(1, $annonces);
        self::assertStringContainsString('Amenhotep Ier', $annonces[0]);
        self::assertSame(30, $this->lignees()->pour($joueur)->getRenommeeAcquise());
    }

    /**
     * **Un cartouche ne s'approxime jamais.** Six des règnes de la dynastie
     * ont le leur, hérité de la campagne ; les autres n'affichent rien plutôt
     * qu'un signe inventé — c'est la règle des hiéroglyphes, et le lot 11.3
     * comblera les manques par un vrai sourcing.
     */
    public function testUnCartoucheEstEtabliOuAbsentJamaisApproche(): void
    {
        $avec = 0;

        foreach ($this->succession()->tous() as $regne) {
            $cartouche = $regne->cartouche();

            if (null === $cartouche) {
                continue;
            }

            self::assertInstanceOf(CartoucheRoyal::class, $cartouche);
            self::assertNotSame('', $cartouche->signes());
            ++$avec;
        }

        self::assertGreaterThan(0, $avec, 'Les pharaons déjà documentés par la campagne gardent leur cartouche.');
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

    private function succession(): SuccessionDesRegnes
    {
        return new SuccessionDesRegnes();
    }

    private function successions(): Successions
    {
        return static::getContainer()->get(Successions::class);
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
