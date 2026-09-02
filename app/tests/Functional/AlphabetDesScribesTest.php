<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\User;
use App\Game\AlphabetDesScribes;
use App\Game\CartoucheRoyal;
use App\Game\CleDeLecture;
use App\Game\Enigme;
use App\Game\FilRouge;
use App\Game\LanceurDePartie;
use App\Game\LeconDeNiout;
use App\Game\Ressource;
use App\Game\SigneAlphabetique;
use App\Game\SymboleHieroglyphique;
use App\Game\TranscriptionDuNom;
use App\Game\TypeDeBatiment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'alphabet des scribes (doc 10) : les vingt-quatre signes qui notent un son.
 */
final class AlphabetDesScribesTest extends WebTestCase
{
    /**
     * **Le contenu est vrai** : vrai code de Gardiner, vrai glyphe, vraie
     * translittération. C'est l'objectif pédagogique du doc 10, et un signe
     * inventé le trahirait.
     */
    public function testChaqueSigneEstCompletEtUnique(): void
    {
        $codes = [];
        $glyphes = [];

        foreach (SigneAlphabetique::cases() as $signe) {
            self::assertNotSame('', $signe->codeDeGardiner());
            self::assertNotSame('', $signe->signe());
            self::assertNotSame('', $signe->objet());
            self::assertNotSame('', $signe->translitteration());
            self::assertNotSame('', $signe->son());

            $codes[] = $signe->codeDeGardiner();
            $glyphes[] = $signe->signe();
        }

        self::assertCount(24, SigneAlphabetique::cases(), 'L\'alphabet unilitère en compte vingt-quatre.');
        self::assertSame($codes, array_unique($codes), 'Deux signes ne partagent pas un code de Gardiner.');
        self::assertSame($glyphes, array_unique($glyphes));
    }

    /**
     * **Chaque glyphe est bien dans le bloc hiéroglyphique d'Unicode.** Un
     * point de code hors bloc ne serait pas dessiné par la police embarquée, et
     * s'afficherait en carré vide sans que rien ne le dise.
     */
    public function testChaqueGlypheEstDansLeBlocEgyptien(): void
    {
        foreach (SigneAlphabetique::cases() as $signe) {
            $point = mb_ord($signe->signe());

            self::assertGreaterThanOrEqual(0x13000, $point, $signe->codeDeGardiner());
            self::assertLessThanOrEqual(0x1342F, $point, $signe->codeDeGardiner());
        }
    }

    /**
     * **Les deux pistes ne se confondent pas.** Six dessins leur sont communs,
     * et c'est voulu : un même signe n'y veut pas dire la même chose — `N35`
     * est « l'eau » dans la clé de lecture, et le son *n* dans l'alphabet. Les
     * dédupliquer enseignerait le contraire de ce que le doc 10 veut faire
     * comprendre.
     */
    public function testUnMemeDessinPorteDeuxLecturesSansSeConfondre(): void
    {
        $glyphesDeLaCle = array_map(
            static fn (SymboleHieroglyphique $s): string => $s->signe(),
            SymboleHieroglyphique::cases(),
        );

        $communs = array_filter(
            SigneAlphabetique::cases(),
            static fn (SigneAlphabetique $s): bool => \in_array($s->signe(), $glyphesDeLaCle, true),
        );

        self::assertNotEmpty($communs, 'Des signes sont communs aux deux pistes — c\'est le propos.');

        // Et ils gardent chacun leur sens : l'eau d'un côté, le son n de l'autre.
        self::assertSame('𓈖', SymboleHieroglyphique::Eau->signe());
        self::assertSame('𓈖', SigneAlphabetique::FiletDEau->signe());
        self::assertSame('n', SigneAlphabetique::FiletDEau->translitteration());
    }

    /**
     * `3 × niveau` atteint exactement vingt-quatre au niveau 8, plafond de la
     * Maison des scribes — et jamais davantage.
     */
    public function testTroisSignesParNiveauOuvrentLAlphabetEntierAuNiveauHuit(): void
    {
        self::bootKernel();
        $partie = $this->lancer('progression-alphabet@example.com');
        $ville = $partie->getVille();

        // Sans bâtiment, seuls les quatre de Niout — comme les quatre d'emblée
        // de la clé de lecture, et pour la même raison. La table, elle, les
        // rend dans l'ordre des grammaires, pas dans celui du mot.
        self::assertEqualsCanonicalizing(
            AlphabetDesScribes::connusDEmblee(),
            AlphabetDesScribes::pour($ville),
        );
        self::assertSame(
            [SigneAlphabetique::RoseauFleuri, SigneAlphabetique::PoussinDeCaille, SigneAlphabetique::FiletDEau, SigneAlphabetique::Pain],
            AlphabetDesScribes::pour($ville),
            'La table suit l\'ordre conventionnel des grammaires.',
        );

        // L'invariant tient sur la formule, pas sur une région : le plafond
        // régional du Delta est plus bas que celui du bâtiment, et le vérifier
        // sur une carte le ferait dépendre de la mission jouée.
        self::assertSame(
            \count(SigneAlphabetique::cases()),
            AlphabetDesScribes::SIGNES_PAR_NIVEAU * TypeDeBatiment::MaisonDesScribes->niveauMax(),
            'Trois par niveau doit tomber juste sur l\'alphabet entier au niveau maximal.',
        );

        $batiment = new Building($ville, TypeDeBatiment::MaisonDesScribes, 1);
        $ville->ajouterBatiment($batiment);

        $precedent = 0;

        while (!$batiment->estAuMaximum()) {
            $connus = \count(AlphabetDesScribes::pour($ville));

            self::assertGreaterThanOrEqual($precedent, $connus, 'L\'alphabet ne rétrécit jamais.');
            self::assertLessThanOrEqual(24, $connus);

            $precedent = $connus;
            $batiment->monterDUnNiveau();
        }
    }

    /**
     * Le mode d'essai les ouvre tous, comme pour la clé de lecture : éprouver
     * l'écran sans jouer les heures qui y mènent.
     */
    public function testLeModeDessaiOuvreToutLAlphabet(): void
    {
        self::bootKernel();
        $partie = $this->lancer('alphabet-divin@example.com');
        $partie->getVille()->basculerLeModeDivin(true);

        self::assertCount(24, AlphabetDesScribes::pour($partie->getVille()));
    }

    /**
     * **Ni le Déchiffreur ni Thot n'y touchent** : leur effet est écrit pour la
     * clé de lecture, et l'étendre ici doublerait un bonus que rien ne demande.
     */
    public function testLAlphabetNeDependQueDuNiveau(): void
    {
        $reflexion = new \ReflectionClass(AlphabetDesScribes::class);
        $source = (string) file_get_contents((string) $reflexion->getFileName());

        self::assertStringNotContainsString('Dechiffreur', $source);
        self::assertStringNotContainsString('Divinite::Thot', $source);

        // La clé de lecture, elle, les connaît : c'est bien deux pistes
        // distinctes, et non la même règle écrite deux fois.
        $cle = new \ReflectionClass(CleDeLecture::class);
        $sourceDeLaCle = (string) file_get_contents((string) $cle->getFileName());

        self::assertStringContainsString('Dechiffreur', $sourceDeLaCle);
        self::assertStringContainsString('Divinite::Thot', $sourceDeLaCle);
    }

    /**
     * La leçon fondatrice écrit le nom du jeu, avec quatre signes connus
     * d'emblée pour qu'elle soit tentable tout de suite.
     */
    public function testLaLeconEcritLeNomDuJeuAvecQuatreSignes(): void
    {
        self::assertCount(4, LeconDeNiout::SIGNES);
        self::assertSame('niwt', implode('', array_map(
            static fn (SigneAlphabetique $s): string => $s->translitteration(),
            LeconDeNiout::SIGNES,
        )));
        self::assertSame('𓈖𓇋𓅱𓏏', LeconDeNiout::motEcrit());
    }

    /**
     * **Elle se retente, et ne se monnaie qu'une fois.** Remettre quatre signes
     * dans l'ordre est un exercice, pas une devinette — on apprend en
     * recommençant. Mais l'exercice ne doit pas devenir une rente.
     */
    public function testLaLeconSeRetenteEtNeRecompenseQuUneFois(): void
    {
        $client = static::createClient();
        $partie = $this->lancerAvecScribes($client, 'lecon-niout@example.com');
        $ville = $partie->getVille();

        $faux = ['pain', 'filet_d_eau', 'roseau_fleuri', 'poussin_de_caille'];
        $juste = array_map(static fn (SigneAlphabetique $s): string => $s->value, LeconDeNiout::SIGNES);

        $debenAvant = $ville->getDeben();

        // Se tromper ne coûte rien, et n'interdit pas de reprendre.
        $this->repondre($client, $partie, $faux);
        self::assertFalse($this->rechargee($partie)->aEcritNiout());
        self::assertSame($debenAvant, $this->rechargee($partie)->getDeben(), 'Une erreur ne coûte rien.');

        $this->repondre($client, $partie, $juste);
        self::assertTrue($this->rechargee($partie)->aEcritNiout());
        self::assertSame($debenAvant + Enigme::RECOMPENSE_EN_DEBEN, $this->rechargee($partie)->getDeben());

        // Et une seconde réussite ne repaie pas.
        $this->repondre($client, $partie, $juste);
        self::assertSame($debenAvant + Enigme::RECOMPENSE_EN_DEBEN, $this->rechargee($partie)->getDeben());
    }

    /**
     * **Ce n'est pas une inscription du fil rouge** : la leçon vit à côté, et
     * ne décide rien de la mission.
     */
    public function testLaLeconNAvancePasLeFilRouge(): void
    {
        $client = static::createClient();
        $partie = $this->lancerAvecScribes($client, 'niout-fil-rouge@example.com');

        $avant = FilRouge::acte($partie);
        $this->repondre($client, $partie, array_map(
            static fn (SigneAlphabetique $s): string => $s->value,
            LeconDeNiout::SIGNES,
        ));

        self::assertSame($avant, FilRouge::acte($partie));
    }

    /**
     * L'alphabet et sa leçon se lisent à la Maison des scribes — là où
     * l'alphabet s'apprend, et nulle part ailleurs.
     */
    public function testLAlphabetSeLitDansLeBonOnglet(): void
    {
        $client = static::createClient();
        $partie = $this->lancerAvecScribes($client, 'ecran-alphabet@example.com');

        $client->request('GET', \sprintf(
            '/partie/%d/ville?onglet=%s',
            $partie->getId(),
            TypeDeBatiment::MaisonDesScribes->value,
        ));

        self::assertResponseIsSuccessful();
        $panneau = '#panneau-'.TypeDeBatiment::MaisonDesScribes->value;
        self::assertSelectorTextContains($panneau, 'L\'alphabet des scribes');
        self::assertSelectorTextContains($panneau, 'Écrire « Niout »');

        // Les glyphes portent la police embarquée : sans elle, ils tombent sur
        // un repli qu'on ne contrôle pas — ou sur un carré vide.
        self::assertSelectorExists($panneau.' .font-hieroglyphes');
    }

    /**
     * **La transcription suit le son, pas la lettre**, et n'invente jamais un
     * signe : ce que la table ne couvre pas est écarté et signalé.
     */
    public function testLaTranscriptionSuitLeSonEtNInventeRien(): void
    {
        // Les groupes valant un seul son passent avant les lettres seules.
        self::assertSame('𓈖𓄿𓐍𓏏', TranscriptionDuNom::ecrire('Nakht'), 'kh vaut un signe, pas deux.');
        self::assertSame('𓈙𓇋', TranscriptionDuNom::ecrire('Chi'), 'ch vaut le bassin d\'eau.');

        // Le c dur et le c doux ne s'écrivent pas du même signe.
        self::assertSame(
            SigneAlphabetique::CorbeilleAAnse->signe(),
            mb_substr(TranscriptionDuNom::ecrire('Ca'), 0, 1),
        );
        self::assertSame(
            SigneAlphabetique::LingePlie->signe(),
            mb_substr(TranscriptionDuNom::ecrire('Ci'), 0, 1),
        );

        // Les accents ne changent rien : l'égyptien ne notait pas les voyelles.
        self::assertSame(TranscriptionDuNom::ecrire('Nefer'), TranscriptionDuNom::ecrire('Néfér'));

        // Et ce qui n'a pas d'équivalent est écarté, jamais remplacé.
        $trait = TranscriptionDuNom::pour('Jean-Luc');
        self::assertSame(['-'], $trait['ecartes']);
        self::assertNotContains(null, $trait['signes']);
    }

    /**
     * Tout signe produit par la transcription appartient à l'alphabet : elle ne
     * peut pas fabriquer un glyphe que la police embarquée ne dessine pas.
     */
    public function testLaTranscriptionNeSortJamaisDeLAlphabet(): void
    {
        foreach (['Nakht', 'Mylène', 'Sobekhotep', 'Xavier', 'Çà et là', 'Ptah-mose'] as $nom) {
            foreach (TranscriptionDuNom::pour($nom)['signes'] as $signe) {
                self::assertContains($signe, SigneAlphabetique::cases());
            }
        }
    }

    /**
     * **Un cartouche ne s'écrit pas avec le seul alphabet** : il mêle des
     * bilitères et des logogrammes. C'est le propos, et c'est ce qui exige que
     * la police embarquée couvre aussi ces signes-là.
     */
    public function testUnCartoucheDepasseLAlphabet(): void
    {
        $lettres = array_map(static fn (SigneAlphabetique $s): string => $s->signe(), SigneAlphabetique::cases());
        $horsAlphabet = 0;

        foreach (CartoucheRoyal::cases() as $cartouche) {
            $signes = preg_split('//u', $cartouche->signes(), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

            self::assertSame(
                \count($signes),
                \count($cartouche->codesDeGardiner()),
                'Chaque signe d\'un cartouche porte son code de Gardiner.',
            );
            self::assertNotSame('', $cartouche->translitteration());
            self::assertNotSame('', $cartouche->sens());

            foreach ($signes as $signe) {
                if (!\in_array($signe, $lettres, true)) {
                    ++$horsAlphabet;
                }
            }
        }

        self::assertGreaterThan(0, $horsAlphabet);
    }

    /**
     * **Rien n'est affiché pour un pharaon dont le cartouche n'est pas établi.**
     * Un cartouche approximatif donné pour réel trahirait la règle qui veut que
     * les hiéroglyphes du jeu soient vrais ; l'absence, elle, ne trompe
     * personne.
     */
    public function testUnPharaonSansCartoucheEtabliNEnAffichePas(): void
    {
        self::assertNotNull(CartoucheRoyal::pourLePharaon('Ahmôsis Ier'));
        self::assertNull(CartoucheRoyal::pourLePharaon('Akhenaton'));
        self::assertNull(CartoucheRoyal::pourLePharaon('Un pharaon qui n\'existe pas'));
    }

    /**
     * Le cartouche paraît à l'introduction de la mission — pour le pharaon qui
     * la commandite, et nulle part ailleurs.
     */
    public function testLeCartoucheSeLitSurLaCommandeDuPharaon(): void
    {
        $client = static::createClient();
        $partie = $this->lancerAvecScribes($client, 'cartouche@example.com');

        $client->request('GET', \sprintf('/partie/%d/commande', $partie->getId()));

        self::assertResponseIsSuccessful();
        $cartouche = CartoucheRoyal::pourLePharaon('Ahmôsis Ier');
        self::assertNotNull($cartouche);
        self::assertSelectorTextContains('body', $cartouche->lecture());
        self::assertSelectorTextContains('body', $cartouche->sens());
        self::assertSelectorExists('.font-hieroglyphes');
    }

    /**
     * La ville telle qu'elle est en base après la requête : le client tourne
     * dans son propre gestionnaire d'entités, et l'objet gardé en mémoire par
     * le test ne voit pas ses écritures.
     */
    private function rechargee(GameSave $partie): City
    {
        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->clear();
        $rechargee = $gestionnaire->find(GameSave::class, $partie->getId());
        self::assertNotNull($rechargee);

        return $rechargee->getVille();
    }

    /**
     * @param list<string> $ordre
     */
    private function repondre(KernelBrowser $client, GameSave $partie, array $ordre): void
    {
        // Le jeton se lit sur la page, comme le ferait un navigateur : le
        // fabriquer hors requête n'ouvre aucune session.
        $crawler = $client->request('GET', \sprintf(
            '/partie/%d/ville?onglet=%s',
            $partie->getId(),
            TypeDeBatiment::MaisonDesScribes->value,
        ));

        $jeton = $crawler
            ->filter(\sprintf('form[action="/partie/%d/scribes/niout"] input[name="_token"]', $partie->getId()))
            ->first()->attr('value');

        $client->request('POST', \sprintf('/partie/%d/scribes/niout', $partie->getId()), [
            '_token' => $jeton,
            'onglet' => TypeDeBatiment::MaisonDesScribes->value,
            'ordre' => implode(',', $ordre),
        ]);
    }

    private function lancerAvecScribes(KernelBrowser $client, string $email): GameSave
    {
        $user = $this->creer($email);
        $client->loginUser($user);
        $partie = static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($user, 'Nakht');
        $ville = $partie->getVille();

        $ville->ajouterBatiment(new Building($ville, TypeDeBatiment::MaisonDesScribes, 1));
        $ville->crediterRessources([Ressource::Deben->value => 100]);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        return $partie;
    }

    private function lancer(string $email): GameSave
    {
        return static::getContainer()->get(LanceurDePartie::class)->lancerCampagne($this->creer($email), 'Nakht');
    }

    private function creer(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('peu-importe-ici');

        $gestionnaire = static::getContainer()->get(EntityManagerInterface::class);
        $gestionnaire->persist($user);
        $gestionnaire->flush();

        return $user;
    }
}
