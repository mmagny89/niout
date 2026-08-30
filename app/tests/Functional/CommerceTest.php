<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Commerce;
use App\Game\CommerceImpossible;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CommerceTest extends KernelTestCase
{
    /**
     * **Ouvrir, c'est envoyer une première caravane** : on paie, elle part, et
     * la route n'existe qu'à son arrivée.
     */
    public function testOuvrirUneRoutePaieEtMetUnConvoiEnChemin(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('ouverture@example.com');
        $ville = $partie->getVille();

        $debenAvant = $ville->getDeben();
        $route = $this->commerce()->ouvrir($partie, 'memphis');

        self::assertSame($debenAvant - TypeDeRoute::Fluviale->coutDOuverture(), $ville->getDeben());
        self::assertFalse($route->estOuverte(), 'Le convoi doit encore faire le trajet.');
        self::assertGreaterThan(0, $route->getQuinzainesAvantOuverture());
    }

    /**
     * Le trajet prend le temps de la distance, puis la route s'ouvre — et
     * l'ouverture est annoncée, une fois.
     */
    public function testLaRouteSOuvreALArriveeDuConvoiEtLeDitUneFois(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('trajet@example.com');
        $ville = $partie->getVille();

        $route = $this->commerce()->ouvrir($partie, 'memphis');
        $distance = $route->getQuinzainesAvantOuverture();

        $annonces = 0;
        for ($i = 0; $i < $distance + 3; ++$i) {
            foreach ($this->cycle()->passer($partie) as $evenement) {
                if (str_contains($evenement, 'la route est ouverte')) {
                    ++$annonces;
                }
            }
        }

        self::assertTrue($ville->routeVers('memphis')?->estOuverte());
        self::assertSame(1, $annonces, 'L\'ouverture s\'annonce une seule fois.');
    }

    /**
     * **Le bâtiment décide de ce qu'on peut ouvrir** (doc 12) : une ville sans
     * quai ne commerce que par la piste. C'est un poids de plus donné à la
     * géographie.
     */
    public function testSansPortAucuneRouteFluvialeNiMaritime(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-quai@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 1000]);

        // La piste, elle, s'ouvre : c'est l'Entrepôt qui l'arme.
        $this->commerce()->ouvrir($partie, 'canaan');
        self::assertNotNull($ville->routeVers('canaan'));

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/Port/');

        $this->commerce()->ouvrir($partie, 'byblos');
    }

    public function testOnNOuvrePasDeuxFoisLaMemeRoute(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('doublon@example.com');
        $this->commerce()->ouvrir($partie, 'memphis');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/déjà engagée/');

        $this->commerce()->ouvrir($partie, 'memphis');
    }

    /**
     * Une cité d'une autre mission n'est pas à portée : les routes sont celles
     * de la région où l'on joue.
     */
    public function testUneCiteDuneAutreMissionNestPasAPortee(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('hors-portee@example.com');

        $this->expectException(CommerceImpossible::class);
        $this->expectExceptionMessageMatches('/pas à votre portée/');

        $this->commerce()->ouvrir($partie, 'pount');
    }

    /**
     * Sans deben, rien ne part — et rien n'est débité.
     */
    public function testUneRouteHorsDeMoyensNeDebiteRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-le-sou@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);

        try {
            $this->commerce()->ouvrir($partie, 'canaan');
            self::fail('Une route sans deben doit être refusée.');
        } catch (CommerceImpossible) {
            self::assertSame(0, $ville->getDeben());
            self::assertNull($ville->routeVers('canaan'));
        }
    }

    /**
     * Le volume d'un convoi suit le niveau du bâtiment qui l'arme (doc 12) :
     * c'est ce qui donne à l'Entrepôt et au Port un effet de niveau de plus.
     */
    public function testLeVolumeDunConvoiSuitLeNiveauDuBatiment(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiCommerce('volume@example.com', niveau: 1);
        $petit = $this->volumeVers($partie, 'memphis');

        $partie = $this->villeQuiCommerce('volume-grand@example.com', niveau: 4);
        $grand = $this->volumeVers($partie, 'memphis');

        self::assertGreaterThan($petit, $grand);
    }

    private function volumeVers(GameSave $partie, string $cle): int
    {
        foreach ($this->commerce()->offrePour($partie) as $offre) {
            if ($offre['partenaire']->cle === $cle) {
                return $offre['volume'];
            }
        }

        self::fail(\sprintf('Aucune offre vers %s.', $cle));
    }

    private function villeQuiCommerce(string $email, int $niveau = 2): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, $niveau));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, $niveau));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000, Ressource::Ble->value => 5_000]);

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

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
