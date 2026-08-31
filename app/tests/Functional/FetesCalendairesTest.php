<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\DateDeJeu;
use App\Game\Divinite;
use App\Game\FeteCalendaire;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\Saison;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Les fêtes du calendrier (lot 6.4).
 *
 * Le doc 07 écarte le « dieu-maître du mois » et lui préfère des fêtes
 * attestées : ce qui se vérifie ici est donc autant leur exactitude
 * calendaire que leur effet.
 */
final class FetesCalendairesTest extends KernelTestCase
{
    /**
     * Les trois fêtes tombent aux mois que les sources leur donnent, et le
     * calendrier du jeu les portait déjà : Opet aux 2ᵉ et 3ᵉ mois de
     * l'inondation, les mystères d'Osiris au 4ᵉ — *Ka-her-ka*, dont les Grecs
     * ont fait *Khoiak* —, la Belle Fête de la Vallée au 10ᵉ.
     */
    public function testChaqueFeteTombeAuMoisQueLuiDonnentLesSources(): void
    {
        self::assertSame(FeteCalendaire::Opet, FeteCalendaire::pour($this->dateDuMois(2)));
        self::assertSame(FeteCalendaire::Opet, FeteCalendaire::pour($this->dateDuMois(3)));
        self::assertSame(FeteCalendaire::MysteresDOsiris, FeteCalendaire::pour($this->dateDuMois(4)));
        self::assertSame(FeteCalendaire::BelleFeteDeLaVallee, FeteCalendaire::pour($this->dateDuMois(10)));

        self::assertNull(FeteCalendaire::pour($this->dateDuMois(1)));
        self::assertNull(FeteCalendaire::pour($this->dateDuMois(5)));

        // Ka-her-ka porte le nom même du mois des mystères.
        self::assertSame('Ka-her-ka', $this->dateDuMois(4)->nomDeMois);
    }

    /**
     * **Une fête ne pousse jamais vers un dieu qui ne fait rien.** Amon-Rê et
     * Osiris agissent tous deux depuis le lot 6.3 ; dédier une fête à Isis ou
     * à Thot reviendrait à inviter le joueur à dépenser pour rien, au moment
     * précis où le jeu lui dit que le moment est favorable.
     */
    public function testUneFeteNeMeneJamaisVersUnDieuInerte(): void
    {
        foreach (FeteCalendaire::cases() as $fete) {
            self::assertTrue(
                $fete->divinite()->agitDeja(),
                \sprintf('%s mène vers %s, qui n\'agit pas encore.', $fete->libelle(), $fete->divinite()->libelle()),
            );
        }
    }

    /**
     * Les fêtes suivent le calendrier réel, jamais un étalement commode sur
     * l'année : deux tombent pendant l'inondation, la Belle Fête de la Vallée
     * au 2ᵉ mois de Chémou, là où les sources la placent.
     */
    public function testLesFetesSuiventLeCalendrierReelEtNonUnEtalementCommode(): void
    {
        self::assertSame(Saison::Akhet, $this->dateDuMois(2)->saison);
        self::assertSame(Saison::Akhet, $this->dateDuMois(4)->saison);
        self::assertSame(Saison::Chemou, $this->dateDuMois(10)->saison);
    }

    /**
     * Les jours épagomènes n'appartiennent à aucun mois : ils n'appartiennent
     * donc à aucune fête.
     */
    public function testLesJoursEpagomenesNontPasDeFete(): void
    {
        $epagomenes = DateDeJeu::pourCycle(DateDeJeu::CYCLES_PAR_ANNEE);

        self::assertTrue($epagomenes->estJoursEpagomenes());
        self::assertNull(FeteCalendaire::pour($epagomenes));
    }

    /**
     * **Le supplément vaut pour le dieu de la fête, et pour lui seul.** Une
     * offrande à Ptah pendant Opet reste une offrande ordinaire : la fête est
     * un rendez-vous, pas une saison faste où tout coûte moins cher.
     */
    public function testLeSupplementNeVautQuePourLeDieuDeLaFete(): void
    {
        $pendantOpet = $this->dateDuMois(2);

        self::assertSame(Offrandes::POINTS_DE_FETE, Offrandes::supplementDeFete($pendantOpet, Divinite::AmonRe));
        self::assertSame(0, Offrandes::supplementDeFete($pendantOpet, Divinite::Ptah));
        self::assertSame(0, Offrandes::supplementDeFete($this->dateDuMois(1), Divinite::AmonRe));
    }

    /**
     * L'effet en partie : la même offrande, portée pendant sa fête, pèse
     * nettement plus lourd. C'est ce qui donne une raison de regarder la date
     * pour autre chose que la saison agricole.
     */
    public function testLaMemeOffrandeVautDavantagePendantSaFete(): void
    {
        self::bootKernel();

        $ordinaire = $this->offrirAuPremierMoisOu('ordinaire@example.com', mois: 1);
        $pendantLaFete = $this->offrirAuPremierMoisOu('fete@example.com', mois: 2);

        self::assertSame($ordinaire + Offrandes::POINTS_DE_FETE, $pendantLaFete);
    }

    /**
     * Le supplément s'ajoute **après** le seuil : un jour saint ne rend pas
     * remarquable une offrande dérisoire.
     */
    public function testUneOffrandeDerisoireLeResteUnJourDeFete(): void
    {
        self::assertSame(0, Offrandes::pointsPour(Ressource::Argile, 1, $this->dateDuMois(2), Divinite::AmonRe));
        self::assertSame(
            Offrandes::POINTS_PAR_OFFRANDE + Offrandes::POINTS_DE_FETE,
            Offrandes::pointsPour(Ressource::Deben, 10, $this->dateDuMois(2), Divinite::AmonRe),
        );
    }

    /**
     * La partie sait dire quelle fête elle traverse — c'est ce que la barre de
     * jeu affiche, et une fête qu'on n'a pas vue passer est une fête qui
     * n'existe pas.
     */
    public function testLaPartieAnnonceLaFeteQuElleTraverse(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('barre@example.com');
        $cycle = static::getContainer()->get(PassageDeCycle::class);

        $rencontrees = [];

        for ($quinzaine = 0; $quinzaine < DateDeJeu::CYCLES_PAR_ANNEE; ++$quinzaine) {
            $fete = $partie->feteEnCours();

            if (null !== $fete) {
                $rencontrees[$fete->value] = true;
            }

            $cycle->passer($partie);
        }

        self::assertCount(
            \count(FeteCalendaire::cases()),
            $rencontrees,
            'Une année entière doit croiser les trois fêtes.',
        );
    }

    private function offrirAuPremierMoisOu(string $email, int $mois): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 1_000]);

        $cycle = static::getContainer()->get(PassageDeCycle::class);

        while ($partie->dateDeJeu()->numeroDeMois !== $mois) {
            $cycle->passer($partie);
        }

        return static::getContainer()->get(Offrandes::class)
            ->offrir($partie, Divinite::AmonRe, Ressource::Deben, 20);
    }

    private function dateDuMois(int $mois): DateDeJeu
    {
        return DateDeJeu::pourCycle(($mois - 1) * DateDeJeu::CYCLES_PAR_MOIS + 1);
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
