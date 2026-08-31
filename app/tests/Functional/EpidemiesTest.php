<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\Effectifs;
use App\Game\Epidemies;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Les épidémies (lot 6.6).
 */
final class EpidemiesTest extends KernelTestCase
{
    /**
     * **Des malades, jamais des morts** : la fièvre retire des bras pour
     * quelques quinzaines, puis les rend — la ville n'a perdu personne.
     */
    public function testLaFievreCoucheDesBrasPuisLesRend(): void
    {
        self::bootKernel();
        $partie = $this->villeDeTravail('fievre@example.com');
        $ville = $partie->getVille();

        $actifs = $ville->getActifs();
        $this->jusquALEpidemie($partie);

        self::assertTrue($ville->estFrappeeParUneEpidemie());
        self::assertGreaterThan(0, $ville->malades());
        self::assertLessThan($actifs, $ville->actifsValides(), 'Des bras manquent à l\'appel.');
        self::assertSame($actifs, $ville->getActifs(), 'Mais la ville n\'a perdu personne.');

        $epidemies = $this->epidemies();

        for ($i = 0; $i < Epidemies::DUREE_MAXIMALE + 1; ++$i) {
            $epidemies->avancerDUnCycle($partie);
        }

        self::assertFalse($ville->estFrappeeParUneEpidemie());
        self::assertSame($actifs, $ville->actifsValides(), 'Tout le monde est debout.');
    }

    /**
     * **La fièvre passe par le canal existant** — le rendement d'effectif —,
     * jamais par un multiplicateur de plus. C'est ce qui laisse tenir le
     * plancher de 50 % du lot 4.5 même en pleine épidémie : le Grenier d'une
     * ville alitée tourne moins bien, il ne s'arrête pas.
     */
    public function testMemeAliteeUneVilleNeTombeJamaisSousLaMoitie(): void
    {
        self::bootKernel();
        $partie = $this->villeDeTravail('plancher-fievre@example.com');
        $ville = $partie->getVille();

        $this->jusquALEpidemie($partie);

        $effectifs = Effectifs::repartir($ville, $partie->getCycle());

        foreach ($effectifs as $batiment) {
            self::assertGreaterThanOrEqual(
                Effectifs::RENDEMENT_PLANCHER,
                $batiment['rendement'],
                'Aucun bâtiment ne descend sous le plancher, fièvre ou pas.',
            );
        }
    }

    /**
     * **Deux causes, cumulables** (doc 07). La seconde referme une boucle
     * laissée ouverte au lot 4.1 : le manque de logement empêchait les
     * naissances, il coûte désormais aussi quand la ville déborde.
     */
    public function testLesDeuxCausesSAdditionnent(): void
    {
        self::bootKernel();
        $partie = $this->villeDeTravail('risques@example.com');
        $ville = $partie->getVille();
        $epidemies = $this->epidemies();

        $fond = $epidemies->risque($partie);
        self::assertSame(Epidemies::RISQUE_DE_FOND, $fond);

        $ville->suivreLaFaveurDe(Divinite::Sekhmet)->ajuster(-Divinite::FAVEUR_MAXIMALE);
        self::assertTrue($ville->palierDe(Divinite::Sekhmet)->nuit());

        self::assertSame(
            Epidemies::RISQUE_DE_FOND + Epidemies::RISQUE_SEKHMET_HOSTILE,
            $epidemies->risque($partie),
        );

        // Et la surpopulation s'y ajoute, sans s'y substituer.
        $ville->accueillir(200, 200, 0);
        self::assertTrue($ville->manqueDeLogements());
        self::assertSame(
            Epidemies::RISQUE_DE_FOND + Epidemies::RISQUE_SEKHMET_HOSTILE + Epidemies::RISQUE_DE_SURPOPULATION,
            $epidemies->risque($partie),
        );
    }

    /**
     * **Celle qui envoie la maladie est celle qui la guérit.** Sekhmet
     * favorable écourte la fièvre de moitié — ses prêtres, les
     * *ouabou-Sekhmet*, étaient les médecins de l'Égypte.
     */
    public function testSekhmetFavorableEcourteLaFievre(): void
    {
        self::bootKernel();

        $sansElle = $this->dureeDeLaFievre('sans-sekhmet@example.com', favorable: false);
        $avecElle = $this->dureeDeLaFievre('avec-sekhmet@example.com', favorable: true);

        self::assertLessThanOrEqual($sansElle, $avecElle);
        self::assertGreaterThanOrEqual(1, $avecElle, 'Une épidémie qu\'on ne verrait pas passer n\'en serait pas une.');
    }

    /**
     * **On peut agir pendant** : c'est l'un des rares événements du jeu qu'on
     * ne fait pas que subir. Une offrande à Sekhmet abrège la fièvre tout de
     * suite.
     */
    public function testUneOffrandeASekhmetAbregeLaFievre(): void
    {
        self::bootKernel();
        $partie = $this->villeDeTravail('soigner@example.com');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->crediterRessources([Ressource::Deben->value => 1_000]);

        $ville->declarerUneEpidemie(4, Epidemies::PART_MINIMALE);

        static::getContainer()->get(Offrandes::class)
            ->offrir($partie, Divinite::Sekhmet, Ressource::Deben, 20);

        self::assertSame(3, $ville->getQuinzainesDepidemie());
    }

    /**
     * Hors épidémie, l'offrande est une offrande ordinaire : on ne réserve
     * pas une guérison pour plus tard.
     */
    public function testOnNeReservePasUneGuerisonPourPlusTard(): void
    {
        self::bootKernel();
        $partie = $this->villeDeTravail('reserve@example.com');
        $ville = $partie->getVille();

        self::assertFalse($this->epidemies()->abregerParUneOffrande($partie));
        self::assertFalse($ville->estFrappeeParUneEpidemie());
    }

    private function dureeDeLaFievre(string $email, bool $favorable): int
    {
        $partie = $this->villeDeTravail($email);
        $ville = $partie->getVille();

        if ($favorable) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
            $ville->crediterRessources([Ressource::Deben->value => 1_000]);
            $offrandes = static::getContainer()->get(Offrandes::class);

            while (!$ville->palierDe(Divinite::Sekhmet)->estAuDessusDuNeutre()) {
                $offrandes->offrir($partie, Divinite::Sekhmet, Ressource::Deben, 20);
            }

            self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Sekhmet));
        }

        $this->jusquALEpidemie($partie);

        return $ville->getQuinzainesDepidemie();
    }

    private function jusquALEpidemie(GameSave $partie): void
    {
        $epidemies = $this->epidemies();

        for ($i = 0; $i < 2_000 && !$partie->getVille()->estFrappeeParUneEpidemie(); ++$i) {
            $epidemies->avancerDUnCycle($partie);
        }

        self::assertTrue(
            $partie->getVille()->estFrappeeParUneEpidemie(),
            'Aucune fièvre en deux mille quinzaines : le tirage ne tombe jamais.',
        );
    }

    private function villeDeTravail(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier, 2));
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation, 2));

        return $partie;
    }

    private function epidemies(): Epidemies
    {
        return new Epidemies(new Randomizer(new Mt19937(20260831)));
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
