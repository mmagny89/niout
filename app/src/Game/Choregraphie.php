<?php

declare(strict_types=1);

namespace App\Game;

/**
 * La reconstitution d'une quinzaine, jour après jour (`docs/plan-pnj-ville-vivante.md`
 * §§ 2, 6, 8).
 *
 * **Le jeu ne simule pas une journée.** Une quinzaine se résout d'un bloc ; aucune
 * personne n'y a d'emploi du temps. Cette classe **reconstitue**, à partir de ce
 * qui a réellement eu lieu — la photographie du début et les faits —, une quinzaine
 * plausible. Cinq règles, dont dépend la confiance qu'on lui doit :
 *
 * 1. **Une scène illustre un fait, elle n'en crée pas.** Le potier ne fabrique que
 *    si un ordre a avancé ; sans fait, une scène est une **routine de métier**
 *    (`GesteDeLaVille::Poste`), et le dit.
 * 2. **Le jour n'a aucune valeur mécanique.** Qu'un convoi parte le quatrième ou le
 *    neuvième jour est une mise en scène, stable (déduite d'une graine) mais sans
 *    conséquence : le joueur ne doit rien pouvoir en déduire sur les règles.
 * 3. **L'ordre du cycle est respecté** : un convoi part *après* être rentré, un
 *    chantier s'achève le dernier jour, une fièvre couche après avoir éclaté.
 * 4. **On ne montre pas ce que le jeu ne tient pas** : les gens anonymes sont
 *    représentatifs, et la reconstitution les traite comme tels.
 * 5. **Identique à chaque lecture** : fonction pure, sans hasard global, sans
 *    horloge — mêmes entrées, même film.
 *
 * Le résultat n'est jamais stocké : la trace garde la photographie et les faits,
 * et la chorégraphie se recalcule.
 *
 * @phpstan-import-type Photo from PhotoDeLaVille
 * @phpstan-import-type Fait from DifferenceDeQuinzaine
 *
 * @phpstan-type Pas array{lieu: ?string, geste: GesteDeLaVille, fait: ?TypeDeFait, couchee: bool, parole: ?array{texte: string, nature: string, avec: string}}
 * @phpstan-type Parole array{jour: int, moment: int, lieu: string, a: int, b: int, situation: string, nature: string, libelleA: string, libelleB: string, texteA: string, texteB: string}
 */
final class Choregraphie
{
    /**
     * Où l'on rentre le soir : le Quartier, ou la Résidence pour la famille.
     */
    private const string CHEZ_SOI = 'quartier_habitation';

    /**
     * Combien de conversations au plus par jour, et la chance que deux silhouettes qui se
     * trouvent au même endroit se parlent. **Valeurs inventées** : assez pour qu'on en voie,
     * pas assez pour que la ville bavarde sans fin et que chaque réplique s'use.
     */
    private const int PAROLES_PAR_JOUR = 3;

    private const int CHANCE_DE_PAROLE = 25;

    /**
     * @param Photo      $photo
     * @param list<Fait> $faits
     *
     * @return array{
     *     jours: int,
     *     pas: array<string, array<int, array<int, Pas>>>,
     *     evenements: array<int, list<string>>,
     *     enCours: list<string>,
     *     jourDeFete: ?int,
     *     paroles: list<Parole>,
     * }
     */
    public static function pour(array $photo, array $faits, int $graine): array
    {
        $jours = $photo['jours'];
        $jourDe = self::joursDesFaits($faits, $jours, $graine);
        $contexte = self::contexte($photo, $faits, $jourDe, $jours, $graine);

        $pas = [];

        foreach ($photo['candidats'] as $candidat) {
            // Les travailleurs d'un même bâtiment partagent une clé et un film.
            if (isset($pas[$candidat['cle']])) {
                continue;
            }

            for ($jour = 1; $jour <= $jours; ++$jour) {
                for ($moment = 0; $moment <= 3; ++$moment) {
                    $pas[$candidat['cle']][$jour][$moment] = self::pasDe($candidat, $jour, $moment, $contexte);
                }
            }
        }

        // Les conversations : deux silhouettes au même endroit, au même moment, qui se parlent.
        ['paroles' => $paroles, 'dites' => $dites] = self::paroles($photo, $pas, $contexte, $jours, $graine);

        foreach ($dites as $dite) {
            $existant = $pas[$dite['cle']][$dite['jour']][$dite['moment']] ?? null;

            if (null !== $existant) {
                $pas[$dite['cle']][$dite['jour']][$dite['moment']] = [...$existant, 'parole' => $dite['parole']];
            }
        }

        $evenements = [];
        $enCours = [];

        foreach ($faits as $i => $fait) {
            $phrase = $fait['type']->phrase(
                null === $fait['lieu'] ? null : TypeDeBatiment::tryFrom($fait['lieu'])?->libelle(),
                $fait['quantite'],
                $fait['details'],
            );

            if (null === $jourDe[$i]) {
                $enCours[] = $phrase;
            } else {
                $evenements[$jourDe[$i]][] = $phrase;
            }
        }

        return [
            'jours' => $jours,
            'pas' => $pas,
            'evenements' => $evenements,
            'enCours' => $enCours,
            'jourDeFete' => $contexte['fete'],
            'paroles' => $paroles,
        ];
    }

    /**
     * Le journal d'une silhouette, jour par jour : ce qu'elle a fait, en phrases.
     * Les moments consécutifs au même endroit se regroupent.
     *
     * @param array<int, array<int, Pas>> $pasDeLaFigure
     *
     * @return list<array{jour: int, lignes: list<string>, fait: bool}>
     */
    public static function journal(array $pasDeLaFigure): array
    {
        $journal = [];

        foreach ($pasDeLaFigure as $jour => $moments) {
            $groupes = [];
            $fait = false;

            foreach ($moments as $moment => $pas) {
                if (null === $pas['lieu'] && GesteDeLaVille::Part !== $pas['geste']) {
                    continue;
                }

                $cle = ($pas['lieu'] ?? '-').'|'.$pas['geste']->value.'|'.(int) $pas['couchee'].'|'.($pas['parole']['texte'] ?? '');
                $groupes[$cle] ??= ['pas' => $pas, 'moments' => []];
                $groupes[$cle]['moments'][] = MomentDeLaJournee::from($moment);
                $fait = $fait || null !== $pas['fait'];
            }

            $lignes = [];

            foreach ($groupes as $groupe) {
                $lieu = null === $groupe['pas']['lieu'] ? null : TypeDeBatiment::tryFrom($groupe['pas']['lieu'])?->libelle();
                $geste = $groupe['pas']['geste']->libelle();
                $ou = null !== $lieu && !\in_array($groupe['pas']['geste'], [GesteDeLaVille::Chez, GesteDeLaVille::Habite], true) ? ' — '.$lieu : '';
                $quand = 4 === \count($groupe['moments']) ? 'toute la journée' : implode(', ', array_map(static fn (MomentDeLaJournee $m): string => $m->court(), $groupe['moments']));
                $ligne = \sprintf('%s%s (%s)', $geste, $ou, $quand);

                // Ce qu'elle a dit, quand elle a parlé : **le même texte que la bulle**, pour qui ne voit pas l'image.
                if (null !== $groupe['pas']['parole']) {
                    $ligne .= \sprintf(' — dit à %s : « %s »', $groupe['pas']['parole']['avec'], $groupe['pas']['parole']['texte']);
                }

                $lignes[] = $ligne;
            }

            $journal[] = ['jour' => $jour, 'lignes' => [] === $lignes ? ['N\'est plus en ville.'] : $lignes, 'fait' => $fait];
        }

        return $journal;
    }

    /**
     * Le jour de chaque fait, ou null s'il dure toute la quinzaine.
     *
     * @param list<Fait> $faits
     *
     * @return list<?int>
     */
    private static function joursDesFaits(array $faits, int $jours, int $graine): array
    {
        $jourDe = [];
        $retours = [];

        // Les retours d'abord : un convoi ne repart qu'après être rentré.
        foreach ($faits as $i => $fait) {
            if (TypeDeFait::ConvoiRentre === $fait['type']) {
                $retours[$fait['acteur'] ?? ''] = 1 + self::hachage($graine, $fait, $i) % max(1, $jours - 1);
            }
        }

        foreach ($faits as $i => $fait) {
            $jourDe[$i] = match ($fait['type']) {
                TypeDeFait::ChantierAvance, TypeDeFait::FabricationAvance, TypeDeFait::ExpeditionEnRoute => null,
                TypeDeFait::ChantierAcheve, TypeDeFait::FabricationAchevee, TypeDeFait::ExpeditionRentree => $jours,
                TypeDeFait::ConvoiRentre => $retours[$fait['acteur'] ?? ''],
                TypeDeFait::ConvoiParti => self::jourDeDepart($fait, $i, $retours, $jours, $graine),
                default => 1 + self::hachage($graine, $fait, $i) % $jours,
            };
        }

        return array_values($jourDe);
    }

    /**
     * @param Fait               $fait
     * @param array<string, int> $retours
     */
    private static function jourDeDepart(array $fait, int $rang, array $retours, int $jours, int $graine): int
    {
        $retour = $retours[$fait['acteur'] ?? ''] ?? 0;

        return $retour + 1 + self::hachage($graine, $fait, $rang) % max(1, $jours - $retour);
    }

    /**
     * @param Fait $fait
     */
    private static function hachage(int $graine, array $fait, int $rang): int
    {
        return crc32($graine.'|'.$fait['type']->value.'|'.($fait['acteur'] ?? '').'|'.($fait['lieu'] ?? '').'|'.$rang);
    }

    /**
     * Les conversations de la quinzaine : deux silhouettes au même endroit, au même moment,
     * se disent quelques mots (`Dialogues`).
     *
     * **Ce n'est jamais un fait** : personne ne « parle » dans l'état du jeu. C'est de la
     * mise en scène, qui n'a de valeur que si ce qui se dit est vrai — `situations()` ne
     * propose que des situations que la quinzaine a réellement vécues. Les silhouettes qui
     * jouent un fait ne bavardent pas (sauf le jour de fête, où la procession se parle) ; les
     * alités non plus ; et personne ne parle chez soi, à l'aube ni au retour.
     *
     * @param Photo                                      $photo
     * @param array<string, array<int, array<int, Pas>>> $pas
     * @param array<string, mixed>                       $c
     *
     * @return array{paroles: list<Parole>, dites: list<array{cle: string, jour: int, moment: int, parole: array{texte: string, nature: string, avec: string}}>}
     */
    private static function paroles(array $photo, array $pas, array $c, int $jours, int $graine): array
    {
        $candidats = \array_slice($photo['candidats'], 0, PhotoDeLaVille::PLAFOND_DE_FIGURES);
        $paroles = [];
        $dites = [];

        for ($jour = 1; $jour <= $jours; ++$jour) {
            $duJour = 0;

            for ($moment = 1; $moment <= 3; ++$moment) {
                $groupes = [];

                foreach ($candidats as $i => $candidat) {
                    $pasDuMoment = $pas[$candidat['cle']][$jour][$moment] ?? null;

                    if (null !== $pasDuMoment && null !== $pasDuMoment['lieu'] && !$pasDuMoment['couchee'] && self::causant($pasDuMoment)) {
                        $groupes[$pasDuMoment['lieu']][] = $i;
                    }
                }

                ksort($groupes);

                foreach ($groupes as $lieu => $indices) {
                    $nombre = \count($indices);

                    if ($nombre < 2 || $duJour >= self::PAROLES_PAR_JOUR) {
                        continue;
                    }

                    $h = crc32($graine.'|parole|'.$jour.'|'.$moment.'|'.$lieu);

                    if ($h % 100 >= self::CHANCE_DE_PAROLE) {
                        continue;
                    }

                    $a = $indices[($h >> 8) % $nombre];
                    $b = $indices[((int) array_search($a, $indices, true) + 1 + (($h >> 16) % ($nombre - 1))) % $nombre];

                    // Celui qui n'a pas d'ouvrage est celui qui s'en plaint.
                    if ('sans_poste' === $candidats[$b]['role'] && 'sans_poste' !== $candidats[$a]['role']) {
                        [$a, $b] = [$b, $a];
                    }

                    $situations = self::situations($photo, $c, $jour, $moment, [$candidats[$a]['role'], $candidats[$b]['role']]);
                    $situation = $situations[($h >> 4) % \count($situations)];
                    $variables = [
                        'fete' => $photo['fete']['libelle'] ?? '',
                        'partenaire' => $photo['convois'][0]['partenaire'] ?? '',
                        'rival' => $photo['rival']['libelle'] ?? '',
                    ];
                    $texteA = Dialogues::replique($situation, 'a', $h >> 12, $variables);
                    $texteB = Dialogues::replique($situation, 'b', $h >> 20, $variables);
                    $nature = Dialogues::nature($situation);

                    $dites[] = ['cle' => $candidats[$a]['cle'], 'jour' => $jour, 'moment' => $moment, 'parole' => ['texte' => $texteA, 'nature' => $nature, 'avec' => $candidats[$b]['libelle']]];

                    // Deux travailleurs du même bâtiment partagent une clé, donc un journal : celui qui parle le garde.
                    if ($candidats[$a]['cle'] !== $candidats[$b]['cle']) {
                        $dites[] = ['cle' => $candidats[$b]['cle'], 'jour' => $jour, 'moment' => $moment, 'parole' => ['texte' => $texteB, 'nature' => $nature, 'avec' => $candidats[$a]['libelle']]];
                    }

                    $paroles[] = [
                        'jour' => $jour, 'moment' => $moment, 'lieu' => (string) $lieu, 'a' => $a, 'b' => $b,
                        'situation' => $situation, 'nature' => $nature,
                        'libelleA' => $candidats[$a]['libelle'], 'libelleB' => $candidats[$b]['libelle'],
                        'texteA' => $texteA, 'texteB' => $texteB,
                    ];
                    ++$duJour;
                }
            }
        }

        return ['paroles' => $paroles, 'dites' => $dites];
    }

    /**
     * Qui peut causer : qui est dehors, à ses affaires, sans scène de fait à jouer — sauf
     * la procession, que tout le monde suit en parlant.
     *
     * @param Pas $pas
     */
    private static function causant(array $pas): bool
    {
        return (null === $pas['fait'] || TypeDeFait::Fete === $pas['fait'])
            && \in_array($pas['geste'], [
                GesteDeLaVille::Poste, GesteDeLaVille::Course, GesteDeLaVille::Visite, GesteDeLaVille::Detente,
                GesteDeLaVille::Procession, GesteDeLaVille::Oisif, GesteDeLaVille::Corvee, GesteDeLaVille::Ronde,
                GesteDeLaVille::Habite,
            ], true);
    }

    /**
     * Ce dont ces deux-là peuvent parler : **seulement ce qui est vrai** cette quinzaine, plus
     * la saison — toujours vraie — et une ambiance, qui ne dit rien.
     *
     * @param Photo                $photo
     * @param array<string, mixed> $c
     * @param list<string>         $roles
     *
     * @return non-empty-list<string>
     */
    private static function situations(array $photo, array $c, int $jour, int $moment, array $roles): array
    {
        $situations = [];

        // La fête se dit le matin, à la procession : l'après-midi, elle est derrière.
        if ($jour === $c['fete'] && 1 === $moment) {
            $situations[] = 'fete';
        }

        $fievre = ($photo['epidemie'] && !(null !== $c['epidemieFinie'] && $jour >= $c['epidemieFinie']))
            || (null !== $c['epidemieEclate'] && $jour >= $c['epidemieEclate']['jour']);

        if ($fievre) {
            $situations[] = 'fievre';
        }

        if ([] !== $photo['chantiers']) {
            $situations[] = 'chantier';
        }

        if ([] !== $photo['convois']) {
            $situations[] = 'convoi';
        }

        if (null !== $photo['rival'] && (null === $c['rivalParti'] || $jour < $c['rivalParti'])) {
            $situations[] = 'rival';
        }

        if (\in_array('sans_poste', $roles, true)) {
            $situations[] = 'sans_ouvrage';
        }

        if (null !== $photo['saison']) {
            $situations[] = 'saison_'.$photo['saison'];
        }

        $situations[] = 3 === $moment ? 'ambiance_soir' : 'ambiance';

        return $situations;
    }

    /**
     * Ce que les faits disent de chaque lieu et de chaque acteur, rangé pour que
     * `pasDe()` n'ait qu'à lire.
     *
     * @param Photo      $photo
     * @param list<Fait> $faits
     * @param list<?int> $jourDe
     *
     * @return array{
     *     jours: int,
     *     chantierAvance: array<string, true>,
     *     achevement: array<string, int>,
     *     fabrication: array<string, true>,
     *     fabricationAchevee: array<string, int>,
     *     convoiRentre: list<int>,
     *     convoiParti: list<int>,
     *     fete: ?int,
     *     chefParti: array<string, int>,
     *     medjayBlesse: array<string, int>,
     *     rivalArrive: ?int,
     *     rivalParti: ?int,
     *     epidemieEclate: ?array{jour: int, malades: int},
     *     epidemieFinie: ?int,
     *     corvee: ?string,
     *     graine: int,
     *     batiments: array<string, true>,
     * }
     */
    private static function contexte(array $photo, array $faits, array $jourDe, int $jours, int $graine): array
    {
        $c = [
            'jours' => $jours,
            'graine' => $graine,
            // Où l'on peut aller : les bâtiments debout. Une trace d'avant ce champ ne
            // connaît que la Résidence, et la chorégraphie n'invente alors aucune course.
            'batiments' => array_fill_keys($photo['batiments'] ?? [TypeDeBatiment::ResidenceFamiliale->value], true),
            'chantierAvance' => [], 'achevement' => [],
            'fabrication' => [], 'fabricationAchevee' => [],
            'convoiRentre' => [], 'convoiParti' => [],
            'fete' => null,
            'chefParti' => [], 'medjayBlesse' => [],
            'rivalArrive' => null, 'rivalParti' => null,
            'epidemieEclate' => null, 'epidemieFinie' => null,
            // La corvée d'Akhèt : quand le fleuve désœuvre les paysans, ils vont
            // aux chantiers — s'il y en a (doc 05).
            'corvee' => 'akhet' === $photo['saison'] && [] !== $photo['chantiers'] ? $photo['chantiers'][0]['lieu'] : null,
        ];

        foreach ($faits as $i => $fait) {
            $lieu = $fait['lieu'] ?? '';
            $jour = $jourDe[$i];
            $acteur = $fait['acteur'] ?? '';

            match ($fait['type']) {
                TypeDeFait::ChantierAvance => $c['chantierAvance'][$lieu] = true,
                TypeDeFait::ChantierAcheve => $c['achevement'][$lieu] = (int) $jour,
                TypeDeFait::FabricationAvance => $c['fabrication'][$lieu] = true,
                TypeDeFait::FabricationAchevee => $c['fabricationAchevee'][$lieu] = (int) $jour,
                TypeDeFait::ConvoiRentre => $c['convoiRentre'][] = (int) $jour,
                TypeDeFait::ConvoiParti => $c['convoiParti'][] = (int) $jour,
                TypeDeFait::Fete => $c['fete'] = $jour,
                TypeDeFait::ChefParti => $c['chefParti'][$acteur] = (int) $jour,
                TypeDeFait::MedjayBlesse => $c['medjayBlesse'][$acteur] = (int) $jour,
                TypeDeFait::RivalArrive => $c['rivalArrive'] = $jour,
                TypeDeFait::RivalParti => $c['rivalParti'] = $jour,
                TypeDeFait::EpidemieEclate => $c['epidemieEclate'] = ['jour' => (int) $jour, 'malades' => $fait['quantite'] ?? 0],
                TypeDeFait::EpidemieFinie => $c['epidemieFinie'] = $jour,
                default => null,
            };
        }

        return $c;
    }

    /**
     * Où est cette silhouette, et que fait-elle, ce jour-là à ce moment.
     *
     * @param array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool} $candidat
     * @param array<string, mixed>                                                            $c
     *
     * @return Pas
     */
    private static function pasDe(array $candidat, int $jour, int $moment, array $c): array
    {
        $role = RoleDeFigure::from($candidat['role']);
        $lieu = $candidat['lieu'];
        $cle = $candidat['cle'];
        $enJournee = \in_array($moment, [1, 2], true);

        // Un jour de fête, le matin, on suit la procession — sauf si l'on est
        // alité. Le Temple, ou la Résidence s'il n'est pas bâti (repli du placement).
        $procession = $jour === $c['fete'] && 1 === $moment
            && !$candidat['couchee']
            && \in_array($role, [RoleDeFigure::ChefDeFamille, RoleDeFigure::Chef, RoleDeFigure::Travailleur, RoleDeFigure::Foyer], true);

        if ($procession) {
            return self::pas(TypeDeBatiment::Temple->value, GesteDeLaVille::Procession, TypeDeFait::Fete);
        }

        return match ($role) {
            RoleDeFigure::ChefDeFamille => match (true) {
                1 === $jour && 1 === $moment => self::pas($lieu, GesteDeLaVille::Paie),
                // L'après-midi, la famille passe parfois au Temple : une visite, pas une offrande.
                2 === $moment && self::existe($c, TypeDeBatiment::Temple) && self::tirage($c, $cle, $jour, $moment) < 30 => self::pas(TypeDeBatiment::Temple->value, GesteDeLaVille::Visite),
                $enJournee => self::pas($lieu, GesteDeLaVille::Poste),
                default => self::pas($lieu, GesteDeLaVille::Chez),
            },
            RoleDeFigure::Chef => self::pasDuChef($candidat, $jour, $moment, $enJournee, $c),
            RoleDeFigure::Travailleur => $enJournee
                ? self::pasDeTravail((string) $lieu, $cle, $jour, $moment, $c)
                : self::pasDeLAube($cle, $jour, $moment, $c),
            RoleDeFigure::SansPoste => match (true) {
                !$enJournee => self::pasDeLAube($cle, $jour, $moment, $c),
                null !== $c['corvee'] => self::pas($c['corvee'], GesteDeLaVille::Corvee),
                default => self::pas($lieu, GesteDeLaVille::Oisif),
            },
            RoleDeFigure::Medjay => self::pasDuMedjay($candidat, $jour, $moment, $enJournee, $c),
            RoleDeFigure::Rival => self::pasDuRival($jour, $enJournee, $lieu, $c),
            RoleDeFigure::Foyer => self::pasDuFoyer($candidat, $jour, $moment, $enJournee, $c),
        };
    }

    /**
     * @param array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool} $candidat
     * @param array<string, mixed>                                                            $c
     *
     * @return Pas
     */
    private static function pasDuChef(array $candidat, int $jour, int $moment, bool $enJournee, array $c): array
    {
        $parti = $c['chefParti'][$candidat['cle']] ?? null;

        // Le jour de son départ, il passe sa matinée à son poste puis s'en va ;
        // ensuite, il n'est plus là.
        if (null !== $parti) {
            if ($jour === $parti && 2 === $moment) {
                return self::pas(null, GesteDeLaVille::Part, TypeDeFait::ChefParti);
            }

            if ($jour > $parti || ($jour === $parti && $moment >= 3)) {
                return self::pas(null, GesteDeLaVille::Chez);
            }
        }

        return $enJournee
            ? self::pasDeTravail((string) $candidat['lieu'], $candidat['cle'], $jour, $moment, $c)
            : self::pasDeLAube($candidat['cle'], $jour, $moment, $c);
    }

    /**
     * Le geste de travail dans un bâtiment : un fait de la quinzaine y met sa
     * scène ; sans fait, c'est la routine du métier.
     *
     * @param array<string, mixed> $c
     *
     * @return Pas
     */
    private static function pasDeTravail(string $lieu, string $cle, int $jour, int $moment, array $c): array
    {
        $entrepot = \in_array($lieu, [TypeDeBatiment::Entrepot->value, TypeDeBatiment::Port->value], true);

        if ($entrepot && 1 === $moment && \in_array($jour, $c['convoiRentre'], true)) {
            return self::pas($lieu, GesteDeLaVille::ConvoiRentre, TypeDeFait::ConvoiRentre);
        }

        if ($entrepot && 2 === $moment && \in_array($jour, $c['convoiParti'], true)) {
            return self::pas($lieu, GesteDeLaVille::ConvoiParti, TypeDeFait::ConvoiParti);
        }

        if (isset($c['achevement'][$lieu]) && $jour === $c['achevement'][$lieu]) {
            return self::pas($lieu, GesteDeLaVille::Acheve, TypeDeFait::ChantierAcheve);
        }

        if (isset($c['chantierAvance'][$lieu])) {
            return self::pas($lieu, GesteDeLaVille::Construit, TypeDeFait::ChantierAvance);
        }

        if (isset($c['fabricationAchevee'][$lieu]) && $jour === $c['fabricationAchevee'][$lieu]) {
            return self::pas($lieu, GesteDeLaVille::Fabrique, TypeDeFait::FabricationAchevee);
        }

        if (isset($c['fabrication'][$lieu]) || isset($c['fabricationAchevee'][$lieu])) {
            return self::pas($lieu, GesteDeLaVille::Fabrique, TypeDeFait::FabricationAvance);
        }

        // Sans fait, la routine du métier — avec ses détours : l'après-midi, une course au
        // Marché, ou le scribe qui porte un rouleau au Temple. **Une variété de mise en
        // scène, sans valeur de règle** : rien dans le jeu ne dit qu'on y va, et le journal
        // le dit « routine ».
        if (2 === $moment && TypeDeBatiment::Marche->value !== $lieu && self::existe($c, TypeDeBatiment::Marche) && self::tirage($c, $cle, $jour, $moment) < 25) {
            return self::pas(TypeDeBatiment::Marche->value, GesteDeLaVille::Course);
        }

        if (2 === $moment && TypeDeBatiment::MaisonDesScribes->value === $lieu && self::existe($c, TypeDeBatiment::Temple) && self::tirage($c, $cle, $jour, $moment) < 40) {
            return self::pas(TypeDeBatiment::Temple->value, GesteDeLaVille::Visite);
        }

        return self::pas($lieu, GesteDeLaVille::Poste);
    }

    /**
     * L'aube et le soir : chez soi — et, le soir, parfois un détour par l'Auberge, quand il y
     * en a une. Une variété de mise en scène, sans valeur de règle.
     *
     * @param array<string, mixed> $c
     *
     * @return Pas
     */
    private static function pasDeLAube(string $cle, int $jour, int $moment, array $c): array
    {
        if (3 === $moment && self::existe($c, TypeDeBatiment::Auberge) && self::tirage($c, $cle, $jour, $moment) < 20) {
            return self::pas(TypeDeBatiment::Auberge->value, GesteDeLaVille::Detente);
        }

        return self::pas(self::CHEZ_SOI, GesteDeLaVille::Chez);
    }

    /**
     * Un tirage de 0 à 99, stable : même partie, même silhouette, même jour, même moment,
     * même nombre. **Jamais le hasard global** — le film doit être identique à chaque
     * lecture.
     *
     * @param array<string, mixed> $c
     */
    private static function tirage(array $c, string $cle, int $jour, int $moment): int
    {
        return crc32((int) $c['graine'].'|'.$cle.'|'.$jour.'|'.$moment) % 100;
    }

    /**
     * Le bâtiment est-il debout ? On ne va pas où il n'y a rien.
     *
     * @param array<string, mixed> $c
     */
    private static function existe(array $c, TypeDeBatiment $type): bool
    {
        return isset($c['batiments'][$type->value]);
    }

    /**
     * @param array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool} $candidat
     * @param array<string, mixed>                                                            $c
     *
     * @return Pas
     */
    private static function pasDuMedjay(array $candidat, int $jour, int $moment, bool $enJournee, array $c): array
    {
        $blesse = $c['medjayBlesse'][$candidat['cle']] ?? null;

        // Blessé avant la quinzaine : il se repose tout du long. Blessé pendant :
        // debout jusqu'à l'après-midi du jour, couché ensuite.
        if ($candidat['couchee'] || (null !== $blesse && ($jour > $blesse || ($jour === $blesse && $moment >= 2)))) {
            return self::pas(
                TypeDeBatiment::QuartierDHabitation->value,
                GesteDeLaVille::Repos,
                null !== $blesse && $jour === $blesse && 2 === $moment ? TypeDeFait::MedjayBlesse : null,
                true,
            );
        }

        // L'après-midi, la ronde passe parfois par la place du Marché.
        if (2 === $moment && self::existe($c, TypeDeBatiment::Marche) && self::tirage($c, $candidat['cle'], $jour, $moment) < 40) {
            return self::pas(TypeDeBatiment::Marche->value, GesteDeLaVille::Ronde);
        }

        return self::pas(TypeDeBatiment::Caserne->value, $enJournee ? GesteDeLaVille::Ronde : GesteDeLaVille::Veille);
    }

    /**
     * @param array<string, mixed> $c
     *
     * @return Pas
     */
    private static function pasDuRival(int $jour, bool $enJournee, ?string $lieu, array $c): array
    {
        $arrive = $c['rivalArrive'] ?? 1;
        $parti = $c['rivalParti'];

        if (!$enJournee || $jour < $arrive || (null !== $parti && $jour >= $parti)) {
            return self::pas(null, GesteDeLaVille::Rode);
        }

        return self::pas($lieu, GesteDeLaVille::Rode, $jour === $c['rivalArrive'] ? TypeDeFait::RivalArrive : null);
    }

    /**
     * Une maison : on y est toute la journée, couché si la fièvre y est. La
     * fièvre qui éclate pendant la quinzaine couche les premières maisons
     * montrées, autant que la ville compte de malades — une reconstitution, pas
     * un état.
     *
     * @param array{cle: string, role: string, libelle: string, lieu: ?string, couchee: bool} $candidat
     * @param array<string, mixed>                                                            $c
     *
     * @return Pas
     */
    private static function pasDuFoyer(array $candidat, int $jour, int $moment, bool $enJournee, array $c): array
    {
        $couchee = $candidat['couchee'];
        $rang = (int) substr($candidat['cle'], 7);

        if (!$couchee && null !== $c['epidemieEclate'] && $jour >= $c['epidemieEclate']['jour'] && $rang <= $c['epidemieEclate']['malades']) {
            $couchee = true;
        }

        if ($couchee && null !== $c['epidemieFinie'] && $jour >= $c['epidemieFinie']) {
            $couchee = false;
        }

        // Qui n'est pas alité va parfois au Marché dans la journée.
        if (!$couchee && $enJournee && self::existe($c, TypeDeBatiment::Marche) && self::tirage($c, $candidat['cle'], $jour, $moment) < 25) {
            return self::pas(TypeDeBatiment::Marche->value, GesteDeLaVille::Course);
        }

        return self::pas(
            self::CHEZ_SOI,
            $couchee ? GesteDeLaVille::Repos : GesteDeLaVille::Habite,
            null !== $c['epidemieEclate'] && $jour === $c['epidemieEclate']['jour'] && $couchee ? TypeDeFait::EpidemieEclate : null,
            $couchee,
        );
    }

    /**
     * @return Pas
     */
    private static function pas(?string $lieu, GesteDeLaVille $geste, ?TypeDeFait $fait = null, bool $couchee = false): array
    {
        return ['lieu' => $lieu, 'geste' => $geste, 'fait' => $fait, 'couchee' => $couchee, 'parole' => null];
    }
}
