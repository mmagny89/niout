<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\GameSave;
use App\Entity\Npc;
use App\Entity\User;
use App\Fenetre\LeMonde;
use App\Game\Cour;
use App\Game\GeographieDeLaPartie;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use App\Game\PrixDuMarche;
use App\Game\QuetesDeChantier;
use App\Game\RoleDePnj;
use App\Game\Successions;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Un courtisan porte les requêtes du pharaon : un titre, une humeur, un présent qu'elle module dans des bornes
 * (`docs/plan-pnj-palier-b.md` § 5.4, § 9).
 */
final class CourDuPharaonTest extends KernelTestCase
{
    public function testLeCourtisanSeDeduitDeLaGraineEtNePorteAucunNom(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-titre@example.com');

        $a = Cour::enPoste($partie, 3);

        self::assertSame($a, Cour::enPoste($partie, 3), 'Même graine, même rang, même courtisan.');
        self::assertMatchesRegularExpression('/^(le|l\')/u', $a['titre'], 'Un titre, jamais un nom propre.');

        $titres = [];
        for ($rang = 1; $rang <= 40; ++$rang) {
            $titres[Cour::enPoste($partie, $rang)['titre']] = true;
        }

        self::assertGreaterThan(2, \count($titres), 'La cour a plusieurs fonctions.');
    }

    public function testLaRequeteEstPorteeParSonCourtisan(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-requete@example.com');

        $messages = $this->requete($partie, $this->quetes());

        self::assertNotSame([], $messages);
        self::assertMatchesRegularExpression('/transmet : .* réclame/u', $messages[0]);
        self::assertNotNull($this->courtisan($partie), 'Sa fiche naît à sa première parole.');
    }

    public function testHonorerLeRendBienveillantEtLePresentMonteDansSesBornes(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-honorer@example.com');
        $quetes = $this->quetes();
        $this->requete($partie, $quetes);
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();
        self::assertNotNull($quete);
        $ville->crediterRessources([$quete->getRessource()->value => 1000]);
        $valeur = (PrixDuMarche::pour($quete->getRessource()) ?? 0) * $quete->getQuantite();

        $quetes->livrer($partie);
        $courtisan = $this->courtisan($partie);

        self::assertNotNull($courtisan);
        self::assertSame(1, Cour::humeurDe($courtisan));
        self::assertSame(QuetesDeChantier::PRESENT_EN_CENTIEMES + Cour::PRESENT_PAR_POINT_D_HUMEUR, Cour::presentEnCentiemes($courtisan));
        self::assertSame('sert', $courtisan->getHistoire()[0]['t']);
        self::assertGreaterThan(0, $valeur);
    }

    public function testRefuserOuLaisserFilerLeDelaiLeRendImpatientSansRienPunir(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-refus@example.com');
        $quetes = $this->quetes();

        $this->requete($partie, $quetes);
        $quetes->refuser($partie);
        self::assertSame(-1, Cour::humeurDe($this->courtisan($partie) ?? self::fail('Pas de courtisan.')));

        // Un délai manqué pèse comme un refus.
        $partie->avancerDUnCycle();
        while (0 !== $partie->getCycle() % QuetesDeChantier::CYCLES_ENTRE_DEUX) {
            $partie->avancerDUnCycle();
        }
        $quetes->avancerDUnCycle($partie);
        self::assertNotNull($partie->getVille()->getQueteDeChantier());

        for ($i = 0; $i < QuetesDeChantier::DELAI_EN_QUINZAINES; ++$i) {
            $quetes->avancerDUnCycle($partie);
        }

        self::assertSame(-2, Cour::humeurDe($this->courtisan($partie) ?? self::fail('Pas de courtisan.')));
    }

    public function testLHumeurEstBorneeEtLePresentAussi(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-bornes@example.com');
        $cour = static::getContainer()->get(Cour::class);
        $courtisan = $cour->obtenir($partie, 1);

        for ($i = 0; $i < 30; ++$i) {
            $cour->honorer($courtisan, 0);
        }

        self::assertSame(Cour::HUMEUR_MAX, Cour::humeurDe($courtisan));
        self::assertSame(70, Cour::presentEnCentiemes($courtisan));

        for ($i = 0; $i < 60; ++$i) {
            $cour->decevoir($courtisan, 0);
        }

        self::assertSame(-Cour::HUMEUR_MAX, Cour::humeurDe($courtisan));
        self::assertSame(50, Cour::presentEnCentiemes($courtisan));
        self::assertSame(Npc::class, $courtisan::class);
        self::assertLessThanOrEqual(Npc::HISTOIRE_MAX, \count($courtisan->getHistoire()), 'Sa mémoire est bornée.');
    }

    public function testUnNouveauReneRameneUnAutreCourtisanEtLAncienSEfface(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-succession@example.com');
        $cour = static::getContainer()->get(Cour::class);
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);

        $premier = $cour->obtenir($partie, 1);
        $gestionnaire->flush();
        $second = $cour->obtenir($partie, 2);
        $gestionnaire->flush();

        self::assertNotSame($premier->getIdentite(), $second->getIdentite());
        self::assertTrue($premier->getProfil()['efface'], 'L\'ancien s\'efface.');
        self::assertSame('s_efface', $premier->getHistoire()[0]['t']);
        self::assertFalse($second->getProfil()['efface']);
        // Retrouver le même rang redonne le même courtisan : rien ne se duplique.
        self::assertSame($premier, $cour->obtenir($partie, 1));
    }

    public function testLeMondeMontreLeCourtisanEtSonHumeur(): void
    {
        self::bootKernel();
        $partie = $this->partie('cour-monde@example.com');
        $this->requete($partie, $this->quetes());
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $fiche = static::getContainer()->get(LeMonde::class)->pour($partie)['personnes'][0];

        self::assertSame('Courtisan du palais', $fiche['role']);
        self::assertTrue($fiche['courtisan']);
        self::assertSame('réservé', $fiche['humeur']);
    }

    /**
     * Fait passer les quinzaines jusqu'à la première requête (une tous les quatre cycles).
     *
     * @return list<string>
     */
    private function requete(GameSave $partie, QuetesDeChantier $quetes): array
    {
        for ($i = 0; $i < 8; ++$i) {
            $messages = $quetes->avancerDUnCycle($partie);

            if (null !== $partie->getVille()->getQueteDeChantier()) {
                return $messages;
            }

            $partie->avancerDUnCycle();
        }

        self::fail('Aucune requête en huit quinzaines.');
    }

    private function courtisan(GameSave $partie): ?Npc
    {
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Npc::class)->findOneBy(['partie' => $partie, 'role' => RoleDePnj::Courtisan]);
    }

    private function quetes(): QuetesDeChantier
    {
        return new QuetesDeChantier(
            static::getContainer()->get(EntityManagerInterface::class),
            new MissionCatalogue(),
            static::getContainer()->get(Successions::class),
            static::getContainer()->get(GeographieDeLaPartie::class),
            new Randomizer(new Mt19937(20260901)),
            static::getContainer()->get(Cour::class),
        );
    }

    private function partie(string $email): GameSave
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
