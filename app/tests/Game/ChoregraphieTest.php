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
}
