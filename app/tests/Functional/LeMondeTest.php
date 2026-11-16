<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Sollicitation;
use App\Entity\User;
use App\Fenetre\LeMonde;
use App\Game\Commerce;
use App\Game\LanceurDePartie;
use App\Game\NatureDeSollicitation;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\SollicitationImpossible;
use App\Game\Sollicitations;
use App\Game\StatutDeSollicitation;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La fenêtre « Le monde » et les propositions des cités (`docs/plan-pnj-palier-a.md` § 6.3 et 7).
 */
final class LeMondeTest extends WebTestCase
{
    public function testSansAubergeNiRouteOnNeSaitRien(): void
    {
        static::createClient();
        $partie = $this->lancer('monde-rien@example.com');

        foreach (static::getContainer()->get(LeMonde::class)->pour($partie)['cartes'] as $carte) {
            self::assertNull($carte['maintenant']);
            self::assertNull($carte['rumeur'], 'Sans Auberge ni Port, aucune rumeur.');
        }
    }

    public function testUneAubergeFaitEntendreDesRumeursEnRetardDeDeuxQuinzaines(): void
    {
        static::createClient();
        $partie = $this->lancer('monde-rumeur@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Auberge, 1));

        $cartes = static::getContainer()->get(LeMonde::class)->pour($partie)['cartes'];

        self::assertNotSame([], $cartes);
        foreach ($cartes as $carte) {
            self::assertNotNull($carte['rumeur']);
            self::assertStringContainsString('Auberge', $carte['rumeur']['source']);
        }
    }

    public function testUneRouteOuverteDitToutEtLeNegociateurVoitLoin(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-ouverte@example.com', $client);

        $cartes = array_values(array_filter(
            static::getContainer()->get(LeMonde::class)->pour($partie)['cartes'],
            static fn (array $c): bool => 'canaan' === $c['partenaire']->cle,
        ));
        $carte = $cartes[0];
        self::assertSame('ouverte', $carte['statut']);
        self::assertNotNull($carte['maintenant']);
        self::assertNull($carte['demain'], 'Sans Négociateur, pas de prévision.');
        self::assertNull($carte['rumeur']);
    }

    public function testUnNegociateurFaitVoirLaQuinzaineSuivante(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-negociateur@example.com', $client);
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new \App\Entity\Employee(
            $ville,
            TypeDeBatiment::Entrepot,
            new \App\Game\Candidat(competence: 60, salaire: 8, ancienneteProbable: 20, traits: [], specialite: \App\Game\SpecialiteDeChef::EntrepotNegociateur, actifsAmenes: 0, inactifsAmenes: 0),
            $partie->getCycle(),
        ));

        $monde = static::getContainer()->get(LeMonde::class)->pour($partie);
        $carte = array_values(array_filter($monde['cartes'], static fn (array $c): bool => 'canaan' === $c['partenaire']->cle))[0];

        self::assertTrue($monde['voitLoin']);
        self::assertNotNull($carte['demain']);
    }

    public function testLaFenetreSeRendEtRefuseLaPartieDUnAutre(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-fenetre@example.com', $client);

        $client->request('GET', \sprintf('/partie/%d/ville/monde', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Le monde');
        self::assertSelectorTextContains('body', 'route ouverte');

        $client->loginUser($this->joueur('autre-joueur@example.com'));
        $client->request('GET', \sprintf('/partie/%d/ville/monde', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testLesPropositionsSontDeterministesEtBornees(): void
    {
        static::createClient();
        $partie = $this->lancer('monde-propositions@example.com');
        $service = static::getContainer()->get(Sollicitations::class);
        $partenaire = static::getContainer()->get(Commerce::class)->partenairesDe($partie)[0];
        $vues = 0;

        for ($cycle = 0; $cycle < 400; $cycle += Sollicitations::FENETRE) {
            $a = $service->proposer($partie, $partenaire, $cycle);
            $b = $service->proposer($partie, $partenaire, $cycle);

            self::assertEquals($a?->getRessource(), $b?->getRessource());

            if (null === $a) {
                continue;
            }

            ++$vues;
            self::assertGreaterThanOrEqual(Sollicitations::QUANTITE_MIN, $a->getQuantite());
            self::assertLessThanOrEqual(Sollicitations::QUANTITE_MAX, $a->getQuantite());

            // Jamais plus généreux que ce que le commerce ordinaire permet : une offre vaut au moins
            // 110 % du cours, une demande au plus 140 %.
            $cours = PrixDuMarche::pour($a->getRessource()) ?? 1;
            NatureDeSollicitation::Offre === $a->getNature()
                ? self::assertGreaterThanOrEqual(intdiv($cours * 110, 100), $a->getPrix())
                : self::assertLessThanOrEqual(max(1, intdiv($cours * 140, 100)), $a->getPrix());
        }

        self::assertGreaterThan(5, $vues, 'Le monde doit parler de temps en temps.');
    }

    public function testAccepterUneOffreAcheteUneDemandeVendRefuserNeCoutePas(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-reponse@example.com', $client);
        $ville = $partie->getVille();
        $service = static::getContainer()->get(Sollicitations::class);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $ville->crediterRessources([Ressource::Deben->value => 5000]);

        $offre = new Sollicitation($partie, 'canaan', 'canaan', NatureDeSollicitation::Offre, Ressource::Cuivre, 10, 3, $partie->getCycle(), $partie->getCycle() + 4);
        $gestionnaire->persist($offre);
        $deben = $ville->quantite(Ressource::Deben);
        $cuivre = $ville->quantite(Ressource::Cuivre);

        $service->accepter($partie, $offre);
        self::assertSame($deben - 30, $ville->quantite(Ressource::Deben));
        self::assertSame($cuivre + 10, $ville->quantite(Ressource::Cuivre));
        self::assertSame(StatutDeSollicitation::Acceptee, $offre->getStatut());

        try {
            $service->accepter($partie, $offre);
            self::fail('Une proposition ne se règle qu\'une fois.');
        } catch (SollicitationImpossible) {
            self::assertSame($deben - 30, $ville->quantite(Ressource::Deben));
        }

        $demande = new Sollicitation($partie, 'canaan', 'canaan', NatureDeSollicitation::Demande, Ressource::Ble, 20, 2, $partie->getCycle(), $partie->getCycle() + 4);
        $gestionnaire->persist($demande);
        $ble = $ville->quantite(Ressource::Ble);
        $service->accepter($partie, $demande);
        self::assertSame($ble - 20, $ville->quantite(Ressource::Ble));

        $refusee = new Sollicitation($partie, 'canaan', 'canaan', NatureDeSollicitation::Demande, Ressource::Ble, 20, 2, $partie->getCycle(), $partie->getCycle() + 4);
        $gestionnaire->persist($refusee);
        $avant = $ville->quantite(Ressource::Ble);
        $service->refuser($partie, $refusee);
        self::assertSame($avant, $ville->quantite(Ressource::Ble), 'Décliner ne coûte rien.');
    }

    public function testOnNePeutPasRepondreCeQuOnNePossedePas(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-impossible@example.com', $client);
        $ville = $partie->getVille();
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);

        $demande = new Sollicitation($partie, 'canaan', 'canaan', NatureDeSollicitation::Demande, Ressource::Or, 9999, 5, $partie->getCycle(), $partie->getCycle() + 4);
        $gestionnaire->persist($demande);
        $stock = $ville->quantite(Ressource::Or);

        $this->expectException(SollicitationImpossible::class);

        try {
            static::getContainer()->get(Sollicitations::class)->accepter($partie, $demande);
        } finally {
            self::assertSame($stock, $ville->quantite(Ressource::Or), 'Rien ne quitte les réserves sans accord possible.');
            self::assertTrue($demande->estEnAttente());
        }
    }

    public function testIgnorerUnePropositionEllesEteintALEcheance(): void
    {
        $client = static::createClient();
        $partie = $this->avecRouteOuverte('monde-ignorer@example.com', $client);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);

        $s = new Sollicitation($partie, 'canaan', 'canaan', NatureDeSollicitation::Offre, Ressource::Cuivre, 10, 3, $partie->getCycle(), $partie->getCycle() + 2);
        $gestionnaire->persist($s);
        $gestionnaire->flush();

        for ($i = 0; $i < 3; ++$i) {
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
        }

        self::assertSame(StatutDeSollicitation::Echue, $s->getStatut());
    }

    private function avecRouteOuverte(string $email, \Symfony\Bundle\FrameworkBundle\KernelBrowser $client): GameSave
    {
        $joueur = $this->joueur($email);
        $client->loginUser($joueur);
        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, niveau: 2));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, niveau: 2));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000, Ressource::Ble->value => 5_000]);
        $route = static::getContainer()->get(Commerce::class)->ouvrir($partie, 'canaan');

        while (!$route->estOuverte()) {
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->joueur($email), 'Nakht');
    }

    private function joueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return $joueur;
    }
}
