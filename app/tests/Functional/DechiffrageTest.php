<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\CleDeLecture;
use App\Game\Dechiffrage;
use App\Game\DechiffrageImpossible;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\SymboleHieroglyphique;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Le déchiffrage (lot 7.1).
 */
final class DechiffrageTest extends WebTestCase
{
    /**
     * **On ne propose que ce qui est lisible.** Une énigme dont on ignore un
     * signe serait un mur, pas une énigme — et la première inscription doit
     * être tentable avec les quatre signes connus d'emblée, sans quoi le
     * tutoriel de l'acte 1 n'ouvrirait sur rien.
     */
    public function testOnNePropseQueDesInscriptionsLisibles(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('lisible@example.com');
        $ville = $partie->getVille();

        foreach (Inscription::disponiblesPour($ville) as $inscription) {
            foreach ($inscription->signes() as $signe) {
                self::assertTrue(CleDeLecture::sait($ville, $signe));
            }
        }

        $proposee = $this->dechiffrage()->proposition($partie);
        self::assertNotNull($proposee, 'Une ville neuve doit avoir au moins une inscription à tenter.');
    }

    /**
     * **Lire juste apprend un signe de plus** : c'est la seconde voie du
     * doc 10, et elle referme la boucle du lot 7.0 — on lit ce qu'on sait, et
     * lire fait savoir davantage.
     */
    public function testUneLectureJusteApprendUnSigne(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('juste@example.com');
        $ville = $partie->getVille();

        $inscription = $this->dechiffrage()->proposition($partie);
        self::assertNotNull($inscription);

        $avant = \count(CleDeLecture::pour($ville));
        $lecture = $this->dechiffrage()->verifier($partie, $inscription, $this->ordreJuste($inscription));

        self::assertTrue($lecture['juste']);
        self::assertInstanceOf(SymboleHieroglyphique::class, $lecture['apprend']);
        self::assertCount($avant + 1, CleDeLecture::pour($ville));
        self::assertContains($inscription, $ville->inscriptionsDechiffrees());
    }

    /**
     * **Se tromper ne coûte rien** (décision de la joueuse) : ni ressource, ni
     * cycle, et l'inscription reste à tenter. Une énigme qui punit est une
     * énigme qu'on cesse de tenter.
     */
    public function testSeTromperNeCouteRien(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('faux@example.com');
        $ville = $partie->getVille();

        $inscription = $this->dechiffrage()->proposition($partie);
        self::assertNotNull($inscription);

        $deben = $ville->getDeben();
        $cycle = $partie->getCycle();
        $cle = CleDeLecture::pour($ville);

        $aLEnvers = array_reverse($this->ordreJuste($inscription));
        $lecture = $this->dechiffrage()->verifier($partie, $inscription, $aLEnvers);

        self::assertFalse($lecture['juste']);
        self::assertNull($lecture['apprend']);
        self::assertSame($deben, $ville->getDeben());
        self::assertSame($cycle, $partie->getCycle());
        self::assertSame($cle, CleDeLecture::pour($ville));
        self::assertSame($inscription, $this->dechiffrage()->proposition($partie), 'Elle reste à tenter.');
    }

    /**
     * Une inscription ne se relit pas : sa récompense serait sinon infinie.
     */
    public function testUneInscriptionNeSeLitQuUneFois(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('deux-fois@example.com');

        $inscription = $this->dechiffrage()->proposition($partie);
        self::assertNotNull($inscription);
        $this->dechiffrage()->verifier($partie, $inscription, $this->ordreJuste($inscription));

        $this->expectException(DechiffrageImpossible::class);
        $this->dechiffrage()->verifier($partie, $inscription, $this->ordreJuste($inscription));
    }

    /**
     * **Ce qui est vrai et ce qui est du jeu.** Les signes sont réels, les
     * combinaisons sont des rébus : chaque inscription doit donc n'employer
     * que des signes du jeu, et dire quelque chose.
     */
    public function testChaqueInscriptionEstFaiteDeVraisSignesEtSeLit(): void
    {
        foreach (Inscription::cases() as $inscription) {
            self::assertGreaterThanOrEqual(2, \count($inscription->signes()));
            self::assertNotSame('', $inscription->lecture());
            self::assertNotSame('', $inscription->provenance());
            self::assertSame(
                $inscription->signes(),
                array_values(array_unique($inscription->signes(), \SORT_REGULAR)),
                'Deux fois le même signe rendrait l\'ordre indécidable.',
            );
        }
    }

    /**
     * **Le clavier d'abord, le glisser-déposer par-dessus.** L'écran doit
     * porter les deux : une interaction bâtie sur le seul `dragstart` est
     * inutilisable au clavier, et aucun test fonctionnel ne le signalerait.
     */
    public function testLEcranSeJoueAuClavierAutantQuALaSouris(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecScribes('clavier@example.com', $client);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        $jetons = $crawler->filter('[data-dechiffrage-target="reserve"] button');
        self::assertGreaterThan(0, $jetons->count());

        $jetons->each(static function (Crawler $jeton): void {
            $action = (string) $jeton->attr('data-action');
            self::assertStringContainsString('click->dechiffrage#placer', $action, 'Un clic, donc une touche Entrée.');
            self::assertStringContainsString('dragstart->dechiffrage#prendre', $action);
            self::assertSame('button', $jeton->attr('type'), 'Un bouton, donc atteignable au clavier.');
        });

        // Les cases sont des boutons aussi : on doit pouvoir en retirer un
        // signe sans souris.
        $crawler->filter('[data-dechiffrage-target="case"]')->each(static function (Crawler $emplacement): void {
            self::assertStringContainsString('click->dechiffrage#retirer', (string) $emplacement->attr('data-action'));
            self::assertSame('button', $emplacement->attr('type'));
        });
    }

    /**
     * L'ordre gravé ne doit pas se lire dans le HTML : les jetons sont
     * mélangés au rendu, sans quoi l'énigme se résoudrait en regardant la
     * source de la page.
     */
    public function testLaReponseNeSeLitPasDansLaSourceDeLaPage(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecScribes('melange@example.com', $client);

        $ordres = [];

        for ($essai = 0; $essai < 30; ++$essai) {
            $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
            $ordres[] = implode(',', $crawler->filter('[data-dechiffrage-target="reserve"] button')->each(
                static fn ($n): string => (string) $n->attr('data-signe'),
            ));
        }

        self::assertGreaterThan(1, \count(array_unique($ordres)), 'Les jetons doivent être mélangés au rendu.');
    }

    /**
     * Le parcours complet, depuis l'écran.
     */
    public function testOnLitUneInscriptionDepuisLEcran(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecScribes('parcours@example.com', $client);

        $inscription = $this->dechiffrage()->proposition($partie);
        self::assertNotNull($inscription);

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $jeton = $crawler->filter(\sprintf('form[action="/partie/%d/scribes/dechiffrer"] input[name="_token"]', $partie->getId()))
            ->attr('value');

        $client->request('POST', \sprintf('/partie/%d/scribes/dechiffrer', $partie->getId()), [
            '_token' => $jeton,
            'inscription' => $inscription->value,
            'ordre' => implode(',', $this->ordreJuste($inscription)),
        ]);

        self::assertResponseRedirects(\sprintf('/partie/%d/ville', $partie->getId()));
        $client->followRedirect();
        self::assertSelectorTextContains('body', $inscription->lecture());
    }

    /**
     * @return list<string>
     */
    private function ordreJuste(Inscription $inscription): array
    {
        return array_map(
            static fn (SymboleHieroglyphique $signe): string => $signe->value,
            $inscription->signes(),
        );
    }

    private function villeAvecScribes(string $email, ?KernelBrowser $client = null): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client?->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $gestionnaire->flush();

        return $partie;
    }

    private function dechiffrage(): Dechiffrage
    {
        return static::getContainer()->get(Dechiffrage::class);
    }
}
