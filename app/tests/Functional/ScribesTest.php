<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\CleDeLecture;
use App\Game\Divinite;
use App\Game\EffetDeChef;
use App\Game\Enigme;
use App\Game\Enigmes;
use App\Game\Enquetes;
use App\Game\LanceurDePartie;
use App\Game\Offrandes;
use App\Game\PalierDeFaveur;
use App\Game\Ressource;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use App\Game\VoixDesAides;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les deux Scribes, et Thot (lot 7.7).
 *
 * Aucun des trois ne passe par la qualité de direction : leur effet n'est pas
 * une production. C'est le canal du Négociateur et du Dévot.
 */
final class ScribesTest extends WebTestCase
{
    /**
     * **Le Déchiffreur lit ce que le bâtiment n'ouvre pas encore.** C'est ce
     * que vaut un homme qui a passé sa vie sur les pierres.
     */
    public function testLeDechiffreurElargitLaCleDeLecture(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('dechiffreur@example.com');
        $ville = $partie->getVille();

        $sansLui = \count(CleDeLecture::pour($ville, $partie->getCycle()));
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur);

        self::assertSame(
            $sansLui + EffetDeChef::SIGNES_DU_DECHIFFREUR,
            \count(CleDeLecture::pour($ville, $partie->getCycle())),
        );
    }

    /**
     * Un autre chef du même bâtiment n'y change rien : c'est le Déchiffreur
     * qu'on paie, pas le fait d'avoir quelqu'un chez les scribes.
     */
    public function testUnAutreChefDesScribesNyChangeRien(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('autre-scribe@example.com');
        $ville = $partie->getVille();

        $avant = \count(CleDeLecture::pour($ville, $partie->getCycle()));
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);

        self::assertCount($avant, CleDeLecture::pour($ville, $partie->getCycle()));
    }

    /**
     * **L'Oraculaire resserre le doute, il ne donne pas la réponse.** Une
     * mauvaise proposition disparaît ; la bonne reste, et l'énigme reste une
     * question.
     */
    public function testLOraculaireEcarteUneMauvaiseProposition(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('oraculaire@example.com');
        $enigmes = static::getContainer()->get(Enigmes::class);

        $toutes = $enigmes->propositionsMontrees($partie, Enigme::IbisDeThot);
        self::assertCount(\count(Enigme::IbisDeThot->propositions()), $toutes);

        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);
        $resserrees = $enigmes->propositionsMontrees($partie, Enigme::IbisDeThot);

        self::assertCount(\count($toutes) - EffetDeChef::PROPOSITIONS_ECARTEES_PAR_LORACULAIRE, $resserrees);
        self::assertContains(Enigme::IbisDeThot->bonneReponse(), $resserrees, 'La bonne réponse ne s\'écarte jamais.');
    }

    /**
     * **La voix de l'Oraculaire dit ce que l'écran retire, et rien d'autre** : une seule liste
     * d'écartées, lue deux fois. Jamais la bonne réponse, même gabarit pour toute énigme, et le silence
     * quand l'Oraculaire n'agit pas.
     */
    public function testLOraculaireSouffleCeQueLEcranRetire(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('souffle@example.com');
        $enigmes = static::getContainer()->get(Enigmes::class);
        $voix = static::getContainer()->get(VoixDesAides::class);

        foreach (Enigme::cases() as $enigme) {
            self::assertNull($voix->souffle($partie, $enigme), 'Sans Oraculaire, personne ne souffle.');
        }

        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);

        foreach (Enigme::cases() as $enigme) {
            $ecartees = $enigmes->propositionsEcartees($partie, $enigme);
            $souffle = $voix->souffle($partie, $enigme);

            self::assertNotNull($souffle);
            self::assertNotContains($enigme->bonneReponse(), $ecartees);
            self::assertStringNotContainsString('« '.$enigme->bonneReponse().' »', $souffle);
            self::assertMatchesRegularExpression('/^(\S+ l|L)\'oraculaire souffle : ce n\'est pas « /u', $souffle);
            self::assertEqualsCanonicalizing(
                $enigme->propositions(),
                [...$enigmes->propositionsMontrees($partie, $enigme), ...$ecartees],
                'Ce que l\'écran garde et ce que l\'Oraculaire écarte font toutes les propositions.',
            );

            foreach ($ecartees as $ecartee) {
                self::assertStringContainsString('« '.$ecartee.' »', $souffle);
            }
        }
    }

    /**
     * Le Déchiffreur et Thot disent **combien** ils ouvrent — le nombre même que compte la clé — et
     * ne parlent que lorsqu'ils agissent.
     */
    public function testLesAidesDisentQuiAAppris(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('qui-a-appris@example.com');
        $ville = $partie->getVille();

        self::assertSame([], VoixDesAides::signesAppris($ville, $partie->getCycle()));

        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur);
        $lignes = VoixDesAides::signesAppris($ville, $partie->getCycle());

        self::assertCount(1, $lignes);
        self::assertStringContainsString((string) EffetDeChef::SIGNES_DU_DECHIFFREUR, $lignes[0]);
        self::assertStringContainsString('déchiffreur', $lignes[0]);

        $this->porterThotAuFavorable($partie);
        $lignes = VoixDesAides::signesAppris($ville, $partie->getCycle());

        self::assertCount(2, $lignes);
        self::assertStringContainsString((string) CleDeLecture::signesDeThot($ville), $lignes[1]);
        self::assertStringContainsString('Thot', $lignes[1]);
    }

    /**
     * **L'explication est dite, jamais changée** : celle de l'énigme, mot pour mot, avec la voix du
     * chef des scribes devant — et sans chef, exactement le texte d'avant.
     */
    public function testLExplicationEstDiteSansEtreChangee(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('explication@example.com');
        $ville = $partie->getVille();

        foreach (Enigme::cases() as $enigme) {
            self::assertSame($enigme->explication(), VoixDesAides::explique($ville, $enigme->explication()));
        }

        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);
        self::assertSame(
            Enigme::IbisDeThot->explication(),
            VoixDesAides::explique($ville, Enigme::IbisDeThot->explication()),
            'Un chef sans prénom ne prête pas sa voix : le texte reste celui d\'avant.',
        );

        $ville->chefsDe(TypeDeBatiment::MaisonDesScribes)[0]->donnerUnPrenom('Hori');
        $dite = VoixDesAides::explique($ville, Enigme::IbisDeThot->explication());

        self::assertSame('Hori, chef de la Maison des scribes, l\'explique : '.Enigme::IbisDeThot->explication(), $dite);
        self::assertStringStartsWith('Hori, chef de la Maison des scribes, l\'explique : ', $dite);
    }

    /**
     * À l'écran : l'Oraculaire souffle sous la devinette, et la clé dit qui a appris quoi.
     */
    public function testLaMaisonDesScribesMontreLaVoixDesAides(): void
    {
        $client = static::createClient();
        $partie = $this->villeAvecScribes('voix-ecran@example.com');
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesOraculaire);
        $this->engager($partie, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur);

        foreach ($partie->getVille()->chefsDe(TypeDeBatiment::MaisonDesScribes) as $i => $chef) {
            $chef->donnerUnPrenom(0 === $i ? 'Hori' : 'Pia');
        }

        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($partie->getJoueur());

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=maison_des_scribes', $partie->getId()));

        self::assertResponseIsSuccessful();
        $page = html_entity_decode((string) $client->getResponse()->getContent());
        self::assertStringContainsString('Hori l\'oraculaire souffle : ce n\'est pas «', $page);
        self::assertStringContainsString('Pia le déchiffreur vous a appris', $page);
    }

    /**
     * **Thot éclaire les écrits** (doc 07) : sa faveur ouvre des signes que le
     * bâtiment ne porte pas encore. C'est par là qu'il cesse d'être un dieu
     * offrable et inerte.
     */
    public function testThotOuvreDesSignes(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('thot@example.com');
        $ville = $partie->getVille();

        self::assertSame(0, CleDeLecture::signesDeThot($ville));

        $this->porterThotAuFavorable($partie);

        self::assertGreaterThan(0, CleDeLecture::signesDeThot($ville));
        self::assertTrue(Divinite::Thot->agitDeja(), 'Thot ne doit plus annoncer son inertie.');
        self::assertNull(Divinite::Thot->attente());
    }

    /**
     * **Thot abrège la reprise d'un dossier mal conclu** — jamais jusqu'à
     * l'annuler : une erreur sans conséquence n'en serait plus une.
     */
    public function testThotAbregeLaRepriseDunDossierSansLAnnuler(): void
    {
        self::bootKernel();
        $partie = $this->villeAvecScribes('thot-dossier@example.com');

        self::assertSame(Enquetes::RETARD_DUNE_ERREUR, Enquetes::retardDUneErreur($partie));

        $this->porterThotAuFavorable($partie);

        $abrege = Enquetes::retardDUneErreur($partie);
        self::assertLessThan(Enquetes::RETARD_DUNE_ERREUR, $abrege);
        self::assertGreaterThanOrEqual(1, $abrege, 'Une erreur doit toujours coûter quelque chose.');
    }

    /**
     * Le compte des dormeurs : il ne reste qu'Isis parmi les dieux, et trois
     * spécialités, toutes suspendues à des phases qui ont leur propre système
     * à écrire.
     */
    public function testCeQuiDortEncoreEtRienDautre(): void
    {
        $dieuxInertes = array_filter(Divinite::pantheon(), static fn (Divinite $d): bool => !$d->agitDeja());
        self::assertSame([], array_values($dieuxInertes), 'Isis agit depuis le lot 10.4 : plus aucun dieu ne dort.');

        $specialitesInertes = array_filter(
            SpecialiteDeChef::cases(),
            static fn (SpecialiteDeChef $s): bool => !$s->agitDeja(),
        );
        self::assertSame([
            SpecialiteDeChef::MarcheAcheteur,
            SpecialiteDeChef::PortCommercantNaval,
        ], array_values($specialitesInertes));
    }

    private function porterThotAuFavorable(GameSave $partie): void
    {
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::Temple, 10));
        $ville->crediterRessources([Ressource::Deben->value => 1_000]);

        $offrandes = static::getContainer()->get(Offrandes::class);

        while (!$ville->palierDe(Divinite::Thot)->estAuDessusDuNeutre()) {
            $offrandes->offrir($partie, Divinite::Thot, Ressource::Deben, 20);
        }

        self::assertSame(PalierDeFaveur::Favorable, $ville->palierDe(Divinite::Thot));
    }

    private function engager(GameSave $partie, TypeDeBatiment $type, SpecialiteDeChef $specialite): void
    {
        $ville = $partie->getVille();
        $ville->ajouterEmploye(new Employee(
            $ville,
            $type,
            new Candidat(
                competence: 60,
                salaire: 8,
                ancienneteProbable: 200,
                traits: [],
                specialite: $specialite,
                actifsAmenes: 0,
                inactifsAmenes: 0,
            ),
            $partie->getCycle(),
        ));
    }

    private function villeAvecScribes(string $email): GameSave
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 2));
        $gestionnaire->flush();

        return $partie;
    }
}
