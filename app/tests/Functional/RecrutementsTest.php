<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\LanceurDePartie;
use App\Game\RecrutementImpossible;
use App\Game\Recrutements;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RecrutementsTest extends KernelTestCase
{
    /**
     * L'invariant qui donne son sens au choix entre deux ou trois candidats
     * (doc 03) : reconsulter l'offre ne relance pas les dés. Sans lui,
     * recharger la page suffirait à obtenir un cinq étoiles.
     */
    public function testUneOffreFigeSonTirage(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('fige@example.com');

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
        $premiere = array_map(
            static fn (\App\Game\Candidat $c): array => $c->enTableau(),
            $offre->candidats(),
        );

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->clear();

        $relue = $gestionnaire->find(\App\Entity\JobOffer::class, $offre->getId());
        self::assertNotNull($relue);

        self::assertSame(
            $premiere,
            array_map(static fn (\App\Game\Candidat $c): array => $c->enTableau(), $relue->candidats()),
            'Relire une offre doit rendre exactement les mêmes candidats.',
        );
    }

    public function testUneOffreCompteDeuxOuTroisCandidats(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('candidats@example.com');

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);

        self::assertGreaterThanOrEqual(2, \count($offre->candidats()));
        self::assertLessThanOrEqual(3, \count($offre->candidats()));
    }

    /**
     * Les trois bâtiments que le doc 03 laisse sans spécialité — la famille
     * les tient elle-même.
     */
    public function testUnBatimentSansSpecialiteNeSeConfiePas(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('sans-specialite@example.com');

        self::assertSame([], SpecialiteDeChef::pour(TypeDeBatiment::ResidenceFamiliale));

        $this->expectException(RecrutementImpossible::class);
        $this->expectExceptionMessageMatches('/tient elle-même/');

        $this->recrutements()->poster($partie, TypeDeBatiment::ResidenceFamiliale);
    }

    public function testOnNePosteQuUneAnnonceALaFoisParBatiment(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('doublon@example.com');

        $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);

        $this->expectException(RecrutementImpossible::class);
        $this->expectExceptionMessageMatches('/déjà affichée/');

        $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
    }

    /**
     * Le doc 01 donne `arrondiSupérieur(niveau / 3)` : un seul chef pour un
     * bâtiment de niveau 1. Sans ce plafond, on embaucherait sans fin.
     */
    public function testUnBatimentDeNiveauUnNAQuUnPoste(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('plafond@example.com');
        $this->loger($partie);

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
        $this->recrutements()->embaucher($partie, $offre, 0);

        $this->expectException(RecrutementImpossible::class);
        $this->expectExceptionMessageMatches('/déjà ses 1 chef/');

        $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
    }

    /**
     * Doc 05 : « durée d'un recrutement une fois le candidat choisi : 1 cycle ».
     */
    public function testLeChefNePrendSonPosteQuALaQuinzaineSuivante(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('poste@example.com');
        $this->loger($partie);

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
        $employe = $this->recrutements()->embaucher($partie, $offre, 0);

        self::assertFalse($employe->estEnPoste($partie->getCycle()));
        self::assertTrue($employe->estEnPoste($partie->getCycle() + 1));
    }

    public function testLeChefSInstalleAvecSaMaisonneeEtRepartAvecElle(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('maisonnee@example.com');
        $this->loger($partie);
        $ville = $partie->getVille();

        $avant = $ville->population();
        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
        $candidat = $offre->candidat(0);
        self::assertNotNull($candidat);

        $employe = $this->recrutements()->embaucher($partie, $offre, 0);

        self::assertSame($avant + $candidat->personnesAmenees(), $ville->population());

        $this->recrutements()->renvoyer($employe);

        self::assertSame(
            $avant,
            $ville->population(),
            'Embaucher puis renvoyer ne doit rien laisser derrière : sinon c\'est du peuplement gratuit.',
        );
    }

    /**
     * Le même verrou que l'appel d'habitants : un chef s'installe avec les
     * siens, il faut donc de la place.
     */
    public function testOnNEmbauchePasSansLogement(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('sans-toit@example.com');

        self::assertTrue($partie->getVille()->manqueDeLogements());

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);

        $this->expectException(RecrutementImpossible::class);
        $this->expectExceptionMessageMatches('/Quartier d\'habitation/');

        $this->recrutements()->embaucher($partie, $offre, 0);
    }

    /**
     * Un rang venu d'un formulaire ne se croit jamais sur parole.
     */
    public function testUnRangInexistantEstRefuse(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('rang@example.com');
        $this->loger($partie);

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);

        $this->expectException(RecrutementImpossible::class);

        $this->recrutements()->embaucher($partie, $offre, 99);
    }

    /**
     * Retirer l'annonce est la seule façon de relancer les dés — et elle passe
     * par un renoncement explicite, pas par un rechargement de page.
     */
    public function testRetirerUneAnnonceLaLibereEtNEmbauchePersonne(): void
    {
        self::bootKernel();
        $partie = $this->lancerAvecGrenier('retrait@example.com');
        $ville = $partie->getVille();
        $population = $ville->population();

        $offre = $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);
        $this->recrutements()->retirer($offre);

        self::assertNull($ville->offrePour(TypeDeBatiment::Grenier));
        self::assertSame([], $ville->chefsDe(TypeDeBatiment::Grenier));
        self::assertSame($population, $ville->population());

        // Et l'on peut reposter aussitôt — c'est bien une relance, pas un
        // blocage : la nouvelle annonce vit à côté d'aucune autre.
        $this->recrutements()->poster($partie, TypeDeBatiment::Grenier);

        self::assertNotNull($ville->offrePour(TypeDeBatiment::Grenier));
    }

    private function loger(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::QuartierDHabitation));
    }

    private function lancerAvecGrenier(string $email): GameSave
    {
        $partie = $this->lancerPartie($email);
        $ville = $partie->getVille();

        if (null === $ville->batimentDeType(TypeDeBatiment::Grenier)) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Grenier));
        }

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

    private function recrutements(): Recrutements
    {
        return static::getContainer()->get(Recrutements::class);
    }
}
