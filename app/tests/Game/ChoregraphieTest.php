<?php

declare(strict_types=1);

namespace App\Tests\Game;

use App\Game\Choregraphie;
use App\Game\DifferenceDeQuinzaine;
use App\Game\GesteDeLaVille;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeFait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * La reconstitution d'une quinzaine : une fonction pure, qui illustre des faits
 * sans en inventer, dans l'ordre du cycle, à l'identique à chaque lecture
 * (`docs/plan-pnj-ville-vivante.md` § 2).
 *
 * @phpstan-import-type Photo from \App\Game\PhotoDeLaVille
 */
#[CoversClass(Choregraphie::class)]
#[CoversClass(DifferenceDeQuinzaine::class)]
final class ChoregraphieTest extends TestCase
{
    public function testLaMemeEntreeRendLeMemeFilm(): void
    {
        $photo = $this->photo();
        $faits = [$this->fait(TypeDeFait::ConvoiRentre, null, 'convoi-1'), $this->fait(TypeDeFait::ConvoiParti, null, 'convoi-1')];

        self::assertEquals(Choregraphie::pour($photo, $faits, 7), Choregraphie::pour($photo, $faits, 7));
    }

    /**
     * **Sans fait, que de la routine** : aucune scène ne porte de fait, et chaque
     * geste est un geste de métier. Une scène sans fait serait une invention.
     */
    public function testSansFaitOnNeVoitQueDeLaRoutine(): void
    {
        $film = Choregraphie::pour($this->photo(), [], 3);
        $routine = [GesteDeLaVille::Poste, GesteDeLaVille::Chez, GesteDeLaVille::Paie, GesteDeLaVille::Ronde, GesteDeLaVille::Veille, GesteDeLaVille::Habite, GesteDeLaVille::Rode, GesteDeLaVille::Oisif];

        foreach ($film['pas'] as $jours) {
            foreach ($jours as $moments) {
                foreach ($moments as $pas) {
                    self::assertNull($pas['fait']);
                    self::assertContains($pas['geste'], $routine);
                }
            }
        }

        self::assertSame([], $film['evenements']);
        self::assertSame([], $film['enCours']);
    }

    /**
     * Un chantier s'achève le dernier jour — et la scène en porte le fait.
     */
    public function testUnChantierSAcheveLeDernierJour(): void
    {
        $faits = [$this->fait(TypeDeFait::ChantierAcheve, 'grenier', 'chantier-1')];
        $film = Choregraphie::pour($this->photo(), $faits, 11);
        $dernier = $film['pas']['chef-1'][15];

        self::assertSame(GesteDeLaVille::Acheve, $dernier[1]['geste']);
        self::assertSame(TypeDeFait::ChantierAcheve, $dernier[1]['fait']);
        self::assertSame(GesteDeLaVille::Poste, $film['pas']['chef-1'][14][1]['geste'], 'Pas avant le dernier jour.');
        self::assertArrayHasKey(15, $film['evenements']);
    }

    /**
     * **Les maçons bâtissent le chantier** : le matin et l'après-midi, sur le lot, tous les jours qu'il dure ;
     * le jour où il s'achève, ils l'achèvent ; ensuite ils rentrent. L'aube et le soir, ils sont chez eux.
     */
    public function testLesMaconsBatissentLeChantierPuisRentrent(): void
    {
        $photo = $this->photo(['candidats' => [
            $this->candidat('famille', 'chef_de_famille', 'residence_familiale'),
            $this->candidat('macon-forge', 'macon', 'forge'),
        ]]);

        // Un chantier qui dure toute la quinzaine.
        $enCours = Choregraphie::pour($photo, [$this->fait(TypeDeFait::ChantierAvance, 'forge', 'chantier-1')], 4)['pas']['macon-forge'];

        foreach ([1, 7, 15] as $jour) {
            foreach ([1, 2] as $moment) {
                self::assertSame('forge', $enCours[$jour][$moment]['lieu']);
                self::assertSame(GesteDeLaVille::Construit, $enCours[$jour][$moment]['geste']);
                self::assertSame(TypeDeFait::ChantierAvance, $enCours[$jour][$moment]['fait']);
            }

            self::assertNotSame('forge', $enCours[$jour][0]['lieu'] ?? null, 'À l\'aube, on n\'est pas au chantier.');
        }

        // Un chantier qui s'achève au jour 9.
        $fini = Choregraphie::pour($photo, [$this->fait(TypeDeFait::ChantierAcheve, 'forge', 'chantier-1')], 4);
        $jourDeFin = (int) array_key_last($fini['evenements']);
        $pas = $fini['pas']['macon-forge'];

        self::assertSame(GesteDeLaVille::Acheve, $pas[$jourDeFin][1]['geste']);
        self::assertSame(GesteDeLaVille::Construit, $pas[1][1]['geste'], 'Avant la fin, on bâtit.');

        if ($jourDeFin < 15) {
            self::assertNotSame('forge', $pas[$jourDeFin + 1][1]['lieu'], 'Le chantier est fini : on rentre.');
        }
    }

    /**
     * **Un maçon ne flâne pas** : pendant la journée il est au chantier, sans course au Marché ni détour — quelle
     * que soit la graine. Sans chantier, il n'y a pas de maçon (la photographie n'en pose aucun).
     */
    public function testUnMaconNeQuittePasSonChantierEnJournee(): void
    {
        $photo = $this->photo(['candidats' => [
            $this->candidat('famille', 'chef_de_famille', 'residence_familiale'),
            $this->candidat('macon-forge', 'macon', 'forge'),
        ], 'batiments' => ['marche', 'temple', 'auberge', 'forge']]);

        foreach (range(1, 40) as $graine) {
            $pas = Choregraphie::pour($photo, [$this->fait(TypeDeFait::ChantierAvance, 'forge', 'chantier-1')], $graine)['pas']['macon-forge'];

            foreach ([1, 4, 8, 12, 15] as $jour) {
                foreach ([1, 2] as $moment) {
                    self::assertSame('forge', $pas[$jour][$moment]['lieu'], \sprintf('Graine %d, jour %d, moment %d', $graine, $jour, $moment));
                }
            }
        }
    }

    /**
     * **L'ordre du cycle** : un convoi ne repart qu'après être rentré, quelle que
     * soit la graine.
     */
    public function testUnConvoiNeRepartQuApresEtreRentre(): void
    {
        $faits = [
            $this->fait(TypeDeFait::ConvoiRentre, null, 'convoi-1', ['partenaire' => 'Byblos']),
            $this->fait(TypeDeFait::ConvoiParti, null, 'convoi-1', ['partenaire' => 'Byblos']),
        ];

        foreach (range(1, 60) as $graine) {
            $film = Choregraphie::pour($this->photo(), $faits, $graine);
            $retour = $this->jourDuGeste($film['pas']['chef-2'], GesteDeLaVille::ConvoiRentre);
            $depart = $this->jourDuGeste($film['pas']['chef-2'], GesteDeLaVille::ConvoiParti);

            self::assertNotNull($retour);
            self::assertNotNull($depart);
            self::assertLessThan($depart, $retour, \sprintf('Graine %d : rentré avant de repartir.', $graine));
        }
    }

    /**
     * Les cinq jours épagomènes : une quinzaine courte, et tout y tient.
     */
    public function testLesJoursEpagomenesNeFontQueCinqJours(): void
    {
        $faits = [
            $this->fait(TypeDeFait::ConvoiRentre, null, 'convoi-1'),
            $this->fait(TypeDeFait::ConvoiParti, null, 'convoi-1'),
            $this->fait(TypeDeFait::EpidemieEclate, null, null, [], 3),
            $this->fait(TypeDeFait::ChantierAcheve, 'grenier', 'chantier-1'),
        ];

        foreach (range(1, 40) as $graine) {
            $film = Choregraphie::pour($this->photo(['jours' => 5]), $faits, $graine);

            self::assertSame(5, $film['jours']);
            self::assertSame([1, 2, 3, 4, 5], array_keys($film['pas']['chef-1']));

            foreach (array_keys($film['evenements']) as $jour) {
                self::assertGreaterThanOrEqual(1, $jour);
                self::assertLessThanOrEqual(5, $jour);
            }
        }
    }

    /**
     * Un Medjaÿ blessé pendant la quinzaine est debout jusqu'à son jour, couché
     * ensuite — et un homme déjà blessé l'est tout du long.
     */
    public function testUnMedjayBlesseSeCouche(): void
    {
        $faits = [$this->fait(TypeDeFait::MedjayBlesse, null, 'medjay-1', ['libelle' => 'Hori'])];
        $film = Choregraphie::pour($this->photo(), $faits, 5);
        $jourBlesse = null;

        foreach ($film['pas']['medjay-1'] as $jour => $moments) {
            if ($moments[3]['couchee']) {
                $jourBlesse = $jour;

                break;
            }
        }

        self::assertNotNull($jourBlesse);
        if ($jourBlesse > 1) {
            self::assertFalse($film['pas']['medjay-1'][$jourBlesse - 1][3]['couchee'], 'Debout avant son jour.');
            self::assertSame(TypeDeBatiment::Caserne->value, $film['pas']['medjay-1'][$jourBlesse - 1][1]['lieu']);
        }

        for ($jour = $jourBlesse; $jour <= 15; ++$jour) {
            self::assertTrue($film['pas']['medjay-1'][$jour][3]['couchee'], 'Couché ensuite.');
            self::assertSame(TypeDeBatiment::QuartierDHabitation->value, $film['pas']['medjay-1'][$jour][3]['lieu']);
        }

        $dejaBlesse = Choregraphie::pour($this->photo(['candidats' => [$this->candidat('medjay-9', 'medjay', 'quartier_habitation', true)]]), [], 5);

        foreach ($dejaBlesse['pas']['medjay-9'] as $moments) {
            foreach ($moments as $pas) {
                self::assertTrue($pas['couchee']);
            }
        }
    }

    /**
     * Un chef parti n'est plus en ville : son journal le dit, et son départ
     * est une scène.
     */
    public function testUnChefPartiNEstPlusEnVille(): void
    {
        $faits = [$this->fait(TypeDeFait::ChefParti, null, 'chef-1', ['libelle' => 'Nebnefer — chef (Grenier)'])];
        $film = Choregraphie::pour($this->photo(), $faits, 9);
        $depart = $this->jourDuGeste($film['pas']['chef-1'], GesteDeLaVille::Part);

        self::assertNotNull($depart);

        foreach ($film['pas']['chef-1'] as $jour => $moments) {
            if ($jour > $depart) {
                foreach ($moments as $pas) {
                    self::assertNull($pas['lieu'], 'Plus là après son départ.');
                }
            }
        }

        $journal = Choregraphie::journal($film['pas']['chef-1']);

        if ($depart < 15) {
            self::assertSame(['N\'est plus en ville.'], $journal[14]['lignes']);
        }

        self::assertTrue($journal[$depart - 1]['fait']);
    }

    /**
     * Un jour de fête, le matin, on suit la procession — sauf si l'on est alité.
     */
    public function testUnJourDeFeteOnSuitLaProcession(): void
    {
        $photo = $this->photo(['fete' => ['cle' => 'opet', 'libelle' => 'Opet']]);
        $photo['candidats'][] = $this->candidat('maison-1', 'foyer', 'quartier_habitation', true);
        $faits = [$this->fait(TypeDeFait::Fete, null, null, ['libelle' => 'Opet', 'fete' => 'opet'])];
        $film = Choregraphie::pour($photo, $faits, 4);

        self::assertNotNull($film['jourDeFete']);
        $pas = $film['pas']['chef-1'][$film['jourDeFete']][1];
        self::assertSame(GesteDeLaVille::Procession, $pas['geste']);
        self::assertSame(TypeDeBatiment::Temple->value, $pas['lieu']);
        self::assertSame(GesteDeLaVille::Repos, $film['pas']['maison-1'][$film['jourDeFete']][1]['geste'], 'Un malade ne suit pas la procession.');
    }

    /**
     * La fièvre qui éclate couche les premières maisons montrées, autant que la
     * ville a de malades ; celle qui finit les relève.
     */
    public function testLaFievreCoucheLesMaisonsPuisLesRelève(): void
    {
        $photo = $this->photo();
        $photo['candidats'][] = $this->candidat('maison-1', 'foyer', 'quartier_habitation');
        $photo['candidats'][] = $this->candidat('maison-2', 'foyer', 'quartier_habitation');
        $film = Choregraphie::pour($photo, [$this->fait(TypeDeFait::EpidemieEclate, null, null, [], 1)], 2);

        $couchee = array_filter($film['pas']['maison-1'], static fn (array $m): bool => $m[1]['couchee']);
        self::assertNotEmpty($couchee, 'La première maison tombe malade.');
        self::assertNotEmpty(array_filter($film['pas']['maison-2'], static fn (array $m): bool => !$m[1]['couchee']), 'La seconde reste debout : un seul malade.');
        self::assertFalse($film['pas']['maison-1'][1][1]['couchee'] && !isset($couchee[1]), 'Pas couchée avant que la fièvre n\'éclate.');
    }

    /**
     * La corvée d'Akhèt : quand le fleuve désœuvre les paysans, les bras sans
     * poste vont au chantier — s'il y en a un.
     */
    public function testLaCorveeSeVoitEnAkhetQuandUnChantierExiste(): void
    {
        $photo = $this->photo(['saison' => 'akhet', 'chantiers' => [['id' => 1, 'lieu' => 'temple']]]);
        $photo['candidats'][] = $this->candidat('sans-poste', 'sans_poste', 'marche');

        self::assertSame(GesteDeLaVille::Corvee, Choregraphie::pour($photo, [], 1)['pas']['sans-poste'][3][1]['geste']);
        self::assertSame('temple', Choregraphie::pour($photo, [], 1)['pas']['sans-poste'][3][1]['lieu']);

        $photo['saison'] = 'peret';
        self::assertSame(GesteDeLaVille::Oisif, Choregraphie::pour($photo, [], 1)['pas']['sans-poste'][3][1]['geste']);
    }

    /**
     * Le journal d'un chef : ses moments se regroupent par endroit.
     */
    public function testLeJournalRegroupeLesMoments(): void
    {
        $film = Choregraphie::pour($this->photo(), [], 1);
        $journal = Choregraphie::journal($film['pas']['chef-1']);

        self::assertCount(15, $journal);
        self::assertEqualsCanonicalizing(
            ['À son poste — Grenier (matin, après-midi)', 'Chez soi (aube, soir)'],
            $journal[2]['lignes'],
        );
        self::assertFalse($journal[2]['fait']);
    }

    /**
     * Le chef de famille verse la paie au premier jour.
     */
    public function testLaPaieSeVersePremierJour(): void
    {
        $film = Choregraphie::pour($this->photo(), [], 1);

        self::assertSame(GesteDeLaVille::Paie, $film['pas']['famille'][1][1]['geste']);
        self::assertSame(GesteDeLaVille::Poste, $film['pas']['famille'][2][1]['geste']);
    }

    /**
     * La différence de deux photographies : un chantier disparu s'est achevé, un
     * convoi qui repart est rentré puis reparti, un chef absent est parti.
     */
    public function testLesFaitsSeDeduisentDeDeuxPhotographies(): void
    {
        $avant = $this->photo([
            'chantiers' => [['id' => 1, 'lieu' => 'grenier'], ['id' => 2, 'lieu' => 'temple']],
            'fabrications' => [['id' => 1, 'lieu' => 'atelier']],
            'convois' => [['id' => 1, 'partenaire' => 'Byblos', 'quinzaines' => 1], ['id' => 2, 'partenaire' => 'Thèbes', 'quinzaines' => 3]],
            'expeditions' => [['id' => 1]],
            'chefs' => [['cle' => 'chef-1', 'libelle' => 'Nebnefer'], ['cle' => 'chef-2', 'libelle' => 'Hori']],
            'medjays' => [['cle' => 'medjay-1', 'libelle' => 'Paneb', 'disponible' => true]],
            'fete' => ['cle' => 'opet', 'libelle' => 'Opet'],
        ]);
        $apres = $this->photo([
            'chantiers' => [['id' => 2, 'lieu' => 'temple']],
            'fabrications' => [],
            'convois' => [['id' => 1, 'partenaire' => 'Byblos', 'quinzaines' => 5], ['id' => 2, 'partenaire' => 'Thèbes', 'quinzaines' => 2], ['id' => 3, 'partenaire' => 'Pount', 'quinzaines' => 8]],
            'expeditions' => [],
            'epidemie' => true,
            'malades' => 4,
            'chefs' => [['cle' => 'chef-2', 'libelle' => 'Hori']],
            'medjays' => [['cle' => 'medjay-1', 'libelle' => 'Paneb', 'disponible' => false]],
            'rival' => ['cle' => 'rival', 'libelle' => 'Panehsy le marchand'],
        ]);

        $types = array_map(static fn (array $f): string => $f['type']->value.':'.($f['acteur'] ?? $f['lieu'] ?? ''), DifferenceDeQuinzaine::faits($avant, $apres));

        foreach ([
            'chantier_acheve:chantier-1', 'chantier_avance:chantier-2',
            'fabrication_achevee:ordre-1',
            'convoi_rentre:convoi-1', 'convoi_parti:convoi-1', 'convoi_parti:convoi-3',
            'expedition_rentree:expedition-1',
            'epidemie_eclate:', 'chef_parti:chef-1', 'rival_arrive:rival', 'medjay_blesse:medjay-1', 'fete:',
        ] as $attendu) {
            self::assertContains($attendu, $types);
        }

        self::assertNotContains('convoi_rentre:convoi-2', $types, 'Un convoi encore en route n\'est pas rentré.');
        self::assertNotContains('chef_parti:chef-2', $types);
    }

    /**
     * **Une ville bâtie a des journées moins plates** : l'après-midi, une course au Marché ;
     * le soir, parfois l'Auberge. Le matin reste le poste — c'est le métier.
     */
    public function testUneVilleBatieVarieSesJournees(): void
    {
        $film = Choregraphie::pour($this->photoBatie(), [], 5);
        $courses = 0;
        $detentes = 0;

        foreach (['chef-1', 'chef-2'] as $cle) {
            foreach ($film['pas'][$cle] as $moments) {
                self::assertSame(GesteDeLaVille::Poste, $moments[1]['geste'], 'Le matin, on est à son poste.');
                self::assertContains($moments[2]['geste'], [GesteDeLaVille::Poste, GesteDeLaVille::Course]);
                self::assertContains($moments[3]['geste'], [GesteDeLaVille::Chez, GesteDeLaVille::Detente]);
                $courses += GesteDeLaVille::Course === $moments[2]['geste'] ? 1 : 0;
                $detentes += GesteDeLaVille::Detente === $moments[3]['geste'] ? 1 : 0;
            }
        }

        self::assertGreaterThan(0, $courses, 'Sur trente après-midi, au moins une course.');
        self::assertLessThan(20, $courses, 'Une course reste l\'exception.');
        self::assertGreaterThan(0, $detentes);
        self::assertSame(TypeDeBatiment::Marche->value, $this->premierPas($film['pas']['chef-1'], GesteDeLaVille::Course)['lieu'] ?? TypeDeBatiment::Marche->value);
    }

    /**
     * **On ne va pas où il n'y a rien** : sans Marché, sans Temple, sans Auberge, aucune
     * course, aucune visite, aucun détour — et les traces d'avant le champ des bâtiments
     * en font autant.
     */
    public function testOnNAllantNullePartOuIlNYARien(): void
    {
        $sansDetour = [
            $this->photoBatie(['batiments' => ['residence_familiale', 'grenier', 'entrepot', 'caserne', 'quartier_habitation']]),
            $this->photo(),
        ];

        foreach ($sansDetour as $photo) {
            foreach (range(1, 8) as $graine) {
                foreach (Choregraphie::pour($photo, [], $graine)['pas'] as $jours) {
                    foreach ($jours as $moments) {
                        foreach ($moments as $pas) {
                            self::assertNotContains($pas['geste'], [GesteDeLaVille::Course, GesteDeLaVille::Visite, GesteDeLaVille::Detente]);
                        }
                    }
                }
            }
        }
    }

    /**
     * **Un fait prime sur la variété** : le jour où le chantier s'achève, le chef y est, quel
     * que soit le tirage de la course.
     */
    public function testUnFaitPrimeSurLaVariete(): void
    {
        $faits = [$this->fait(TypeDeFait::ChantierAcheve, 'grenier', 'chantier-1')];

        foreach (range(1, 40) as $graine) {
            $film = Choregraphie::pour($this->photoBatie(), $faits, $graine);

            self::assertSame(GesteDeLaVille::Acheve, $film['pas']['chef-1'][15][1]['geste'], 'Graine '.$graine);
            self::assertSame(GesteDeLaVille::Acheve, $film['pas']['chef-1'][15][2]['geste'], 'Graine '.$graine);
        }
    }

    /**
     * Un Medjaÿ alité ne patrouille pas, quelle que soit la variété.
     */
    public function testUnMedjayAliteNePatrouillePas(): void
    {
        $photo = $this->photoBatie();
        $photo['candidats'][] = $this->candidat('medjay-9', 'medjay', 'quartier_habitation', true);

        foreach (range(1, 20) as $graine) {
            foreach (Choregraphie::pour($photo, [], $graine)['pas']['medjay-9'] as $moments) {
                foreach ($moments as $pas) {
                    self::assertSame(GesteDeLaVille::Repos, $pas['geste']);
                }
            }
        }
    }

    /**
     * Le journal dit où l'on est allé, et que c'est une course.
     */
    public function testLeJournalNommeLeLieuDeLaCourse(): void
    {
        $film = Choregraphie::pour($this->photoBatie(), [], 5);
        $lignes = [];

        foreach (Choregraphie::journal($film['pas']['chef-1']) as $jour) {
            $lignes = [...$lignes, ...$jour['lignes']];
        }

        self::assertContains('Fait une course — Marché (après-midi)', $lignes);
    }

    /**
     * La variété se rejoue à l'identique, et change avec la partie.
     */
    public function testLaVarieteEstStableEtDependDeLaPartie(): void
    {
        self::assertEquals(Choregraphie::pour($this->photoBatie(), [], 9), Choregraphie::pour($this->photoBatie(), [], 9));

        $films = array_map(static fn (int $graine): array => Choregraphie::pour(self::photoBatieStatique(), [], $graine)['pas'], range(1, 12));

        self::assertGreaterThan(1, \count(array_unique(array_map('serialize', $films))), 'Deux parties n\'ont pas toutes le même film.');
    }

    /**
     * **On se parle quand on se croise** : deux silhouettes au même endroit, au même moment, dehors
     * et à leurs affaires. Jamais plus de trois conversations par jour, jamais à l'aube.
     */
    public function testOnSeParleQuandOnSeCroise(): void
    {
        $photo = $this->photoDeFoule();
        $film = Choregraphie::pour($photo, [], 3);

        self::assertNotEmpty($film['paroles'], 'Dans une ville peuplée, on se parle.');

        $parJour = [];

        foreach ($film['paroles'] as $parole) {
            $parJour[$parole['jour']] = ($parJour[$parole['jour']] ?? 0) + 1;

            self::assertContains($parole['moment'], [1, 2, 3], 'Jamais à l\'aube.');
            self::assertNotSame($parole['a'], $parole['b']);
            self::assertNotSame('', $parole['texteA']);
            self::assertNotSame('', $parole['texteB']);

            // Les deux sont bien au même endroit, à ce moment-là.
            foreach ([$parole['a'], $parole['b']] as $indice) {
                $pas = $film['pas'][$photo['candidats'][$indice]['cle']][$parole['jour']][$parole['moment']];
                self::assertSame($parole['lieu'], $pas['lieu']);
                self::assertFalse($pas['couchee']);
            }
        }

        self::assertNotEmpty($parJour);
        self::assertLessThanOrEqual(3, max([0, ...array_values($parJour)]));
    }

    /**
     * **Chacun dit ce qui est vrai** : une situation de fait n'est proposée que si le fait est
     * vrai cette quinzaine. Sans fièvre, sans chantier, sans convoi, sans rival, on ne parle
     * que de la saison — toujours vraie — ou d'ambiance.
     */
    public function testOnNeParleQueDeCeQuiEstVrai(): void
    {
        $situations = [];

        foreach (range(1, 80) as $graine) {
            foreach (Choregraphie::pour($this->photoDeFoule(), [], $graine)['paroles'] as $parole) {
                $situations[$parole['situation']] = true;
            }
        }

        self::assertNotEmpty($situations);

        foreach (array_keys($situations) as $situation) {
            self::assertContains($situation, ['saison_peret', 'ambiance', 'ambiance_soir'], 'Rien de faux : '.$situation);
        }
    }

    /**
     * Et quand le fait **est** vrai, on en parle : chaque situation apparaît avec son fait.
     */
    public function testOnParleDesFaitsVrais(): void
    {
        $photo = $this->photoDeFoule([
            'epidemie' => true, 'malades' => 4,
            'chantiers' => [['id' => 1, 'lieu' => 'temple']],
            'convois' => [['id' => 1, 'partenaire' => 'Byblos', 'quinzaines' => 3]],
            'rival' => ['cle' => 'rival', 'libelle' => 'Panehsy le marchand'],
        ]);
        $vues = [];

        foreach (range(1, 120) as $graine) {
            foreach (Choregraphie::pour($photo, [], $graine)['paroles'] as $parole) {
                $vues[$parole['situation']] = $parole;
            }
        }

        foreach (['fievre', 'chantier', 'convoi', 'rival'] as $situation) {
            self::assertArrayHasKey($situation, $vues, $situation.' est vrai : on doit en parler.');
            self::assertSame('fait', $vues[$situation]['nature']);
        }

        self::assertStringContainsString('Byblos', implode(' ', array_column(array_filter($vues, static fn (array $p): bool => 'convoi' === $p['situation']), 'texteA')) ?: 'Byblos');
    }

    /**
     * Le jour de fête, la procession se parle ; un autre jour, jamais de fête.
     */
    public function testLaFeteNeSeDitQueLeJourDeFete(): void
    {
        $photo = $this->photoDeFoule(['fete' => ['cle' => 'opet', 'libelle' => 'Opet']]);
        $faits = [$this->fait(TypeDeFait::Fete, null, null, ['libelle' => 'Opet', 'fete' => 'opet'])];
        $vue = false;

        foreach (range(1, 120) as $graine) {
            $film = Choregraphie::pour($photo, $faits, $graine);

            foreach ($film['paroles'] as $parole) {
                if ('fete' === $parole['situation']) {
                    $vue = true;
                    self::assertSame($film['jourDeFete'], $parole['jour'], 'La fête ne se dit que le jour de fête.');
                    self::assertSame('temple', $parole['lieu'], 'On en parle à la procession.');
                    self::assertStringContainsString('Opet', $parole['texteA'].$parole['texteB'].implode('', [$parole['libelleA']]) ?: 'Opet');
                }
            }
        }

        self::assertTrue($vue);
    }

    /**
     * Celui qui n'a pas d'ouvrage est celui qui s'en plaint — et seulement s'il y en a un.
     */
    public function testLeSansOuvrageSePlaintEtSeulSiIlYEnADeux(): void
    {
        $photo = $this->photoDeFoule(['candidats' => [
            ...array_map(fn (int $i): array => $this->candidat('maison-'.$i, 'foyer', 'quartier_habitation'), range(1, 3)),
            $this->candidat('sans-poste', 'sans_poste', 'marche'),
            $this->candidat('sans-poste', 'sans_poste', 'marche'),
        ]]);
        $vue = false;

        foreach (range(1, 120) as $graine) {
            foreach (Choregraphie::pour($photo, [], $graine)['paroles'] as $parole) {
                if ('sans_ouvrage' === $parole['situation']) {
                    $vue = true;
                    self::assertSame('sans-poste', $parole['libelleA'], 'C\'est lui qui se plaint.');
                }
            }
        }

        self::assertTrue($vue);

        foreach (range(1, 60) as $graine) {
            foreach (Choregraphie::pour($this->photoDeFoule(), [], $graine)['paroles'] as $parole) {
                self::assertNotSame('sans_ouvrage', $parole['situation'], 'Personne n\'est sans ouvrage.');
            }
        }
    }

    /**
     * Un alité ne bavarde pas ; et une silhouette qui joue un fait non plus.
     */
    public function testUnAliteNeBavardePasEtUnFaitPrimeSurLaParole(): void
    {
        $photo = $this->photoDeFoule();
        $photo['candidats'][] = $this->candidat('maison-9', 'foyer', 'quartier_habitation', true);
        $faits = [$this->fait(TypeDeFait::ChantierAcheve, 'grenier', 'chantier-1')];

        foreach (range(1, 60) as $graine) {
            $film = Choregraphie::pour($photo, $faits, $graine);

            foreach ($film['paroles'] as $parole) {
                $participants = array_map(static fn (int $i): string => $photo['candidats'][$i]['cle'], [$parole['a'], $parole['b']]);

                self::assertNotContains('maison-9', $participants);

                if ('grenier' === $parole['lieu'] && 15 === $parole['jour'] && \in_array($parole['moment'], [1, 2], true)) {
                    self::fail('Un chantier s\'achève au Grenier ce jour-là : on n\'y bavarde pas.');
                }
            }
        }
    }

    /**
     * Ce qui s'est dit se lit dans le journal de celui qui l'a dit : **le même texte que la
     * bulle**, avec à qui.
     */
    public function testLaParoleSeLitDansLeJournal(): void
    {
        $photo = $this->photoDeFoule();
        $film = Choregraphie::pour($photo, [], 3);
        $parole = $film['paroles'][0];
        $cleA = $photo['candidats'][$parole['a']]['cle'];
        $lignes = [];

        foreach (Choregraphie::journal($film['pas'][$cleA]) as $jour) {
            $lignes = [...$lignes, ...$jour['lignes']];
        }

        self::assertNotEmpty(array_filter($lignes, static fn (string $l): bool => str_contains($l, '« '.$parole['texteA'].' »') && str_contains($l, 'dit à '.$parole['libelleB'])));
    }

    /**
     * Sans personne au même endroit, on ne se parle pas ; et la conversation se rejoue
     * à l'identique.
     */
    public function testPersonneNeParleSeulEtLaConversationEstStable(): void
    {
        self::assertSame([], Choregraphie::pour($this->photo(), [], 3)['paroles'], 'Chacun est ailleurs : personne à qui parler.');
        self::assertEquals(Choregraphie::pour($this->photoDeFoule(), [], 7)['paroles'], Choregraphie::pour($this->photoDeFoule(), [], 7)['paroles']);
    }

    /**
     * **Un témoignage rapporté se dit, et se lit** : l'après-midi du jour, la famille — ou le chef de la
     * Maison des scribes, s'il y en a un — répète ce que l'émissaire a rapporté, dans les mots du
     * témoin ; l'autre répond. C'est un fait vrai, et le même texte est au journal.
     */
    public function testUnTemoignageRapporteSeDitEtSeLitAuJournal(): void
    {
        $faits = [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'plainte_dun_paysan'])];
        $film = Choregraphie::pour($this->photo(), $faits, 5);
        $temoignages = array_values(array_filter($film['paroles'], static fn (array $p): bool => 'temoignage' === $p['situation']));

        self::assertCount(1, $temoignages);
        self::assertSame('fait', $temoignages[0]['nature']);
        self::assertSame(2, $temoignages[0]['moment']);
        self::assertStringContainsString('Un paysan', $temoignages[0]['texteA']);
        self::assertStringContainsString('Le fleuve a tout bougé', $temoignages[0]['texteA']);
        self::assertSame('famille', $temoignages[0]['libelleA'], 'Sans scribe, c\'est la famille qui rapporte.');

        $lignes = [];

        foreach (Choregraphie::journal($film['pas']['famille']) as $jour) {
            $lignes = [...$lignes, ...$jour['lignes']];
        }

        self::assertNotEmpty(array_filter($lignes, static fn (string $l): bool => str_contains($l, 'Le fleuve a tout bougé')));
        self::assertContains('L\'émissaire rapporte un témoignage : un paysan.', $film['evenements'][$temoignages[0]['jour']] ?? []);
    }

    /**
     * Le chef de la Maison des scribes rapporte, et la famille répond.
     */
    public function testLeScribeRapporteEtLaFamilleRepond(): void
    {
        $photo = $this->photo();
        $photo['candidats'][] = $this->candidat('chef-9', 'chef', 'maison_des_scribes');
        $faits = [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'dire_dun_berger'])];

        foreach (range(1, 30) as $graine) {
            $temoignages = array_values(array_filter(Choregraphie::pour($photo, $faits, $graine)['paroles'], static fn (array $p): bool => 'temoignage' === $p['situation']));

            self::assertCount(1, $temoignages);
            self::assertSame('chef-9', $temoignages[0]['libelleA']);
            self::assertSame('famille', $temoignages[0]['libelleB']);
        }
    }

    /**
     * Ceux qui rapportent ne bavardent pas ce moment-là : la parole du témoignage n'est jamais
     * recouverte par une conversation d'ambiance, et il n'y en a qu'une.
     */
    public function testLeTemoignageNEstJamaisRecouvertParUneConversation(): void
    {
        $photo = $this->photoDeFoule();
        $photo['candidats'][] = $this->candidat('famille', 'chef_de_famille', 'residence_familiale');
        $faits = [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'recit_du_batelier'])];

        foreach (range(1, 60) as $graine) {
            $film = Choregraphie::pour($photo, $faits, $graine);
            $temoignage = array_values(array_filter($film['paroles'], static fn (array $p): bool => 'temoignage' === $p['situation']));

            self::assertCount(1, $temoignage, 'Graine '.$graine);

            foreach ($film['paroles'] as $parole) {
                if ('temoignage' !== $parole['situation'] && $parole['jour'] === $temoignage[0]['jour'] && 2 === $parole['moment']) {
                    self::assertNotContains($temoignage[0]['a'], [$parole['a'], $parole['b']]);
                    self::assertNotContains($temoignage[0]['b'], [$parole['a'], $parole['b']]);
                }
            }

            $ordre = array_map(static fn (array $p): array => [$p['jour'], $p['moment']], $film['paroles']);
            $trie = $ordre;
            sort($trie);
            self::assertSame($trie, $ordre, 'Dans l\'ordre du temps.');
        }
    }

    /**
     * Personne à qui le dire, ou un indice inconnu : on ne met rien en scène — le témoignage se lit
     * au dossier.
     */
    public function testSansPersonneOuSansIndiceOnNeMetRienEnScene(): void
    {
        $seul = $this->photo(['candidats' => [$this->candidat('famille', 'chef_de_famille', 'residence_familiale')]]);
        $faits = [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'plainte_dun_paysan'])];

        self::assertSame([], Choregraphie::pour($seul, $faits, 3)['paroles']);
        self::assertSame([], Choregraphie::pour($this->photo(), [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'inconnu'])], 3)['paroles']);
        self::assertSame([], Choregraphie::pour($this->photo(), [$this->fait(TypeDeFait::TemoignageRecueilli, null, null, ['indice' => 'borne_renversee'])], 3)['paroles'], 'Un indice de terrain ne parle pas.');
    }

    /**
     * Un témoignage de plus dans les dossiers, d'une photographie à l'autre : l'émissaire l'a rapporté.
     * Une trace d'avant ce champ n'en invente aucun.
     */
    public function testUnTemoignageDeMoinsAvantEstUnFait(): void
    {
        $avant = $this->photo(['temoignages' => []]);
        $apres = $this->photo(['temoignages' => ['plainte_dun_paysan']]);
        $faits = DifferenceDeQuinzaine::faits($avant, $apres);

        $temoignages = array_values(array_filter($faits, static fn (array $f): bool => TypeDeFait::TemoignageRecueilli === $f['type']));
        self::assertCount(1, $temoignages);
        self::assertSame('plainte_dun_paysan', $temoignages[0]['details']['indice']);
        self::assertSame(['indice' => 'plainte_dun_paysan'], $temoignages[0]['details'], 'La trace ne garde que la valeur de l\'indice, jamais le texte.');

        $ancien = $this->photo();
        unset($ancien['temoignages']);
        $nouveaux = array_filter(DifferenceDeQuinzaine::faits($ancien, $this->photo(['temoignages' => ['dire_dun_berger']])), static fn (array $f): bool => TypeDeFait::TemoignageRecueilli === $f['type']);
        self::assertCount(1, $nouveaux, 'Une trace d\'avant le champ se compare sans erreur.');
        self::assertSame([], array_filter(DifferenceDeQuinzaine::faits($ancien, $ancien), static fn (array $f): bool => TypeDeFait::TemoignageRecueilli === $f['type']));
    }

    /**
     * @param array<int, array<int, array{lieu: ?string, geste: GesteDeLaVille, fait: ?TypeDeFait, couchee: bool}>> $jours
     *
     * @return array{lieu: ?string, geste: GesteDeLaVille, fait: ?TypeDeFait, couchee: bool}|null
     */
    private function premierPas(array $jours, GesteDeLaVille $geste): ?array
    {
        foreach ($jours as $moments) {
            foreach ($moments as $pas) {
                if ($pas['geste'] === $geste) {
                    return $pas;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $surcharge
     *
     * @return Photo
     */
    private function photoBatie(array $surcharge = []): array
    {
        return self::photoBatieStatique($surcharge);
    }

    /**
     * @param array<string, mixed> $surcharge
     *
     * @return Photo
     */
    private static function photoBatieStatique(array $surcharge = []): array
    {
        $test = new self('photo');

        return $test->photo($surcharge + [
            'candidats' => [
                $test->candidat('famille', 'chef_de_famille', 'residence_familiale'),
                $test->candidat('chef-1', 'chef', 'grenier'),
                $test->candidat('chef-2', 'chef', 'entrepot'),
                $test->candidat('medjay-1', 'medjay', 'caserne'),
                $test->candidat('maison-1', 'foyer', 'quartier_habitation'),
                $test->candidat('sans-poste', 'sans_poste', 'marche'),
            ],
            'batiments' => ['residence_familiale', 'quartier_habitation', 'grenier', 'entrepot', 'caserne', 'marche', 'temple', 'auberge', 'maison_des_scribes'],
        ]);
    }

    /**
     * @param array<int, array<int, array{lieu: ?string, geste: GesteDeLaVille, fait: ?TypeDeFait, couchee: bool}>> $jours
     */
    private function jourDuGeste(array $jours, GesteDeLaVille $geste): ?int
    {
        foreach ($jours as $jour => $moments) {
            foreach ($moments as $pas) {
                if ($pas['geste'] === $geste) {
                    return $jour;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $surcharge
     *
     * @return Photo
     */
    private function photo(array $surcharge = []): array
    {
        /** @var Photo $photo */
        $photo = $surcharge + [
            'cycle' => 3,
            'jours' => 15,
            'saison' => 'peret',
            'fete' => null,
            'candidats' => [
                $this->candidat('famille', 'chef_de_famille', 'residence_familiale'),
                $this->candidat('chef-1', 'chef', 'grenier'),
                $this->candidat('chef-2', 'chef', 'entrepot'),
                $this->candidat('medjay-1', 'medjay', 'caserne'),
            ],
            'chantiers' => [],
            'fabrications' => [],
            'convois' => [],
            'expeditions' => [],
            'epidemie' => false,
            'malades' => 0,
            'maisons' => 0,
            'chefs' => [],
            'medjays' => [],
            'rival' => null,
        ];

        return $photo;
    }

    /**
     * @return array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool}
     */
    private function candidat(string $cle, string $role, ?string $lieu, bool $couchee = false): array
    {
        return ['cle' => $cle, 'role' => $role, 'libelle' => $cle, 'lieu' => $lieu, 'couchee' => $couchee];
    }

    /**
     * @param array<string, mixed> $details
     *
     * @return array{type: TypeDeFait, lieu: ?string, acteur: ?string, quantite: ?int, details: array<string, mixed>}
     */
    private function fait(TypeDeFait $type, ?string $lieu = null, ?string $acteur = null, array $details = [], ?int $quantite = null): array
    {
        return ['type' => $type, 'lieu' => $lieu, 'acteur' => $acteur, 'quantite' => $quantite, 'details' => $details];
    }

    /**
     * Une ville où l'on se croise : plusieurs travailleurs au même poste, plusieurs maisons au
     * même quartier, un chef parmi eux.
     *
     * @param array<string, mixed> $surcharge
     *
     * @return Photo
     */
    private function photoDeFoule(array $surcharge = []): array
    {
        return $this->photo($surcharge + [
            'candidats' => [
                $this->candidat('chef-1', 'chef', 'grenier'),
                $this->candidat('poste-grenier', 'travailleur', 'grenier'),
                $this->candidat('poste-grenier', 'travailleur', 'grenier'),
                $this->candidat('poste-grenier', 'travailleur', 'grenier'),
                $this->candidat('maison-1', 'foyer', 'quartier_habitation'),
                $this->candidat('maison-2', 'foyer', 'quartier_habitation'),
                $this->candidat('maison-3', 'foyer', 'quartier_habitation'),
                $this->candidat('maison-4', 'foyer', 'quartier_habitation'),
            ],
        ]);
    }
}
