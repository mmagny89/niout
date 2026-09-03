<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\Medjays;
use App\Game\PrenomEgyptien;
use App\Game\SuccessionFamiliale;
use App\Game\SuccessionImpossible;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La succession familiale (doc 13, lot 11.5, ex-lot 9.6).
 *
 * **Reportée depuis la Phase 9, et pour une raison qui tenait** : une génération
 * dure soixante cycles, quand une mission de campagne les dépasse rarement. Le
 * mode Aventure est le premier où le lot se déclenche vraiment.
 *
 * **Rien ne se perd** : la renommée, les contacts, la faveur divine et la ville
 * traversent la succession. Seuls le chef et son trait changent.
 */
final class SuccessionFamilialeTest extends KernelTestCase
{
    /**
     * **Elle ne s'ouvre pas en campagne** : une mission dure une trentaine de
     * cycles, et changer de chef au milieu d'une commande royale n'aurait pas
     * de sens.
     */
    public function testElleNeSOuvrePasEnCampagne(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('succ-campagne@example.com');
        $campagne = $this->lanceur()->lancerCampagne($joueur, 'Nakht');

        $this->menerAuCycle($campagne, SuccessionFamiliale::DUREE_DUNE_GENERATION * 3);

        self::assertFalse($this->successions()->estOuverte($campagne));
        self::assertSame([], $this->successions()->heritiers($campagne));
    }

    /**
     * **Une génération dure soixante cycles, plus ou moins vingt** (doc 13) :
     * elle ne s'ouvre pas avant, et s'ouvre bien après.
     */
    public function testElleSOuvreQuandLaGenerationAFaitSonTemps(): void
    {
        self::bootKernel();
        $aventure = $this->lancerAventure('succ-duree@example.com');

        self::assertFalse($this->successions()->estOuverte($aventure));

        $terme = $this->successions()->cycleDeLaProchaineSuccession($aventure);

        self::assertGreaterThanOrEqual(
            SuccessionFamiliale::DUREE_DUNE_GENERATION - SuccessionFamiliale::ECART_DE_GENERATION,
            $terme,
        );
        self::assertLessThanOrEqual(
            SuccessionFamiliale::DUREE_DUNE_GENERATION + SuccessionFamiliale::ECART_DE_GENERATION,
            $terme,
        );

        $this->menerAuCycle($aventure, $terme);

        self::assertTrue($this->successions()->estOuverte($aventure));
    }

    /**
     * **Les héritiers ne changent pas entre deux clics.** Ils se déduisent
     * d'une graine gardée sur la famille, non d'un tirage refait à chaque
     * consultation : on choisit entre eux, ils doivent tenir en place.
     */
    public function testLesHeritiersNeChangentPasEntreDeuxConsultations(): void
    {
        self::bootKernel();
        $aventure = $this->lancerAventure('succ-graine@example.com');
        $this->menerAuCycle($aventure, $this->successions()->cycleDeLaProchaineSuccession($aventure));

        $premiers = $this->successions()->heritiers($aventure);
        $seconds = $this->successions()->heritiers($aventure);

        self::assertGreaterThanOrEqual(SuccessionFamiliale::HERITIERS_MINIMUM, \count($premiers));
        self::assertLessThanOrEqual(SuccessionFamiliale::HERITIERS_MAXIMUM, \count($premiers));

        self::assertSame(
            array_map(static fn ($h): string => $h->prenom, $premiers),
            array_map(static fn ($h): string => $h->prenom, $seconds),
        );
    }

    /**
     * **Rien ne se perd, sinon la personne** : la renommée, la ville et le nom
     * de famille traversent la succession ; le chef et son trait changent.
     */
    public function testLheritierPrendLaSuiteSansRienEmporter(): void
    {
        self::bootKernel();
        $aventure = $this->lancerAventure('succ-passage@example.com');
        $aventure->getFamille()->ajusterRenommee(35);
        $this->menerAuCycle($aventure, $this->successions()->cycleDeLaProchaineSuccession($aventure));

        $famille = $aventure->getFamille();
        $nom = $famille->getNom();
        $habitants = $aventure->getVille()->population();

        $heritier = $this->successions()->choisir($aventure, 0);

        self::assertSame($heritier->prenom, $famille->getChefDeFamille());
        self::assertSame(2, $famille->getGeneration());
        self::assertContains($heritier->prenom, PrenomEgyptien::tous());

        // Ce qui persiste, et c'est le point du document.
        self::assertSame($nom, $famille->getNom(), 'Le nom de famille ne change pas.');
        self::assertSame(35, $famille->getRenommee(), 'La renommée traverse la succession.');
        self::assertSame($habitants, $aventure->getVille()->population(), 'La ville ne change pas de mains.');

        // Et la succession se referme.
        self::assertFalse($this->successions()->estOuverte($aventure));
    }

    /**
     * Un héritier qui ne se présente pas ne prend rien : le contrôle vit dans
     * le domaine, pas seulement dans le gabarit.
     */
    public function testUnHeritierQuiNeSePresentePasEstRefuse(): void
    {
        self::bootKernel();
        $aventure = $this->lancerAventure('succ-refus@example.com');
        $this->menerAuCycle($aventure, $this->successions()->cycleDeLaProchaineSuccession($aventure));

        $this->expectException(SuccessionImpossible::class);
        $this->successions()->choisir($aventure, 99);
    }

    /**
     * **Les prénoms sont attestés, et ce ne sont pas des noms de rois** : la
     * famille du joueur n'est pas royale, et le doc 09 pose déjà cette règle.
     */
    public function testLesPrenomsNeSontPasDesNomsDeRois(): void
    {
        $royaux = ['Ahmôsis Ier', 'Hatchepsout', 'Akhenaton', 'Toutânkhamon', 'Horemheb'];

        foreach (PrenomEgyptien::tous() as $prenom) {
            self::assertNotSame('', $prenom);
            self::assertNotContains($prenom, $royaux);
        }

        self::assertGreaterThan(20, \count(PrenomEgyptien::tous()));
    }

    /**
     * **La Résidence familiale ajoute des emplacements de Medjaÿ** (doc 01,
     * lot 11.6). Le document les promettait sans les chiffrer, alors qu'il
     * chiffrait l'effectif de la Caserne : les deux s'ajoutent, la Caserne
     * décidant de l'essentiel.
     */
    public function testLaResidenceOuvreDesEmplacementsDeMedjays(): void
    {
        self::bootKernel();
        $aventure = $this->lancerAventure('succ-residence@example.com');
        $ville = $aventure->getVille();
        $residence = $ville->batimentDeType(TypeDeBatiment::ResidenceFamiliale);

        self::assertNotNull($residence, 'La Résidence est là dès l\'arrivée.');
        self::assertSame(1, Medjays::emplacementsDeLaResidence($ville));

        $residence->monterDUnNiveau()->monterDUnNiveau();

        self::assertSame(3, $residence->getNiveau());
        self::assertSame(2, Medjays::emplacementsDeLaResidence($ville), 'Le palier 3 ouvre le second.');

        $residence->monterDUnNiveau()->monterDUnNiveau();

        self::assertSame(5, $residence->getNiveau());
        self::assertSame(3, Medjays::emplacementsDeLaResidence($ville), 'Le palier 5 ouvre le troisième.');
        self::assertCount(3, Medjays::PALIERS_DE_RESIDENCE, 'Trois emplacements au plus : rien n\'en dérègle le calibrage.');
    }

    private function menerAuCycle(GameSave $partie, int $cycle): void
    {
        while ($partie->getCycle() < $cycle) {
            $partie->avancerDUnCycle();
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();
    }

    private function lancerAventure(string $email): GameSave
    {
        return $this->lanceur()->lancerAventure(
            $this->creerJoueur($email),
            'Nakht',
            difficulte: 0,
            tailleGrille: 3,
        );
    }

    private function creerJoueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return $joueur;
    }

    private function lanceur(): LanceurDePartie
    {
        return static::getContainer()->get(LanceurDePartie::class);
    }

    private function successions(): SuccessionFamiliale
    {
        return static::getContainer()->get(SuccessionFamiliale::class);
    }
}
