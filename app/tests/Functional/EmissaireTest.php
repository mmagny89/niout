<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Enquetes;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\Indice;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\SourceDIndice;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'Émissaire et les témoignages (lot 7.5).
 *
 * Le rôle existait depuis le lot 3.4 et faisait le travail de l'éclaireur en
 * plus cher : il trouve ici son emploi propre.
 */
final class EmissaireTest extends WebTestCase
{
    /**
     * **Un éclaireur va vers l'inconnu, un émissaire va vers les gens.** Il
     * n'y a personne à qui parler sur une case que nul n'a jamais vue.
     */
    public function testUnEmissaireNeVaQueLaOuLonSaitDejaQuIlYADuMonde(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('emissaire-inconnu@example.com');
        $inconnue = $this->uneCase($partie, decouverte: false);

        self::assertTrue(RoleDExploration::Eclaireur->viseUneCaseInconnue());
        self::assertFalse(RoleDExploration::Emissaire->viseUneCaseInconnue());

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessage('éclaireur');
        $this->explorations()->envoyer($partie, $inconnue, RoleDExploration::Emissaire);
    }

    /**
     * Et l'inverse : un éclaireur n'a rien à reconnaître sur une case déjà vue.
     */
    public function testUnEclaireurNaRienAFaireSurUneCaseDejaReconnue(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('eclaireur-connu@example.com');
        $connue = $this->uneCase($partie, decouverte: true);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessage('déjà reconnue');
        $this->explorations()->envoyer($partie, $connue, RoleDExploration::Eclaireur);
    }

    /**
     * **Il faut des scribes pour consigner un témoignage** — même règle que la
     * fouille : sans eux, l'émissaire rapporterait des paroles que rien ne
     * retiendrait.
     */
    public function testSansScribesPersonneNeConsigneCeQuOnRapporte(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('emissaire-sans-scribes@example.com');
        $connue = $this->uneCase($partie, decouverte: true);
        $partie->getVille()->crediterRessources([Ressource::Deben->value => 500, Ressource::Ble->value => 500]);

        $this->expectException(ExplorationImpossible::class);
        $this->expectExceptionMessage('Maison des scribes');
        $this->explorations()->envoyer($partie, $connue, RoleDExploration::Emissaire);
    }

    /**
     * Le parcours : l'émissaire part, revient, et verse un **témoignage** au
     * dossier — jamais un indice de terrain, qui se fouille.
     */
    public function testUnEmissaireRentreAvecUnTemoignage(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('emissaire-rentre@example.com');
        $connue = $this->uneCase($partie, decouverte: true);

        $this->explorations()->envoyer($partie, $connue, RoleDExploration::Emissaire);
        self::assertCount(1, $partie->getVille()->getExpeditions());

        $cycle = static::getContainer()->get(PassageDeCycle::class);
        $rapports = [];

        for ($i = 0; $i < 12 && 0 !== \count($partie->getVille()->getExpeditions()); ++$i) {
            $rapports = [...$rapports, ...$cycle->passer($partie)];
        }

        self::assertCount(0, $partie->getVille()->getExpeditions(), 'L\'émissaire finit par rentrer.');

        $verses = [];

        foreach ($partie->getVille()->getDossiers() as $dossier) {
            foreach ($dossier->indices() as $indice) {
                $verses[] = $indice;
            }
        }

        self::assertCount(1, $verses);
        self::assertSame(SourceDIndice::Temoignage, $verses[0]->source());
        self::assertNotSame([], array_filter($rapports, static fn (string $m): bool => str_contains($m, 'émissaire')));
    }

    /**
     * **Il peut revenir bredouille**, et l'écran le dit : faire semblant
     * d'avoir appris quelque chose serait pire que de l'avouer.
     */
    public function testUnEmissaireQuiNaPlusRienAApprendreLeDit(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('bredouille@example.com');
        $enquetes = static::getContainer()->get(Enquetes::class);

        // On épuise le corpus des témoignages.
        foreach (Indice::cases() as $indice) {
            if (SourceDIndice::Temoignage === $indice->source()) {
                $partie->getVille()->ouvrirLeDossierDe($indice->enquete())->verser($indice);
            }
        }

        self::assertNull($enquetes->recueillirUnTemoignage($partie));
    }

    /**
     * **On ne propose pas un départ qui ne peut rien rapporter.** Une fois
     * tous les témoignages versés, l'émissaire ne ramènerait qu'un « rien
     * appris de neuf » payé trente deben : le bouton disparaît de la carte
     * plutôt que de mentir. Même règle que la prospection.
     */
    public function testLeBoutonDeLEmissaireDisparaitQuandIlNyAPlusRienAApprendre(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecScribes('bouton-emissaire@example.com');
        $client->loginUser($partie->getJoueur());

        $zone = $this->uneCase($partie, decouverte: true);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();

        $adresse = \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY());

        $client->request('GET', $adresse);
        self::assertSelectorTextContains('body', 'Envoyer un émissaire');

        foreach (Indice::cases() as $indice) {
            if (SourceDIndice::Temoignage === $indice->source()) {
                $partie->getVille()->ouvrirLeDossierDe($indice->enquete())->verser($indice);
            }
        }
        $gestionnaire->flush();

        $client->request('GET', $adresse);
        self::assertSelectorTextNotContains('body', 'Envoyer un émissaire');
    }

    /**
     * Le catalogue porte bien les deux sources : un témoignage ne se ramasse
     * pas sur le terrain, et réciproquement.
     */
    public function testLesDeuxSourcesExistentEtSontDistinctes(): void
    {
        $terrain = 0;
        $temoignages = 0;

        foreach (Indice::cases() as $indice) {
            SourceDIndice::Terrain === $indice->source() ? ++$terrain : ++$temoignages;
        }

        self::assertGreaterThan(0, $terrain);
        self::assertGreaterThan(0, $temoignages);
    }

    private function uneCase(GameSave $partie, bool $decouverte): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->porteLaVille() || $zone->estDecouverte() !== $decouverte) {
                continue;
            }

            return $zone;
        }

        // Aucune case dans l'état voulu : on en prépare une.
        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->porteLaVille()) {
                continue;
            }

            if ($decouverte) {
                $zone->decouvrir();
            }

            return $zone;
        }

        self::fail('Aucune case disponible.');
    }

    private function villeAvecScribes(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $ville->crediterRessources([Ressource::Deben->value => 500, Ressource::Ble->value => 500]);

        static::getContainer()->get(EntityManagerInterface::class)->flush();

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

    private function explorations(): Explorations
    {
        return static::getContainer()->get(Explorations::class);
    }
}
