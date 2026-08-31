<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Enigme;
use App\Game\EnigmeImpossible;
use App\Game\Enigmes;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Les énigmes courtes (lot 7.2).
 */
final class EnigmesTest extends WebTestCase
{
    /**
     * **Une seule tentative.** Avec quatre propositions et un droit de
     * reprise, on essaie tout : il n'y aurait plus de question, seulement un
     * formulaire.
     */
    public function testOnNeRepondQuUneFois(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecLieux('une-fois@example.com');

        $verdict = $this->enigmes()->repondre($partie, Enigme::IbisDeThot, Enigme::IbisDeThot->bonneReponse());
        self::assertTrue($verdict['juste']);

        $this->expectException(EnigmeImpossible::class);
        $this->enigmes()->repondre($partie, Enigme::IbisDeThot, Enigme::IbisDeThot->bonneReponse());
    }

    /**
     * **Une réponse fausse ferme l'énigme, et n'ôte rien.** Elle se perd : ni
     * ressource retirée, ni cycle. C'est le cas des enquêtes secondaires,
     * tranché par la joueuse, appliqué à ce qui est facultatif.
     */
    public function testUneReponseFausseFermeLEnigmeSansRienOter(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecLieux('fausse@example.com');
        $ville = $partie->getVille();

        $deben = $ville->getDeben();
        $cycle = $partie->getCycle();

        $mauvaise = Enigme::IbisDeThot->propositions()[1];
        $verdict = $this->enigmes()->repondre($partie, Enigme::IbisDeThot, $mauvaise);

        self::assertFalse($verdict['juste']);
        self::assertSame(0, $verdict['recompense']);
        self::assertSame($deben, $ville->getDeben(), 'Se tromper n\'ôte rien.');
        self::assertSame($cycle, $partie->getCycle());
        self::assertNotContains(Enigme::IbisDeThot, $this->enigmes()->disponibles($partie));
    }

    /**
     * **L'explication tombe dans les deux cas** : le vrai gain d'une énigme
     * est ce qu'elle apprend, pas ce qu'elle rapporte. Une énigme ratée qui
     * n'expliquerait rien punirait deux fois, et n'enseignerait pas.
     */
    public function testLExplicationTombeQuOnAitRaisonOuTort(): void
    {
        self::bootKernel();
        $juste = $this->villeAvecLieux('explique-juste@example.com');
        $faux = $this->villeAvecLieux('explique-faux@example.com');

        $surJuste = $this->enigmes()->repondre($juste, Enigme::EtoileDeLaCrue, Enigme::EtoileDeLaCrue->bonneReponse());
        $surFaux = $this->enigmes()->repondre($faux, Enigme::EtoileDeLaCrue, Enigme::EtoileDeLaCrue->propositions()[2]);

        self::assertSame($surJuste['explication'], $surFaux['explication']);
        self::assertStringContainsString('Sirius', $surJuste['explication']);
    }

    /**
     * Répondre juste rapporte, et c'est la seule chose qui rapporte.
     */
    public function testUneBonneReponseRapporteDesDeben(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecLieux('recompense@example.com');
        $ville = $partie->getVille();

        $avant = $ville->quantite(Ressource::Deben);
        $this->enigmes()->repondre($partie, Enigme::DevinetteDuFleuve, Enigme::DevinetteDuFleuve->bonneReponse());

        self::assertSame($avant + Enigme::RECOMPENSE_EN_DEBEN, $ville->quantite(Ressource::Deben));
    }

    /**
     * **Le lieu compte** : chaque énigme se rencontre quelque part, et
     * l'Auberge trouve ici sa première raison d'exister.
     */
    public function testUneEnigmeDemandeSonLieu(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-auberge@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));

        foreach ($this->enigmes()->disponibles($partie) as $disponible) {
            self::assertSame(TypeDeBatiment::MaisonDesScribes, $disponible->lieu());
        }

        $this->expectException(EnigmeImpossible::class);
        $this->enigmes()->repondre($partie, Enigme::DevinetteDuFleuve, Enigme::DevinetteDuFleuve->bonneReponse());
    }

    /**
     * **Le contenu est vrai, et dit d'où il vient** (doc 10) : moitié attesté,
     * moitié écrit dans le même esprit. Tout présenter comme antique
     * tromperait ; tout présenter comme inventé effacerait ce qui est vrai.
     */
    public function testChaqueEnigmeSePresenteHonnetement(): void
    {
        $attestees = 0;

        foreach (Enigme::cases() as $enigme) {
            self::assertNotSame('', $enigme->enonce());
            self::assertNotSame('', $enigme->explication());
            self::assertGreaterThanOrEqual(3, \count($enigme->propositions()), 'Trop peu de choix, et l\'on devine.');
            self::assertContains($enigme->bonneReponse(), $enigme->propositions());
            self::assertSame(
                $enigme->propositions(),
                array_values(array_unique($enigme->propositions())),
                'Deux propositions identiques rendraient la question insoluble.',
            );

            $attestees += $enigme->sourceAttestee() ? 1 : 0;
        }

        self::assertGreaterThan(0, $attestees);
        self::assertLessThan(\count(Enigme::cases()), $attestees, 'Le doc 10 veut un corpus mixte.');
    }

    /**
     * La bonne réponse ne se lit pas dans la source de la page : les
     * propositions sont mélangées au rendu, comme les jetons du déchiffrage.
     */
    public function testLaBonneReponseNeSeLitPasDansLaPage(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecLieux('melange-enigme@example.com', $client);

        $ordres = [];

        for ($essai = 0; $essai < 30; ++$essai) {
            $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
            $ordres[] = implode('|', $crawler->filter('form[action*="/scribes/enigme"] button')->each(
                static fn (Crawler $n): string => (string) $n->attr('value'),
            ));
        }

        self::assertGreaterThan(1, \count(array_unique($ordres)));
    }

    /**
     * Le parcours depuis l'écran, et la disparition de l'énigme une fois
     * répondue.
     */
    public function testOnRepondDepuisLEcran(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecLieux('parcours-enigme@example.com', $client);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $jeton = $crawler->filter('form[action*="/scribes/enigme"] input[name="_token"]')->first()->attr('value');

        $client->request('POST', \sprintf('/partie/%d/scribes/enigme', $partie->getId()), [
            '_token' => $jeton,
            'enigme' => Enigme::ChacalDAnubis->value,
            'reponse' => Enigme::ChacalDAnubis->bonneReponse(),
        ]);

        self::assertResponseRedirects(\sprintf('/partie/%d/ville', $partie->getId()));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Anubis');
        self::assertSelectorTextNotContains('#panneau-scribes', Enigme::ChacalDAnubis->enonce());
    }

    private function villeAvecLieux(string $email, ?KernelBrowser $client = null): GameSave
    {
        $partie = $this->lancerPartie($email, $client);
        $ville = $partie->getVille();

        foreach ([TypeDeBatiment::MaisonDesScribes, TypeDeBatiment::Auberge, TypeDeBatiment::Temple] as $type) {
            $ville->ajouterBatiment(new Building($ville, $type, 1));
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancerPartie(string $email, ?KernelBrowser $client = null): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client?->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function enigmes(): Enigmes
    {
        return static::getContainer()->get(Enigmes::class);
    }
}
