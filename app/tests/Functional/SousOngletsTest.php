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

        $document = $crawler->getNode(0)?->ownerDocument;
        self::assertInstanceOf(\DOMDocument::class, $document);
        $xpath = new \DOMXPath($document);

        $barres = $xpath->query('//nav[@role="tablist"][not(@aria-label="Sections de la ville")]');
        self::assertInstanceOf(\DOMNodeList::class, $barres);

        // **Toutes les pages, construites de la même manière** : la Résidence,
        // la Maison des scribes, le Marché, le Port, le Grenier, l'Entrepôt,
        // l'Atelier, la Forge, la Caserne, le Temple, le Quartier, l'Auberge.
        self::assertSame(12, $barres->length);

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
}
