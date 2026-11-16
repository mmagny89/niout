<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\Candidat;
use App\Game\Enquete;
use App\Game\Indice;
use App\Game\LanceurDePartie;
use App\Game\MemoireDuDossier;
use App\Game\StatutDEnquete;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * La mémoire du dossier (lot I4) : **tout nommer, ou rien**.
 *
 * C'est le lot où une erreur d'écriture trahit une enquête sans qu'aucun test d'écran ne le voie : ces
 * tests gardent l'uniformité.
 */
final class MemoireDuDossierTest extends WebTestCase
{
    private const array MOTS_DE_JUGEMENT = ['concordant', 'trompeur', 'optionnel', 'faux', 'vrai', 'important', 'fiable', 'douteux', 'piste', 'contexte', 'indice'];

    /**
     * **Tout nommer** : chaque indice du dossier, dans l'ordre où il est venu — jamais une sélection.
     */
    public function testChaqueIndiceEstNommeDansLOrdreDeLaDecouverte(): void
    {
        self::bootKernel();

        foreach (Enquete::cases() as $enquete) {
            $indices = $this->indicesDe($enquete);

            if (\count($indices) < MemoireDuDossier::INDICES_AU_MOINS) {
                continue;
            }

            $partie = $this->partieAvecChef('ordre-'.$enquete->value.'@example.com');
            $dossier = $partie->getVille()->ouvrirLeDossierDe($enquete);

            foreach (array_reverse($indices) as $indice) {
                $dossier->verser($indice);
            }

            $memoire = MemoireDuDossier::pour($partie->getVille(), $dossier);

            self::assertNotNull($memoire, $enquete->value);
            self::assertCount(\count($indices), $memoire['lignes'], 'Tous, jamais une sélection.');

            foreach (array_reverse($indices) as $rang => $indice) {
                $attendu = null === $indice->parole() ? $indice->texte() : $indice->temoin().' : « '.$indice->parole().' »';
                self::assertSame($attendu, $memoire['lignes'][$rang], 'Dans l\'ordre où ils sont venus.');
            }
        }
    }

    /**
     * **La nature ne se lit pas** : l'introduction et la question sont les mêmes pour tout dossier (le
     * nombre mis à part), et aucun mot de jugement ne s'y glisse.
     */
    public function testLeResumeNeTrahitAucuneNature(): void
    {
        self::bootKernel();
        $introductions = [];

        foreach (Enquete::cases() as $enquete) {
            $indices = $this->indicesDe($enquete);

            if (\count($indices) < MemoireDuDossier::INDICES_AU_MOINS) {
                continue;
            }

            // Toutes les paires d'indices du dossier : chaque combinaison de natures doit parler pareil.
            foreach ($indices as $i => $premier) {
                foreach (\array_slice($indices, $i + 1) as $second) {
                    $partie = $this->partieAvecChef(\sprintf('nature-%s-%s-%s@example.com', $enquete->value, $premier->value, $second->value));
                    $dossier = $partie->getVille()->ouvrirLeDossierDe($enquete);
                    $dossier->verser($premier);
                    $dossier->verser($second);

                    $memoire = MemoireDuDossier::pour($partie->getVille(), $dossier);
                    self::assertNotNull($memoire);

                    $introductions[] = $memoire['voix'].'|'.$memoire['constat'].'|'.$memoire['question'];
                    $entete = mb_strtolower($memoire['constat'].' '.$memoire['question']);

                    foreach (self::MOTS_DE_JUGEMENT as $mot) {
                        self::assertStringNotContainsString($mot, $entete);
                    }
                }
            }
        }

        self::assertNotSame([], $introductions);
        self::assertCount(1, array_unique($introductions), 'Même voix, même phrase, même question pour toute paire d\'indices.');
    }

    /**
     * Elle ne répond pas, et ne crée rien : le dossier est identique avant et après.
     */
    public function testElleNeRepondPasEtNeToucheAuDossier(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecChef('intact@example.com');
        $dossier = $partie->getVille()->ouvrirLeDossierDe(Enquete::PassageCoupe);
        $dossier->verser(Indice::BorneRenversee);
        $dossier->verser(Indice::PlainteDunPaysan);
        $avant = $dossier->indices();

        $memoire = MemoireDuDossier::pour($partie->getVille(), $dossier);

        self::assertSame($avant, $dossier->indices());
        self::assertSame(StatutDEnquete::EnCours, $dossier->getStatut());
        self::assertSame(MemoireDuDossier::QUESTION, $memoire['question'] ?? null);
        self::assertEquals($memoire, MemoireDuDossier::pour($partie->getVille(), $dossier), 'Déterministe.');
    }

    /**
     * Ou rien : un seul indice, un dossier clos, ou personne pour parler — le dossier se lit seul.
     */
    public function testOuRienDuTout(): void
    {
        self::bootKernel();
        $partie = $this->partieAvecChef('rien@example.com');
        $dossier = $partie->getVille()->ouvrirLeDossierDe(Enquete::PassageCoupe);
        $dossier->verser(Indice::BorneRenversee);

        self::assertNull(MemoireDuDossier::pour($partie->getVille(), $dossier), 'Un seul indice : rien à remettre en ordre.');

        $dossier->verser(Indice::PlainteDunPaysan);
        self::assertNotNull(MemoireDuDossier::pour($partie->getVille(), $dossier));

        $dossier->conclure(StatutDEnquete::Resolue);
        self::assertNull(MemoireDuDossier::pour($partie->getVille(), $dossier), 'Un dossier clos ne se résume plus.');

        $sansChef = $this->lancer('sans-chef@example.com');
        $autre = $sansChef->getVille()->ouvrirLeDossierDe(Enquete::PassageCoupe);
        $autre->verser(Indice::BorneRenversee);
        $autre->verser(Indice::PlainteDunPaysan);

        self::assertNull(MemoireDuDossier::pour($sansChef->getVille(), $autre), 'Sans chef nommé, personne ne parle.');
    }

    /**
     * À l'écran : le résumé est au dossier, et ne dit pas un mot de plus que ses indices.
     */
    public function testLEcranLeMontre(): void
    {
        $client = static::createClient();
        $partie = $this->partieAvecChef('ecran-memoire@example.com');
        $dossier = $partie->getVille()->ouvrirLeDossierDe(Enquete::PassageCoupe);
        $dossier->verser(Indice::BorneRenversee);
        $dossier->verser(Indice::PlainteDunPaysan);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        $client->loginUser($partie->getJoueur());

        $client->request('GET', \sprintf('/partie/%d/ville?onglet=maison_des_scribes', $partie->getId()));

        self::assertResponseIsSuccessful();
        $page = html_entity_decode((string) $client->getResponse()->getContent());
        self::assertStringContainsString('J\'ai relu le dossier : 2 choses notées', $page);
        self::assertStringContainsString(MemoireDuDossier::QUESTION, $page);
    }

    /**
     * @return list<Indice>
     */
    private function indicesDe(Enquete $enquete): array
    {
        return array_values(array_filter(Indice::cases(), static fn (Indice $i): bool => $i->enquete() === $enquete));
    }

    private function partieAvecChef(string $email): GameSave
    {
        $partie = $this->lancer($email);
        $ville = $partie->getVille();
        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $chef = new Employee($ville, TypeDeBatiment::MaisonDesScribes, new Candidat(60, 5, 20, [], null, 2, 0), $partie->getCycle());
        $chef->donnerUnPrenom('Hori');
        $ville->ajouterEmploye($chef);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(string $email): GameSave
    {
        $joueur = new User();
        $joueur->setEmail($email);
        $joueur->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($joueur);
        $gestionnaire->flush();

        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($joueur, 'Nakht');
    }
}
