<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\RivalCommercial;
use App\Entity\RouteCommerciale;
use App\Entity\User;
use App\Fenetre\LeMonde;
use App\Game\LanceurDePartie;
use App\Game\RoleDePnj;
use App\Game\TraitDeCandidat;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La fiche d'une personne et sa chronique, dans « Le monde » (`docs/plan-pnj-palier-b.md` § 8) :
 * ce qu'elle a fait se voit toujours, pourquoi se mérite.
 */
final class PersonnesDuMondeTest extends WebTestCase
{
    public function testSansPersonneConnueAucuneFiche(): void
    {
        static::createClient();
        $partie = $this->partie('personne-aucune@example.com');

        $monde = static::getContainer()->get(LeMonde::class)->pour($partie);

        self::assertSame([], $monde['personnes']);
        self::assertSame([], $monde['chronique']);
    }

    public function testLaFicheDitOuIlEstEtLeFiltreIsoleSaChronique(): void
    {
        static::createClient();
        $partie = $this->partie('personne-fiche@example.com');
        [$hori, $panehsy] = $this->deuxMarchands($partie);

        $monde = static::getContainer()->get(LeMonde::class)->pour($partie);

        self::assertCount(2, $monde['personnes']);
        $fiche = array_values(array_filter($monde['personnes'], static fn (array $f): bool => $f['identite'] === $hori->getIdentite()))[0];
        self::assertTrue($fiche['ici']);
        self::assertNotNull($fiche['route']);
        self::assertNull($fiche['pourquoi'], 'Sans Auberge, on voit ce qu\'il fait, pas pourquoi.');

        $autre = array_values(array_filter($monde['personnes'], static fn (array $f): bool => $f['identite'] === $panehsy->getIdentite()))[0];
        self::assertNotNull($autre['ailleurs'], 'Un marchand payé est ailleurs jusqu\'à son délai.');

        // La chronique, du plus récent au plus ancien ; filtrée, elle ne montre que lui.
        self::assertCount(3, $monde['chronique']);
        self::assertSame([12, 8, 3], array_column($monde['chronique'], 'quinzaine'));

        $filtre = static::getContainer()->get(LeMonde::class)->pour($partie, $panehsy->getIdentite());
        self::assertCount(2, $filtre['chronique']);
        self::assertSame([$panehsy->getNom()], array_unique(array_column($filtre['chronique'], 'nom')));
    }

    public function testUneAubergeDonneLeUnPourquoi(): void
    {
        static::createClient();
        $partie = $this->partie('personne-pourquoi@example.com');
        $this->deuxMarchands($partie);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Auberge, 1));

        $fiche = array_values(array_filter(
            static::getContainer()->get(LeMonde::class)->pour($partie)['personnes'],
            static fn (array $f): bool => $f['ici'],
        ))[0];

        self::assertStringContainsString('a choisi cette route', (string) $fiche['pourquoi']);
    }

    public function testLaChroniqueEstBornee(): void
    {
        $npc = new Npc($this->partie('personne-borne@example.com'), 9, RoleDePnj::Rival, 'Hori le marchand', TraitDeCandidat::Fidele);

        for ($i = 0; $i < 40; ++$i) {
            $npc->noter($i, 'lasse', 'canaan');
        }

        self::assertCount(Npc::HISTOIRE_MAX, $npc->getHistoire());
        self::assertSame(39, $npc->getHistoire()[0]['q'], 'Les plus récents sont gardés.');
    }

    public function testLaFenetreRendLesFichesEtLeFiltre(): void
    {
        $client = static::createClient();
        $partie = $this->partie('personne-rendu@example.com');
        [, $panehsy] = $this->deuxMarchands($partie);
        $client->loginUser($partie->getJoueur());

        $client->request('GET', \sprintf('/partie/%d/ville/monde?personne=%d', $partie->getId(), $panehsy->getIdentite()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Les personnes que vous connaissez');
        self::assertSelectorTextContains('body', 'Sa chronique');
        self::assertSelectorTextContains('body', 'est payé et s\'écarte');
        self::assertSelectorTextContains('body', 'Toute la chronique');
    }

    /**
     * @return array{0: Npc, 1: Npc} un marchand installé, un marchand payé qui est ailleurs
     */
    private function deuxMarchands(GameSave $partie): array
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $ville = $partie->getVille();

        $hori = new Npc($partie, 111, RoleDePnj::Rival, 'Hori le marchand', TraitDeCandidat::Econome);
        $hori->noter(12, 'installe', 'canaan');
        $panehsy = new Npc($partie, 222, RoleDePnj::Rival, 'Panehsy le marchand', TraitDeCandidat::Ambitieux);
        $panehsy->noter(3, 'installe', 'canaan');
        $panehsy->noter(8, 'paye', 'canaan');
        $panehsy->etrePaye(8, 12);

        $gestionnaire->persist($hori);
        $gestionnaire->persist($panehsy);
        $rival = new RivalCommercial($ville, 'canaan', 'Hori le marchand', 15, 10, 111, ['route' => 'canaan', 'prosperite' => 60, 'prestige' => 40, 'caractere' => 'econome', 'revenant' => false]);
        $ville->installerUnRival($rival);
        $gestionnaire->persist($rival);
        $gestionnaire->flush();

        return [$hori, $panehsy];
    }

    private function partie(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, 1));
        $ville->ajouterRouteCommerciale(new RouteCommerciale($ville, 'canaan', TypeDeRoute::Terrestre, 0));
        $gestionnaire->flush();

        return $partie;
    }
}
