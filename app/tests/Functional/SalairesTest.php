<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\ContenuDeZone;
use App\Game\Culture;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\Salaires;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SalairesTest extends KernelTestCase
{
    /**
     * La première charge récurrente en deben du jeu : elle tombe à chaque
     * quinzaine, comme les vivres.
     */
    public function testLesSalairesSontPrelevesAChaqueQuinzaine(): void
    {
        self::bootKernel();
        $partie = $this->villeQuiEmploie('paie@example.com');
        $ville = $partie->getVille();

        $masse = $this->salaires()->masseSalariale($ville, $partie->getCycle());
        self::assertGreaterThan(0, $masse, 'Une ville qui emploie doit devoir quelque chose.');

        $avant = $ville->getDeben();
        $this->salaires()->reglerLaQuinzaine($partie);

        self::assertSame($avant - $masse, $ville->getDeben());
    }

    /**
     * La masse salariale se calcule sur la composition réelle des effectifs,
     * jamais sur un forfait (demande de la joueuse) : ouvrir une carrière la
     * fait monter d'elle-même.
     */
    public function testLaMasseSalarialeSuitLesEffectifsReels(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('masse@example.com');
        $ville = $partie->getVille();

        self::assertSame(
            0,
            $this->salaires()->masseSalariale($ville, $partie->getCycle()),
            'Une ville qui n\'emploie personne ne doit rien.',
        );

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Calcaire, 999);
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);

        self::assertSame(
            2 * Salaires::SALAIRE_DUN_TRAVAILLEUR,
            $this->salaires()->masseSalariale($ville, $partie->getCycle()),
            'Deux hommes sur une carrière, deux deben.',
        );
    }

    /**
     * Un chef coûte bien plus qu'un ouvrier — c'est ce qui fait de son
     * embauche un arbitrage plutôt qu'une évidence.
     */
    public function testUnChefPeseBienPlusQuUnOuvrier(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('chef-cher@example.com');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $avecLeSeulOuvrier = $this->salaires()->masseSalariale($ville, $partie->getCycle());

        $this->installerUnChef($partie, TypeDeBatiment::Grenier, salaire: 8);
        $avecLeChef = $this->salaires()->masseSalariale($ville, $partie->getCycle());

        self::assertSame(0, $avecLeSeulOuvrier, 'Sans chef, le Grenier ne réclame aucun bras.');
        self::assertSame(8 + Salaires::SALAIRE_DUN_TRAVAILLEUR, $avecLeChef);
    }

    /**
     * Ce que le lot tranche là où aucun document ne le disait : une équipe
     * qu'on ne paie pas cesse le travail.
     */
    public function testUneEquipeImpayeeCesseLeTravail(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('impaye@example.com');
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Calcaire, 999);
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);

        // Bourse vidée : plus rien pour payer les deux hommes de la carrière.
        $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);

        $paie = $this->salaires()->reglerLaQuinzaine($partie);

        self::assertFalse($paie->toutEstPaye());
        self::assertSame(0, $paie->verse);
        self::assertGreaterThan(0, $paie->manque());

        $avant = $ville->quantite(Ressource::Calcaire);
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        self::assertSame(
            $avant,
            $ville->quantite(Ressource::Calcaire),
            'Une équipe impayée ne descend pas au fond : la carrière ne rend rien.',
        );
    }

    /**
     * La conséquence assumée du lot, celle qui donne au joueur une action
     * claire : un poste impayé rend **moins** qu'un poste vacant, qui tourne
     * encore à moitié. Mieux vaut renvoyer que laisser en poste sans payer.
     */
    public function testUnPosteImpayeRendMoinsQuUnPosteVacant(): void
    {
        self::bootKernel();

        $impaye = $this->extraireUnCycle('carriere-impayee@example.com', solvable: false);
        $vacant = $this->extraireUnCycle('carriere-vacante@example.com', solvable: true, avecBras: false);

        self::assertSame(0, $impaye, 'Impayé : rien du tout.');
        self::assertGreaterThan($impaye, $vacant, 'Vacant : la famille gratte encore.');
    }

    /**
     * Le pharaon avance une année de salaires en plus de l'année de vivres
     * (décision de la joueuse) : il finance le démarrage, pas la suite.
     */
    public function testLaDotationCouvreUneAnneeDeSalairesDesBrasEnvoyes(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('dotation@example.com');

        self::assertGreaterThanOrEqual(
            \App\Game\Population::ACTIFS_AU_DEPART
                * Salaires::SALAIRE_DUN_TRAVAILLEUR
                * \App\Game\DateDeJeu::CYCLES_PAR_ANNEE,
            $partie->getVille()->getDeben(),
        );
    }

    private function extraireUnCycle(string $email, bool $solvable, bool $avecBras = true): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Calcaire, 999);
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);

        if (!$solvable) {
            $ville->debiterRessources([Ressource::Deben->value => $ville->getDeben()]);
        }

        if (!$avecBras) {
            $ville->laisserPartir($ville->getActifs(), 0);
        }

        $avant = $ville->quantite(Ressource::Calcaire);
        static::getContainer()->get(PassageDeCycle::class)->passer($partie);

        return $ville->quantite(Ressource::Calcaire) - $avant;
    }

    /**
     * Une ville qui emploie pour de bon : un Grenier tenu et un champ semé.
     */
    private function villeQuiEmploie(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier, salaire: 8);

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->definirTerrain(TypeDeTerrain::Fertile)
            ->poserUnContenu(ContenuDeZone::ChampEligible)
            ->decouvrir();
        static::getContainer()->get(Exploitations::class)->semer($partie, $zone, Culture::Ble);

        return $partie;
    }

    private function installerUnChef(GameSave $partie, TypeDeBatiment $type, int $salaire): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: $salaire,
                ancienneteProbable: 20,
                traits: [],
                specialite: null,
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

    private function salaires(): Salaires
    {
        return static::getContainer()->get(Salaires::class);
    }
}
