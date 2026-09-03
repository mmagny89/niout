<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\BonusDeDepart;
use App\Game\DotationRoyale;
use App\Game\LanceurDePartie;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce qu'une famille qui a déjà servi apporte avec elle (doc 13, lot 9.5).
 *
 * Deux invariants s'y croisent, et ce sont eux que ces tests portent : le bonus
 * **s'ajoute** à la dotation royale sans la remplacer — c'est ce qui garde
 * chaque mission jouable seule — et il ne la **dépasse** pas, sans quoi le don
 * du roi cesserait d'être le socle de la partie pour n'en être plus que
 * l'appoint.
 */
final class BonusDeDepartTest extends KernelTestCase
{
    /**
     * Une première mission n'apporte rien : il n'y a rien avant elle.
     */
    public function testUnePremiereMissionNapporteRien(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('bonus-premiere@example.com');

        $partie = $this->lancerA($joueur, 1);

        self::assertSame(0, $this->bonus()->missionsQuiComptent($partie));
        self::assertSame($this->dotationDe($partie), $partie->getVille()->getDeben());
    }

    /**
     * **Le bonus s'ajoute à la dotation, il ne la remplace pas.** Une première
     * mission et une troisième démarrent sur le même socle : c'est ce qui garde
     * chaque mission jouable seule, et l'invariant vaut depuis le legs du lot
     * 8.6.
     */
    public function testLeBonusSAjouteALaDotationSansLaRemplacer(): void
    {
        self::bootKernel();
        $novice = $this->creerJoueur('bonus-novice@example.com');
        $chevronne = $this->creerJoueur('bonus-chevronne@example.com');

        $sansRien = $this->lancerA($novice, 3);

        $this->acheverLaMission($chevronne, 1);
        $this->acheverLaMission($chevronne, 2);
        $avecBonus = $this->lancerA($chevronne, 3);

        self::assertSame(2, $this->bonus()->missionsQuiComptent($avecBonus));

        // Le legs du pharaon précédent s'ajoute lui aussi : ce sont deux
        // apports distincts, l'un du roi, l'autre de la maisonnée.
        $legs = $avecBonus->getLegsEnDeben();

        self::assertSame(
            $sansRien->getVille()->getDeben() + $legs + 2 * BonusDeDepart::DEBEN_PAR_MISSION,
            $avecBonus->getVille()->getDeben(),
        );
    }

    /**
     * **Il ne dépasse jamais la dotation** (arbitrage 9.0). Neuf missions
     * accomplies vaudraient à elles seules de quoi refaire une bonne part de
     * ce que le pharaon envoie. Le plafond se lit **sur la dotation
     * elle-même**, ressource par ressource : il n'y a rien à calibrer, et il
     * suit tout changement de coût des bâtiments d'ouverture comme toute
     * hausse du convoi de départ.
     */
    public function testLeBonusNeDepasseJamaisLaDotation(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('bonus-plafond@example.com');

        foreach (range(1, 9) as $mission) {
            $this->acheverLaMission($joueur, $mission);
        }

        // Sans passer par le lanceur : le plafond de parties par compte
        // compte aussi les parties achevées, ce qui est un défaut à part
        // entière et n'a rien à voir avec ce que ce test vérifie.
        $partie = GameSave::pourCampagne($joueur, new Family('Nakht'), new City('Ville', 3, 5));
        $partie->commencerALaMission(10);

        $dotation = DotationRoyale::pour(3, 10)->enRessources();
        $bonus = $this->bonus()->pour($partie, $dotation);

        self::assertSame(9, $this->bonus()->missionsQuiComptent($partie));

        // Le bonus brut de neuf missions est loin d'être négligeable devant la
        // dotation : c'est ce qui rend le plafond nécessaire, et c'est lui
        // qu'on vérifie ensuite, ressource par ressource.
        self::assertGreaterThan(
            intdiv($dotation[Ressource::Deben->value], 2),
            9 * BonusDeDepart::DEBEN_PAR_MISSION,
        );

        self::assertNotEmpty($bonus);

        foreach ($bonus as $valeur => $quantite) {
            self::assertLessThanOrEqual(
                $dotation[$valeur],
                $quantite,
                \sprintf('Le bonus de %s dépasse ce que le pharaon envoie.', $valeur),
            );
        }
    }

    /**
     * **Les vivres restent à la dotation seule** : elle les taille sur la
     * consommation réelle de la maisonnée envoyée, et un forfait par-dessus
     * casserait ce calcul — une famille nombreuse et une famille réduite
     * recevraient le même supplément.
     */
    public function testLesVivresRestentALaDotationSeule(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('bonus-vivres@example.com');

        $this->acheverLaMission($joueur, 1);
        $partie = $this->lancerA($joueur, 2);
        $dotation = DotationRoyale::pour(
            $partie->getVille()->getDifficulte(),
            $partie->getVille()->consommationDeNourriture(),
        )->enRessources();

        self::assertArrayNotHasKey(Ressource::Ble->value, $this->bonus()->pour($partie, $dotation));
    }

    /**
     * Rejouer une mission déjà faite ne la compte pas deux fois — même règle
     * que pour le carnet de contacts.
     */
    public function testRejouerUneMissionNeLaComptePasDeuxFois(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('bonus-rejeu@example.com');

        $this->acheverLaMission($joueur, 1);
        $this->acheverLaMission($joueur, 2);

        $rejeu = $this->lancerA($joueur, 2);

        self::assertSame(1, $this->bonus()->missionsQuiComptent($rejeu));
    }

    private function dotationDe(GameSave $partie): int
    {
        return DotationRoyale::pour(
            $partie->getVille()->getDifficulte(),
            $partie->getVille()->consommationDeNourriture(),
        )->enRessources()[Ressource::Deben->value];
    }

    private function lancerA(User $joueur, int $mission): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)
            ->lancerCampagne($joueur, 'Nakht', numeroDeMission: $mission);
    }

    private function acheverLaMission(User $joueur, int $mission): void
    {
        $partie = GameSave::pourCampagne($joueur, new Family('Nakht'), new City('Ville', 0, 3));
        $partie->commencerALaMission($mission);
        $partie->achever(100);

        $this->gestionnaire()->persist($partie);
        $this->gestionnaire()->flush();
    }

    private function creerJoueur(string $email): User
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');
        $joueur->setRoles([User::ROLE_DIVIN]);

        $this->gestionnaire()->persist($joueur);
        $this->gestionnaire()->flush();

        return $joueur;
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function bonus(): BonusDeDepart
    {
        return static::getContainer()->get(BonusDeDepart::class);
    }
}
