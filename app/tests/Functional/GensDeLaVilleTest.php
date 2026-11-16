<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\RivalCommercial;
use App\Entity\User;
use App\Fenetre\FigureDeLaVille;
use App\Fenetre\GensDeLaVille;
use App\Game\Candidat;
use App\Game\Effectifs;
use App\Game\LanceurDePartie;
use App\Game\RoleDeFigure;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La ville habitée (`docs/plan-pnj-ville-vivante.md` § 15, lot V1).
 *
 * **Une silhouette dit une chose vraie** : ces tests gardent le lien entre ce que
 * le visuel montre et ce que le jeu sait. Le placement, lui — des gens bien
 * posés devant leur bâtiment, sans recouvrir un lien —, ne se voit qu'au
 * navigateur.
 */
final class GensDeLaVilleTest extends WebTestCase
{
    /**
     * Une représentation, pas un état : la même ville rend les mêmes figures, à
     * la virgule près. Sans cela, un rechargement recomposerait la ville sous les
     * yeux du joueur.
     */
    public function testLaMemeVilleRendLesMemesFigures(): void
    {
        static::createClient();
        $partie = $this->lancerAvecDuMonde('determinisme@example.com');

        self::assertEquals($this->gens()->pour($partie), $this->gens()->pour($partie));
    }

    /**
     * Les désignés d'abord : le chef de famille, chaque chef en poste, chaque
     * Medjaÿ. Une ville bondée coupe la foule, jamais un chef.
     */
    public function testUneVilleBondeeCoupeLaFouleEtJamaisUnDesigne(): void
    {
        static::createClient();
        $partie = $this->lancerAvecDuMonde('plafond@example.com');
        $ville = $partie->getVille();
        $ville->accueillir(200, 120, 20);

        $figures = $this->gens()->pour($partie)['figures'];
        $cles = array_map(static fn (FigureDeLaVille $f): string => $f->cle, $figures);

        self::assertLessThanOrEqual(GensDeLaVille::PLAFOND_DE_FIGURES, \count($figures));
        self::assertContains('famille', $cles);

        foreach ($ville->getEmployes() as $chef) {
            self::assertContains('chef-'.$chef->getId(), $cles, 'Un chef en poste ne se coupe jamais.');
        }

        foreach ($ville->getMedjays() as $medjay) {
            self::assertContains('medjay-'.$medjay->getId(), $cles, 'Un Medjaÿ ne se coupe jamais.');
        }
    }

    /**
     * **Fidélité** : un chef qui prend son poste à la quinzaine suivante n'est
     * pas encore à l'ouvrage, donc pas à son bâtiment ; un chef en poste y est.
     */
    public function testSeuleUnChefEnPosteSeVoitASonBatiment(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('chefs@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));

        $enPoste = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $enPoste->donnerUnPrenom('Nebnefer');
        $arrive = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle() + 1);
        $arrive->donnerUnPrenom('Paneb');
        $ville->ajouterEmploye($enPoste);
        $ville->ajouterEmploye($arrive);
        $this->enregistrer();

        $figures = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Chef);

        self::assertCount(1, $figures);
        self::assertSame('chef-'.$enPoste->getId(), $figures[0]->cle);
        self::assertSame(TypeDeBatiment::Grenier, $figures[0]->lieu);
        self::assertStringContainsString('Nebnefer', $figures[0]->libelle);
    }

    /**
     * Un Medjaÿ disponible est debout à la Caserne ; un blessé est couché au
     * Quartier, et se relève quand il est rétabli.
     */
    public function testUnMedjayBlesseEstCoucheEtSeRelevera(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('medjays@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 1));
        $debout = new Medjay($ville, SpecialisationMedjay::Fantassin, 'Hori');
        $blesse = new Medjay($ville, SpecialisationMedjay::Fantassin, 'Baki');
        $blesse->blesser($partie->getCycle(), 3);
        $ville->leverUnMedjay($debout);
        $ville->leverUnMedjay($blesse);
        $this->enregistrer();

        $hommes = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Medjay);
        $debout = array_values(array_filter($hommes, static fn (FigureDeLaVille $f): bool => !$f->couchee));
        $couches = array_values(array_filter($hommes, static fn (FigureDeLaVille $f): bool => $f->couchee));

        self::assertCount(1, $debout);
        self::assertCount(1, $couches);
        self::assertSame(TypeDeBatiment::Caserne, $debout[0]->lieu);
        self::assertStringContainsString('Hori', $debout[0]->libelle);
        self::assertSame(TypeDeBatiment::QuartierDHabitation, $couches[0]->lieu);
        self::assertStringContainsString('blessé', $couches[0]->libelle);

        // Rétabli, il se relève : le même état, quelques quinzaines plus tard.
        foreach (range(1, 5) as $ignore) {
            $partie->avancerDUnCycle();
        }

        $apres = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Medjay);

        self::assertCount(0, array_filter($apres, static fn (FigureDeLaVille $f): bool => $f->couchee));
    }

    /**
     * **Pas de fantôme** : jamais plus de silhouettes représentatives qu'un
     * bâtiment n'a de travailleurs, et **aucune** dans un bâtiment à l'arrêt.
     */
    public function testLesTravailleursNeDepassentJamaisLesEffectifs(): void
    {
        static::createClient();
        $partie = $this->lancerAvecDuMonde('effectifs@example.com');
        $ville = $partie->getVille();

        $figures = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Travailleur);
        $parBatiment = [];

        foreach ($figures as $figure) {
            self::assertNotNull($figure->lieu);
            $parBatiment[$figure->lieu->value] = ($parBatiment[$figure->lieu->value] ?? 0) + 1;
        }

        foreach (Effectifs::repartir($ville, $partie->getCycle()) as $cle => $poste) {
            self::assertLessThanOrEqual(
                min($poste['affectes'], GensDeLaVille::TRAVAILLEURS_PAR_BATIMENT),
                $parBatiment[$cle] ?? 0,
                \sprintf('%s : jamais plus de silhouettes que de travailleurs.', $cle),
            );

            if (0 === $poste['affectes']) {
                self::assertArrayNotHasKey($cle, $parBatiment, 'Un bâtiment à l\'arrêt n\'a personne à l\'écran.');
            }
        }
    }

    /**
     * Les malades se couchent parmi les maisons : jamais plus de figures
     * couchées au Quartier que la ville n'a de malades.
     */
    public function testLesMaladesSeCouchentAuQuartier(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('malades@example.com');
        $ville = $partie->getVille();
        $ville->accueillir(30, 20, 5);
        $ville->declarerUneEpidemie(4, 40);

        $foyers = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Foyer);
        $couchees = array_filter($foyers, static fn (FigureDeLaVille $f): bool => $f->couchee);

        self::assertNotEmpty($couchees, 'Une épidémie se voit.');
        self::assertLessThanOrEqual($ville->malades(), \count($couchees));
        self::assertLessThanOrEqual(GensDeLaVille::MAISONS_MONTREES, \count($foyers));
    }

    /**
     * Le rival se tient au Marché, sous son nom.
     */
    public function testLeRivalSeTientAuMarche(): void
    {
        static::createClient();
        $partie = $this->lancerPartie('rival@example.com');
        $ville = $partie->getVille();
        $ville->installerUnRival(new RivalCommercial($ville, 'canaan', 'Panehsy le marchand', 15, 10));

        $figures = $this->parRole($this->gens()->pour($partie)['figures'], RoleDeFigure::Rival);

        self::assertCount(1, $figures);
        self::assertSame(TypeDeBatiment::Marche, $figures[0]->lieu);
        self::assertSame('Panehsy le marchand', $figures[0]->libelle);
    }

    /**
     * Toute silhouette reste dans le visuel, et les désignés ont une clé
     * propre : c'est elle qui ouvre leur fiche.
     */
    public function testChaqueFigureResteDansLeVisuelEtLesDesignesOntUneCleUnique(): void
    {
        static::createClient();
        $partie = $this->lancerAvecDuMonde('bornes@example.com');
        $partie->getVille()->accueillir(60, 30, 10);

        $cles = [];

        foreach ($this->gens()->pour($partie)['figures'] as $figure) {
            self::assertGreaterThanOrEqual(0.0, $figure->gauche);
            self::assertGreaterThanOrEqual(0.0, $figure->haut);
            self::assertLessThanOrEqual(100.0, $figure->gauche + $figure->largeur);
            self::assertLessThanOrEqual(100.0, $figure->haut);

            if (!str_starts_with($figure->cle, 'poste-') && 'sans-poste' !== $figure->cle) {
                self::assertNotContains($figure->cle, $cles, 'Une clé de désigné est unique.');
                $cles[] = $figure->cle;
            }
        }
    }

    /**
     * La ville vue d'en haut porte les silhouettes **comme du décor** — masquées
     * aux lecteurs d'écran, hors de l'ordre de tabulation — et, à côté, le bouton
     * de la liste équivalente et la mention de reconstitution.
     */
    public function testLaVueDeLaVillePorteLesFiguresLeBoutonEtLaMention(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'vue@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/carte?vue=ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('svg symbol#fig-habitant')->count(), 'Les silhouettes sont définies une fois.');
        self::assertGreaterThan(0, $crawler->filter('div[aria-hidden="true"] a[tabindex="-1"][href*="/ville/gens?cle="]')->count());
        self::assertSelectorTextContains('body', 'Image reconstituée');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/partie/%d/ville/gens"]', $partie->getId())));
        self::assertSame(0, $crawler->filter('div[aria-hidden="true"] a:not([tabindex="-1"])')->count(), 'Aucun lien du calque ne prend le focus.');
    }

    /**
     * La liste est la voie équivalente : tout ce que le visuel montre s'y lit.
     */
    public function testLaListeDitLesGensEnToutesLettres(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'liste@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(70, 5, 20, [], null, 2, 0), $partie->getCycle());
        $chef->donnerUnPrenom('Nebnefer');
        $ville->ajouterEmploye($chef);
        $this->enregistrer();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville/gens', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Nebnefer — chef (Grenier)');
        self::assertSelectorTextContains('body', 'Image reconstituée');
        self::assertCount(1, $crawler->filter(\sprintf('a[href="/partie/%d/ville/gens?cle=chef-%d"]', $partie->getId(), $chef->getId())));
    }

    /**
     * Une fiche de chef dit ce qui est vrai, et jamais la compétence chiffrée
     * (doc 03 : qualitatif à l'affichage).
     */
    public function testLaFicheDUnChefEstQualitative(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'fiche@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 4));
        $chef = new Employee($ville, TypeDeBatiment::Grenier, new Candidat(70, 5, 20, [], null, 2, 0), $partie->getCycle());
        $chef->donnerUnPrenom('Nebnefer');
        $ville->ajouterEmploye($chef);
        $this->enregistrer();

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=chef-%d', $partie->getId(), $chef->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#fiche-titre', 'Nebnefer');
        self::assertSelectorTextContains('body', '4 étoiles sur 5');
        self::assertStringNotContainsString('70', (string) $client->getResponse()->getContent(), 'La compétence ne s\'imprime pas.');
    }

    /**
     * Une maison est dite représentative : le jeu compte des habitants, pas des
     * individus.
     */
    public function testUneMaisonEstDiteRepresentative(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'maison@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=maison-1', $partie->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Représentatif');
        self::assertSelectorTextContains('#fiche-titre', 'Maison 1');
    }

    /**
     * **Une clé forgée ne rend rien** : un chef d'une autre partie, une maison qui
     * n'existe pas, un nom inventé — 404, jamais la fiche d'un autre.
     */
    public function testUneCleQuiNeDesigneRienDansCetteVilleRendUne404(): void
    {
        $client = static::createClient();
        $autre = $this->lancerAvecDuMonde('autre-ville@example.com');
        $chefDUnAutre = $autre->getVille()->getEmployes()->first();
        self::assertInstanceOf(Employee::class, $chefDUnAutre);

        $partie = $this->lancerConnecte($client, 'forge@example.com');

        foreach (['chef-'.$chefDUnAutre->getId(), 'maison-9999', 'medjay-0', 'poste-inconnu', 'n-importe-quoi'] as $cle) {
            $client->request('GET', \sprintf('/partie/%d/ville/gens?cle=%s', $partie->getId(), $cle), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);
            self::assertResponseStatusCodeSame(404, $cle);
        }
    }

    /**
     * Les gens de la ville d'une autre joueuse ne se voient pas.
     */
    public function testUnJoueurNeVoitPasLesGensDUnAutre(): void
    {
        $client = static::createClient();
        $proprietaire = $this->lancerPartie('proprio-gens@example.com');

        $this->lancerConnecte($client, 'intrus-gens@example.com');
        $client->request('GET', \sprintf('/partie/%d/ville/gens', $proprietaire->getId()), [], [], ['HTTP_TURBO_FRAME' => 'fenetre']);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Sans l'en-tête de cadre, on rend la carte avec la fenêtre déjà ouverte :
     * l'adresse est partageable et un rechargement la retrouve.
     */
    public function testUneAdresseTapeeOuvreLaFenetreSurLaCarte(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'adresse@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville/gens', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('dialog[open] turbo-frame#fenetre');
        self::assertSelectorTextContains('dialog', 'Les gens de la ville');
    }

    /**
     * @param list<FigureDeLaVille> $figures
     *
     * @return list<FigureDeLaVille>
     */
    private function parRole(array $figures, RoleDeFigure $role): array
    {
        return array_values(array_filter($figures, static fn (FigureDeLaVille $f): bool => $f->role === $role));
    }

    private function lancerAvecDuMonde(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        foreach ([TypeDeBatiment::Grenier, TypeDeBatiment::Atelier, TypeDeBatiment::Forge] as $type) {
            $ville->ajouterBatiment(new Building($ville, $type, 4));
        }

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 5));

        foreach ([TypeDeBatiment::Grenier, TypeDeBatiment::Atelier] as $type) {
            $chef = new Employee($ville, $type, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
            $chef->donnerUnPrenom(['Hori', 'Baki'][TypeDeBatiment::Grenier === $type ? 0 : 1]);
            $ville->ajouterEmploye($chef);
        }

        foreach (range(1, 8) as $ignore) {
            $ville->leverUnMedjay(new Medjay($ville, SpecialisationMedjay::Fantassin));
        }

        $this->enregistrer();

        return $partie;
    }

    private function lancerPartie(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function lancerConnecte(KernelBrowser $client, string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();
        $client->loginUser($joueur);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function enregistrer(): void
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function gens(): GensDeLaVille
    {
        return static::getContainer()->get(GensDeLaVille::class);
    }
}
