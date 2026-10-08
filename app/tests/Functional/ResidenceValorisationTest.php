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
 * Ce que la Résidence met sous les yeux du joueur pour qu'il comprenne les
 * leviers du jeu : l'impôt, l'écriture, les chefs.
 */
final class ResidenceValorisationTest extends WebTestCase
{
    public function testLaResidenceAnnonceLImpotEtSonEcheance(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-impot@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Impôt du mois');
        self::assertSelectorTextContains('body', 'perçu dans 2 quinzaines');
    }

    public function testLaResidenceMontreCeQueRapporteLEcriture(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-ecriture@example.com');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('body', 'Les écritures de votre ville');
        self::assertSelectorTextContains('body', 'Néophyte');
        self::assertSelectorTextContains('body', 'Maison des scribes');
    }

    public function testUnBatimentSansChefEstSignale(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-chef@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        self::assertSelectorTextContains('body', 'sans chef');
        self::assertSelectorTextContains('body', 'Grenier');
    }

    public function testLAnnonceDitCeQueVautUnChef(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-annonce@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Sans chef, ce bâtiment ne dépasse pas 50 %');
        self::assertSelectorTextContains('body', 'Spécialités possibles ici');
    }

    /**
     * Ce qu'il reste à bâtir se lit en cartes : le sprite du bâtiment, son coût en pastilles (avec les
     * illustrations des ressources), et ce qu'on peut engager passe avant ce qui est bloqué.
     */
    public function testLesBatimentsABatirSontDesCartesAvecSpriteEtCoutEnPastilles(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-a-batir@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();

        $cartes = $crawler->filter('#residence-section-batiments li[data-etat="a-batir"], #residence-section-batiments li[data-etat="bloque"]');
        self::assertGreaterThan(3, $cartes->count(), 'Une ville neuve a de quoi bâtir.');
        self::assertCount($cartes->count(), $cartes->filter('img[src*="/images/ville/batiments/"]'), 'Chaque carte porte le sprite de son bâtiment.');
        self::assertGreaterThan(0, $cartes->first()->filter('ul[aria-label="Ce que coûte le chantier"] li')->count());

        // Réalisables d'abord : une fois un bloqué rencontré, plus aucun réalisable ne suit.
        $boutons = $cartes->each(static fn ($carte): bool => $carte->filter('form[action$="/batir"]')->count() > 0);
        $bloque = false;
        foreach ($boutons as $realisable) {
            if (!$realisable) {
                $bloque = true;
            }
            self::assertFalse($bloque && $realisable, 'Ce qu\'on peut engager passe avant ce qui est bloqué.');
        }
    }

    /**
     * Le salaire des bras se règle au seul curseur ; un champ caché reste la source soumise : le
     * curseur, le verdict et le champ vivent dans le même contrôleur (une cible hors de son contrôleur
     * n'est jamais trouvée, sans erreur), et le verdict arrive en trois textes déjà rendus.
     */
    public function testLeSalaireDesBrasSeRegleAuSeulCurseurSurUnChampCache(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'salaire-curseur@example.com');

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertResponseIsSuccessful();

        $formulaire = $crawler->filter('#residence-section-gouvernement form[data-controller="curseur"]');
        self::assertCount(1, $formulaire);
        self::assertSame('true', $formulaire->attr('data-curseur-mauvais-en-bas-value'), 'Un salaire trop bas est le mauvais côté.');
        self::assertCount(1, $formulaire->filter('input[type="hidden"]#salaire[data-curseur-target="champ"][name="salaire"]'));
        self::assertCount(0, $formulaire->filter('input[type="number"]'), 'La jauge ne se double pas d\'un champ numérique.');
        self::assertCount(1, $formulaire->filter('input[type="range"][data-curseur-target="piste"]'));
        self::assertCount(1, $formulaire->filter('[data-curseur-target="nombre"]'));
        $verdict = $formulaire->filter('[data-curseur-target="verdict"]');
        self::assertCount(1, $verdict);
        foreach (['data-bas', 'data-milieu', 'data-haut'] as $attribut) {
            self::assertNotSame('', trim((string) $verdict->attr($attribut)), $attribut);
        }
    }

    /**
     * Un bâtiment dressé n'est plus « à bâtir » : il porte son bouton
     * « Améliorer » sur sa propre carte.
     */
    public function testUnBatimentDresseSeMonteSurSaCarteEtQuitteLaListeABatir(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'valorisation-ameliorer@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));

        // Une seule liste de cartes : les dressés, puis ce qu'il reste à bâtir.
        $dresses = $crawler->filter('#residence-section-batiments li[data-etat="dresse"]');
        $aBatir = $crawler->filter('#residence-section-batiments li[data-etat="a-batir"], #residence-section-batiments li[data-etat="bloque"]');

        self::assertCount(1, $crawler->filter('#residence-section-batiments ul.grid'), 'Dressés et à bâtir ne font qu\'une liste.');
        self::assertStringContainsString('Grenier', $dresses->text());
        self::assertStringContainsString('Améliorer', $dresses->text());
        self::assertStringNotContainsString('Grenier', $aBatir->text(), 'Déjà dressé : il ne reste pas à bâtir.');
        self::assertStringNotContainsString('Améliorer', $aBatir->text());
    }

    /**
     * Le Quartier montre ses habitants rangés en maisons, chacune décrite en
     * toutes lettres : le dessin seul ne se lit pas à l'oreille.
     */
    public function testLeQuartierDessineLesMaisonnees(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'quartier-maisons@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=quartier_habitation', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('[role="img"][aria-label^="Maisonnée 1 :"]')->count());
        self::assertSelectorTextContains('body', 'Maison libre');
        self::assertSelectorTextContains('body', 'Alité par la fièvre');
    }

    /**
     * Le Marché propose d'emblée la quantité que la place absorbe, et montre sa
     * place du jour en jauge.
     */
    public function testLeMarcheProposeLaQuantiteQueLaPlaceAbsorbe(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'marche-quantite@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->crediterRessources([\App\Game\Ressource::Poterie->value => 500]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=marche', $partie->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'La place du jour');
        // La place du jour est une tuile : son chiffre est écrit, la jauge est décorative.
        self::assertSelectorTextContains('body', 'écoulés sur');
        self::assertGreaterThan(0, $crawler->filter('[data-controller="vente"]')->count(), 'Chaque lot montre ce que sa vente rapporterait.');

        $quantite = (int) $crawler->filter('#quantite-poterie')->attr('value');
        self::assertGreaterThan(1, $quantite, 'Une place neuve absorbe plus d\'une poterie.');
    }

    /**
     * L'Entrepôt range ses seuils en cartes à curseur : le champ numérique reste la source
     * soumise (il marche sans JavaScript), le curseur le double.
     */
    public function testLEntrepotRepartitLesSeuilsEnCartesACurseur(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'entrepot-curseurs@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot));
        $ville->crediterRessources([\App\Game\Ressource::Argile->value => 40]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=entrepot', $partie->getId()));

        self::assertResponseIsSuccessful();
        $formulaire = $crawler->filter('form[data-controller="curseur"][data-curseur-mode-value="part"]')->reduce(
            static fn ($n): bool => 1 === $n->filter('input#garde-argile')->count(),
        );
        self::assertCount(1, $formulaire, 'Chaque ressource en réserve a sa carte et son curseur.');
        self::assertSame('0', $formulaire->filter('input#garde-argile')->attr('min'));
        self::assertGreaterThan(0, $formulaire->filter('input[name="_token"]')->count(), 'Le formulaire reste soumis par le champ.');
    }

    /**
     * Le contrôleur d'aperçu des lots est sur la carte de la recette, **au-dessus** de ce
     * qu'il met à jour : une cible hors de son contrôleur n'est jamais trouvée, et rien ne
     * le dit à l'écran. Aucun test fonctionnel n'exécute le JavaScript — la parade est
     * cette assertion de structure.
     */
    public function testLApercuDesLotsEnglobeLesMatieresEtLeChamp(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'forge-lots@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge));
        $ville->crediterRessources([\App\Game\Ressource::Cuivre->value => 50, \App\Game\Ressource::BoisLocal->value => 50]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=forge', $partie->getId()));

        self::assertResponseIsSuccessful();
        $cartes = $crawler->filter('li[data-controller="lots"]');
        self::assertGreaterThan(0, $cartes->count());
        $cartes->each(static function ($carte): void {
            self::assertGreaterThan(0, $carte->filter('[data-lots-target="matiere"][data-base]')->count(), 'Les matières sont dans le contrôleur.');
            self::assertGreaterThan(0, $carte->filter('input[data-lots-target="lots"]')->count());
            self::assertGreaterThan(0, $carte->filter('form[action$="/fabriquer"]')->count());
        });
    }

    /**
     * La Maison des scribes tient en quatre sections et sa Direction, une seule ouverte : chaque
     * section a son panneau, et l'ordre des deux listes est le même.
     */
    public function testLaMaisonDesScribesEstDecoupeeEnSections(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'scribes-sections@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', \sprintf('/partie/%d/ville?onglet=maison_des_scribes', $partie->getId()));

        $onglets = $crawler->filter('nav[aria-label="Sections de la Maison des scribes"] [role="tab"]')->each(static fn ($n): string => (string) $n->attr('aria-controls'));
        $panneaux = $crawler->filter('[role="tabpanel"][id^="scribes-section-"]')->each(static fn ($n): string => (string) $n->attr('id'));

        // Quatre sections de contenu, plus la Direction, comme tout bâtiment.
        self::assertCount(5, $onglets);
        self::assertSame($onglets, $panneaux);
        self::assertContains('scribes-section-direction', $panneaux);
        self::assertCount(4, $crawler->filter('[role="tabpanel"][id^="scribes-section-"][hidden]'));
        self::assertSelectorTextContains('body', 'Écrire « Niout »');
    }

    /**
     * L'Atelier et la Caserne se rendent, avec la consigne dans un repli et la
     * troupe en cases : c'est le seul contrôle que ces deux gabarits subissent.
     */
    public function testLAtelierEtLaCaserneSeRendent(): void
    {
        $client = static::createClient();
        $partie = $this->partie($client, 'atelier-caserne@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Atelier));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=atelier', $partie->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Consigne permanente');

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=caserne', $partie->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Lever un homme');
    }

    private function partie(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): \App\Entity\GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
    }
}
