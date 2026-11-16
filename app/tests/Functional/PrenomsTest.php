<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\RivalCommercial;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\LanceurDePartie;
use App\Game\Medjays;
use App\Game\PassageDeCycle;
use App\Game\PrenomEgyptien;
use App\Game\Prenoms;
use App\Game\Recrutements;
use App\Game\Ressource;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Un prénom pour chaque chef et chaque Medjaÿ (lot N,
 * `docs/plan-pnj-ville-vivante.md` § 16).
 *
 * Ces tests gardent ce qui rend un prénom supportable : qu'il ne change jamais
 * sous les yeux du joueur, qu'on ne croise jamais deux fois le même dans une
 * ville, et qu'une partie commencée avant le lot n'ait pas de trou.
 */
final class PrenomsTest extends WebTestCase
{
    /**
     * On embauche *quelqu'un* : le candidat a son prénom dès l'annonce, et deux
     * candidats d'une même annonce n'ont jamais le même.
     */
    public function testChaqueCandidatDUneAnnonceALeSienEtDeuxNeLePartagentPas(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('annonce@example.com');

        $offre = static::getContainer()->get(Recrutements::class)->poster($partie, TypeDeBatiment::Grenier);
        $prenoms = array_map(static fn (Candidat $c): ?string => $c->prenom, $offre->candidats());

        self::assertNotContains(null, $prenoms, 'Chaque candidat porte un prénom dès l\'annonce.');
        self::assertCount(\count($prenoms), array_unique($prenoms), 'Deux candidats ne partagent pas un prénom.');
    }

    /**
     * Le prénom lu à l'annonce est celui du chef que l'on retient : il ne se
     * re-tire pas à l'embauche.
     */
    public function testLePrenomVuALAnnonceEstCeluiDuChefEmbauche(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('embauche@example.com');
        $ville = $partie->getVille();

        $offre = static::getContainer()->get(Recrutements::class)->poster($partie, TypeDeBatiment::Grenier);
        $candidat = $offre->candidat(0);
        self::assertNotNull($candidat);

        $chef = new Employee($ville, TypeDeBatiment::Grenier, $candidat, $partie->getCycle() + 1);

        self::assertSame($candidat->prenom, $chef->getPrenom());
    }

    /**
     * Une annonce affichée avant le lot n'a pas la clé : elle se lit encore,
     * sans prénom, au lieu de rendre la partie illisible.
     */
    public function testUneAnnonceSansPrenomSeLitEncore(): void
    {
        $candidat = Candidat::depuisTableau([
            'competence' => 50, 'salaire' => 5, 'anciennete' => 20, 'traits' => [],
            'specialite' => null, 'actifs' => 2, 'inactifs' => 0,
        ]);

        self::assertNull($candidat->prenom);
        self::assertSame('Hori', $candidat->avecPrenom('Hori')->prenom);
        self::assertSame('Hori', Candidat::depuisTableau($candidat->avecPrenom('Hori')->enTableau())->prenom);
    }

    /**
     * **L'invariant du lot** : quel que soit le nombre de chefs et de Medjaÿ,
     * aucun prénom n'est porté deux fois. Au-delà du fonds, la filiation prend
     * le relais — sans erreur, et sans doublon.
     */
    public function testAucunPrenomNEstPorteDeuxFoisMemeQuandLeFondsEstEpuise(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('unicite@example.com');
        $ville = $partie->getVille();

        foreach (range(1, 14) as $ignore) {
            $ville->ajouterEmploye($this->unChefSansPrenom($partie));
        }

        foreach (range(1, 14) as $ignore) {
            $ville->leverUnMedjay(new Medjay($ville, SpecialisationMedjay::Fantassin));
        }

        $donnes = $this->prenoms()->completer($partie);

        self::assertSame(28, $donnes);

        $tous = [
            ...array_map(static fn (Employee $e): ?string => $e->getPrenom(), $ville->getEmployes()->toArray()),
            ...array_map(static fn (Medjay $m): ?string => $m->getPrenom(), $ville->getMedjays()->toArray()),
        ];

        self::assertNotContains(null, $tous);
        self::assertCount(28, array_unique($tous), 'Aucun prénom n\'est porté deux fois.');
        self::assertNotEmpty(
            array_filter($tous, static fn (?string $p): bool => null !== $p && str_contains($p, ', fils de ')),
            'Le fonds épuisé, la filiation prend le relais.',
        );
    }

    /**
     * Un Medjaÿ est un homme d'armes : il ne reçoit jamais un prénom du fonds
     * féminin, filiation comprise.
     */
    public function testUnMedjayNeRecoitQuUnPrenomMasculin(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('hommes@example.com');
        $ville = $partie->getVille();

        foreach (range(1, 15) as $ignore) {
            $ville->leverUnMedjay(new Medjay($ville, SpecialisationMedjay::Fantassin));
        }

        $this->prenoms()->completer($partie);

        foreach ($ville->getMedjays() as $medjay) {
            $bases = array_map('trim', explode(', fils de ', (string) $medjay->getPrenom()));

            foreach ($bases as $base) {
                self::assertContains($base, PrenomEgyptien::ordinairesMasculins(), (string) $medjay->getPrenom());
            }
        }
    }

    /**
     * Qui a déjà un prénom le garde : c'est ce qui fait qu'un nom ne change
     * pas sous les yeux du joueur quand un autre chef arrive ou s'en va.
     */
    public function testQuiAUnPrenomLeGardeEtCompleterEstIdempotent(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('stable@example.com');
        $ville = $partie->getVille();

        $ancien = $this->unChefSansPrenom($partie)->donnerUnPrenom('Hori');
        $ville->ajouterEmploye($ancien);
        $neuf = $this->unChefSansPrenom($partie);
        $ville->ajouterEmploye($neuf);

        self::assertSame(1, $this->prenoms()->completer($partie));
        self::assertSame('Hori', $ancien->getPrenom(), 'Un prénom donné ne bouge pas.');
        self::assertNotSame('Hori', $neuf->getPrenom(), 'Le nouveau ne prend pas celui d\'un autre.');

        $prenomDuNeuf = $neuf->getPrenom();

        self::assertSame(0, $this->prenoms()->completer($partie), 'Rien à donner : l\'étape est idempotente.');
        self::assertSame($prenomDuNeuf, $neuf->getPrenom());
    }

    /**
     * Le rival s'appelle « Hori le marchand » : on ne croise pas deux Hori
     * dans la même ville.
     */
    public function testOnNeCroiseJamaisLePrenomDuRival(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rival@example.com');
        $ville = $partie->getVille();
        $ville->installerUnRival(new RivalCommercial($ville, 'canaan', 'Hori le marchand', 15, 10));

        self::assertContains('Hori', Prenoms::pris($partie));

        foreach (range(1, 40) as $graine) {
            $prenoms = new Prenoms(new Randomizer(new Xoshiro256StarStar(hash('sha256', (string) $graine, true))));

            self::assertNotSame('Hori', $prenoms->libre($partie));
        }
    }

    /**
     * Le fonds des chefs laisse de côté les hauts fonctionnaires identifiés,
     * mais **l'ordre des héritiers ne bouge pas** : `selonLaGraine()` indexe
     * `tous()`, et une graine déjà gardée doit rendre le même prénom.
     */
    public function testLeFondsDesChefsExclutLesFonctionnairesSansTouchAuxHeritiers(): void
    {
        self::assertContains('Rekhmirê', PrenomEgyptien::tous());
        self::assertNotContains('Rekhmirê', PrenomEgyptien::ordinaires());
        self::assertNotContains('Ineni', PrenomEgyptien::ordinaires());

        self::assertCount(30, PrenomEgyptien::tous());
        self::assertSame('Sennedjem', PrenomEgyptien::tous()[0]);
        self::assertSame('Moutemouia', PrenomEgyptien::tous()[29]);
        self::assertSame('Sennedjem', PrenomEgyptien::selonLaGraine(0));
        self::assertSame('Sennefer', PrenomEgyptien::selonLaGraine(10));
    }

    /**
     * Un homme levé reçoit son prénom à la levée, dans le fonds masculin.
     */
    public function testUnHommeLeveALeSienDesLaLevee(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('levee@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        $medjay = static::getContainer()->get(Medjays::class)->lever($partie, SpecialisationMedjay::Fantassin);

        self::assertContains($medjay->getPrenom(), PrenomEgyptien::ordinairesMasculins());
    }

    /**
     * Une partie commencée avant le lot n'a pas de trou : la quinzaine qui
     * passe donne un prénom à qui n'en a pas.
     */
    public function testLaQuinzaineQuiPasseNommeLesGensSansPrenom(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('rattrapage@example.com');
        $ville = $partie->getVille();

        $medjay = new Medjay($ville, SpecialisationMedjay::Fantassin);
        $ville->leverUnMedjay($medjay);
        self::assertNull($medjay->getPrenom());

        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        self::assertNotNull($medjay->getPrenom());
    }

    /**
     * **Ce que le joueur lit** : le prénom du chef sur sa carte, celui des
     * candidats d'une annonce, celui d'un homme à la Caserne. Un chef d'avant
     * le lot — sans prénom — n'imprime pas de trou.
     */
    public function testLePrenomSeLitSurLesCartesDuChefDesCandidatsEtDeLaCaserne(): void
    {
        $client = static::createClient();
        $partie = $this->lancerConnecte($client, 'affichage@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 1));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 1));
        $chef = $this->unChefSansPrenom($partie)->donnerUnPrenom('Nebnefer');
        $ville->ajouterEmploye($chef);
        $ville->leverUnMedjay(new Medjay($ville, SpecialisationMedjay::Fantassin, 'Paneb'));
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $grenier = $client->request('GET', \sprintf('/partie/%d/ville?onglet=grenier', $partie->getId()));
        self::assertStringContainsString('Nebnefer', $grenier->filter('section[aria-label="Chefs en poste"]')->text());

        $caserne = $client->request('GET', \sprintf('/partie/%d/ville?onglet=caserne', $partie->getId()));
        self::assertStringContainsString('Paneb — ', $caserne->text());
    }

    private function unChefSansPrenom(GameSave $partie): Employee
    {
        return new Employee(
            $partie->getVille(),
            TypeDeBatiment::Grenier,
            new Candidat(50, 5, 20, [], null, 2, 0),
            $partie->getCycle() + 1,
        );
    }

    private function lancerAvecGrenier(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        if (null === $ville->batimentDeType(TypeDeBatiment::Grenier)) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        }

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

    private function prenoms(): Prenoms
    {
        return static::getContainer()->get(Prenoms::class);
    }
}
