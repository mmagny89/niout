<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Gisement;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Effectifs;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * **Un filon épuisé se ferme de lui-même.**.
 *
 * Tant qu'il restait « en activité » sur un vide, il retenait son équipage —
 * qui manquait ailleurs — et le passage de cycle répétait « le gisement est
 * épuisé » à chaque quinzaine, indéfiniment.
 */
final class GisementEpuiseTest extends WebTestCase
{
    public function testUnFilonEpuiseSeFermeEtRendSesBras(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecUneCarriere('fermeture@example.com');
        $gisement = $this->carriere($partie);

        $brasPris = Effectifs::bilan($partie->getVille(), $partie->getCycle())['requis'];
        self::assertGreaterThan(0, $brasPris, 'Une carrière ouverte réclame un équipage.');

        $this->viderLeFilon($partie, $gisement);

        self::assertTrue($gisement->estEpuise());
        self::assertFalse($gisement->estExploitee(), 'Un filon tari ne reste pas « en activité » sur un vide.');
        self::assertSame(
            $brasPris - Effectifs::TRAVAILLEURS_PAR_GISEMENT,
            Effectifs::bilan($partie->getVille(), $partie->getCycle())['requis'],
            'Les bras de la carrière fermée repassent au service de la ville.',
        );
    }

    /**
     * **Le message tombe une fois**, au moment de la fermeture. Répété à chaque
     * quinzaine, il noyait tout le reste du journal de cycle.
     */
    public function testLEpuisementNeSeDitQuUneSeuleFois(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecUneCarriere('une-seule-fois@example.com');
        $gisement = $this->carriere($partie);

        $annonces = $this->viderLeFilon($partie, $gisement);
        self::assertSame(1, $annonces, 'L\'épuisement s\'annonce exactement une fois.');

        $muettes = 0;
        for ($i = 0; $i < 3; ++$i) {
            $muettes += $this->annoncesDEpuisement($this->cycle()->passer($partie));
        }

        self::assertSame(0, $muettes, 'Un filon déjà fermé ne se rappelle plus au joueur.');
    }

    /**
     * Le filon reste sur la carte, et se rouvre — mais il faut rouvrir la
     * carrière : on ne rappelle pas des équipes qui sont parties.
     */
    public function testUnFilonFermeSeRouvreALaMain(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecUneCarriere('rouverture@example.com');
        $gisement = $this->carriere($partie);
        $this->viderLeFilon($partie, $gisement);

        $gisement->rouvrir(50);
        self::assertFalse($gisement->estEpuise());
        self::assertFalse($gisement->estExploitee(), 'La veine revient, l\'équipe non.');

        static::getContainer()->get(Exploitations::class)
            ->exploiter($partie, $gisement->getZone(), $gisement->getRessource());

        self::assertTrue($gisement->estExploitee());
    }

    /**
     * **Le joueur doit savoir s'il produit.** Une carrière ouverte, une
     * carrière jamais ouverte et une carrière épuisée se ressemblaient sur la
     * carte : le récapitulatif les réunit sous le bâtiment qui les gouverne.
     */
    public function testLeRecapitulatifDitCeQuiProduitEtCeQuiNeProduitPas(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecUneCarriere('recap@example.com', $client);

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=%s', $partie->getId(), TypeDeBatiment::Entrepot->value));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#panneau-entrepot', 'Vos carrières et vos mines');
        self::assertSelectorTextContains('#panneau-entrepot', 'en activité');
        self::assertSelectorTextContains('#panneau-entrepot', 'Produit');

        $this->viderLeFilon($partie, $this->carriere($partie));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=%s', $partie->getId(), TypeDeBatiment::Entrepot->value));
        self::assertSelectorTextContains('#panneau-entrepot', 'Épuisée, fermée');
        self::assertSelectorTextContains('#panneau-entrepot', 'un prospecteur y retrouve la veine');
    }

    /**
     * Fait tourner les quinzaines jusqu'à ce que le filon rende l'âme, et
     * renvoie combien de fois son épuisement a été annoncé.
     */
    private function viderLeFilon(GameSave $partie, Gisement $gisement): int
    {
        $annonces = 0;

        for ($i = 0; $i < 400 && !$gisement->estEpuise(); ++$i) {
            $annonces += $this->annoncesDEpuisement($this->cycle()->passer($partie));
        }

        self::assertTrue($gisement->estEpuise(), 'Le filon aurait dû s\'épuiser en quatre cents quinzaines.');

        return $annonces;
    }

    /**
     * @param list<string> $evenements
     */
    private function annoncesDEpuisement(array $evenements): int
    {
        return \count(array_filter($evenements, static fn (string $e): bool => str_contains($e, 'est épuisé')));
    }

    private function carriere(GameSave $partie): Gisement
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            foreach ($zone->getGisements() as $gisement) {
                if ($gisement->estExploitee()) {
                    return $gisement;
                }
            }
        }

        self::fail('Aucune carrière ouverte.');
    }

    /**
     * Une partie dont une carrière tarissable tourne, sur une carte de
     * difficulté assez élevée pour que les filons s'épuisent.
     */
    private function partieAvecUneCarriere(string $email, ?KernelBrowser $client = null): GameSave
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

        // Un Entrepôt pour gouverner la carrière, et de quoi payer les
        // quinzaines qu'il faudra passer.
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 1));
        $ville->crediterRessources([Ressource::Deben->value => 100000]);

        $zone = $this->zoneAvecUnFilonTarissable(array_values($ville->getZones()->toArray()));
        $gisement = $this->premierFilonTarissable($zone);
        $zone->decouvrir();
        $gisement->exploiter();
        $gestionnaire->flush();

        return $partie;
    }

    /**
     * @param list<Zone> $zones
     */
    private function zoneAvecUnFilonTarissable(array $zones): Zone
    {
        foreach ($zones as $zone) {
            foreach ($zone->getGisements() as $gisement) {
                if (!$gisement->getRessource()->estRenouvelable()) {
                    return $zone;
                }
            }
        }

        self::fail('Une carte neuve porte toujours un filon tarissable.');
    }

    private function premierFilonTarissable(Zone $zone): Gisement
    {
        foreach ($zone->getGisements() as $gisement) {
            if (!$gisement->getRessource()->estRenouvelable()) {
                return $gisement;
            }
        }

        self::fail('Cette case ne porte aucun filon tarissable.');
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
