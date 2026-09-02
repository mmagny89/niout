<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Equipement;
use App\Game\LanceurDePartie;
use App\Game\MedjayImpossible;
use App\Game\Medjays;
use App\Game\Recette;
use App\Game\Ressource;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * L'équipement des Medjaÿ (doc 03, doc 01, lot 10.3).
 *
 * C'est ce lot qui donne enfin aux **armes** une raison d'exister autre que la
 * vente. Deux arbitrages du 10.0 y sont vérifiés : l'arme est **durable**, et
 * un homme sans arme **part quand même** — aucune chaîne de production ne
 * décide du rythme militaire.
 */
final class EquipementDesMedjaysTest extends KernelTestCase
{
    /**
     * Le compte du doc 01 : « +5 % par niveau à partir du niveau 3 ». Une Forge
     * de niveau 6, son maximum, vaut +20 %. En dessous du troisième niveau, on
     * forge des armes mais pas de meilleures armes.
     *
     * **La difficulté régionale y met sa propre borne** : `niveauMaxRegion =
     * 5 + difficulté` (doc 01), si bien qu'une Forge de niveau 6 est hors
     * d'atteinte dans le Delta. La meilleure arme du jeu se forge donc dans les
     * régions difficiles — ce qui tombe juste, ce sont elles qui portent des
     * bandits.
     */
    public function testLaQualiteSuitLeNiveauDeForge(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('qualite-forge@example.com');
        $ville = $partie->getVille();

        // Sans Forge : l'arme vient d'ailleurs et vaut la référence.
        self::assertSame(Equipement::QUALITE_DE_REFERENCE, Equipement::qualiteForgeePar($ville));

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge));
        self::assertSame(Equipement::QUALITE_DE_REFERENCE, Equipement::qualiteForgeePar($ville));

        self::assertSame(105, Equipement::qualiteForgeePar($this->villeAvecForgeDeNiveau(3)));
        self::assertSame(120, Equipement::qualiteForgeePar($this->villeAvecForgeDeNiveau(6)));
    }

    /**
     * **Un homme sans arme part quand même**, à qualité réduite (arbitrage
     * 10.0). Rien ne bloque une expédition : c'est ce qui évite qu'une carrière
     * gardée reste imprenable faute de cuivre.
     */
    public function testUnHommeSansArmeCombatQuandMeme(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('sans-arme@example.com');
        $medjay = $this->leverUnFantassin($partie);

        self::assertFalse($medjay->estArme());
        self::assertSame(Equipement::QUALITE_SANS_ARME, $medjay->getQualiteDeLequipement());
        self::assertGreaterThan(0, $medjay->force());
    }

    /**
     * **L'arme est durable** (arbitrage 10.0) : ce qu'on dépense est la pièce
     * elle-même, prise au stock, et non une consommation par combat. La Forge
     * est un palier à franchir, pas un robinet à tenir ouvert.
     */
    public function testArmerPrendUneArmeAuStockEtRenforceLhomme(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('armer@example.com');
        $ville = $partie->getVille();
        $medjay = $this->leverUnFantassin($partie);

        $ville->crediterRessources([Ressource::Armes->value => 2]);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, niveau: 4));

        $avant = $medjay->force();
        $qualite = $this->medjays()->armer($partie, $medjay);

        self::assertSame(110, $qualite);
        self::assertTrue($medjay->estArme());
        self::assertGreaterThan($avant, $medjay->force());
        self::assertSame(1, $ville->quantite(Ressource::Armes), 'Une arme, et une seule.');
    }

    /**
     * **La qualité se fige à la remise de l'arme.** Monter la Forge n'améliore
     * pas rétroactivement ce qu'on a déjà donné : il faut réarmer, ce qui fait
     * du niveau de Forge une décision plutôt qu'un compteur.
     */
    public function testMonterLaForgeNameliorePasLesArmesDejaDonnees(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('rearmer@example.com');
        $ville = $partie->getVille();
        $medjay = $this->leverUnFantassin($partie);

        $ville->crediterRessources([Ressource::Armes->value => 2]);
        $forge = new Building($ville, TypeDeBatiment::Forge, niveau: 3);
        $ville->ajouterBatiment($forge);

        $this->medjays()->armer($partie, $medjay);
        self::assertSame(105, $medjay->getQualiteDeLequipement());

        // Deux niveaux de plus : la première région plafonne les bâtiments à
        // cinq (`niveauMaxRegion = 5 + difficulté`, doc 01), ce qui borne aussi
        // la qualité d'arme qu'on peut y forger.
        $forge->monterDUnNiveau()->monterDUnNiveau();
        self::assertSame(5, $forge->getNiveau());
        self::assertSame(105, $medjay->getQualiteDeLequipement(), 'L\'arme déjà donnée ne se bonifie pas seule.');

        $this->medjays()->armer($partie, $medjay);
        self::assertSame(115, $medjay->getQualiteDeLequipement());
    }

    /**
     * On ne réarme pas pour rien : une Forge qui ne sait rien faire de mieux
     * refuse, et ne prend aucune arme au stock.
     */
    public function testOnNeRearmePasSansGain(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('sans-gain@example.com');
        $ville = $partie->getVille();
        $medjay = $this->leverUnFantassin($partie);

        $ville->crediterRessources([Ressource::Armes->value => 3]);
        $this->medjays()->armer($partie, $medjay);

        $reste = $ville->quantite(Ressource::Armes);

        try {
            $this->medjays()->armer($partie, $medjay);
            self::fail('Le réarmement aurait dû être refusé.');
        } catch (MedjayImpossible) {
            self::assertSame($reste, $ville->quantite(Ressource::Armes), 'Un refus ne doit rien prélever.');
        }
    }

    /**
     * **Les armes ne dorment plus.** Le jeu disait d'elles qu'elles n'avaient
     * pas d'usage propre ; elles en ont un, et l'interface ne doit plus
     * prétendre le contraire.
     */
    public function testLesArmesNeSontPlusUnProduitSansUsage(): void
    {
        self::assertFalse(Recette::Armes->produitDortEnAttendantSonUsage());
    }

    private function villeAvecForgeDeNiveau(int $niveau): \App\Entity\City
    {
        $ville = new \App\Entity\City('Ville', 9, 3);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Forge, $niveau));

        return $ville;
    }

    private function leverUnFantassin(GameSave $partie): \App\Entity\Medjay
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne));
        $ville->crediterRessources([Ressource::Deben->value => 500]);

        return $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
    }

    private function lancerUnePartie(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }

    private function medjays(): Medjays
    {
        return static::getContainer()->get(Medjays::class);
    }
}
