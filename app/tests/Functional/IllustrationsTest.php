<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Twig\IllustrationsExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les illustrations de ressources, d'objets et de dieux, découpées des planches
 * du Drive (`outils/decouper-icones.py`).
 *
 * Une image manquante n'est pas une erreur : le deben, le poisson, les dattes,
 * les outils et les armes n'ont pas encore leur planche, et l'écran s'en passe.
 */
final class IllustrationsTest extends WebTestCase
{
    public function testChaqueDieuAUnPortrait(): void
    {
        $extension = static::getContainer()->get(IllustrationsExtension::class);

        foreach (Divinite::cases() as $dieu) {
            self::assertSame(\sprintf('images/dieux/%s.webp', $dieu->value), $extension->imageDeDivinite($dieu), $dieu->value);
        }
    }

    /**
     * Les valeurs des énumérations donnent les noms de fichiers : renommer un cas
     * sans renommer l'image ferait disparaître l'illustration sans erreur.
     */
    public function testLesRessourcesDeLaPlancheSontToutesConnues(): void
    {
        $extension = static::getContainer()->get(IllustrationsExtension::class);
        $valeurs = array_map(static fn (Ressource $r): string => $r->value, Ressource::cases());

        foreach (glob(\dirname(__DIR__, 2).'/assets/images/ressources/*.webp') ?: [] as $fichier) {
            $nom = basename($fichier, '.webp');
            self::assertContains($nom, $valeurs, \sprintf('« %s » n\'est pas une ressource du jeu.', $nom));
            self::assertNotNull($extension->imageDeRessource($nom));
        }
    }

    public function testUneImageManquanteOuSuspecteNeLeveRien(): void
    {
        $extension = static::getContainer()->get(IllustrationsExtension::class);

        self::assertSame('images/ressources/deben.webp', $extension->imageDeRessource(Ressource::Deben), 'Le deben reprend le pictogramme de l\'interface.');
        self::assertNull($extension->imageDeRessource(null));
        self::assertNull($extension->imageDeRessource('../../.env'), 'Une valeur qui finit dans un chemin est contrainte.');
        self::assertNull($extension->imageDeRessource('inconnue'));
        self::assertSame('images/ressources/poterie.webp', $extension->imageDeRessource('poterie'), 'Une Recette et une Ressource partagent leurs valeurs.');
    }

    public function testLeTempleMontreLePortraitDeSesHuitDieux(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'illustrations-temple@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=temple', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $crawler->filter('#panneau-temple img[src*="/dieux/"]')->count());
    }

    public function testLeMarcheEtLEntrepotMontrentLIconeDesRessources(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'illustrations-stock@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        foreach (['marche', 'entrepot'] as $onglet) {
            $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=%s', $partie->getId(), $onglet), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

            self::assertResponseIsSuccessful($onglet);
            self::assertGreaterThan(0, $crawler->filter('#panneau-'.$onglet.' img[src*="/ressources/"]')->count(), $onglet);
        }
    }

    private function partie(KernelBrowser $client, string $email): \App\Entity\GameSave
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

    /**
     * Les pictogrammes de l'interface sont nommés d'après leur usage, et `EtatDeLaVille`
     * en cite par leur nom : un nom qui ne mène à aucun fichier ferait disparaître le
     * dessin sans erreur.
     */
    public function testLesPictogrammesDeLInterfaceSontTousLa(): void
    {
        $extension = static::getContainer()->get(IllustrationsExtension::class);

        foreach (['deben', 'quinzaine', 'prestige', 'faveur', 'habitants', 'chantier', 'amelioration', 'chef',
            'expedition', 'troupe', 'echange', 'offrande', 'enigme', 'danger', 'fievre', 'pharaon'] as $nom) {
            self::assertSame(\sprintf('images/interface/%s.webp', $nom), $extension->imageDInterface($nom), $nom);
        }
        self::assertNull($extension->imageDInterface('../../.env'));
        self::assertNull($extension->imageDInterface('inconnu'));
    }

    /**
     * Le sprite d'un bâtiment est celui de son palier : les planches n'en livrent que quatre, et un
     * bâtiment de niveau cinq reprend le quatrième. Un type qui ne désigne aucun fichier ne rend rien.
     */
    public function testLeSpriteDUnBatimentSuitSonPalier(): void
    {
        $extension = static::getContainer()->get(IllustrationsExtension::class);

        self::assertSame('images/ville/batiments/grenier_1.webp', $extension->imageDeBatiment(TypeDeBatiment::Grenier, 1));
        self::assertSame('images/ville/batiments/grenier_4.webp', $extension->imageDeBatiment(TypeDeBatiment::Grenier, 5));
        self::assertSame('images/ville/batiments/grenier_1.webp', $extension->imageDeBatiment('grenier', 0), 'Un niveau nul reprend le premier palier.');
        self::assertNull($extension->imageDeBatiment('inconnu', 1));
        self::assertNull($extension->imageDeBatiment('../../.env', 1));
    }
}
