<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Fenetre\FigureDeLaVille;
use App\Fenetre\GensDeLaVille;
use App\Game\Enigme;
use App\Game\LanceurDePartie;
use App\Game\PhotoDeLaVille;
use App\Game\RoleDeFigure;
use App\Game\TypeDeBatiment;
use App\Game\VisiteurDePassage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le voyageur de l'Auberge et le prêtre du Temple (lot I2).
 *
 * Du décor qui renvoie vers une énigme **qui existe déjà** : le lieu décide, l'énigme doit être jouable,
 * et la silhouette ne dit jamais un mot de l'énoncé.
 */
final class VisiteursDePassageTest extends WebTestCase
{
    public function testSansAubergeNiTempleNiVoyageurNiPretre(): void
    {
        static::createClient();
        $partie = $this->lancer('sans-lieu@example.com');
        $this->construire($partie, TypeDeBatiment::MaisonDesScribes);

        self::assertSame([], VisiteurDePassage::pour($partie->getVille()), 'Les scribes sont déjà en ville : pas de visiteur chez eux.');
    }

    /**
     * **Le lieu décide** : la devinette se dit à l'Auberge, l'oracle au Temple, jamais l'inverse.
     */
    public function testLeLieuDecideQuiPasse(): void
    {
        static::createClient();
        $partie = $this->lancer('lieu-decide@example.com');
        $this->construire($partie, TypeDeBatiment::Auberge);

        $visiteurs = VisiteurDePassage::pour($partie->getVille());
        self::assertCount(1, $visiteurs);
        self::assertSame(RoleDeFigure::Voyageur, $visiteurs[0]['role']);
        self::assertSame(TypeDeBatiment::Auberge, $visiteurs[0]['lieu']);

        $this->construire($partie, TypeDeBatiment::Temple);
        $visiteurs = VisiteurDePassage::pour($partie->getVille());

        self::assertSame([RoleDeFigure::Voyageur, RoleDeFigure::Pretre], array_column($visiteurs, 'role'));
        self::assertSame([TypeDeBatiment::Auberge, TypeDeBatiment::Temple], array_column($visiteurs, 'lieu'));
    }

    /**
     * **Rien sans fait** : une énigme jouée ne se repose pas. Quand toutes celles du lieu le sont,
     * le voyageur s'en va.
     */
    public function testLeVoyageurSenVaQuandToutEstJoue(): void
    {
        static::createClient();
        $partie = $this->lancer('tout-joue@example.com');
        $ville = $partie->getVille();
        $this->construire($partie, TypeDeBatiment::Auberge);
        $this->construire($partie, TypeDeBatiment::Temple);

        foreach (Enigme::cases() as $enigme) {
            if (TypeDeBatiment::Auberge === $enigme->lieu()) {
                $ville->tenterUneEnigme($enigme);
            }
        }

        $this->enregistrer();

        self::assertSame([RoleDeFigure::Pretre], array_column(VisiteurDePassage::pour($ville), 'role'));
    }

    /**
     * Du décor de la ville d'à présent : sur la carte et dans la liste, jamais dans la photographie
     * (donc jamais dans une quinzaine rejouée, ni dans la trace).
     */
    public function testIlsSeVoientEnVilleMaisJamaisDansLaPhotographie(): void
    {
        static::createClient();
        $partie = $this->lancer('decor@example.com');
        $this->construire($partie, TypeDeBatiment::Auberge);

        $gens = static::getContainer()->get(GensDeLaVille::class)->pour($partie);
        $voyageurs = array_values(array_filter($gens['figures'], static fn (FigureDeLaVille $f): bool => RoleDeFigure::Voyageur === $f->role));

        self::assertCount(1, $voyageurs);
        self::assertSame(TypeDeBatiment::Auberge, $voyageurs[0]->lieu);
        self::assertSame('visiteur-voyageur', $voyageurs[0]->cle);

        $lignes = [];

        foreach ($gens['lieux'] as $lieu) {
            foreach ($lieu['lignes'] as $ligne) {
                $lignes[] = $ligne['cle'];
            }
        }

        self::assertContains('visiteur-voyageur', $lignes, 'La liste, voie équivalente, le dit aussi.');

        $photo = static::getContainer()->get(PhotoDeLaVille::class)->prendre($partie);
        $roles = array_column($photo['candidats'], 'role');
        self::assertNotContains(RoleDeFigure::Voyageur->value, $roles);
        self::assertNotContains(RoleDeFigure::Pretre->value, $roles);
    }

    /**
     * La fiche **invite** et renvoie à l'énigme existante : ni énoncé, ni réponse, ni explication.
     */
    public function testLaFicheInviteSansRienDireDeLEnigme(): void
    {
        $client = static::createClient();
        $partie = $this->lancer('fiche-visiteur@example.com');
        $client->loginUser($partie->getJoueur());
        $this->construire($partie, TypeDeBatiment::Auberge);

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=visiteur-voyageur', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#fiche-titre', 'Un voyageur de passage');
        $page = html_entity_decode((string) $client->getResponse()->getContent());
        self::assertStringContainsString('Une devinette, étranger ?', $page);
        self::assertStringContainsString('onglet=auberge', $page);

        foreach (Enigme::cases() as $enigme) {
            if (TypeDeBatiment::Auberge === $enigme->lieu()) {
                self::assertStringNotContainsString($enigme->enonce(), $page);
                self::assertStringNotContainsString($enigme->bonneReponse(), $page);
                self::assertStringNotContainsString($enigme->explication(), $page);
            }
        }
    }

    /**
     * Pas de voyageur sans Auberge : la clé ne désigne rien, c'est une 404 comme pour toute clé inconnue.
     */
    public function testSansAubergeLaFicheDuVoyageurEstIntrouvable(): void
    {
        $client = static::createClient();
        $partie = $this->lancer('sans-auberge@example.com');
        $client->loginUser($partie->getJoueur());

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=visiteur-voyageur', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseStatusCodeSame(404);
    }

    private function construire(GameSave $partie, TypeDeBatiment $type): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, $type, 2));
        $this->enregistrer();
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

    private function enregistrer(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }
}
