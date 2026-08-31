<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Game\ContenuDeZone;
use App\Game\Enquete;
use App\Game\EnqueteImpossible;
use App\Game\Enquetes;
use App\Game\Indice;
use App\Game\LanceurDePartie;
use App\Game\NatureDIndice;
use App\Game\SourceDIndice;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Le dossier d'enquête (lot 7.3).
 */
final class EnquetesTest extends WebTestCase
{
    /**
     * **Les cases « quelque chose s'y trame » trouvent enfin leur emploi.**
     * Elles sont posées par la génération de carte depuis le lot 3.2 et ne
     * menaient nulle part.
     */
    public function testUneCaseOuQuelqueChoseSeTrameRendUnIndice(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('fouille@example.com');
        $zone = $this->caseAEvenement($partie);

        self::assertTrue($this->enquetes()->peutFouiller($partie->getVille(), $zone));

        $indice = $this->enquetes()->fouiller($partie, $zone);

        self::assertSame(SourceDIndice::Terrain, $indice->source(), 'Le terrain se fouille, la parole se recueille.');
        $dossier = $partie->getVille()->dossierDe($indice->enquete());
        self::assertNotNull($dossier);
        self::assertTrue($dossier->contient($indice));
    }

    /**
     * **Une case ne se fouille qu'une fois.** Sans quoi la même case rendrait
     * tout un dossier, et l'exploration cesserait d'avoir un coût.
     */
    public function testUneCaseNeSeFouilleQuUneFois(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('deux-fouilles@example.com');
        $zone = $this->caseAEvenement($partie);

        $this->enquetes()->fouiller($partie, $zone);

        self::assertTrue($zone->indiceRecueilli());
        self::assertFalse($this->enquetes()->peutFouiller($partie->getVille(), $zone));

        $this->expectException(EnqueteImpossible::class);
        $this->enquetes()->fouiller($partie, $zone);
    }

    /**
     * **Un dossier n'existe qu'à partir du premier indice** — comme la faveur
     * d'un dieu. Ouvrir trois dossiers vides au lancement de chaque partie ne
     * dirait rien, et il faudrait les migrer à chaque enquête ajoutée.
     */
    public function testUnDossierNaitAuPremierIndice(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('dossier-vide@example.com');

        self::assertCount(0, $partie->getVille()->getDossiers());
        self::assertSame([], $this->enquetes()->dossiers($partie));

        $this->enquetes()->fouiller($partie, $this->caseAEvenement($partie));

        self::assertCount(1, $partie->getVille()->getDossiers());
    }

    /**
     * **Les fausses pistes ne comptent pas.** C'est ce qui distingue une
     * enquête d'une case à cocher : si tous les indices concouraient, il
     * suffirait de les compter.
     */
    public function testUneFaussePisteNeRapprochePasDeLaConclusion(): void
    {
        self::bootKernel();
        $partie = $this->lancerPartie('fausse-piste@example.com');
        $ville = $partie->getVille();

        $trompeur = Indice::OstraconDeGarnison;
        self::assertSame(NatureDIndice::Trompeur, $trompeur->nature());

        $dossier = $ville->ouvrirLeDossierDe($trompeur->enquete());
        $dossier->verser($trompeur);

        self::assertSame(0, $dossier->concordantsReunis());
        self::assertFalse($dossier->peutConclure());

        foreach ($trompeur->enquete()->indices() as $indice) {
            if (NatureDIndice::Concordant === $indice->nature()) {
                $dossier->verser($indice);
            }
        }

        self::assertTrue($dossier->peutConclure());
    }

    /**
     * Le même indice versé deux fois n'est pas une découverte.
     */
    public function testUnIndiceNeSeVersePasDeuxFois(): void
    {
        self::bootKernel();
        $ville = $this->lancerPartie('doublon@example.com')->getVille();
        $dossier = $ville->ouvrirLeDossierDe(Enquete::PassageCoupe);

        self::assertTrue($dossier->verser(Indice::BorneRenversee));
        self::assertFalse($dossier->verser(Indice::BorneRenversee));
        self::assertCount(1, $dossier->indices());
    }

    /**
     * **Le joueur ne doit pas savoir qu'un indice est trompeur** : l'écran ne
     * l'écrit jamais. « À vérifier » vaut pour un indice de contexte comme
     * pour une fausse piste — afficher la nature réelle résoudrait l'enquête
     * à sa place.
     */
    public function testLEcranNeTrahitPasLesFaussesPistes(): void
    {
        self::assertSame(
            NatureDIndice::Optionnel->libelleAffiche(),
            NatureDIndice::Trompeur->libelleAffiche(),
            'Un trompeur et un contexte doivent se présenter pareil.',
        );
        self::assertNotSame(
            NatureDIndice::Concordant->libelleAffiche(),
            NatureDIndice::Trompeur->libelleAffiche(),
        );
    }

    /**
     * **Chaque enquête tient debout** : assez d'indices concordants pour être
     * concluable, et au moins un qui ne l'est pas — sans quoi il n'y aurait
     * rien à démêler.
     */
    public function testChaqueEnqueteEstSolubleEtNonTriviale(): void
    {
        foreach (Enquete::cases() as $enquete) {
            $indices = $enquete->indices();
            self::assertGreaterThanOrEqual(3, \count($indices), 'Le doc 10 veut trois à cinq indices.');
            self::assertLessThanOrEqual(5, \count($indices));

            $concordants = 0;
            $autres = 0;

            foreach ($indices as $indice) {
                if (NatureDIndice::Concordant === $indice->nature()) {
                    ++$concordants;
                } else {
                    ++$autres;
                }
            }

            self::assertGreaterThanOrEqual(
                $enquete->indicesRequis(),
                $concordants,
                \sprintf('« %s » doit pouvoir se conclure.', $enquete->libelle()),
            );
            self::assertGreaterThan(
                0,
                $autres,
                \sprintf('« %s » n\'aurait rien à démêler.', $enquete->libelle()),
            );
        }
    }

    /**
     * Une seule enquête porte le fil rouge : c'est elle qui se rejouera
     * jusqu'à être résolue, les autres pouvant se perdre.
     */
    public function testUneSeuleEnqueteEstPrincipale(): void
    {
        $principales = array_filter(Enquete::cases(), static fn (Enquete $e): bool => $e->estPrincipale());

        self::assertCount(1, $principales);
    }

    /**
     * Le parcours depuis la carte, et le dossier qui apparaît dans la ville.
     */
    public function testOnFouilleDepuisLaCarteEtLeDossierSAffiche(): void
    {
        $client = static::createClient();
        $user = new User();
        $user->setEmail('parcours-fouille@example.com');
        $user->setPassword('peu-importe-ici');
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();
        $client->loginUser($user);

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $zone = $this->caseAEvenement($partie);
        $gestionnaire->flush();

        $adresse = \sprintf('/partie/%d/carte?zone=%d-%d', $partie->getId(), $zone->getX(), $zone->getY());
        $crawler = $client->request('GET', $adresse);
        $jeton = $crawler->filter(\sprintf('form[action="/partie/%d/carte/fouiller"] input[name="_token"]', $partie->getId()))
            ->attr('value');

        $client->request('POST', \sprintf('/partie/%d/carte/fouiller', $partie->getId()), [
            '_token' => $jeton,
            'zone' => $zone->getX().'-'.$zone->getY(),
        ]);

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Versé au dossier');

        $client->request('GET', \sprintf('/partie/%d/ville', $partie->getId()));
        self::assertSelectorTextContains('#panneau-scribes', 'Dossiers d\'enquête');
    }

    /**
     * Une case à événement, découverte : c'est le point d'entrée du doc 10.
     */
    private function caseAEvenement(GameSave $partie): Zone
    {
        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->porteLaVille()) {
                continue;
            }

            $zone->decouvrir();
            $zone->poserUnContenu(ContenuDeZone::Evenement);

            // Il faut des scribes pour consigner ce qu'on trouve : c'est le
            // bâtiment qui conduit les enquêtes (doc 01).
            if (!$partie->getVille()->possede(TypeDeBatiment::MaisonDesScribes)) {
                $partie->getVille()->ajouterBatiment(
                    new Building($partie->getVille(), TypeDeBatiment::MaisonDesScribes, 1),
                );
            }

            return $zone;
        }

        self::fail('Aucune case disponible.');
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

    private function enquetes(): Enquetes
    {
        return new Enquetes(
            static::getContainer()->get(EntityManagerInterface::class),
            new Randomizer(new Mt19937(20260831)),
        );
    }
}
