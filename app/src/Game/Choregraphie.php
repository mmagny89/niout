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
 * @phpstan-type Pas array{lieu: ?string, geste: GesteDeLaVille, fait: ?TypeDeFait, couchee: bool}
 */
final class Choregraphie
{
    /**
     * Où l'on rentre le soir : le Quartier, ou la Résidence pour la famille.
     */
    private const string CHEZ_SOI = 'quartier_habitation';

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
     * }
     */
    public static function pour(array $photo, array $faits, int $graine): array
    {
        $jours = $photo['jours'];
        $jourDe = self::joursDesFaits($faits, $jours, $graine);
        $contexte = self::contexte($photo, $faits, $jourDe, $jours);

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

                $cle = ($pas['lieu'] ?? '-').'|'.$pas['geste']->value.'|'.(int) $pas['couchee'];
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
                $lignes[] = \sprintf('%s%s (%s)', $geste, $ou, $quand);
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
     * }
     */
    private static function contexte(array $photo, array $faits, array $jourDe, int $jours): array
    {
        $c = [
            'jours' => $jours,
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
                $enJournee => self::pas($lieu, GesteDeLaVille::Poste),
                default => self::pas($lieu, GesteDeLaVille::Chez),
            },
            RoleDeFigure::Chef => self::pasDuChef($candidat, $jour, $moment, $enJournee, $c),
            RoleDeFigure::Travailleur => $enJournee
                ? self::pasDeTravail((string) $lieu, $jour, $moment, $c)
                : self::pas(self::CHEZ_SOI, GesteDeLaVille::Chez),
            RoleDeFigure::SansPoste => match (true) {
                !$enJournee => self::pas(self::CHEZ_SOI, GesteDeLaVille::Chez),
                null !== $c['corvee'] => self::pas($c['corvee'], GesteDeLaVille::Corvee),
                default => self::pas($lieu, GesteDeLaVille::Oisif),
            },
            RoleDeFigure::Medjay => self::pasDuMedjay($candidat, $jour, $moment, $enJournee, $c),
            RoleDeFigure::Rival => self::pasDuRival($jour, $enJournee, $lieu, $c),
            RoleDeFigure::Foyer => self::pasDuFoyer($candidat, $jour, $c),
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
            ? self::pasDeTravail((string) $candidat['lieu'], $jour, $moment, $c)
            : self::pas(self::CHEZ_SOI, GesteDeLaVille::Chez);
    }

    /**
     * Le geste de travail dans un bâtiment : un fait de la quinzaine y met sa
     * scène ; sans fait, c'est la routine du métier.
     *
     * @param array<string, mixed> $c
     *
     * @return Pas
     */
    private static function pasDeTravail(string $lieu, int $jour, int $moment, array $c): array
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

        return self::pas($lieu, GesteDeLaVille::Poste);
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
    private static function pasDuFoyer(array $candidat, int $jour, array $c): array
    {
        $couchee = $candidat['couchee'];
        $rang = (int) substr($candidat['cle'], 7);

        if (!$couchee && null !== $c['epidemieEclate'] && $jour >= $c['epidemieEclate']['jour'] && $rang <= $c['epidemieEclate']['malades']) {
            $couchee = true;
        }

        if ($couchee && null !== $c['epidemieFinie'] && $jour >= $c['epidemieFinie']) {
            $couchee = false;
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
        return ['lieu' => $lieu, 'geste' => $geste, 'fait' => $fait, 'couchee' => $couchee];
    }
}
