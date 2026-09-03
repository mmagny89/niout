<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\CataloguePartenaires;
use App\Game\Commerce;
use App\Game\LanceurDePartie;
use App\Game\PartenaireCommercial;
use App\Game\Ressource;
use App\Game\SuccessionDesRegnes;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Memphis commerce, et ses routes suivent le règne (doc 14, lot 11.2).
 *
 * C'était le défaut de fond de la phase : `partenairesDe()` s'indexait par
 * numéro de mission et rendait un tableau vide sans elle, si bien qu'en mode
 * Aventure **aucune route n'était ouvrable**. L'Entrepôt et le Port ne
 * servaient à rien — alors que le doc 14 refuse délibérément à Memphis l'or, le
 * cuivre et la turquoise en zone locale, parce que « son atout réel est l'accès
 * privilégié aux ressources importées ».
 */
final class CommerceDeMemphisTest extends KernelTestCase
{
    /**
     * **Memphis n'est jamais sans partenaire**, quel que soit le règne : le
     * fleuve est le socle, et il ne dépend d'aucun roi. Sans lui, un règne
     * tourné vers l'intérieur reproduirait le défaut que ce lot corrige.
     */
    public function testMemphisNestJamaisSansPartenaire(): void
    {
        $catalogue = new CataloguePartenaires();

        foreach ((new SuccessionDesRegnes())->tous() as $regne) {
            self::assertNotEmpty(
                $catalogue->pourMemphis($regne),
                \sprintf('Sous %s, Memphis n\'a aucun débouché.', $regne->pharaon),
            );
        }

        // Et même une fois la succession épuisée, le fleuve reste.
        self::assertNotEmpty($catalogue->pourMemphis(null));
    }

    /**
     * **Les routes suivent le pharaon** (arbitrage 11.0), et c'est ce qui fait
     * de la succession autre chose qu'un habillage narratif : sous Hatchepsout
     * on arme pour Pount, sous Amenhotep III on traite avec Babylone.
     */
    public function testLesRoutesSuiventLePharaon(): void
    {
        $catalogue = new CataloguePartenaires();
        $regnes = [];

        foreach ((new SuccessionDesRegnes())->tous() as $regne) {
            $regnes[$regne->pharaon] = $regne;
        }

        self::assertContains('pount', $this->clesDe($catalogue->pourMemphis($regnes['Hatchepsout'])));
        self::assertNotContains('pount', $this->clesDe($catalogue->pourMemphis($regnes['Horemheb'])));

        self::assertContains('babylone', $this->clesDe($catalogue->pourMemphis($regnes['Amenhotep III'])));
        self::assertNotContains('babylone', $this->clesDe($catalogue->pourMemphis($regnes['Ahmôsis Ier'])));

        // Un règne bref peut n'ouvrir que le fleuve : c'est une contrainte de
        // jeu autant qu'un fait.
        self::assertSame(['delta', 'thebes'], $this->clesDe($catalogue->pourMemphis($regnes['Aÿ'])));
    }

    /**
     * Le parcours réel : une partie Aventure peut ouvrir une route et voir un
     * convoi partir. C'est ce qui était impossible avant ce lot.
     */
    public function testUnePartieAventurePeutOuvrirUneRoute(): void
    {
        self::bootKernel();
        $partie = $this->lancerAventure('memphis-route@example.com');
        $ville = $partie->getVille();

        $offre = $this->commerce()->offrePour($partie);
        self::assertNotEmpty($offre, 'Memphis doit avoir des routes à ouvrir.');

        $partenaire = $offre[0]['partenaire'];
        $ville->ajouterBatiment(new Building($ville, $partenaire->route->batiment()));
        $ville->crediterRessources([Ressource::Deben->value => 10_000]);

        $route = $this->commerce()->ouvrir($partie, $partenaire->cle);

        self::assertSame($partenaire->cle, $route->getPartenaire());
        self::assertNotNull($this->commerce()->partenaireDe($partie, $partenaire->cle));
    }

    /**
     * **La campagne n'est pas touchée** : elle garde ses partenaires par
     * mission, et Memphis y reste un partenaire du Delta comme avant.
     */
    public function testLaCampagneGardeSesPartenairesParMission(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('memphis-campagne@example.com');
        $campagne = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');

        $cles = $this->clesDe($this->commerce()->partenairesDe($campagne));

        self::assertContains('memphis', $cles, 'Memphis reste un débouché de la première mission.');
        self::assertNotContains('delta', $cles, 'Les routes de Memphis ne fuient pas dans la campagne.');
    }

    /**
     * @param list<PartenaireCommercial> $partenaires
     *
     * @return list<string>
     */
    private function clesDe(array $partenaires): array
    {
        return array_map(static fn (PartenaireCommercial $p): string => $p->cle, $partenaires);
    }

    private function lancerAventure(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)
            ->lancerAventure($this->creerJoueur($email), 'Nakht', difficulte: 0, tailleGrille: 5);
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

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }
}
