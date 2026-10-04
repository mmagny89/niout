<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\EmplacementsDeLaVille;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La ville vue d'en haut : un visuel, des bâtiments posés sur leurs enclos, et
 * un clic qui ouvre la bonne fenêtre (`carte?vue=ville`).
 *
 * Le rendu — sprites bien posés, clic dans le bon losange — ne se vérifie
 * qu'au navigateur ; ce qu'on garde ici, c'est ce dont il dépend.
 */
final class VueDeLaVilleTest extends WebTestCase
{
    public function testLaTuileDeLaVilleMeneALaVue(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-tuile@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte', $partie->getId()));

        self::assertCount(1, $crawler->filter(\sprintf('a[href="/partie/%d/carte?vue=ville"]', $partie->getId())));
    }

    public function testLaVueRemplaceLaCarteEtOffreLeRetour(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-remplace@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-carte-target="grille"]', 'La carte cède la place à la ville.');
        self::assertSelectorTextContains('body', 'Retour au territoire');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/partie/%d/carte"]', $partie->getId()))->reduce(
            static fn ($lien): bool => str_contains($lien->text(), 'Retour au territoire'),
        ));
        self::assertSelectorExists('dialog[data-fenetre-target="fenetre"]', 'La fenêtre est la même que sur la carte.');
    }

    public function testLeVisuelSansPortQuandAucunePointDEauNeJouxteLaVille(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-sans-port@example.com');
        $this->mettreAuSec($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertStringContainsString('ville-', (string) $crawler->filter('img[width="1408"]')->attr('src'));
        self::assertStringNotContainsString('ville_port', (string) $crawler->filter('img[width="1408"]')->attr('src'));
    }

    public function testLeVisuelAvecPortQuandLaVilleJouxteLEau(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-avec-port@example.com');
        $this->poserDeLEauContreLaVille($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertStringContainsString('ville_port', (string) $crawler->filter('img[width="1408"]')->attr('src'));
    }

    /**
     * Un bâtiment dressé est un lien — son sprite — vers sa fenêtre ; un enclos
     * pas encore bâti mène à la liste de ce qu'il reste à bâtir.
     */
    public function testUnBatimentDresseOuvreSaFenetreEtUnEnclosVideMeneALaResidence(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-clics@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 2));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));
        $id = $partie->getId();

        $grenier = $crawler->filter(\sprintf('a[href="/partie/%d/ville?onglet=grenier"]', $id));
        self::assertCount(1, $grenier);
        // AssetMapper versionne les noms : `grenier_2-<empreinte>.webp`.
        self::assertStringContainsString('/batiments/grenier_2', (string) $grenier->filter('img')->attr('src'), 'Le palier suit le niveau.');
        self::assertSame('fenetre', $grenier->attr('data-turbo-frame'));

        // Le Marché n'est pas bâti : son enclos est vide, et mène à la Résidence.
        $marche = $crawler->filter('a[title="Marché — à bâtir"]');
        self::assertCount(1, $marche);
        self::assertSame(\sprintf('/partie/%d/ville?onglet=residence_familiale', $id), $marche->attr('href'));
        self::assertCount(0, $marche->filter('img'), 'Un enclos vide ne porte pas de sprite.');
    }

    public function testLaResidenceEstLaDesLePremierJour(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-residence@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertStringContainsString('/batiments/residence_familiale_1', (string) $crawler->filter('a[title^="Résidence familiale"] img')->attr('src'));
    }

    public function testLesEnclosLibresSontDuDecor(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-decor@example.com');
        $ville = $partie->getVille();

        foreach (TypeDeBatiment::cases() as $type) {
            if (!$type->estLeBatimentDeDepart() && null === $ville->batimentDeType($type)) {
                $ville->ajouterBatiment(new Building($ville, $type));
            }
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        // Douze bâtiments, douze liens ; les trois enclos libres n'en portent pas.
        self::assertCount(12, $crawler->filter('a[data-turbo-frame="fenetre"][aria-label$="ouvrir"]'));
        self::assertCount(12, array_filter(EmplacementsDeLaVille::tous(), static fn (array $e): bool => null !== $e['type']));
        self::assertCount(3, array_filter(EmplacementsDeLaVille::tous(), static fn (array $e): bool => null === $e['type']));
    }

    public function testChaqueBatimentADesEnclosUniqueEtUnSpriteParPalier(): void
    {
        $types = [];
        foreach (EmplacementsDeLaVille::tous() as $enclos) {
            if (null !== $enclos['type']) {
                $types[] = $enclos['type']->value;
            }
        }

        self::assertCount(\count(TypeDeBatiment::cases()), array_unique($types), 'Un enclos par bâtiment, pas deux.');

        foreach (TypeDeBatiment::cases() as $type) {
            for ($palier = 1; $palier <= EmplacementsDeLaVille::PALIERS_DE_SPRITE; ++$palier) {
                self::assertFileExists(
                    \sprintf('%s/assets/images/ville/batiments/%s_%d.webp', \dirname(__DIR__, 2), $type->value, $palier),
                );
            }
        }
    }

    public function testLePalierDeSpriteSuitLeNiveauEtGardeLeDernier(): void
    {
        self::assertSame(1, EmplacementsDeLaVille::palierDeSprite(0));
        self::assertSame(1, EmplacementsDeLaVille::palierDeSprite(1));
        self::assertSame(3, EmplacementsDeLaVille::palierDeSprite(3));
        self::assertSame(4, EmplacementsDeLaVille::palierDeSprite(4));
        self::assertSame(4, EmplacementsDeLaVille::palierDeSprite(5), 'Le niveau cinq garde le dernier palier.');
    }

    public function testLeCycleRamenePartoutOuLeJoueurEtait(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'vue-cycle@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertSame('ville', $crawler->filter('form[action$="/cycle"] input[name="vue"]')->attr('value'));
    }

    private function mettreAuSec(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $centre = $ville->zoneDeLaVille();
        foreach ($ville->getZones() as $zone) {
            if ($zone->getTerrain()->estUnPointDEau() && null !== $centre && $zone->estAdjacenteA($centre)) {
                $zone->definirTerrain(TypeDeTerrain::Desert);
            }
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function poserDeLEauContreLaVille(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $centre = $ville->zoneDeLaVille();
        self::assertNotNull($centre);

        foreach ($ville->getZones() as $zone) {
            if ($zone !== $centre && $zone->estAdjacenteA($centre)) {
                $zone->definirTerrain(TypeDeTerrain::Nil);

                break;
            }
        }
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertTrue($ville->jouxteUnPointDEau());
    }

    private function partie(KernelBrowser $client, string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
