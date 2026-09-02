<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\CleDeLecture;
use App\Game\LanceurDePartie;
use App\Game\SymboleHieroglyphique;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La clé de lecture (lot 7.0).
 *
 * Deux exigences se croisent ici : une exigence de jeu — la clé s'enrichit et
 * donne une raison de monter la Maison des scribes — et une exigence
 * **pédagogique**, qui est l'objectif propre du doc 10 : ce que le joueur
 * apprend doit être vrai.
 */
final class CleDeLectureTest extends WebTestCase
{
    /**
     * **Quatre signes sans rien apprendre** : l'eau, l'homme, la maison et la
     * marche. De quoi tenter la première énigme avant d'avoir bâti quoi que ce
     * soit — sans quoi le tutoriel de l'acte 1 serait inatteignable.
     */
    public function testUneVilleNeuveLitDejaQuatreSignes(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('cle-neuve@example.com')->getVille();

        self::assertSame(CleDeLecture::SIGNES_CONNUS_DEMBLEE, \count(CleDeLecture::pour($ville)));
        self::assertTrue(CleDeLecture::sait($ville, SymboleHieroglyphique::Eau));
        self::assertTrue(CleDeLecture::sait($ville, SymboleHieroglyphique::Marcher));
        self::assertFalse(CleDeLecture::sait($ville, SymboleHieroglyphique::Vie));
    }

    /**
     * **Chaque niveau ouvre quelque chose**, et les deux bornes du doc 10 —
     * quatre au départ, vingt au dernier niveau du bâtiment — se rejoignent :
     * `4 + 2 × 8` fait exactement vingt.
     */
    public function testChaqueNiveauOuvreDesSignesJusquAuxVingt(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('cle-montee@example.com')->getVille();
        $scribes = new Building($ville, TypeDeBatiment::MaisonDesScribes, 1);
        $ville->ajouterBatiment($scribes);

        $precedent = 0;

        while ($scribes->getNiveau() < $ville->niveauMaxRegional()) {
            $lus = \count(CleDeLecture::pour($ville));
            self::assertGreaterThan($precedent, $lus, 'Chaque niveau doit ouvrir quelque chose.');
            $precedent = $lus;
            $scribes->monterDUnNiveau();
        }

        self::assertSame(
            \count(SymboleHieroglyphique::cases()),
            CleDeLecture::SIGNES_CONNUS_DEMBLEE
                + TypeDeBatiment::MaisonDesScribes->niveauMax() * CleDeLecture::SIGNES_PAR_NIVEAU,
            'Les deux bornes du doc 10 doivent se rejoindre au dernier niveau.',
        );
    }

    /**
     * **Le Delta ne lira jamais tout.** Le niveau maximal y est régional, et
     * la mission 1 le plafonne bien en deçà du huitième : la clé complète est
     * une affaire de campagne, pas de première partie. Même progression que le
     * craft de luxe, qui demande un Entrepôt hors d'atteinte au Delta.
     */
    public function testLeDeltaNeLitJamaisTouteLaCle(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('cle-delta@example.com')->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, $ville->niveauMaxRegional()));

        self::assertLessThan(
            TypeDeBatiment::MaisonDesScribes->niveauMax(),
            $ville->niveauMaxRegional(),
            'La borne régionale de la mission 1 est bien en deçà du dernier niveau.',
        );
        self::assertLessThan(\count(SymboleHieroglyphique::cases()), \count(CleDeLecture::pour($ville)));
        self::assertNotNull(CleDeLecture::prochainSigne($ville), 'Il reste toujours un signe à espérer.');
    }

    /**
     * **La seconde voie du doc 10** : une énigme réussie apprend un signe que
     * le bâtiment n'ouvrait pas encore. C'est le seul état persisté de la clé —
     * le reste se recalcule du niveau.
     */
    public function testUnSigneApprisParEnigmeSurvitEtNeSeCompteQuUneFois(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('cle-enigme@example.com');
        $ville = $partie->getVille();

        self::assertTrue($ville->apprendreUnSymbole(SymboleHieroglyphique::Or));
        self::assertFalse(
            $ville->apprendreUnSymbole(SymboleHieroglyphique::Or),
            'Un signe déjà su n\'est pas une récompense : l\'écran doit pouvoir le dire.',
        );

        self::assertTrue(CleDeLecture::sait($ville, SymboleHieroglyphique::Or));
        self::assertCount(CleDeLecture::SIGNES_CONNUS_DEMBLEE + 1, CleDeLecture::pour($ville));

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->flush();
        $gestionnaire->clear();

        $rechargee = $gestionnaire->find(GameSave::class, $partie->getId());
        self::assertNotNull($rechargee);
        self::assertTrue(CleDeLecture::sait($rechargee->getVille(), SymboleHieroglyphique::Or));
    }

    /**
     * **La clé garde l'ordre d'apprentissage**, jamais celui du hasard : deux
     * parties au même niveau lisent les mêmes signes, et une inscription
     * écrite pour un niveau donné reste lisible à ce niveau.
     */
    public function testLaCleSuitToujoursLeMemeOrdre(): void
    {
        self::bootKernel();
        $premiere = $this->lancerPartie('ordre-1@example.com')->getVille();
        $seconde = $this->lancerPartie('ordre-2@example.com')->getVille();

        foreach ([$premiere, $seconde] as $ville) {
            $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 3));
        }

        self::assertSame(CleDeLecture::pour($premiere), CleDeLecture::pour($seconde));
    }

    /**
     * **Ce que le joueur apprend doit être vrai** — c'est l'objectif
     * pédagogique du doc 10. Chaque signe porte un code de Gardiner de la
     * bonne forme, un glyphe unique, et une glose.
     */
    public function testChaqueSigneEstUnVraiSigne(): void
    {
        $codes = [];
        $glyphes = [];

        foreach (SymboleHieroglyphique::cases() as $symbole) {
            // Le suffixe est celui des variantes de la liste : `N35A`, les
            // trois ondulations de l'eau, se distingue ainsi de `N35`, l'unique
            // ondulation qui note le son n.
            self::assertMatchesRegularExpression(
                '/^(Aa|[A-Z])\d+[A-Z]?$/',
                $symbole->codeDeGardiner(),
                \sprintf('%s doit porter un code de la liste de Gardiner.', $symbole->libelle()),
            );
            self::assertNotSame('', $symbole->sens());
            self::assertSame(1, mb_strlen($symbole->signe()), 'Un signe, pas une suite de signes.');

            $codes[] = $symbole->codeDeGardiner();
            $glyphes[] = $symbole->signe();
        }

        self::assertSame($codes, array_unique($codes), 'Deux signes ne peuvent pas porter le même code.');
        self::assertSame($glyphes, array_unique($glyphes));
        self::assertCount(20, SymboleHieroglyphique::cases(), 'Vingt signes, comme le doc 10 les compte.');
    }

    /**
     * L'écran : la clé se lit à la Maison des scribes, et l'onglet n'existe
     * pas tant que le bâtiment n'est pas dressé.
     */
    public function testLaCleSAfficheUneFoisLaMaisonDressee(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('cle-ecran@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorNotExists('#onglet-maison_des_scribes');

        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $gestionnaire->flush();

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorExists('#onglet-maison_des_scribes');
        self::assertSelectorTextContains('#panneau-maison_des_scribes', 'Clé de lecture');
        self::assertSelectorTextContains('#panneau-maison_des_scribes', 'N35');
        self::assertSelectorTextContains('#panneau-maison_des_scribes', 'Gardiner');
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
