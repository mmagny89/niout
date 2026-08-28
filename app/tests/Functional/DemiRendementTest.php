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
use App\Game\Effectifs;
use App\Game\Exploitations;
use App\Game\LanceurDePartie;
use App\Game\PassageDeCycle;
use App\Game\Ressource;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeTerrain;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Ce que le demi-rendement change réellement en partie (lot 4.4).
 *
 * La formule a ses tests unitaires ; ceux-ci vérifient qu'elle **mord** — un
 * Grenier sans personne conserve moins qu'un Grenier tenu, et la différence
 * arrive bien dans le stock.
 */
final class DemiRendementTest extends KernelTestCase
{
    /**
     * L'esprit de la décision de la joueuse — « un bâtiment sans chef
     * fonctionne mais partiellement » — appliqué au canal qui le porte depuis
     * le lot 4.5 : un champ sans bras donne encore, mais moitié moins.
     *
     * La famille le moissonne elle-même ; c'est le plancher, jamais zéro.
     */
    public function testUnChampSansBrasNeDonneQueLaMoitieDeSaRecolte(): void
    {
        self::bootKernel();

        $sansBras = $this->recolterSurUnCycleComplet('champ-sans-bras@example.com', avecBras: false);
        $avecBras = $this->recolterSurUnCycleComplet('champ-avec-bras@example.com', avecBras: true);

        self::assertGreaterThan(
            0,
            $sansBras,
            'Rien ne s\'éteint faute d\'employés : un champ sans personne donne encore.',
        );

        self::assertSame(
            intdiv($avecBras, 2),
            $sansBras,
            'Sans bras, la moitié exactement de ce qu\'un champ tenu rapporte.',
        );
    }

    /**
     * L'invariant que le lot 4.4 promet — « tout tourne au moins à moitié » —
     * et que deux modificateurs qui se multiplieraient casseraient : une ville
     * entièrement dépeuplée doit garder la moitié de sa récolte, pas le quart.
     *
     * C'est ce qui a fait retirer le second modificateur du Grenier au lot 4.5 :
     * depuis qu'il gouverne les champs, il pesait deux fois sur la même
     * récolte.
     */
    public function testLaChaineAlimentaireNeDescendJamaisSousLaMoitie(): void
    {
        self::bootKernel();

        $sansBras = $this->recolterSurUnCycleComplet('plancher-sans@example.com', avecBras: false);
        $avecBras = $this->recolterSurUnCycleComplet('plancher-avec@example.com', avecBras: true);

        self::assertGreaterThanOrEqual(
            intdiv($avecBras, 2),
            $sansBras,
            'La récolte d\'une ville déserte ne doit jamais tomber sous la moitié.',
        );
    }

    /**
     * La réparation du déséquilibre le plus profond de la phase : jusqu'au
     * lot 4.5, une carrière rapportait autant à une ville déserte qu'à une
     * ville pourvue — la moitié de l'économie échappait au système d'emploi.
     */
    public function testUneCarriereSansBrasRapporteMoinsQuUneCarriereTenue(): void
    {
        self::bootKernel();

        $tenue = $this->extraireSurCinqCycles('carriere-tenue@example.com', avecBras: true);
        $deserte = $this->extraireSurCinqCycles('carriere-deserte@example.com', avecBras: false);

        self::assertGreaterThan(0, $deserte, 'La famille creuse elle-même : jamais zéro.');
        self::assertLessThan($tenue, $deserte, 'Une ville déserte ne doit pas extraire autant qu\'une ville pourvue.');
    }

    /**
     * La boucle que le lot referme : bâtir plus haut fait produire plus — à
     * condition d'avoir les bras, puisque le niveau réclame aussi un équipage
     * plus large.
     */
    public function testMonterLEntrepotFaitExtraireDavantage(): void
    {
        self::bootKernel();

        $basique = $this->extraireSurCinqCycles('entrepot-1@example.com', avecBras: true, niveauEntrepot: 1);
        $eleve = $this->extraireSurCinqCycles('entrepot-7@example.com', avecBras: true, niveauEntrepot: 7);

        self::assertGreaterThan($basique, $eleve);
    }

    /**
     * Ouvre une carrière et rend ce qu'elle a versé au stock.
     */
    private function extraireSurCinqCycles(string $email, bool $avecBras, int $niveauEntrepot = 1): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->decouvrir();
        $zone->poserUnGisement(Ressource::Calcaire, 999);

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Entrepot, $niveauEntrepot));
        static::getContainer()->get(Exploitations::class)->exploiter($partie, $zone, Ressource::Calcaire);

        if (!$avecBras) {
            $ville->laisserPartir($ville->getActifs(), 0);
        }

        $avant = $ville->quantite(Ressource::Calcaire);

        for ($i = 0; $i < 5; ++$i) {
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
        }

        return $ville->quantite(Ressource::Calcaire) - $avant;
    }

    /**
     * Les bras d'un bâtiment se puisent dans le vivier d'actifs (doc 05) : une
     * ville sans actifs disponibles ne tient rien, même avec un chef.
     */
    public function testUnChefSansBrasNeTientPasSonBatiment(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-bras@example.com');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier);

        self::assertSame(
            Effectifs::RENDEMENT_PLEIN,
            Effectifs::rendementDe($ville, TypeDeBatiment::Grenier, $partie->getCycle()),
        );

        // La ville perd tous ses actifs : il ne reste personne à envoyer.
        $ville->laisserPartir($ville->getActifs(), 0);

        self::assertSame(
            Effectifs::RENDEMENT_PLANCHER,
            Effectifs::rendementDe($ville, TypeDeBatiment::Grenier, $partie->getCycle()),
        );
    }

    /**
     * Un chef embauché n'a rien recruté tant qu'il n'a pas pris son poste
     * (doc 05) : le bâtiment ne réclame donc encore aucun bras.
     */
    public function testUnChefPasEncoreEnPosteNeReclameAucunBras(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('pas-en-poste@example.com');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier, dansUnCycle: true);

        $batiment = $ville->batimentDeType(TypeDeBatiment::Grenier);
        self::assertNotNull($batiment);

        self::assertSame(0, Effectifs::travailleursRequis($batiment, $partie->getCycle()));
        self::assertSame(1, Effectifs::travailleursRequis($batiment, $partie->getCycle() + 1));
    }

    /**
     * Les chefs sont des actifs comme les autres : ils ne s'encadrent pas
     * eux-mêmes, et ne comptent donc pas parmi les bras à placer.
     */
    public function testUnChefNeSeCompteJamaisParmiLesBrasQuIlEncadre(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('bras@example.com');
        $ville = $partie->getVille();
        $actifs = $ville->getActifs();

        self::assertSame($actifs, Effectifs::brasDisponibles($ville, $partie->getCycle()));

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        $this->installerUnChef($partie, TypeDeBatiment::Grenier);

        self::assertSame($actifs - 1, Effectifs::brasDisponibles($ville, $partie->getCycle()));
    }

    /**
     * Sème un champ, fait tourner un cycle agricole complet et rend ce qui est
     * réellement entré au stock — consommation neutralisée.
     */
    private function recolterSurUnCycleComplet(string $email, bool $avecBras): int
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        $zone = $this->premiereZoneHorsVille($partie);
        $zone->definirTerrain(TypeDeTerrain::Fertile)
            ->poserUnContenu(ContenuDeZone::ChampEligible)
            ->decouvrir();

        static::getContainer()->get(Exploitations::class)->semer($partie, $zone, Culture::Ble);
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));

        if (!$avecBras) {
            // Plus un seul actif : personne pour tenir le champ ni le Grenier.
            $ville->laisserPartir($ville->getActifs(), 0);
        }

        $recolte = 0;

        for ($i = 0; $i < \App\Game\CycleAgricoleTerrestre::DUREE_TOTALE; ++$i) {
            $avant = $ville->quantite(Ressource::Ble);
            $consommation = $ville->consommationDeNourriture();
            static::getContainer()->get(PassageDeCycle::class)->passer($partie);
            $recolte += $ville->quantite(Ressource::Ble) - $avant + $consommation;
        }

        return $recolte;
    }

    private function installerUnChef(GameSave $partie, TypeDeBatiment $type, bool $dansUnCycle = false): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 20,
                traits: [],
                specialite: null,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $dansUnCycle ? $partie->getCycle() + 1 : $partie->getCycle(),
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
}
