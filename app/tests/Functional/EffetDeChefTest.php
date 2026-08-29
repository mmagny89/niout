<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\PassageDeCycle;
use App\Game\PrixDuMarche;
use App\Game\Ressource;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce que la compétence d'un chef change **en partie**, et non seulement dans
 * la formule : c'est là que le lot 4.8 se juge.
 */
final class EffetDeChefTest extends KernelTestCase
{
    /**
     * L'arbitrage central de la phase : un chef doit rapporter plus qu'il ne
     * coûte, sans quoi tout le système d'emploi serait une taxe qu'on éviterait.
     */
    public function testUnChefDeMarcheRapportePlusQuIlNeCoute(): void
    {
        self::bootKernel();

        $sansChef = $this->vendreDixCalcaire('marche-desert@example.com', competence: null);
        $avecChef = $this->vendreDixCalcaire('marche-tenu@example.com', competence: 60);

        $gain = $avecChef - $sansChef;

        self::assertGreaterThan(
            0,
            $gain,
            'Un Marché tenu doit rapporter davantage qu\'un Marché désert.',
        );

        // Un chef de compétence moyenne coûte une dizaine de deben la
        // quinzaine : le gain sur une seule vente doit déjà s'en approcher,
        // sinon l'embauche ne se défendrait jamais.
        self::assertGreaterThanOrEqual(10, $gain, \sprintf('Gain mesuré : %d deben.', $gain));
    }

    /**
     * Le doc 03 chiffre le Pêcheur à +20 % : la spécialité doit se voir dans
     * le poisson qui rentre, pas seulement dans le libellé affiché.
     */
    public function testUnPecheurRamenePlusDePoissonQuUnCommercantNaval(): void
    {
        self::bootKernel();

        $pecheur = $this->pecherUnCycle('pecheur@example.com', SpecialiteDeChef::PortPecheur);
        $autre = $this->pecherUnCycle('naval@example.com', SpecialiteDeChef::PortCommercantNaval);

        self::assertGreaterThan(
            $autre,
            $pecheur,
            'La spécialité du doc 03 doit peser sur ce qui rentre.',
        );
    }

    /**
     * Un chef ne doit **jamais** introduire un multiplicateur de plus sur la
     * base : il module la qualité de direction, aux côtés de l'effectif. Le
     * garde-fou est la promesse de la règle — une ville sans bras ne descend
     * pas sous la moitié, même mal dirigée.
     */
    public function testUnMauvaisChefNeFaitJamaisPireQuePasDeChef(): void
    {
        self::bootKernel();

        $sansChef = $this->vendreDixCalcaire('sans-chef-marche@example.com', competence: null);
        $mauvaisChef = $this->vendreDixCalcaire('mauvais-chef@example.com', competence: 20);

        self::assertGreaterThan(
            $sansChef,
            $mauvaisChef,
            'Même le pire des chefs doit valoir mieux que personne.',
        );
    }

    private function vendreDixCalcaire(string $email, ?int $competence): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Marche));
        $ville->crediterRessources([Ressource::Calcaire->value => 10]);

        if (null !== $competence) {
            $this->engager($partie, TypeDeBatiment::Marche, $competence, SpecialiteDeChef::MarcheVendeur);
        }

        $recette = static::getContainer()->get(Marche::class)->vendre($partie, Ressource::Calcaire, 10);

        self::assertLessThanOrEqual(
            10 * PrixDuMarche::pour(Ressource::Calcaire) * 2,
            $recette,
            'Un chef ne doit pas faire exploser les prix.',
        );

        return $recette;
    }

    private function pecherUnCycle(string $email, SpecialiteDeChef $specialite): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Poisson, 999);

        // Un Port de niveau 4 : le bonus de niveau existe, donc la qualité de
        // direction a prise dessus. Au niveau 1 il n'y a aucun bonus à moduler.
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Port, 4));
        $ville->crediterRessources([Ressource::Deben->value => 100000]);

        // Assez de bras pour que le Port et sa pêcherie soient au complet :
        // en sous-effectif, l'écart entre deux spécialités se perd dans
        // l'arrondi entier d'une pêche de référence de 10 unités.
        $ville->accueillir(20, 0, 0);
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Poisson);

        $this->engager($partie, TypeDeBatiment::Port, competence: 90, specialite: $specialite);

        $avant = $ville->quantite(Ressource::Poisson);
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        return $ville->quantite(Ressource::Poisson) - $avant;
    }

    private function engager(
        GameSave $partie,
        TypeDeBatiment $type,
        int $competence,
        SpecialiteDeChef $specialite,
    ): void {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: $competence,
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

    private function premiereZoneHorsVille(GameSave $partie): \App\Entity\Zone
    {
        $ville = $partie->getVille();
        $zoneDeLaVille = $ville->zoneDeLaVille();

        foreach ($ville->getZones() as $zone) {
            if ($zone !== $zoneDeLaVille) {
                return $zone;
            }
        }

        self::fail('Une carte doit avoir des cases autour de sa ville.');
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
}
