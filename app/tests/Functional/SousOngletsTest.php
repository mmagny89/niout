<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Chaque panneau de bâtiment range ses sections en sous-onglets : la page de
 * ville ne doit jamais dépasser la fenêtre. Le contrôle est structurel — sans
 * JavaScript, on ne peut pas cliquer, mais un onglet sans panneau est un
 * bouton mort et se voit ici.
 */
final class SousOngletsTest extends WebTestCase
{
    public function testChaqueBatimentRangeSesSectionsEnSousOnglets(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('sous-onglets@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();

        foreach ([
            TypeDeBatiment::Marche, TypeDeBatiment::Port, TypeDeBatiment::Grenier, TypeDeBatiment::Entrepot,
            TypeDeBatiment::Atelier, TypeDeBatiment::Forge, TypeDeBatiment::Caserne, TypeDeBatiment::Temple,
            TypeDeBatiment::QuartierDHabitation, TypeDeBatiment::MaisonDesScribes, TypeDeBatiment::Auberge,
        ] as $type) {
            $ville->ajouterBatiment(new Building($ville, $type));
        }

        $gestionnaire->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();

        $this->verifierLesBarres($crawler, 12);
    }

    /**
     * Toutes les barres de sous-onglets d'une page — hors celle de la ville —
     * se suivent onglet pour panneau, dans le même ordre, avec un seul panneau
     * ouvert et aucun identifiant en double.
     */
    private function verifierLesBarres(\Symfony\Component\DomCrawler\Crawler $crawler, int $attendues): void
    {
        $document = $crawler->getNode(0)?->ownerDocument;
        self::assertInstanceOf(\DOMDocument::class, $document);
        $xpath = new \DOMXPath($document);

        $barres = $xpath->query('//nav[@role="tablist"][not(@aria-label="Sections de la ville")]');
        self::assertInstanceOf(\DOMNodeList::class, $barres);

        // **Toutes les pages, construites de la même manière** : la Résidence,
        // la Maison des scribes, le Marché, le Port, le Grenier, l'Entrepôt,
        // l'Atelier, la Forge, la Caserne, le Temple, le Quartier, l'Auberge.
        self::assertSame($attendues, $barres->length);

        $ids = $crawler->filter('[id]')->each(static fn ($n): string => (string) $n->attr('id'));
        self::assertSame($ids, array_values(array_unique($ids)), 'Deux éléments partagent un identifiant : un onglet ouvrirait le voisin.');

        foreach ($barres as $barre) {
            self::assertInstanceOf(\DOMElement::class, $barre);
            $libelle = $barre->getAttribute('aria-label');
            $controles = [];

            foreach ($xpath->query('.//*[@role="tab"]', $barre) ?: [] as $onglet) {
                self::assertInstanceOf(\DOMElement::class, $onglet);
                $controles[] = $onglet->getAttribute('aria-controls');
            }

            $panneaux = [];
            $caches = 0;

            foreach ($xpath->query('../*[@role="tabpanel"]', $barre) ?: [] as $panneau) {
                self::assertInstanceOf(\DOMElement::class, $panneau);
                $panneaux[] = $panneau->getAttribute('id');
                $caches += $panneau->hasAttribute('hidden') ? 1 : 0;
            }

            self::assertNotSame([], $controles, $libelle);
            self::assertSame($controles, $panneaux, 'Onglets et panneaux se suivent dans le même ordre : '.$libelle);
            self::assertSame(\count($controles) - 1, $caches, 'Une seule section est ouverte : '.$libelle);
        }
    }

    /**
     * Les pages qui ne sont pas des bâtiments suivent la même règle : le
     * territoire, la commande, la reprise et la liste des parties.
     */
    public function testLesAutresPagesDeJeuRangentAussiLeursSections(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('autres-pages@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $id = $partie->getId();

        foreach ([
            \sprintf('/partie/%d/carte', $id) => 1,
            \sprintf('/partie/%d/commande', $id) => 1,
            \sprintf('/partie/%d', $id) => 1,
            '/parties' => 1,
        ] as $adresse => $attendues) {
            $crawler = $client->request('GET', $adresse);

            if (!$client->getResponse()->isSuccessful()) {
                self::fail($adresse.' : '.$client->getResponse()->getStatusCode());
            }

            $this->verifierLesBarres($crawler, $attendues);
        }
    }

    /**
     * L'onglet d'essai, réservé aux comptes privilégiés, range lui aussi ses
     * sections : une exception à la règle ferait douter qu'elle en soit une.
     */
    public function testLEssaiRangeSesSectionsLuiAussi(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('essai-sections@example.com');
        $user->setPassword('peu-importe-ici');
        $user->setRoles([User::ROLE_ADMIN]);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        // La Résidence et l'Essai.
        $this->verifierLesBarres($crawler, 2);
    }
}
