<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AvantageDeNegoce;
use App\Game\CarnetDeContacts;
use App\Game\Commerce;
use App\Game\EffetDeChef;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use App\Game\Ressource;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Le carnet de contacts et l'héritage des routes (doc 13 et doc 12, lot 9.4).
 *
 * Une mission accomplie laisse deux choses derrière elle : des gens qu'on
 * connaît dans la région — qui font un prix sur ce qu'elle porte — et un chemin
 * qu'on a déjà pris, qui s'ouvre moins cher. Les deux se complètent : l'un
 * porte sur les prix courants, l'autre sur le droit d'entrée.
 */
final class CarnetDeContactsTest extends KernelTestCase
{
    /**
     * **Rien ne se persiste** : le carnet se déduit des missions accomplies,
     * comme les partenaires commerciaux se déduisent du catalogue. Le nom, la
     * région et les ressources sont du contenu.
     */
    public function testLeCarnetSeDeduitDesMissionsAccomplies(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('carnet-deduction@example.com');

        $partie = $this->lancerA($joueur, 3);
        self::assertSame([], $this->carnet()->pour($partie));

        $this->acheverLaMission($joueur, 1);
        $this->acheverLaMission($joueur, 2);

        $contacts = $this->carnet()->pour($partie);

        self::assertCount(2, $contacts);
        self::assertSame(
            [$this->missions()->get(1)->ville, $this->missions()->get(2)->ville],
            array_map(static fn ($mission): string => $mission->ville, $contacts),
        );
    }

    /**
     * **On ne se fait pas de prix à soi-même** : la ville de la mission en
     * cours n'est pas un contact, même si le joueur l'a déjà accomplie une fois
     * et la rejoue.
     */
    public function testLaVilleDeLaMissionEnCoursNestPasUnContact(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('carnet-soi-meme@example.com');

        $this->acheverLaMission($joueur, 1);
        $rejouee = $this->lancerA($joueur, 1);

        self::assertSame([], $this->carnet()->pour($rejouee));
    }

    /**
     * Le contact fait un prix **sur ce que sa région porte**, et sur rien
     * d'autre. C'est ce qui l'empêche d'être une remise générale de plus.
     */
    public function testUnContactNeFaitUnPrixQueSurSaRegion(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('carnet-region@example.com');

        $this->acheverLaMission($joueur, 1);
        $partie = $this->lancerA($joueur, 2);

        $duDelta = $this->missions()->get(1)->geographie->ressourcesDeZone;
        self::assertNotEmpty($duDelta, 'La première région doit porter des gisements.');

        self::assertSame(
            CarnetDeContacts::AVANTAGE_PAR_CONTACT,
            $this->carnet()->avantageSur($partie, $duDelta[0]),
        );

        $etrangere = $this->uneRessourceHorsDe($duDelta);
        self::assertSame(0, $this->carnet()->avantageSur($partie, $etrangere));
    }

    /**
     * **Le carnet s'ajoute dans le même facteur que la renommée**, il ne pose
     * pas son propre coefficient — et la somme passe par le plafond commun
     * (arbitrage 9.0). Sans Négociateur, une famille illustre et un contact
     * font vingt-deux points : le plafond ne mord pas encore, et c'est bien ce
     * qu'on veut vérifier avant de vérifier qu'il mord.
     */
    public function testLeCarnetSAjouteALaRenommeeDansLeMemeFacteur(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('carnet-plafond@example.com');

        $this->acheverLaMission($joueur, 1);
        $partie = $this->lancerA($joueur, 2);
        $partie->getFamille()->ajusterRenommee(Family::RENOMMEE_MAX);

        $duDelta = $this->missions()->get(1)->geographie->ressourcesDeZone;
        $attendu = AvantageDeNegoce::deLaRenommee(Family::RENOMMEE_MAX) + CarnetDeContacts::AVANTAGE_PAR_CONTACT;

        self::assertLessThan(AvantageDeNegoce::PLAFOND_TOTAL, $attendu);
        self::assertSame($attendu, $this->commerce()->avantageDeNegoce($partie, $duDelta[0]));

        // Et le plafond mord dès qu'un Négociateur s'y ajoute.
        self::assertSame(
            AvantageDeNegoce::PLAFOND_TOTAL,
            AvantageDeNegoce::total(Family::RENOMMEE_MAX, CarnetDeContacts::AVANTAGE_PAR_CONTACT + EffetDeChef::BONUS_NEGOCIATEUR),
        );
    }

    /**
     * **L'héritage du doc 12** : une route déjà armée dans une partie
     * précédente s'ouvre à −20 %. Ce n'est pas le carnet — celui-ci porte sur
     * les prix courants, l'héritage sur le droit d'entrée.
     */
    public function testUneRouteDejaArmeeSOuvreMoinsCher(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('routes-heritees@example.com');

        $premiere = $this->lancerA($joueur, 1);
        $cle = $this->uneRouteOuvrable($premiere);

        $plein = $this->commerce()->coutDOuverture($premiere, $cle);
        $this->armerLaRoute($premiere, $cle);

        $seconde = $this->lancerA($joueur, 1);
        $herite = $this->commerce()->coutDOuverture($seconde, $cle);

        self::assertLessThan($plein, $herite);
        self::assertSame(
            $plein - intdiv($plein * Commerce::RABAIS_DUNE_ROUTE_HERITEE, 100),
            $herite,
        );
    }

    /**
     * **La partie en cours ne s'hérite pas elle-même** : rouvrir une route
     * qu'on vient de fermer dans la même ville ne relève pas de l'héritage.
     */
    public function testUnePartieNeSHeritePasElleMeme(): void
    {
        self::bootKernel();
        $joueur = $this->creerJoueur('routes-soi-meme@example.com');

        $partie = $this->lancerA($joueur, 1);
        $cle = $this->uneRouteOuvrable($partie);

        $plein = $this->commerce()->coutDOuverture($partie, $cle);
        $this->armerLaRoute($partie, $cle);

        self::assertSame($plein, $this->commerce()->coutDOuverture($partie, $cle));
    }

    private function uneRouteOuvrable(GameSave $partie): string
    {
        foreach ($this->commerce()->offrePour($partie) as $offre) {
            return $offre['partenaire']->cle;
        }

        self::fail('Aucun partenaire n\'est à portée de cette mission.');
    }

    /**
     * Arme la route sans jouer les quinzaines : c'est son existence qui fait
     * l'héritage, pas l'arrivée du premier convoi.
     */
    private function armerLaRoute(GameSave $partie, string $cle): void
    {
        $ville = $partie->getVille();
        $partenaire = $this->commerce()->partenairesDe($partie)[0];
        $ville->ajouterBatiment(new Building($ville, $partenaire->route->batiment()));
        $ville->crediterRessources([Ressource::Deben->value => 10_000]);

        $this->commerce()->ouvrir($partie, $cle);
    }

    /**
     * @param list<Ressource> $exclues
     */
    private function uneRessourceHorsDe(array $exclues): Ressource
    {
        foreach (Ressource::cases() as $ressource) {
            if (!\in_array($ressource, $exclues, true)) {
                return $ressource;
            }
        }

        self::fail('Toutes les ressources appartiennent à cette région.');
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
        // Le mode d'essai ouvre les dix régions : ces tests portent sur le
        // carnet, pas sur l'ordre de la campagne.
        $joueur->setRoles([User::ROLE_DIVIN]);

        $this->gestionnaire()->persist($joueur);
        $this->gestionnaire()->flush();

        return $joueur;
    }

    private function gestionnaire(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function carnet(): CarnetDeContacts
    {
        return static::getContainer()->get(CarnetDeContacts::class);
    }

    private function commerce(): Commerce
    {
        return static::getContainer()->get(Commerce::class);
    }

    private function missions(): MissionCatalogue
    {
        return static::getContainer()->get(MissionCatalogue::class);
    }
}
