<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\VenteImpossible;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MarcheTest extends KernelTestCase
{
    /**
     * L'invariant qui débloque le jeu : sans le Marché, la monnaie n'a
     * **aucune** source. La dotation royale en donne une fois pour toutes,
     * chaque bâtiment en consomme, et la partie finit par se figer.
     */
    public function testVendreEstLaSeuleFaconDeGagnerDesDeben(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('vente@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Calcaire->value => 10]);
        $debenAvant = $ville->getDeben();

        $recette = $this->marche()->vendre($partie, Ressource::Calcaire, 10);

        // Un Marché sans personne écoule à moitié prix (lot 4.8) : le plancher
        // de 50 % vaut ici comme partout. Le chef du Marché double donc les
        // prix de vente, ce qui est exactement le calibrage voulu.
        self::assertSame(intdiv(10 * PrixDuMarche::pour(Ressource::Calcaire), 2), $recette);
        self::assertSame($debenAvant + $recette, $ville->getDeben());
        self::assertSame(0, $ville->quantite(Ressource::Calcaire));
    }

    public function testSansMarcheOnNeVendRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-marche@example.com');
        $partie->getVille()->crediterRessources([Ressource::Calcaire->value => 10]);

        $this->expectException(VenteImpossible::class);
        $this->expectExceptionMessageMatches('/Marché/');

        $this->marche()->vendre($partie, Ressource::Calcaire, 5);
    }

    public function testOnNeVendPasCeQuOnNaPas(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('decouvert@example.com');
        $ville = $partie->getVille();
        $avant = $ville->quantite(Ressource::Calcaire);

        try {
            $this->marche()->vendre($partie, Ressource::Calcaire, $avant + 1);
            self::fail('La vente aurait dû être refusée.');
        } catch (VenteImpossible) {
            self::assertSame($avant, $ville->quantite(Ressource::Calcaire), 'Un refus ne doit rien prélever.');
        }
    }

    /**
     * On ne vend pas la monnaie contre elle-même. L'or, lui, se vend très bien
     * depuis le lot 4.0 : c'est un métal qu'on extrait, pas un moyen de paiement.
     */
    public function testLaMonnaieNeSeVendPasContreElleMeme(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('monnaie@example.com');

        $this->expectException(VenteImpossible::class);

        $this->marche()->vendre($partie, Ressource::Deben, 1);
    }

    public function testLOrSeVendCommeNImporteQuelMetal(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('mine-dor@example.com');
        $ville = $partie->getVille();
        // Deux lingots, pas quatre : la place d'une ville neuve n'absorbe
        // qu'une quarantaine de deben par quinzaine
        // (`Marche::plafondDeLaQuinzaine()`), et un lot plus gros passerait
        // par les routes commerciales.
        $ville->crediterRessources([Ressource::Or->value => 2]);
        $debenAvant = $ville->getDeben();

        $recette = $this->marche()->vendre($partie, Ressource::Or, 2);

        self::assertSame(intdiv(2 * PrixDuMarche::pour(Ressource::Or), 2), $recette, 'Marché sans chef : moitié prix.');
        self::assertSame($debenAvant + $recette, $ville->getDeben());
        self::assertSame(0, $ville->quantite(Ressource::Or));
    }

    /**
     * **Le Marché vend aux gens de la ville et aux passants**, pas au vaste
     * monde : sa place se sature, et c'est ce qui l'empêche d'être un doublon
     * du commerce par caravanes (décision de la joueuse). Un lot qui dépasse
     * le débouché de la quinzaine est refusé — jamais vendu à moitié.
     */
    public function testLaPlaceNAbsorbeQuUnVolumeParQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('debouche@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Or->value => 200]);

        $plafond = Marche::plafondDeLaQuinzaine($partie);
        self::assertGreaterThan(0, $plafond, 'Un Marché dressé ouvre un débouché.');

        $stockAvant = $ville->quantite(Ressource::Or);
        $debenAvant = $ville->getDeben();

        try {
            $this->marche()->vendre($partie, Ressource::Or, 200);
            self::fail('Un lot plus grand que le débouché de la quinzaine aurait dû être refusé.');
        } catch (VenteImpossible $impossible) {
            self::assertStringContainsString('absorber', $impossible->getMessage());
            // **Le plafond se vérifie avant le débit** : un lot repris au stock
            // repasserait par le plafond de réserve, et un Entrepôt plein le
            // refuserait — le joueur perdrait sa marchandise.
            self::assertSame($stockAvant, $ville->quantite(Ressource::Or), 'Un refus ne coûte pas la marchandise.');
            self::assertSame($debenAvant, $ville->getDeben());
        }
    }

    /**
     * Un nouveau jour de marché rouvre le débouché : la borne appartient à la
     * quinzaine, pas à la partie.
     */
    public function testLaQuinzaineSuivanteRouvreLaPlace(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('jour-de-marche@example.com');
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Or->value => 200]);

        $this->marche()->vendre($partie, Ressource::Or, 1);
        $restantApresLaVente = $this->marche()->venteRestante($partie);
        self::assertLessThan(Marche::plafondDeLaQuinzaine($partie), $restantApresLaVente);

        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        self::assertSame(
            Marche::plafondDeLaQuinzaine($partie),
            $this->marche()->venteRestante($partie),
            'La place se reconstitue à chaque quinzaine.',
        );
    }

    public function testUneQuantiteNulleOuNegativeEstRefusee(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('zero@example.com');
        $partie->getVille()->crediterRessources([Ressource::Calcaire->value => 10]);

        foreach ([0, -5] as $quantite) {
            try {
                $this->marche()->vendre($partie, Ressource::Calcaire, $quantite);
                self::fail(\sprintf('Vendre %d unités aurait dû être refusé.', $quantite));
            } catch (VenteImpossible) {
                self::assertSame(10, $partie->getVille()->quantite(Ressource::Calcaire));
            }
        }
    }

    public function testLEtalNeMontreQueCeQuiSeVendEtQuOnPossede(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('etal@example.com');
        $partie->getVille()->crediterRessources([Ressource::Granite->value => 3]);

        $vendables = [];
        foreach ($this->marche()->etalPour($partie) as $lot) {
            self::assertGreaterThan(0, $lot['quantite']);
            self::assertNotSame(Ressource::Deben, $lot['ressource'], 'Le deben est la monnaie, pas une marchandise.');
            $vendables[] = $lot['ressource'];
        }

        self::assertContains(Ressource::Granite, $vendables);
    }

    /**
     * Le joueur écoule d'abord ce qui vaut cher : l'étal le lui présente dans
     * cet ordre, plutôt que dans celui, arbitraire, de son stock.
     */
    public function testLEtalPresenteLePlusPrecieuxEnPremier(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecMarche('ordre@example.com');
        $partie->getVille()->crediterRessources([
            Ressource::Argile->value => 5,
            Ressource::Granite->value => 5,
            Ressource::Calcaire->value => 5,
        ]);

        $prix = array_map(
            static fn (array $lot): int => $lot['prix'],
            $this->marche()->etalPour($partie),
        );

        $trie = $prix;
        rsort($trie);

        self::assertSame($trie, $prix);
    }

    private function lancerAvecMarche(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));

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

    private function marche(): Marche
    {
        return static::getContainer()->get(Marche::class);
    }
}
