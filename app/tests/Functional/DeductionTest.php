<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Enquete;
use App\Game\EnqueteImpossible;
use App\Game\Enquetes;
use App\Game\Indice;
use App\Game\LanceurDePartie;
use App\Game\NatureDIndice;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\StatutDEnquete;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La déduction (lot 7.4).
 *
 * C'est ici que la décision de la joueuse sur les deux familles d'enquêtes
 * prend corps : une principale se rejoue, une secondaire se perd.
 */
final class DeductionTest extends WebTestCase
{
    /**
     * **On ne tranche pas sans savoir.** Tant que les indices concordants
     * manquent, la conclusion est refusée — sinon l'écran de déduction ne
     * serait qu'un tirage à quatre.
     */
    public function testOnNeConclutPasSansAssezDIndices(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('trop-tot@example.com');
        $partie->getVille()->ouvrirLeDossierDe(Enquete::PassageCoupe)->verser(Indice::BorneRenversee);

        $this->expectException(EnqueteImpossible::class);
        $this->expectExceptionMessage('pas encore assez');
        $this->enquetes()->conclure($partie, Enquete::PassageCoupe, Enquete::PassageCoupe->bonneConclusion());
    }

    /**
     * Une enquête résolue rapporte, et fait parler de la famille : le doc 10
     * veut une « récompense notable ».
     *
     * Le compte exact de la renommée et son plafond de mission sont dans
     * `RenommeeDesAffairesTest` (lot 9.2) ; ici on vérifie que la conclusion
     * en verse bien et le rapporte à l'écran.
     */
    public function testUneEnqueteResolueRapporteEtFaitParlerDeVous(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecDossierComplet('resolue@example.com', Enquete::PassageCoupe);
        $ville = $partie->getVille();

        $deben = $ville->quantite(Ressource::Deben);
        $renommee = $partie->getFamille()->getRenommee();

        $verdict = $this->enquetes()->conclure($partie, Enquete::PassageCoupe, Enquete::PassageCoupe->bonneConclusion());

        self::assertTrue($verdict['juste']);
        self::assertSame($deben + Enquete::PassageCoupe->recompenseEnDeben(), $ville->quantite(Ressource::Deben));
        self::assertSame($renommee + Enquete::RENOMMEE_POUR_UNE_RESOLUE, $partie->getFamille()->getRenommee());
        self::assertSame(Enquete::RENOMMEE_POUR_UNE_RESOLUE, $verdict['renommee']);
        self::assertSame(StatutDEnquete::Resolue, $ville->dossierDe(Enquete::PassageCoupe)?->getStatut());
    }

    /**
     * **Une principale se rejoue** : son échec définitif bloquerait la
     * campagne. Elle coûte les deux cycles du doc 10, et rien d'autre.
     */
    public function testUnePrincipaleSeRejoueApresDeuxQuinzaines(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecDossierComplet('rejouee@example.com', Enquete::PassageCoupe);
        $ville = $partie->getVille();

        $deben = $ville->quantite(Ressource::Deben);
        $mauvaise = Enquete::PassageCoupe->conclusions()[1];

        $verdict = $this->enquetes()->conclure($partie, Enquete::PassageCoupe, $mauvaise);

        self::assertFalse($verdict['juste']);
        self::assertFalse($verdict['definitif']);
        self::assertSame($deben, $ville->quantite(Ressource::Deben), 'Aucune perte de ressource.');
        self::assertSame(StatutDEnquete::EnCours, $ville->dossierDe(Enquete::PassageCoupe)?->getStatut());

        // Tout de suite après, on ne peut pas retenter.
        try {
            $this->enquetes()->conclure($partie, Enquete::PassageCoupe, Enquete::PassageCoupe->bonneConclusion());
            self::fail('Le retard doit empêcher de reconclure aussitôt.');
        } catch (EnqueteImpossible $impossible) {
            self::assertStringContainsString('reprennent le dossier', $impossible->getMessage());
        }

        $cycle = static::getContainer()->get(PassageDeCycle::class);

        for ($i = 0; $i < Enquetes::RETARD_DUNE_ERREUR; ++$i) {
            $cycle->passer($partie);
        }

        $seconde = $this->enquetes()->conclure($partie, Enquete::PassageCoupe, Enquete::PassageCoupe->bonneConclusion());
        self::assertTrue($seconde['juste'], 'Le fil rouge se rejoue jusqu\'à être résolu.');
    }

    /**
     * **Une secondaire se perd** (décision de la joueuse), et c'est ce qui
     * donne du poids à une déduction : sans ce risque, conclure au hasard puis
     * recommencer serait toujours la meilleure stratégie.
     */
    public function testUneSecondaireSEnterreQuandOnSeTrompe(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecDossierComplet('perdue@example.com', Enquete::CarrieresAbandonnees);
        $ville = $partie->getVille();

        $deben = $ville->quantite(Ressource::Deben);
        $mauvaise = Enquete::CarrieresAbandonnees->conclusions()[2];

        $verdict = $this->enquetes()->conclure($partie, Enquete::CarrieresAbandonnees, $mauvaise);

        self::assertFalse($verdict['juste']);
        self::assertTrue($verdict['definitif']);
        self::assertSame($deben, $ville->quantite(Ressource::Deben), 'Perdre une affaire n\'ôte rien.');
        self::assertSame(StatutDEnquete::Echouee, $ville->dossierDe(Enquete::CarrieresAbandonnees)?->getStatut());

        $this->expectException(EnqueteImpossible::class);
        $this->expectExceptionMessage('close');
        $this->enquetes()->conclure(
            $partie,
            Enquete::CarrieresAbandonnees,
            Enquete::CarrieresAbandonnees->bonneConclusion(),
        );
    }

    /**
     * **Le dénouement tombe dans les deux cas**, comme l'explication d'une
     * énigme : le vrai gain d'une enquête est de savoir ce qui s'est passé.
     */
    public function testLeDenouementSeDitMemeQuandOnSeTrompe(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecDossierComplet('denouement@example.com', Enquete::CarrieresAbandonnees);

        $verdict = $this->enquetes()->conclure(
            $partie,
            Enquete::CarrieresAbandonnees,
            Enquete::CarrieresAbandonnees->conclusions()[1],
        );

        self::assertSame(Enquete::CarrieresAbandonnees->denouement(), $verdict['denouement']);
        self::assertNotSame('', $verdict['denouement']);
    }

    /**
     * Chaque enquête propose plusieurs issues plausibles, dont une seule est
     * juste — et aucune n'est répétée, ce qui rendrait la question insoluble.
     */
    public function testChaqueEnqueteProposeDesIssuesDistinctes(): void
    {
        foreach (Enquete::cases() as $enquete) {
            $conclusions = $enquete->conclusions();

            self::assertGreaterThanOrEqual(3, \count($conclusions));
            self::assertSame($conclusions, array_values(array_unique($conclusions)));
            self::assertContains($enquete->bonneConclusion(), $conclusions);
            self::assertNotSame('', $enquete->denouement());
            self::assertGreaterThan(0, $enquete->recompenseEnDeben());
        }
    }

    /**
     * La bonne conclusion ne se lit pas dans la source de la page.
     */
    public function testLaBonneConclusionNeSeLitPasDansLaPage(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecDossierComplet('melange-conclusion@example.com', Enquete::PassageCoupe, $client);

        $ordres = [];

        for ($essai = 0; $essai < 30; ++$essai) {
            $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
            $ordres[] = implode('|', $crawler->filter('form[action*="/scribes/conclure"] button')->each(
                static fn ($n): string => substr((string) $n->attr('value'), 0, 20),
            ));
        }

        self::assertGreaterThan(1, \count(array_unique($ordres)));
    }

    private function partieAvecDossierComplet(string $email, Enquete $enquete, ?object $client = null): GameSave
    {
        $partie = $this->lancerPartie($email);

        if (null !== $client && method_exists($client, 'loginUser')) {
            $client->loginUser($partie->getJoueur());
            // Le dossier se lit chez les scribes : sans eux, l'écran de
            // déduction n'existe pas — c'est le bâtiment qui conduit les
            // enquêtes (doc 01).
            $partie->getVille()->ajouterBatiment(
                new Building($partie->getVille(), TypeDeBatiment::MaisonDesScribes, 1),
            );
        }

        $dossier = $partie->getVille()->ouvrirLeDossierDe($enquete);

        foreach ($enquete->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                $dossier->verser($indice);
            }
        }

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

    private function enquetes(): Enquetes
    {
        return static::getContainer()->get(Enquetes::class);
    }
}
