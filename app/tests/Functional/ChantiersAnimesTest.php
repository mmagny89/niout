<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Entity\User;
use App\Fenetre\FigureDeLaVille;
use App\Fenetre\GensDeLaVille;
use App\Fenetre\VueDeLaVille;
use App\Game\LanceurDePartie;
use App\Game\PhotoDeLaVille;
use App\Game\RoleDeFigure;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les chantiers en cours se voient : des maçons sur le lot, et un lot qui s'anime.
 */
final class ChantiersAnimesTest extends WebTestCase
{
    public function testUnChantierEnCoursPoseSesMaconsSurSonLot(): void
    {
        static::createClient();
        $partie = $this->avecUnChantier('macons@example.com');

        $photo = static::getContainer()->get(PhotoDeLaVille::class)->prendre($partie);
        $macons = array_filter($photo['candidats'], static fn (array $c): bool => RoleDeFigure::Macon->value === $c['role']);

        self::assertCount(PhotoDeLaVille::MACONS_PAR_CHANTIER, $macons);
        self::assertSame(['forge'], array_values(array_unique(array_column($macons, 'lieu'))));

        $figures = static::getContainer()->get(GensDeLaVille::class)->pour($partie)['figures'];
        $surLeLot = array_filter($figures, static fn (FigureDeLaVille $f): bool => RoleDeFigure::Macon === $f->role);

        self::assertCount(PhotoDeLaVille::MACONS_PAR_CHANTIER, $surLeLot);

        foreach ($surLeLot as $macon) {
            self::assertSame(TypeDeBatiment::Forge, $macon->lieu, 'Sur le lot du chantier, pas à la Résidence (le repli).');
        }
    }

    public function testSansChantierPasDeMaconNiDAnimation(): void
    {
        static::createClient();
        $partie = $this->lancer('sans-chantier@example.com');

        $photo = static::getContainer()->get(PhotoDeLaVille::class)->prendre($partie);
        self::assertNotContains(RoleDeFigure::Macon->value, array_column($photo['candidats'], 'role'));

        foreach (static::getContainer()->get(VueDeLaVille::class)->pour($partie)['emplacements'] as $emplacement) {
            self::assertFalse($emplacement['chantier']);
        }
    }

    public function testLeLotDuChantierPorteLAnimation(): void
    {
        $client = static::createClient();
        $partie = $this->avecUnChantier('animation@example.com');
        $client->loginUser($partie->getJoueur());

        $animes = array_filter(
            static::getContainer()->get(VueDeLaVille::class)->pour($partie)['emplacements'],
            static fn (array $e): bool => $e['chantier'],
        );

        self::assertCount(1, $animes);
        self::assertSame(TypeDeBatiment::Forge, array_values($animes)[0]['type']);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.chantier[aria-hidden="true"]'), 'Un lot animé, décoratif.');
    }

    public function testLaFicheDesMaconsDitLeResteAFaire(): void
    {
        $client = static::createClient();
        $partie = $this->avecUnChantier('fiche-macons@example.com');
        $client->loginUser($partie->getJoueur());

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=macon-forge', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#fiche-titre', 'Maçons du chantier');
        self::assertSelectorTextContains('body', 'Reste à bâtir');

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=macon-temple', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * **Chaque rôle a sa couleur dans les deux gabarits** (la ville et le film). Un rôle neuf oublié dans l'un
     * des deux fait lever une 500 au rendu — défaut réel, vu avec les maçons, que rien ne voyait tant qu'aucune
     * quinzaine n'avait photographié un chantier.
     */
    public function testChaqueRoleACouleurDansLesDeuxGabarits(): void
    {
        foreach (['_figures_de_la_ville', '_film_de_la_ville'] as $gabarit) {
            $source = (string) file_get_contents(\dirname(__DIR__, 2).'/templates/partie/'.$gabarit.'.html.twig');

            foreach (RoleDeFigure::cases() as $role) {
                self::assertStringContainsString($role->value.": 'text-", $source, \sprintf('%s sans couleur pour « %s »', $gabarit, $role->value));
            }
        }
    }

    /**
     * Le parcours entier : un chantier, une quinzaine qui passe, et la ville se rend avec le film de ses maçons.
     */
    public function testLeFilmDUneQuinzaineDeChantierSeRend(): void
    {
        $client = static::createClient();
        $partie = $this->avecUnChantier('film-chantier@example.com');
        $client->loginUser($partie->getJoueur());

        static::getContainer()->get(\App\Game\PassageDeCycle::class)->passer($partie);

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville&film=1', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-ville-vivante-lancer-value="true"]'));
        self::assertGreaterThanOrEqual(PhotoDeLaVille::MACONS_PAR_CHANTIER, \count($crawler->filter('[data-ville-vivante-target="film"] [data-part="debout"]')));
    }

    private function avecUnChantier(string $email): GameSave
    {
        $partie = $this->lancer($email);
        $ville = $partie->getVille();
        $ville->ajouterChantier(new Chantier($ville, TypeDeBatiment::Forge, 1));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }
}
