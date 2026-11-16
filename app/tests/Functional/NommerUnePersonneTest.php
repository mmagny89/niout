<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\LanceurDePartie;
use App\Game\PrenomEgyptien;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Nommer un chef ou un Medjaÿ qui n'a pas de prénom : celui qu'on tape, ou un prénom égyptien proposé.
 */
final class NommerUnePersonneTest extends WebTestCase
{
    public function testUnChefSansPrenomProposeDesPrenomsEgyptiensEtUnChampLibre(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-fiche@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=chef-%d', $partie->getId(), $chef->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        $propositions = $crawler->filter('form[action$="/gens/nommer"] input[type="radio"][name="choix"]');
        self::assertGreaterThanOrEqual(4, \count($propositions));

        foreach ($propositions->each(static fn ($radio): ?string => $radio->attr('value')) as $valeur) {
            self::assertContains($valeur, PrenomEgyptien::ordinaires(), 'Tous des prénoms égyptiens.');
        }

        self::assertCount(1, $crawler->filter('form input[name="prenom"][type="text"]'));
        self::assertCount(1, $crawler->filter('form input[name="_token"]'));
    }

    public function testOnChoisitUnPrenomEgyptien(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-choix@example.com');
        $choix = static::getContainer()->get(\App\Game\Prenoms::class)->propositions($partie)[0];

        $this->nommer($client, $partie, 'chef-'.$chef->getId(), ['choix' => $choix]);

        self::assertResponseRedirects();
        self::assertSame($choix, $this->prenomDuChef($chef));
    }

    public function testOnTapeLeSienEtLeSienPrimeSurLeChoix(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-libre@example.com');

        $this->nommer($client, $partie, 'chef-'.$chef->getId(), ['choix' => 'Hori', 'prenom' => '  nefer-iti ']);

        self::assertResponseRedirects();
        self::assertSame('Nefer-Iti', $this->prenomDuChef($chef));
    }

    public function testUnMedjaySeNommeAussi(): void
    {
        $client = static::createClient();
        [$partie] = $this->avecUnChefSansPrenom($client, 'nommer-medjay@example.com');
        $ville = $partie->getVille();
        $medjay = new Medjay($ville, SpecialisationMedjay::Fantassin);
        $ville->leverUnMedjay($medjay);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=medjay-%d', $partie->getId(), $medjay->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertCount(1, $crawler->filter('form[action$="/gens/nommer"]'));

        $this->nommer($client, $partie, 'medjay-'.$medjay->getId(), ['prenom' => 'Khaemouaset']);

        self::assertResponseRedirects();
        self::assertSame('Khaemouaset', static::getContainer()->get(EntityManagerInterface::class)->find(Medjay::class, $medjay->getId())?->getPrenom());
    }

    /**
     * Les refus : un nom déjà porté, des signes qui n'en sont pas, rien du tout, et **qui a un prénom ne se renomme pas**.
     */
    public function testLesRefusDeLaVille(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-refus@example.com');
        $ville = $partie->getVille();
        $autre = new Employee($ville, TypeDeBatiment::MaisonDesScribes, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $autre->donnerUnPrenom('Baki');
        $ville->ajouterEmploye($autre);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach ([['prenom' => 'baki'], ['prenom' => 'Ra<script>'], ['prenom' => 'X'], []] as $envoi) {
            $this->nommer($client, $partie, 'chef-'.$chef->getId(), $envoi);
            self::assertResponseRedirects();
            self::assertNull($this->prenomDuChef($chef), 'Refusé : '.json_encode($envoi));
        }

        $this->nommer($client, $partie, 'chef-'.$autre->getId(), ['prenom' => 'Pia']);
        self::assertSame('Baki', $this->prenomDuChef($autre), 'Qui a un prénom ne se renomme pas.');
    }

    public function testJetonInvalideEtPersonneInconnue(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-jeton@example.com');

        $client->request('POST', \sprintf('/partie/%d/ville/gens/nommer', $partie->getId()), ['_token' => 'faux', 'cle' => 'chef-'.$chef->getId(), 'prenom' => 'Pia']);
        self::assertResponseStatusCodeSame(403);

        $this->nommer($client, $partie, 'chef-999999', ['prenom' => 'Pia']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUnAutreJoueurNeNommePasVosGens(): void
    {
        $client = static::createClient();
        [$partie, $chef] = $this->avecUnChefSansPrenom($client, 'nommer-proprio@example.com');

        $intrus = new User();
        $intrus->setEmail('nommer-intrus@example.com');
        $intrus->setPassword('peu-importe-ici');
        static::getContainer()->get(EntityManagerInterface::class)->persist($intrus);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($intrus);

        $this->nommer($client, $partie, 'chef-'.$chef->getId(), ['prenom' => 'Pia'], jeton: 'quelconque');
        self::assertResponseStatusCodeSame(403);
        self::assertNull($this->prenomDuChef($chef));
    }

    /**
     * @param array<string, string> $envoi
     */
    private function nommer(KernelBrowser $client, GameSave $partie, string $cle, array $envoi, ?string $jeton = null): void
    {
        $jeton ??= $this->jeton($client, $partie);
        $client->request('POST', \sprintf('/partie/%d/ville/gens/nommer', $partie->getId()), ['_token' => $jeton, 'cle' => $cle] + $envoi);
    }

    /**
     * Le jeton du formulaire, lu sur la fiche d'un chef qui n'a pas de prénom — comme le ferait le navigateur.
     */
    private function jeton(KernelBrowser $client, GameSave $partie): string
    {
        foreach ($partie->getVille()->getEmployes() as $chef) {
            if (null === $chef->getPrenom()) {
                $crawler = $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=chef-%d', $partie->getId(), $chef->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

                return (string) $crawler->filter('form[action$="/gens/nommer"] input[name="_token"]')->attr('value');
            }
        }

        self::fail('Aucun chef sans prénom pour lire le jeton.');
    }

    /**
     * Le prénom **en base** : le noyau redémarre entre deux requêtes, l'objet du test est périmé.
     */
    private function prenomDuChef(Employee $chef): ?string
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->clear();

        return $gestionnaire->find(Employee::class, $chef->getId())?->getPrenom();
    }

    /**
     * @return array{GameSave, Employee}
     */
    private function avecUnChefSansPrenom(KernelBrowser $client, string $email): array
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();
        $client->loginUser($joueur);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $ville->ajouterEmploye($chef);
        $gestionnaire->flush();

        return [$partie, $chef];
    }
}
