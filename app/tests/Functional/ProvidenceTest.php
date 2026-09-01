<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\Divinite;
use App\Game\GeographieDeLaPartie;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\PassageDeCycle;
use App\Game\Providence;
use App\Game\Ressource;
use App\Game\Subsistance;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Bénédictions et malédictions (lot 6.5).
 *
 * Le hasard est semé : ce qui se vérifie n'est jamais un tirage attendu, mais
 * ce qu'un événement a le droit de faire — et surtout ce qu'il n'a pas le
 * droit de faire.
 */
final class ProvidenceTest extends KernelTestCase
{
    /**
     * **Seule la pleine dévotion fait se manifester quelqu'un.** Un dieu
     * favorable règle le quotidien (lot 6.3), il ne surgit pas.
     */
    public function testSeulUnDieuDevoueBenit(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('benir@example.com', Divinite::Sobek, PalierDeFaveur::Favorable);

        self::assertSame([], $this->providence()->avancerDUnCycle($partie), 'Un dieu favorable ne se manifeste pas.');

        $this->porterAuDevouement($partie, Divinite::Sobek);
        $avant = $partie->getVille()->quantite(Ressource::Deben);

        $messages = $this->jusquAUnEvenement($partie);

        self::assertNotSame([], $messages);
        self::assertGreaterThan($avant, $partie->getVille()->quantite(Ressource::Deben));
    }

    /**
     * **Une malédiction retarde, elle n'efface pas.** Un chantier recule d'une
     * quinzaine, il ne se démolit pas, et son avancement ne repasse jamais
     * sous zéro.
     */
    public function testUneMaledictionRetardeUnChantierSansLeDetruire(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('maudire@example.com', Divinite::Ptah, PalierDeFaveur::Hostile);
        $ville = $partie->getVille();

        $chantier = new Chantier($ville, TypeDeBatiment::Temple, 5);
        $ville->ajouterChantier($chantier);
        $chantier->avancerDUnCycle(null);
        $chantier->avancerDUnCycle(null);

        $this->jusquAUnEvenement($partie);

        self::assertCount(1, $ville->getChantiers(), 'Le chantier est toujours là.');
        self::assertGreaterThanOrEqual(0, $chantier->pourcentageDAvancement());

        // Et un chantier à peine ouvert ne devient pas négatif.
        $neuf = new Chantier($ville, TypeDeBatiment::Grenier, 1);
        $neuf->retarder(Providence::AJOURNEMENT * 10);
        self::assertSame(0, $neuf->pourcentageDAvancement());
    }

    /**
     * **Jamais tout.** Une colère divine gâte une part des vivres ; un grenier
     * vidé d'un coup serait une perte définitive déguisée en événement.
     */
    public function testUneColereGateUnePartDesVivresJamaisTout(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('gater@example.com', Divinite::Hapi, PalierDeFaveur::Hostile);
        $ville = $partie->getVille();
        $ville->crediterRessources([Ressource::Ble->value => 200]);

        $avant = $ville->quantite(Ressource::Ble);
        $this->jusquAUnEvenement($partie);
        $apres = $ville->quantite(Ressource::Ble);

        self::assertLessThan($avant, $apres, 'Une part se gâte.');
        self::assertGreaterThan(0, $apres, 'Mais le Grenier n\'est jamais vidé.');
    }

    /**
     * **Aucun événement ne termine une partie** (décision de la joueuse) : la
     * famine reste la seule cause d'échec du jeu.
     */
    public function testAucuneMaledictionNeTerminaLaPartie(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('echec@example.com', Divinite::Osiris, PalierDeFaveur::Hostile);

        for ($i = 0; $i < 60; ++$i) {
            $this->providence()->avancerDUnCycle($partie);
        }

        self::assertSame(StatutDePartie::EnCours, $partie->getStatut());
    }

    /**
     * **La faim est la seule chose qui fâche vraiment un dieu.** Sans cette
     * source, la branche « malédiction » serait du code mort : la négligence
     * s'arrête au neutre, et les quêtes ratées relèvent des Phases 7 et 8. Le
     * piège d'`ajusterRenommee()` ne se repaie pas.
     */
    public function testLaFamineEstCeQuiRendUnDieuHostile(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('affamee@example.com', Divinite::Hapi, PalierDeFaveur::Favorable);
        $ville = $partie->getVille();

        self::assertFalse($ville->palierDe(Divinite::Hapi)->nuit());

        // Une ville affamée, et rien à manger : les offrandes s'arrêtent avec
        // le reste.
        $partie->enregistrerUneQuinzaineDeFamine();

        for ($i = 0; $i < Subsistance::SEUIL_DE_FAMINE + 40; ++$i) {
            $partie->enregistrerUneQuinzaineDeFamine();
            $this->providence()->avancerDUnCycle($partie);
        }

        self::assertTrue(
            $ville->palierDe(Divinite::Hapi)->nuit(),
            'Une ville qui ne se nourrit plus ne nourrit plus ses dieux.',
        );
    }

    /**
     * Et l'inaction reste gratuite : ne jamais mettre les pieds au Temple ne
     * peut pas rendre un dieu hostile, même en pleine famine.
     */
    public function testUneVilleSansTempleNeSeFaitAucunEnnemi(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-dieux@example.com');

        for ($i = 0; $i < 40; ++$i) {
            $partie->enregistrerUneQuinzaineDeFamine();
            $this->providence()->avancerDUnCycle($partie);
        }

        self::assertCount(0, $partie->getVille()->getFaveurs());

        foreach (Divinite::pantheon() as $divinite) {
            self::assertFalse($partie->getVille()->palierDe($divinite)->nuit());
        }
    }

    /**
     * Le cycle du jeu appelle bien la providence : sans ce branchement, tout
     * ce lot resterait un service que personne n'invoque.
     */
    public function testLaProvidencePasseDansLeCycle(): void
    {
        self::bootKernel();
        $partie = $this->villeDevouee('cycle-divin@example.com', Divinite::Sobek, PalierDeFaveur::Favorable);
        $this->porterAuDevouement($partie, Divinite::Sobek);

        $cycle = static::getContainer()->get(PassageDeCycle::class);
        $vus = [];

        for ($i = 0; $i < 40; ++$i) {
            foreach ($cycle->passer($partie) as $message) {
                if (str_contains($message, 'fleuve') || str_contains($message, 'Sobek')) {
                    $vus[] = $message;
                }
            }
        }

        self::assertNotSame([], $vus, 'Un dieu dévoué finit par se manifester dans le journal.');
    }

    /**
     * @return list<string>
     */
    private function jusquAUnEvenement(GameSave $partie): array
    {
        $providence = $this->providence();

        for ($i = 0; $i < 200; ++$i) {
            $messages = $providence->avancerDUnCycle($partie);

            if ([] !== $messages) {
                return $messages;
            }
        }

        self::fail('Aucun événement divin en deux cents quinzaines.');
    }

    private function porterAuDevouement(GameSave $partie, Divinite $divinite): void
    {
        $offrandes = static::getContainer()->get(Offrandes::class);

        while (PalierDeFaveur::Devoue !== $partie->getVille()->palierDe($divinite)) {
            $offrandes->offrir($partie, $divinite, Ressource::Deben, 20);
        }
    }

    private function villeDevouee(string $email, Divinite $divinite, PalierDeFaveur $palier): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([Ressource::Deben->value => 10_000]);

        $offrandes = static::getContainer()->get(Offrandes::class);
        $offrandes->offrir($partie, $divinite, Ressource::Deben, 20);

        if (PalierDeFaveur::Hostile === $palier) {
            // Rien ne rend un dieu hostile hors famine : on l'y met à la main,
            // pour éprouver la malédiction elle-même.
            $ville->faveurDe($divinite)?->ajuster(-Divinite::FAVEUR_MAXIMALE);
        }

        return $partie;
    }

    private function providence(): Providence
    {
        // Semé : l'événement doit finir par tomber, sans que le test dépende
        // du tirage de la machine qui l'exécute.
        return new Providence(
            static::getContainer()->get(GeographieDeLaPartie::class),
            new Randomizer(new Mt19937(20260831)),
        );
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
