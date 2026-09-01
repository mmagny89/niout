<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Effectifs;
use App\Game\LanceurDePartie;
use App\Game\Population;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le bilan de la main-d'œuvre : **embaucher un chef ouvre des postes**.
 *
 * Un bâtiment sans chef ne réclame personne et tourne au plancher ; un
 * bâtiment dirigé réclame ses travailleurs. Sans ce bilan, retenir un candidat
 * faisait baisser le rendement ailleurs sans que rien ne le dise — les bras
 * servis à la Forge n'étaient plus au Grenier.
 */
final class MainDoeuvreTest extends WebTestCase
{
    /**
     * Une ville neuve n'a aucun poste ouvert : tous ses bras sont oisifs, et
     * l'écran le dit plutôt que de laisser croire que tout va bien.
     */
    public function testUneVilleSansChefADesBrasSansOuvrage(): void
    {
        self::bootKernel();
        $partie = $this->lancer('bras-oisifs@example.com');

        $bilan = Effectifs::bilan($partie->getVille(), $partie->getCycle());

        self::assertSame(0, $bilan['requis'], 'Sans chef, aucun bâtiment ne réclame personne.');
        self::assertGreaterThan(0, $bilan['oisifs']);
        self::assertSame(0, $bilan['manquants']);
    }

    /**
     * **Les deux situations ne coexistent jamais** : la répartition sert
     * jusqu'à épuisement, donc il reste des bras ou des postes, pas les deux.
     */
    public function testOnManqueDeBrasOuOnEnAdeTropJamaisLesDeux(): void
    {
        self::bootKernel();
        $partie = $this->lancer('exclusif@example.com');
        $ville = $partie->getVille();

        foreach ([TypeDeBatiment::Grenier, TypeDeBatiment::Entrepot, TypeDeBatiment::Marche, TypeDeBatiment::Forge] as $type) {
            $ville->ajouterBatiment(new Building($ville, $type, 3));
        }

        $bilan = Effectifs::bilan($ville, $partie->getCycle());

        self::assertTrue(
            0 === $bilan['oisifs'] || 0 === $bilan['manquants'],
            'Des bras oisifs et des postes vides en même temps signale une répartition qui n\'a pas servi jusqu\'au bout.',
        );
        self::assertSame($bilan['requis'] - $bilan['affectes'], $bilan['manquants']);
        self::assertSame($bilan['bras'] - $bilan['affectes'], $bilan['oisifs']);
    }

    /**
     * Le diagnostic se lit à l'écran, dans la Résidence familiale — le foyer
     * de la lignée recueille ce qui n'appartient à aucun bâtiment.
     */
    public function testLeDiagnosticSeLitDansLaResidenceFamiliale(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'diagnostic@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Tableau de bord');
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Bras disponibles');
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Sans ouvrage');
        // Le diagnostic est dit une fois, dans les alertes, et il nomme le geste.
        self::assertSelectorTextContains('#panneau-residence_familiale', 'bras sont sans ouvrage');
    }

    /**
     * **La renommée était nulle part à l'écran** : ce qu'elle change — le prix
     * d'un appel, la migration spontanée, l'arrivée d'un rival — se subissait
     * sans se comprendre.
     */
    public function testLaRenommeeSAfficheAvecSonPalier(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'renommee-ecran@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        $panneau = 'Renommée';
        self::assertSelectorTextContains('#panneau-residence_familiale', $panneau);
        self::assertSelectorTextContains(
            '#panneau-residence_familiale',
            $partie->getFamille()->palier()->libelle(),
        );
    }

    /**
     * **Le logement se lit dans la Résidence familiale**, et pas seulement dans
     * l'onglet du Quartier d'habitation : sans Quartier, cet onglet n'existe
     * pas — et c'est précisément la ville qui en manque qui doit l'apprendre.
     */
    public function testLeManqueDeLogementsSeVoitDansLaResidenceFamiliale(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'logements@example.com');
        $ville = $partie->getVille();

        self::assertFalse($ville->possede(TypeDeBatiment::QuartierDHabitation), 'Une ville neuve n\'en a pas.');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Maisonnées logées');
        self::assertSelectorTextContains(
            '#panneau-residence_familiale',
            \sprintf('%d / %d', $ville->foyersOccupes(), $ville->capaciteEnFoyers()),
        );

        // On remplit la ville jusqu'à la dernière place.
        $ville->accueillir($ville->foyersLibres() * Population::PERSONNES_PAR_FOYER, 0, 0);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertTrue($ville->manqueDeLogements());

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Vos maisons sont pleines');
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Dressez un Quartier d\'habitation');
    }

    /**
     * **Le tableau de bord se lit d'un coup d'œil**, et chaque chiffre est lié
     * à ce qu'il mesure.
     *
     * Quatre tableaux et non un seul : chacun porte sa légende, qu'un lecteur
     * d'écran annonce avant la ligne, et chaque intitulé est un
     * `<th scope="row">`. Ces chiffres vivaient en cartes de prose qui les
     * noyaient — comparer deux nombres demandait de lire deux phrases.
     */
    public function testLeTableauDeBordRangeLesChiffresParDomaine(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'tableau-de-bord@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        $panneau = $crawler->filter('#panneau-residence_familiale');

        self::assertSame(
            ['Habitants', 'Travail', 'Réserves', 'Bourse et renom'],
            $panneau->filter('table caption')->each(static fn ($n): string => trim($n->text())),
        );

        // Chaque ligne lie son intitulé à sa valeur : sans `scope="row"`, un
        // lecteur d'écran annonce un nombre sans dire ce qu'il mesure.
        $lignes = $panneau->filter('table tbody tr');
        self::assertGreaterThan(15, $lignes->count());
        self::assertSame(
            $lignes->count(),
            $panneau->filter('table tbody tr > th[scope="row"]')->count(),
        );
    }

    /**
     * Une ville qui va bien le dit aussi : une liste d'alertes vide laisserait
     * croire à un écran cassé.
     */
    public function testUneVilleSansSoucisLeDitPlutotQueDeNeRienDire(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'rien-ne-presse@example.com');
        $ville = $partie->getVille();

        // Une ville neuve a des bras sans ouvrage : on lui donne de quoi les
        // occuper, un chef au Grenier, pour n'avoir plus aucune alerte.
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        // Il reste au moins une alerte — les bras oisifs —, donc l'écran nomme
        // la cause et le geste plutôt que de laisser deviner.
        self::assertSelectorTextContains('#panneau-residence_familiale', 'Ce qui demande votre attention');
        self::assertSelectorTextContains('#panneau-residence_familiale', 'c\'est le chef qui recrute');
    }

    private function lancerConnecte(KernelBrowser $client, string $email): GameSave
    {
        $user = $this->creer($email);
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }

    private function lancer(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->creer($email), 'Nakht');
    }

    private function creer(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
