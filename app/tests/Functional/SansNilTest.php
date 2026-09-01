<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\EtatDeLaVille;
use App\Game\GeographieDeLaPartie;
use App\Game\LanceurDePartie;
use App\Game\OffrandeImpossible;
use App\Game\Offrandes;
use App\Game\PassageDeCycle;
use App\Game\QualiteDeCrue;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * **Pas de crue là où il n'y a pas de fleuve.**.
 *
 * Cinq missions sur dix se jouent loin du Nil — Pount, Megiddo, les oasis,
 * l'Ouadi Hammamat, le Sinaï. Le jeu y annonçait pourtant une crue chaque
 * année, en affichait la qualité dans la barre, accélérait les chantiers
 * pendant une inondation qui n'avait pas lieu, et laissait porter des offrandes
 * à Hâpi — le dieu du Nil — dans un désert.
 *
 * Megiddo (mission 4) sert de témoin : Méditerranée et forêt, aucun Nil.
 */
final class SansNilTest extends WebTestCase
{
    private const int MISSION_SANS_NIL = 4;
    private const int MISSION_AVEC_NIL = 1;

    public function testUneRegionSansFleuveNeConnaitPasLaCrue(): void
    {
        self::bootKernel();
        $geographies = static::getContainer()->get(GeographieDeLaPartie::class);

        self::assertFalse($geographies->connaitLaCrue($this->lancer('sans-nil@example.com', self::MISSION_SANS_NIL)));
        self::assertTrue($geographies->connaitLaCrue($this->lancer('avec-nil@example.com', self::MISSION_AVEC_NIL)));
    }

    /**
     * **La crue ne s'annonce pas dans un désert.** Le message tombait au
     * changement d'année, quelle que soit la région.
     */
    public function testAucuneCrueNeSAnnonceSansFleuve(): void
    {
        self::bootKernel();
        $partie = $this->lancer('annonce@example.com', self::MISSION_SANS_NIL);
        $cycle = static::getContainer()->get(PassageDeCycle::class);

        $annonces = 0;
        // Assez de quinzaines pour franchir plusieurs changements d'année.
        for ($i = 0; $i < 60; ++$i) {
            foreach ($cycle->passer($partie) as $evenement) {
                if (str_contains($evenement, 'La crue de cette année')) {
                    ++$annonces;
                }
            }
        }

        self::assertSame(0, $annonces);
    }

    /**
     * **Le bilan des habitants, lui, tombe partout** : on naît et l'on meurt au
     * Levant comme au Delta. Le sortir du bloc de la crue est ce qui évite
     * qu'une région sans fleuve cesse de vieillir.
     */
    public function testLaDemographieContinueDeTomberSansFleuve(): void
    {
        self::bootKernel();
        $partie = $this->lancer('demographie@example.com', self::MISSION_SANS_NIL);
        $cycle = static::getContainer()->get(PassageDeCycle::class);
        $ville = $partie->getVille();
        $avant = [$ville->getActifs(), $ville->getEnfants(), $ville->getAnciens()];

        // Une dizaine d'années : chaque personne est tirée séparément, et sur
        // cette durée il est acquis que quelqu'un grandit, vieillit ou meurt.
        // Mesurer la composition et non le total : naissances et décès
        // peuvent se compenser.
        for ($i = 0; $i < 240; ++$i) {
            $cycle->passer($partie);
        }

        self::assertNotSame(
            $avant,
            [$ville->getActifs(), $ville->getEnfants(), $ville->getAnciens()],
            'Sans bilan démographique, une ville sans fleuve ne vieillirait jamais.',
        );
    }

    /**
     * **Hâpi n'a pas de prise sur une terre sans fleuve**, et l'offrande est
     * refusée plutôt qu'encaissée pour rien. À distinguer d'Isis, dont le
     * système viendra : sa promesse tient, elle est seulement datée.
     */
    public function testUneOffrandeAHapiEstRefuseeSansFleuve(): void
    {
        self::bootKernel();
        $partie = $this->avecUnTemple('offrande-hapi@example.com', self::MISSION_SANS_NIL);
        $debenAvant = $partie->getVille()->getDeben();

        try {
            $this->offrandes()->offrir($partie, Divinite::Hapi, Ressource::Deben, 50);
            self::fail('Hâpi ne peut rien pour une ville sans fleuve : l\'offrande aurait dû être refusée.');
        } catch (OffrandeImpossible $impossible) {
            self::assertStringContainsString('pas de prise', $impossible->getMessage());
            self::assertSame($debenAvant, $partie->getVille()->getDeben(), 'Un refus ne coûte rien.');
        }

        // Isis, elle, reste offrable : le panthéon serait faux sans elle.
        self::assertGreaterThan(0, $this->offrandes()->offrir($partie, Divinite::Isis, Ressource::Deben, 50));
    }

    /**
     * Au bord du Nil, Hâpi reste ce qu'il est : la règle est régionale, pas un
     * retrait pur et simple.
     */
    public function testHapiResteHonorableAuBordDuNil(): void
    {
        self::bootKernel();
        $partie = $this->avecUnTemple('hapi-delta@example.com', self::MISSION_AVEC_NIL);

        self::assertGreaterThan(0, $this->offrandes()->offrir($partie, Divinite::Hapi, Ressource::Deben, 50));
    }

    /**
     * Ni bandeau de crue dans la barre de jeu, ni signal de crue forte : les
     * deux écrans se taisent.
     */
    public function testAucunEcranNAnnonceDeCrueSansFleuve(): void
    {
        $client = static::createClient();
        $partie = $this->lancer('ecrans@example.com', self::MISSION_SANS_NIL, $client);
        $partie->annoncerLaCrue(QualiteDeCrue::Forte);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (['ville', 'carte'] as $ecran) {
            $client->request('GET', \sprintf('/partie/%d/%s', $partie->getId(), $ecran));
            self::assertResponseIsSuccessful();
            self::assertSelectorTextNotContains('body', 'crue');
        }

        $signaux = static::getContainer()->get(EtatDeLaVille::class)->bonnesNouvelles($partie);
        self::assertSame(
            [],
            array_filter($signaux, static fn (array $s): bool => str_contains($s['titre'], 'crue')),
        );
    }

    private function offrandes(): Offrandes
    {
        return static::getContainer()->get(Offrandes::class);
    }

    private function avecUnTemple(string $email, int $mission): GameSave
    {
        $partie = $this->lancer($email, $mission);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 3));
        $ville->crediterRessources([Ressource::Deben->value => 1000]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(string $email, int $mission, ?KernelBrowser $client = null): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        // Le mode d'essai ouvre les dix missions : c'est le seul moyen de
        // lancer Megiddo sans jouer les trois précédentes.
        $user->setRoles([User::ROLE_DIVIN]);

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $client?->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht', $mission);
    }
}
