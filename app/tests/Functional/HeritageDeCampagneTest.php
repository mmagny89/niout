<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AchevementDeMission;
use App\Game\AvantageDeNegoce;
use App\Game\BonusDeDepart;
use App\Game\CarnetDeContacts;
use App\Game\Commerce;
use App\Game\DotationRoyale;
use App\Game\FilRouge;
use App\Game\LanceurDePartie;
use App\Game\Lignees;
use App\Game\Marche;
use App\Game\MissionCatalogue;
use App\Game\Ressource;
use App\Game\StatutDEnquete;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le parcours que la Phase 9 devait rendre possible, de bout en bout.
 *
 * Chaque maillon a son test — `RenommeeDeLigneeTest`, `RenommeeDansLesPrixTest`,
 * `CarnetDeContactsTest`, `BonusDeDepartTest`. Celui-ci vérifie la **chaîne**,
 * qu'aucun d'eux ne couvre : accomplir une mission, en lancer une autre, et
 * retrouver dans la seconde ce que la première a gagné.
 *
 * Il passe par `AchevementDeMission` plutôt que par `GameSave::achever()` : le
 * versement à la lignée s'y trouve, et c'est le seul point du jeu qui écrit
 * dans elle. Un test qui court-circuiterait ce chemin ne dirait rien du
 * branchement lui-même.
 */
final class HeritageDeCampagneTest extends KernelTestCase
{
    /**
     * **Le parcours entier**, tel que la définition de « fini » de la Phase 9
     * le décrit : accomplir la mission 1, voir la renommée gagnée rester au
     * lancement de la mission 2, constater un prix d'achat plus bas et un
     * contact au carnet, recevoir le bonus de départ par-dessus la dotation.
     */
    public function testCeQuUneMissionAccomplieLaisseALaSuivante(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('heritage-bout-en-bout@example.com');

        // ---- Mission 1 : on la mène à son terme, renommée en poche.
        $premiere = $this->lancerA($joueur, 1);
        $premiere->getFamille()->ajusterRenommee(60);

        $rapport = $this->accomplirLeFilRouge($premiere);

        self::assertTrue($premiere->estAchevee(), 'Le fil rouge résolu doit clore la mission.');
        self::assertSame(60, $this->lignees()->pour($joueur)->getRenommeeAcquise());
        self::assertNotEmpty($rapport);

        // ---- Mission 2 : ce que la première a laissé.
        $seconde = $this->lancerA($joueur, 2);

        // 1. La renommée est restée.
        self::assertSame(60, $seconde->getFamille()->getRenommee());

        // 2. Elle se voit sur les prix.
        $avantage = $this->commerce()->avantageDeNegoce($seconde);
        self::assertSame(AvantageDeNegoce::deLaRenommee(60), $avantage);
        self::assertGreaterThan(0, $avantage);

        // 3. La ville de la première mission est au carnet, et fait un prix sur
        //    ce que sa région porte.
        $contacts = $this->carnet()->pour($seconde);
        self::assertCount(1, $contacts);
        self::assertSame($this->missions()->get(1)->ville, $contacts[0]->ville);

        $duDelta = $this->missions()->get(1)->geographie->ressourcesDeZone[0];
        self::assertSame(
            $avantage + CarnetDeContacts::AVANTAGE_PAR_CONTACT,
            $this->commerce()->avantageDeNegoce($seconde, $duDelta),
        );

        // 4. Le bonus de départ est venu **par-dessus** la dotation, pas à sa
        //    place — l'invariant qui garde chaque mission jouable seule.
        $dotation = DotationRoyale::pour(
            $seconde->getVille()->getDifficulte(),
            $seconde->getVille()->consommationDeNourriture(),
        )->enRessources();

        self::assertSame(1, $this->bonus()->missionsQuiComptent($seconde));
        self::assertGreaterThan(
            $dotation[Ressource::Deben->value],
            $seconde->getVille()->getDeben(),
        );
    }

    /**
     * **Une mission mal finie ne rabaisse pas l'acquis.** C'est le plancher du
     * doc 13, et il ne se voit qu'en enchaînant deux missions réelles : la
     * seconde peut perdre de la renommée sans que la troisième en pâtisse.
     */
    public function testUneSecondeMissionMalFinieNeRabaissePasLacquis(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('heritage-plancher@example.com');

        $premiere = $this->lancerA($joueur, 1);
        $premiere->getFamille()->ajusterRenommee(45);
        $this->accomplirLeFilRouge($premiere);

        $seconde = $this->lancerA($joueur, 2);
        $seconde->getFamille()->ajusterRenommee(-30);
        $this->accomplirLeFilRouge($seconde);

        self::assertSame(15, $seconde->getFamille()->getRenommee());
        self::assertSame(45, $this->lignees()->pour($joueur)->getRenommeeAcquise());

        $troisieme = $this->lancerA($joueur, 3);
        self::assertSame(45, $troisieme->getFamille()->getRenommee());
    }

    /**
     * **La vente au Marché ne gagne pas un multiplicateur de plus** : la
     * renommée s'ajoute au coefficient de direction, et la recette se calcule
     * en une multiplication et une division. Le compte se vérifie ici sur une
     * partie réellement héritée.
     */
    public function testAucuneChaineNeGagneUnMultiplicateurDePlus(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('heritage-multiplicateur@example.com');

        $premiere = $this->lancerA($joueur, 1);
        $premiere->getFamille()->ajusterRenommee(50);
        $this->accomplirLeFilRouge($premiere);

        $seconde = $this->lancerA($joueur, 2);
        $ville = $seconde->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->crediterRessources([Ressource::Calcaire->value => 10]);

        $recette = $this->marche()->vendre($seconde, Ressource::Calcaire, 10);
        $prix = \App\Game\PrixDuMarche::pour(Ressource::Calcaire);

        self::assertNotNull($prix);
        // 50 : un Marché sans chef écoule à moitié prix. Plus les points de la
        // renommée, dans le même coefficient.
        self::assertSame(
            intdiv($prix * 10 * (50 + AvantageDeNegoce::deLaRenommee(50)), 100),
            $recette,
        );
    }

    /**
     * Mène le fil rouge à son terme : la tablette du roi, l'enquête qui porte
     * l'acte II, puis la stèle qu'on grave. C'est le chemin réel — celui qui
     * passe par `AchevementDeMission`, et donc par le versement à la lignée.
     *
     * @return list<string> ce qui est rapporté au joueur
     */
    private function accomplirLeFilRouge(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $mission = $partie->getMission() ?? 0;

        $ouverture = FilRouge::ouverture($mission);
        $stele = FilRouge::stele($mission);
        $enquete = FilRouge::enquete($partie);

        self::assertNotNull($ouverture);
        self::assertNotNull($stele);
        self::assertNotNull($enquete, 'Chaque mission de campagne porte une enquête de fil rouge.');

        $ville->dechiffrer($ouverture);
        $ville->ouvrirLeDossierDe($enquete)->conclure(StatutDEnquete::Resolue);
        $ville->dechiffrer($stele);

        $rapport = $this->achevement()->verifier($partie);
        $this->gestionnaire()->flush();

        return $rapport;
    }

    private function lancerA(User $joueur, int $mission): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)
            ->lancerCampagne($joueur, 'Nakht', numeroDeMission: $mission);
    }

    private function creerJoueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $this->gestionnaire()->persist($joueur);
        $this->gestionnaire()->flush();

        return $joueur;
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function achevement(): AchevementDeMission
    {
        return static::getContainer()->get(AchevementDeMission::class);
    }

    private function lignees(): Lignees
    {
        return static::getContainer()->get(Lignees::class);
    }

    private function carnet(): CarnetDeContacts
    {
        return static::getContainer()->get(CarnetDeContacts::class);
    }

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }

    private function bonus(): BonusDeDepart
    {
        return static::getContainer()->get(BonusDeDepart::class);
    }

    private function marche(): Marche
    {
        return static::getContainer()->get(Marche::class);
    }

    private function missions(): MissionCatalogue
    {
        return static::getContainer()->get(MissionCatalogue::class);
    }
}
