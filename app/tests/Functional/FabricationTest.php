<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\OrdreDeFabrication;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\EffetDeChef;
use App\Game\Fabrication;
use App\Game\FabricationImpossible;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Recette;
use App\Game\Ressource;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FabricationTest extends KernelTestCase
{
    /**
     * Les matières sont **payées à l'engagement**, comme un chantier. Sans
     * cela, un joueur lancerait dix ordres avec les ressources d'un seul et
     * verrait lesquels aboutissent.
     */
    public function testLesMatieresSontDebiteesAuLancement(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('engagement@example.com');
        $ville = $partie->getVille();

        $argileAvant = $ville->quantite(Ressource::Argile);
        $boisAvant = $ville->quantite(Ressource::BoisLocal);

        $this->fabrication()->lancer($partie, Recette::Poterie, lots: 1);

        self::assertSame($argileAvant - 8, $ville->quantite(Ressource::Argile));
        self::assertSame($boisAvant - 3, $ville->quantite(Ressource::BoisLocal));
    }

    /**
     * **Rien ne rentre avant l'achèvement** — la règle des champs.
     */
    public function testLesPiecesNArriventQuALAchevement(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('achevement@example.com');
        $ville = $partie->getVille();

        $ordre = $this->fabrication()->lancer($partie, Recette::Poterie, lots: 2);
        $attendues = $ordre->piecesAttendues();

        self::assertSame(0, $ville->quantite(Ressource::Poterie), 'Rien n\'est livré au lancement.');

        for ($i = 0; $i < 20 && null !== $ville->ordreDeFabricationDe(TypeDeBatiment::Atelier); ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame($attendues, $ville->quantite(Ressource::Poterie));
        self::assertNull($ville->ordreDeFabricationDe(TypeDeBatiment::Atelier), 'L\'ordre achevé libère l\'Atelier.');
    }

    /**
     * **Un seul ouvrage à la fois** : c'est ce qui donne son coût
     * d'opportunité à la fabrication — tisser, c'est ne pas cuire.
     */
    public function testLAtelierNeMenePasDeuxOuvragesDeFront(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('un-seul@example.com');
        $this->fabrication()->lancer($partie, Recette::Poterie, lots: 1);

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/déjà un ouvrage/');

        $this->fabrication()->lancer($partie, Recette::Pain, lots: 1);
    }

    /**
     * Le niveau ouvre les recettes (doc 01) : les tissus demandent un Atelier
     * de niveau 4, la poterie se fait dès le premier.
     */
    public function testLeNiveauDeLAtelierOuvreLesRecettes(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('niveaux@example.com', niveau: 1);

        $ouvertes = array_map(
            static fn (array $offre): Recette => $offre['recette'],
            $this->fabrication()->offrePour($partie, TypeDeBatiment::Atelier),
        );

        self::assertContains(Recette::Poterie, $ouvertes);
        self::assertNotContains(Recette::Tissus, $ouvertes);

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/niveau 4/');

        $this->fabrication()->lancer($partie, Recette::Tissus, lots: 1);
    }

    /**
     * Le niveau élargit aussi la taille d'un ordre : un grand atelier
     * travaille par plus gros lots.
     */
    public function testLeNiveauDeLAtelierElargitLaTailleDesOrdres(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('lots@example.com', niveau: 1);

        self::assertSame(Fabrication::LOTS_PAR_NIVEAU, Fabrication::lotsMaximum(1));
        self::assertGreaterThan(Fabrication::lotsMaximum(1), Fabrication::lotsMaximum(4));

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/pas plus de/');

        $this->fabrication()->lancer($partie, Recette::Poterie, lots: Fabrication::lotsMaximum(1) + 1);
    }

    /**
     * **Les bras décident du rythme** (`EffetDeChef`) : un Atelier désert
     * tourne au plancher de 50 % et met donc deux fois plus longtemps, sans
     * jamais s'arrêter — « rien ne s'éteint faute d'employés ».
     */
    public function testUnAtelierDesertMetDeuxFoisPlusDeTemps(): void
    {
        self::bootKernel();

        $desert = $this->quinzainesPourUnLot('atelier-desert@example.com', avecChef: false);
        $tenu = $this->quinzainesPourUnLot('atelier-tenu@example.com', avecChef: true);

        self::assertGreaterThan($tenu, $desert, 'Un atelier sans personne doit être plus lent.');
        self::assertGreaterThan(0, $tenu, 'Et il ne s\'arrête jamais tout à fait.');
    }

    /**
     * **L'arbitrage central du lot** : transformer doit rapporter davantage
     * que vendre la matière brute. Sinon personne ne fabriquerait, et l'Atelier
     * ne serait qu'un bâtiment de plus à payer.
     */
    public function testTransformerRapportePlusQueVendreLaMatiereBrute(): void
    {
        foreach (Recette::cases() as $recette) {
            $brut = 0;

            foreach ($recette->ingredientsDunLot() as $valeur => $quantite) {
                $brut += (PrixDuMarche::pour(Ressource::from($valeur)) ?? 0) * $quantite;
            }

            $transforme = (PrixDuMarche::pour($recette->produit()) ?? 0) * $recette->piecesDunLot();

            self::assertGreaterThan(
                $brut,
                $transforme,
                \sprintf('%s : mieux vaudrait vendre la matière.', $recette->libelle()),
            );
        }
    }

    /**
     * Le pain sert de matière à la bière — des pains d'orge à peine cuits,
     * émiettés et mis à fermenter. C'est la seule recette qui en consomme une
     * autre, et l'ordre des niveaux le permet toujours.
     */
    public function testLaBiereSeFaitAvecDuPainEtLeNiveauLePermet(): void
    {
        self::assertArrayHasKey(Ressource::Pain->value, Recette::Biere->ingredientsDunLot());
        self::assertLessThanOrEqual(
            Recette::Biere->niveauRequis(),
            Recette::Pain->niveauRequis(),
            'Le pain doit être ouvert avant la bière qui en consomme.',
        );
    }

    /**
     * **L'Atelier et la Forge travaillent de front** : ce sont deux lieux, et
     * la règle « un seul ouvrage » vaut par bâtiment, pas par ville.
     */
    public function testLAtelierEtLaForgeTravaillentDeFront(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('deux-lieux@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, niveau: 2));
        $ville->crediterRessources([Ressource::Cuivre->value => 500]);

        $this->fabrication()->lancer($partie, Recette::Poterie, lots: 1);
        $this->fabrication()->lancer($partie, Recette::Outils, lots: 1);

        self::assertNotNull($ville->ordreDeFabricationDe(TypeDeBatiment::Atelier));
        self::assertNotNull($ville->ordreDeFabricationDe(TypeDeBatiment::Forge));
    }

    /**
     * **La Forge est le premier bâtiment dont la production dépend du
     * commerce** : le Delta ne porte pas de cuivre. Sans lui, elle ne fabrique
     * rien — ce qui fait d'elle la démonstration du lot suivant.
     */
    public function testSansCuivreLaForgeNeFabriqueRien(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-cuivre@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, niveau: 2));

        self::assertSame(0, $ville->quantite(Ressource::Cuivre), 'Le Delta ne porte pas de cuivre.');

        $offre = $this->fabrication()->offrePour($partie, TypeDeBatiment::Forge);
        self::assertNotEmpty($offre, 'Les recettes s\'affichent, mais hors de portée.');

        foreach ($offre as $ligne) {
            self::assertFalse($ligne['realisable']);
            self::assertNotNull($ligne['empechement']);
            self::assertStringContainsString('cuivre', $ligne['empechement']);
        }

        $this->expectException(FabricationImpossible::class);
        $this->expectExceptionMessageMatches('/cuivre/');

        $this->fabrication()->lancer($partie, Recette::Outils, lots: 1);
    }

    /**
     * Les outils n'ont **pas encore d'usage propre** : ils se vendent, et
     * l'interface doit le dire — promettre un usage qui n'existe nulle part
     * tromperait le joueur au moment où il engage ses matières.
     *
     * **Les armes n'en sont plus** depuis le lot 10.3 : elles équipent les
     * Medjaÿ, et la qualité de la Forge décide de ce qu'elles valent au combat.
     */
    public function testSeulsLesOutilsSeDisentSansUsagePropre(): void
    {
        $dormants = array_values(array_filter(
            Recette::cases(),
            static fn (Recette $r): bool => $r->produitDortEnAttendantSonUsage(),
        ));

        self::assertSame([Recette::Outils], $dormants);
        self::assertFalse(Recette::Armes->produitDortEnAttendantSonUsage());

        foreach (Recette::pour(TypeDeBatiment::Atelier, 10) as $recette) {
            self::assertFalse(
                $recette->produitDortEnAttendantSonUsage(),
                \sprintf('%s se mange, se vend ou s\'emploie déjà.', $recette->libelle()),
            );
        }
    }

    /**
     * **Le craft de luxe s'ouvre à l'Entrepôt, pas à l'Atelier** (docs 01
     * et 08) : un commerce longue distance conséquent suppose une logistique
     * développée. On fait de l'orfèvrerie à l'Atelier, mais on ne l'y débloque
     * pas.
     */
    public function testLeCraftDeLuxeDemandeUnEntrepotDeHautNiveau(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('luxe@example.com', niveau: 8);
        $ville = $partie->getVille();
        $ville->crediterRessources([
            Ressource::Or->value => 500,
            Ressource::Turquoise->value => 500,
        ]);

        // L'Atelier suffit-il ? Non : il manque l'Entrepôt.
        try {
            $this->fabrication()->lancer($partie, Recette::Bijoux, lots: 1);
            self::fail('Le luxe ne doit pas s\'ouvrir sans Entrepôt.');
        } catch (FabricationImpossible $impossible) {
            self::assertStringContainsString('Entrepôt', $impossible->getMessage());
        }

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, niveau: 8));

        $this->fabrication()->lancer($partie, Recette::Bijoux, lots: 1);

        self::assertNotNull(
            $ville->ordreDeFabricationDe(TypeDeBatiment::Atelier),
            'L\'Entrepôt de niveau 8 ouvre l\'orfèvrerie.',
        );
    }

    /**
     * **Le Delta n'y accède jamais**, et c'est voulu : son plafond régional
     * est de cinq niveaux, quand le luxe en demande huit. Le doc 01 le dit
     * expressément de la région d'apprentissage.
     */
    public function testLeDeltaNAtteintJamaisLeCraftDeLuxe(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('delta-luxe@example.com');
        $ville = $partie->getVille();

        foreach (Recette::cases() as $recette) {
            $supplementaire = $recette->deblocageSupplementaire();

            if (null === $supplementaire) {
                continue;
            }

            self::assertGreaterThan(
                $ville->niveauMaxRegional(),
                $recette->niveauRequis(),
                \sprintf('%s serait atteignable au Delta.', $recette->libelle()),
            );
        }
    }

    /**
     * **Une spécialité d'atelier ne vaut que sur son propre ouvrage.** Un
     * Brasseur presse la bière, pas le papyrus — et c'est ce qui donne un sens
     * au choix d'un candidat plutôt qu'un autre.
     *
     * Le contrôle porte sur la **qualité de direction**, non sur le nombre de
     * quinzaines : celles-ci se comptent en entiers, et un ordre de quatre
     * cycles ne distingue pas 134 % de 114 %. Mesurer la durée aurait donné un
     * test qui passe ou non selon la taille du lot, ce qui n'apprend rien.
     */
    public function testUneSpecialiteNeSertQueSonProprOuvrage(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecAtelier('brasseur@example.com');
        $ville = $partie->getVille();
        $this->engagerUnChef($partie, TypeDeBatiment::Atelier, SpecialiteDeChef::AtelierBierePain);

        $surSonOuvrage = EffetDeChef::qualiteDeDirection($ville, TypeDeBatiment::Atelier, $partie->getCycle(), Recette::Pain);
        $surUnAutre = EffetDeChef::qualiteDeDirection($ville, TypeDeBatiment::Atelier, $partie->getCycle(), Recette::Papyrus);

        self::assertGreaterThan($surUnAutre, $surSonOuvrage, 'Le Brasseur ne fait pas de meilleurs papyrus.');
        self::assertSame(
            EffetDeChef::BONUS_DATELIER,
            EffetDeChef::bonusDeSpecialite(SpecialiteDeChef::AtelierBierePain, Recette::Pain),
        );
        self::assertSame(0, EffetDeChef::bonusDeSpecialite(SpecialiteDeChef::AtelierBierePain, Recette::Papyrus));
    }

    /**
     * Et l'effet se voit bien dans l'ouvrage : à recette et lot égaux, un
     * atelier mieux dirigé n'est jamais plus lent.
     *
     * Le contrôle se fait sur l'ordre lui-même plutôt qu'en menant deux
     * parties sur une dizaine de quinzaines : sur une telle durée, un chef
     * peut rendre son tablier — son ancienneté est tirée —, et l'atelier
     * retombe alors au plancher. Le test mesurait donc le départ d'un chef
     * autant que sa spécialité, et tombait une fois sur plusieurs. Défaut
     * réel, payé en intégration continue.
     */
    public function testUnAtelierMieuxDirigeNestJamaisPlusLent(): void
    {
        self::bootKernel();
        $ville = $this->villeAvecAtelier('cadence@example.com')->getVille();

        $precedent = \PHP_INT_MAX;

        foreach ([50, 80, 100, 114, 134] as $qualite) {
            $ordre = new OrdreDeFabrication($ville, Recette::Tissus, lots: 4);
            $quinzaines = 0;

            while (!$ordre->estAcheve()) {
                $ordre->avancerDUnCycle($qualite);
                ++$quinzaines;
            }

            self::assertLessThanOrEqual(
                $precedent,
                $quinzaines,
                \sprintf('Une qualité de %d %% ne peut pas être plus lente qu\'une qualité moindre.', $qualite),
            );
            $precedent = $quinzaines;
        }
    }

    private function engagerUnChef(GameSave $partie, TypeDeBatiment $type, SpecialiteDeChef $specialite): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 20,
                traits: [],
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $partie->getCycle(),
        ));
    }

    private function quinzainesPourUnLot(string $email, bool $avecChef): int
    {
        $partie = $this->villeAvecAtelier($email);
        $ville = $partie->getVille();

        if ($avecChef) {
            $ville->ajouterEmploye(new Employee(
                $ville,
                TypeDeBatiment::Atelier,
                new Candidat(
                    competence: 90,
                    salaire: 8,
                    ancienneteProbable: 20,
                    traits: [],
                    specialite: null,
                    actifsAmenes: 0,
                    inactifsAmenes: 0,
                ),
                $partie->getCycle(),
            ));
        }

        $this->fabrication()->lancer($partie, Recette::Poterie, lots: 3);

        $quinzaines = 0;
        while (null !== $ville->ordreDeFabricationDe(TypeDeBatiment::Atelier) && $quinzaines < 40) {
            $this->cycle()->passer($partie);
            ++$quinzaines;
        }

        return $quinzaines;
    }

    private function villeAvecAtelier(string $email, int $niveau = 4): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Atelier, $niveau));
        // Le mode d'essai lève les plafonds : ces tests portent sur l'Atelier,
        // pas sur les réserves.
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([
            Ressource::Deben->value => 10_000,
            Ressource::Argile->value => 500,
            Ressource::BoisLocal->value => 500,
            Ressource::Ble->value => 500,
            Ressource::Orge->value => 500,
            Ressource::Roseaux->value => 500,
            Ressource::Lin->value => 500,
        ]);

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

    private function fabrication(): Fabrication
    {
        return static::getContainer()->get(Fabrication::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
