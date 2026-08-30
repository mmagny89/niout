<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Divinite;
use App\Game\LanceurDePartie;
use App\Game\Negligence;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\Temple;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La négligence (lot 6.2) : ce que devient un dieu qu'on cesse d'honorer.
 */
final class NegligenceTest extends KernelTestCase
{
    /**
     * **Cinq quinzaines de grâce**, puis un point par quinzaine (doc 07).
     * Entretenir un dieu est un geste occasionnel, jamais un abonnement.
     */
    public function testLaFaveurTientCinqQuinzainesAvantDeRetomber(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('grace@example.com', niveau: 4);
        $ville = $partie->getVille();

        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 60);
        $apresLOffrande = $ville->faveurEnvers(Divinite::Ptah);

        for ($i = 0; $i < Negligence::QUINZAINES_DE_GRACE; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame($apresLOffrande, $ville->faveurEnvers(Divinite::Ptah), 'Le délai de grâce ne coûte rien.');

        $this->cycle()->passer($partie);
        self::assertSame(
            $apresLOffrande - Negligence::PERTE_PAR_QUINZAINE,
            $ville->faveurEnvers(Divinite::Ptah),
            'Passé le délai, la faveur retombe d\'un point par quinzaine.',
        );
    }

    /**
     * **Elle s'arrête au neutre.** C'est la garantie centrale du lot : un dieu
     * délaissé se détourne, il ne se retourne pas contre vous. Sans ce
     * plancher, une partie menée sans mettre les pieds au Temple finirait
     * avec huit dieux hostiles — punie pour n'avoir pas joué à ce système-là.
     */
    public function testLaNegligenceNeDescendJamaisSousLeNeutre(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('plancher@example.com', niveau: 4);
        $ville = $partie->getVille();

        $this->offrandes()->offrir($partie, Divinite::Hapi, Ressource::Deben, 60);

        // Deux années entières sans la moindre offrande.
        for ($i = 0; $i < 50; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertSame(Negligence::PLANCHER, $ville->faveurEnvers(Divinite::Hapi));
        self::assertSame(PalierDeFaveur::Neutre, $ville->palierDe(Divinite::Hapi));
        self::assertFalse($ville->palierDe(Divinite::Hapi)->nuit(), 'Négliger n\'est pas offenser.');
    }

    /**
     * Une ville qui n'a jamais offert ne subit rien, et ne traîne toujours
     * aucune ligne : la négligence n'a rien à ronger.
     */
    public function testUneVilleQuiNaJamaisOffertNeSubitRien(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('jamais@example.com');
        $ville = $partie->getVille();

        for ($i = 0; $i < 20; ++$i) {
            $this->cycle()->passer($partie);
        }

        self::assertCount(0, $ville->getFaveurs());

        foreach (Divinite::pantheon() as $divinite) {
            self::assertSame(PalierDeFaveur::Neutre, $ville->palierDe($divinite));
        }
    }

    /**
     * **La négligence se compte dieu par dieu** : on peut couvrir Ptah
     * d'offrandes en laissant Sekhmet s'éloigner. C'est l'arbitrage que le
     * doc 07 cherche.
     */
    public function testOnPeutEntretenirUnDieuEnEnLaissantFilerUnAutre(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('arbitrage@example.com', niveau: 4);
        $ville = $partie->getVille();

        $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 60);
        $this->offrandes()->offrir($partie, Divinite::Sekhmet, Ressource::Deben, 60);
        $vise = $ville->faveurEnvers(Divinite::Ptah);

        // Trente-cinq quinzaines : de quoi ramener Sekhmet au plancher depuis
        // le plafond d'un Temple de niveau 4, délai de grâce compris.
        for ($quinzaine = 0; $quinzaine < 40; ++$quinzaine) {
            $this->cycle()->passer($partie);

            // Ptah reçoit de quoi compenser ce que la quinzaine lui retire.
            // On n'offre que s'il reste de la place : au plafond du Temple,
            // l'offrande est refusée plutôt qu'encaissée pour rien (lot 6.1),
            // et c'est bien ce qu'un joueur attentif ferait.
            if ($ville->faveurEnvers(Divinite::Ptah) < Temple::plafondDeFaveur($ville)) {
                $this->offrandes()->offrir($partie, Divinite::Ptah, Ressource::Deben, 10);
            }
        }

        self::assertGreaterThanOrEqual($vise, $ville->faveurEnvers(Divinite::Ptah), 'Ptah est entretenu.');
        self::assertSame(Negligence::PLANCHER, $ville->faveurEnvers(Divinite::Sekhmet), 'Sekhmet a filé.');
    }

    /**
     * Le joueur est prévenu **quand l'effet cesse**, pas à chaque point perdu :
     * un message par dieu et par quinzaine noierait le journal de cycle.
     */
    public function testLeJoueurEstPrevenuAuChangementDePalierEtPasAvant(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecTemple('journal@example.com', niveau: 4);
        $ville = $partie->getVille();

        $this->offrandes()->offrir($partie, Divinite::Osiris, Ressource::Deben, 40);
        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Osiris));

        $annonces = [];

        for ($i = 0; $i < 40; ++$i) {
            foreach ($this->cycle()->passer($partie) as $message) {
                if (str_contains($message, Divinite::Osiris->libelle())) {
                    $annonces[] = $message;
                }
            }
        }

        self::assertCount(1, $annonces, 'Un seul message : celui du palier franchi.');
        self::assertStringContainsString('neutre', $annonces[0]);
    }

    private function villeAvecTemple(string $email, int $niveau = 1): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, $niveau));
        // Le mode d'essai écarte la famine et les salaires : ce test porte sur
        // les dieux, pas sur la subsistance d'une ville menée vingt ans.
        $ville->basculerLeModeDivin(true);
        $ville->crediterRessources([
            Ressource::Deben->value => 100_000,
            Ressource::Ble->value => 100_000,
        ]);

        self::assertGreaterThan(Temple::PLAFOND_DE_BASE, Temple::plafondDeFaveur($ville));

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

    private function offrandes(): Offrandes
    {
        return static::getContainer()->get(Offrandes::class);
    }

    private function cycle(): PassageDeCycle
    {
        return static::getContainer()->get(PassageDeCycle::class);
    }
}
