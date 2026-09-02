<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\Bandits;
use App\Game\Combat;
use App\Game\CombatImpossible;
use App\Game\Divinite;
use App\Game\EffetDeFaveur;
use App\Game\LanceurDePartie;
use App\Game\Medjays;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\Ressource;
use App\Game\SpecialisationMedjay;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * La résolution automatique d'une sortie (doc 03, lot 10.4).
 *
 * **Aucun contrôle pendant le combat** : le joueur agit en amont, et la sortie
 * se résout d'un bloc. Ces tests portent donc sur ce qui décide *avant* — la
 * force, le terrain, la faveur — et sur ce que la sortie laisse derrière elle.
 *
 * Les tirages aléatoires ne s'y testent pas au dé près : ce qui se vérifie est
 * qu'une victoire prend la case et qu'une case prise le reste.
 */
final class CombatTest extends KernelTestCase
{
    /**
     * **On n'attaque pas une case qui n'est pas gardée**, ni une case qu'on
     * n'a pas reconnue. Les gardes vivent dans le domaine, pas seulement dans
     * le gabarit.
     */
    public function testOnNattaquePasUneCaseLibre(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-libre@example.com');
        $zone = $this->uneZoneOrdinaire($partie);
        $zone->decouvrir();

        $this->expectException(CombatImpossible::class);
        $this->combat()->livrer($partie, $zone, [$this->leverUnHomme($partie)]);
    }

    /**
     * **Sans homme valide, rien ne part.** Un blessé ne compte pas — c'est ce
     * qui donne son poids à la convalescence.
     */
    public function testSansHommeValideRienNePart(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-sans-homme@example.com');
        $zone = $this->uneZoneGardee($partie);

        $medjay = $this->leverUnHomme($partie);
        $medjay->blesser($partie->getCycle(), Combat::QUINZAINES_DE_CONVALESCENCE);

        $this->expectException(CombatImpossible::class);
        $this->combat()->livrer($partie, $zone, [$medjay]);
    }

    /**
     * **Une victoire prend la case, et elle le reste** (arbitrage 10.0). Le
     * butin tombe, les survivants apprennent quelque chose.
     *
     * La troupe est ici largement supérieure : à quinze hommes contre une
     * bande, la victoire est quasi certaine, et c'est bien la conséquence
     * d'une victoire qu'on veut vérifier, pas le dé.
     */
    public function testUneVictoirePrendLaCasePourDeBon(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-victoire@example.com');
        $zone = $this->uneZoneGardee($partie);
        $ville = $partie->getVille();

        $troupe = $this->leverUneTroupe($partie, 15);
        $deben = $ville->getDeben();

        $resultat = $this->combat()->livrer($partie, $zone, $troupe);

        self::assertTrue($resultat->victoire, 'Quinze hommes contre une bande : la victoire est attendue.');
        self::assertFalse($zone->estGardee(), 'Une case prise le reste.');
        // Le butin se compte sur ce que la bande opposait **réellement**,
        // renfort de région compris — pas sur sa défense nue.
        self::assertSame(
            intdiv($resultat->scoreDefense * Combat::BUTIN_POUR_CENT_DE_LA_DEFENSE, 100),
            $resultat->butin,
        );
        self::assertGreaterThan(Bandits::DEFENSE_DE_BASE, $resultat->scoreDefense);
        self::assertSame($deben + $resultat->butin, $ville->getDeben());

        // Les survivants ont appris. Ceux qui sont tombés ne comptent plus.
        foreach ($ville->getMedjays() as $survivant) {
            self::assertSame(Medjay::EXPERIENCE_PAR_VICTOIRE, $survivant->getExperience());
        }
    }

    /**
     * **Le désert avantage le défenseur** (doc 03) : celui qui connaît les
     * points d'eau tient contre plus nombreux que lui.
     */
    public function testLeDesertAvantageLeDefenseur(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-desert@example.com');

        $ville = $partie->getVille();

        // Le facteur ne dépend que du terrain de la case : on le vérifie sur
        // deux cases fabriquées, la carte du Delta ne portant pas de désert.
        $neutre = new Zone($ville, 0, 0, TypeDeTerrain::Fertile);
        $desert = new Zone($ville, 0, 1, TypeDeTerrain::Desert);

        self::assertSame(Combat::TERRAIN_NEUTRE, $this->combat()->facteurDeTerrain($partie, $neutre));
        self::assertSame(Combat::TERRAIN_DESERT, $this->combat()->facteurDeTerrain($partie, $desert));
    }

    /**
     * **Sekhmet décide du sort de tous** (doc 03, doc 07) : elle infléchit le
     * score, à la hausse comme à la baisse. Isis, elle, n'entre pas ici.
     */
    public function testSekhmetInflechitLeScore(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-sekhmet@example.com');
        $ville = $partie->getVille();

        self::assertSame(Combat::TERRAIN_NEUTRE, $this->combat()->facteurDeFaveur($ville));

        $this->porterAuFavorable($partie, Divinite::Sekhmet);

        self::assertSame(Combat::FAVEUR_ACQUISE, $this->combat()->facteurDeFaveur($ville));
    }

    /**
     * **Isis protège l'homme, elle ne décide pas du combat** (doc 07). Elle
     * réduit la mort permanente, et rien d'autre — c'est ce qui la distingue de
     * Sekhmet, et c'est ce qui lui donne enfin un domaine.
     */
    public function testIsisReduitLaMortSansToucherALissue(): void
    {
        self::bootKernel();
        $partie = $this->lancerUnePartie('combat-isis@example.com');
        $ville = $partie->getVille();

        self::assertSame(0, EffetDeFaveur::protectionDIsis($ville));

        $this->porterAuFavorable($partie, Divinite::Isis);

        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Isis));
        self::assertSame(EffetDeFaveur::MORT_MOINS_PROBABLE_FAVORABLE, EffetDeFaveur::protectionDIsis($ville));

        // Et l'issue du combat reste ce qu'elle était : Isis n'y touche pas.
        self::assertSame(Combat::TERRAIN_NEUTRE, $this->combat()->facteurDeFaveur($ville));
    }

    /**
     * **Isis n'est plus la divinité sans emploi.** Elle était la dernière du
     * panthéon à l'annoncer ; plus aucun dieu ne dort.
     */
    public function testPlusAucunDieuNestSansEmploi(): void
    {
        foreach (Divinite::pantheon() as $divinite) {
            self::assertTrue($divinite->agitDeja(), \sprintf('%s agit.', $divinite->libelle()));
        }
    }

    private function porterAuFavorable(GameSave $partie, Divinite $divinite): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->crediterRessources([Ressource::Deben->value => 5_000]);

        $offrandes = static::getContainer()->get(Offrandes::class);

        while (!$ville->palierDe($divinite)->estAuDessusDuNeutre()) {
            $offrandes->offrir($partie, $divinite, Ressource::Deben, 20);
        }
    }

    private function uneZoneOrdinaire(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->porteLaVille() && !$zone->estGardee()) {
                return $zone;
            }
        }

        self::fail('La carte ne porte aucune case ordinaire.');
    }

    private function uneZoneGardee(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->porteLaVille() || $zone->estGardee()) {
                continue;
            }

            $zone->decouvrir();
            $zone->installerUneBande(Bandits::DEFENSE_DE_BASE);

            return $zone;
        }

        self::fail('La carte ne porte aucune case où installer une bande.');
    }

    /**
     * @return list<Medjay>
     */
    private function leverUneTroupe(GameSave $partie, int $combien): array
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Caserne, 9));
        $ville->crediterRessources([Ressource::Deben->value => 5_000]);

        $troupe = [];

        foreach (range(1, $combien) as $ignore) {
            $troupe[] = $this->medjays()->lever($partie, SpecialisationMedjay::Fantassin);
        }

        return $troupe;
    }

    private function leverUnHomme(GameSave $partie): Medjay
    {
        return $this->leverUneTroupe($partie, 1)[0];
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

    private function combat(): Combat
    {
        return static::getContainer()->get(Combat::class);
    }

    private function medjays(): Medjays
    {
        return static::getContainer()->get(Medjays::class);
    }
}
