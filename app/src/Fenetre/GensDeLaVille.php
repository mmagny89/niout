<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Game\Choregraphie;
use App\Game\Commerce;
use App\Game\Effectifs;
use App\Game\EmplacementsDeLaVille;
use App\Game\Maisonnees;
use App\Game\MomentDeLaJournee;
use App\Game\PhotoDeLaVille;
use App\Game\Prenoms;
use App\Game\RoleDeFigure;
use App\Game\TraceDeQuinzaine;
use App\Game\TypeDeBatiment;
use App\Game\VisiteurDePassage;

/**
 * Les gens de la ville, posés sur la ville vue d'en haut
 * (`docs/plan-pnj-ville-vivante.md` § 15 et suivants, lots V1 et V2).
 *
 * **Une représentation, pas un état** — comme `Maisonnees`, dont ce service est
 * le prolongement : rien n'est tiré au sort, et la même ville donne toujours les
 * mêmes figures. Un rechargement ne recompose pas la ville sous les yeux du
 * joueur.
 *
 * **Chaque silhouette dit une chose vraie.** Un chef en poste se voit à son
 * bâtiment, un Medjaÿ blessé est couché, un bâtiment à l'arrêt n'a personne, des
 * bras sans poste traînent au Marché. Ce que le jeu ne tient pas — il ne compte
 * que des nombres d'habitants, pas des individus — est montré comme
 * **représentatif**, et dit tel. Le service **lit** les règles (`PhotoDeLaVille`),
 * il n'en pose aucune.
 *
 * **Deux façons de voir la même ville.** *Maintenant* : la photographie de la ville
 * telle qu'elle est (V1). *Un jour de la quinzaine passée* : la ville telle que la
 * chorégraphie la reconstitue ce jour-là, à ce moment (`Choregraphie`, V2) — une
 * reconstitution, dont le jour n'a aucune valeur de règle. Dans les deux cas, ce
 * service ne fait que **poser** des silhouettes ; ce qui les met là vient de la
 * photographie ou du film.
 *
 * **Les désignés d'abord, la foule ensuite** : si le plafond est atteint, on coupe
 * la foule, jamais un chef.
 *
 * La liste (`lieux`) est la **voie équivalente** : complète, sans plafond, et
 * lisible sans image — c'est elle qui sert le téléphone et les lecteurs
 * d'écran, où les silhouettes ne sont que du décor.
 */
final readonly class GensDeLaVille
{
    /**
     * Combien de silhouettes au plus. **Valeur inventée**, à mesurer au
     * navigateur : au-delà, la ville n'est plus qu'une foule.
     */
    public const int PLAFOND_DE_FIGURES = PhotoDeLaVille::PLAFOND_DE_FIGURES;

    public const int TRAVAILLEURS_PAR_BATIMENT = PhotoDeLaVille::TRAVAILLEURS_PAR_BATIMENT;

    public const int MAISONS_MONTREES = PhotoDeLaVille::MAISONS_MONTREES;

    /**
     * Combien de silhouettes un même lot accueille avant d'être plein.
     */
    private const int PAR_LOT_AU_PLUS = 12;

    /**
     * La taille d'une silhouette debout, en pixels du visuel de 1376 × 768 :
     * une personne est deux fois plus haute que large. Couchée, c'est
     * l'inverse.
     */
    public const int LARGEUR_DEBOUT = 30;

    public const int HAUTEUR_DEBOUT = 60;

    /**
     * Où se tient la n-ième silhouette d'un lot : un décalage **devant** le
     * bâtiment, en fraction de la largeur du lot — `[vers la droite, vers le
     * bas]`. Un éventail, pour que personne ne se superpose.
     *
     * @var list<array{float, float}>
     */
    public const array ANCRAGES = [
        [0.00, 0.17], [-0.20, 0.12], [0.20, 0.12], [-0.10, 0.24],
        [0.10, 0.24], [-0.30, 0.06], [0.30, 0.06], [0.00, 0.31],
        [-0.40, 0.14], [0.40, 0.14], [-0.22, 0.34], [0.22, 0.34],
    ];

    public function __construct(
        private Commerce $commerce,
        private PhotoDeLaVille $photo,
        private TraceDeQuinzaine $trace,
        private Prenoms $prenoms,
    ) {
    }

    /**
     * @param int|null $jour   le jour de la quinzaine passée à reconstituer, ou null pour la ville d'à présent
     * @param int      $moment le moment de ce jour (`MomentDeLaJournee`), ignoré sans jour
     *
     * @return array{
     *     figures: list<FigureDeLaVille>,
     *     lieux: list<array{titre: string, lieu: ?TypeDeBatiment, lignes: list<array{texte: string, cle: ?string}>}>,
     *     auxChamps: int,
     *     jour: ?int,
     *     moment: int,
     *     momentLibelle: string,
     * }
     */
    public function pour(GameSave $partie, ?int $jour = null, int $moment = 1): array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();
        $photo = $this->photo->prendre($partie);
        $postes = Effectifs::repartir($ville, $cycle);
        $maisons = Maisonnees::repartir($ville);
        $moment = MomentDeLaJournee::tryFrom($moment)->value ?? MomentDeLaJournee::Matin->value;

        $reconstitution = null === $jour ? null : $this->reconstitution($partie);
        $candidats = [];

        if (null !== $jour && null !== $reconstitution && $jour >= 1 && $jour <= $reconstitution['film']['jours']) {
            // Un jour de la quinzaine passée : les mêmes gens que ce matin-là,
            // placés où la chorégraphie les met.
            foreach ($reconstitution['photo']['candidats'] as $candidat) {
                $pas = $reconstitution['film']['pas'][$candidat['cle']][$jour][$moment] ?? null;

                if (null === $pas || null === $pas['lieu']) {
                    continue;
                }

                $candidats[] = [
                    'cle' => $candidat['cle'],
                    'role' => RoleDeFigure::from($candidat['role']),
                    'libelle' => \sprintf('%s : %s', $candidat['libelle'], mb_strtolower($pas['geste']->libelle())),
                    'lieu' => TypeDeBatiment::tryFrom($pas['lieu']),
                    'couchee' => $pas['couchee'],
                ];
            }
        } else {
            $jour = null;

            foreach ($photo['candidats'] as $candidat) {
                $candidats[] = [
                    'cle' => $candidat['cle'],
                    'role' => RoleDeFigure::from($candidat['role']),
                    'libelle' => $candidat['libelle'],
                    'lieu' => null === $candidat['lieu'] ? null : TypeDeBatiment::tryFrom($candidat['lieu']),
                    'couchee' => $candidat['couchee'],
                ];
            }
        }

        // Les silhouettes de passage ne se voient que dans la ville d'à présent, et s'ajoutent au plafond :
        // elles dépendent d'énigmes jouables maintenant, pas d'une quinzaine rejouée.
        $passage = [];

        if (null === $jour) {
            foreach (VisiteurDePassage::pour($ville) as $visiteur) {
                $passage[] = [
                    'cle' => $visiteur['cle'],
                    'role' => $visiteur['role'],
                    'libelle' => $visiteur['libelle'],
                    'lieu' => $visiteur['lieu'],
                    'couchee' => false,
                ];
            }
        }

        return [
            'figures' => $this->poser($partie, [...\array_slice($candidats, 0, self::PLAFOND_DE_FIGURES), ...$passage]),
            'lieux' => $this->lieux($partie, $postes, $maisons, Effectifs::bilan($ville, $cycle)['oisifs']),
            'auxChamps' => Effectifs::brasPourLesChamps($ville, $cycle),
            'jour' => $jour,
            'moment' => $moment,
            'momentLibelle' => MomentDeLaJournee::from($moment)->libelle(),
        ];
    }

    /**
     * La quinzaine qu'on peut rejouer : ses jours, ses fêtes, ce qui s'y est
     * passé jour par jour. Null si la trace n'en garde aucune — une partie qui
     * n'a pas encore vécu de quinzaine, ou dont le jeu précède la trace.
     *
     * @return array{
     *     cycle: int,
     *     jours: int,
     *     jourDeFete: ?int,
     *     fete: ?string,
     *     evenements: array<int, list<string>>,
     *     enCours: list<string>,
     *     conversations: array<int, list<array{moment: string, lieu: string, nature: string, lignes: list<array{qui: string, texte: string}>}>>,
     * }|null
     */
    public function frise(GameSave $partie): ?array
    {
        $reconstitution = $this->reconstitution($partie);

        if (null === $reconstitution) {
            return null;
        }

        // Les conversations du jour, en texte : **le même que la bulle**, pour qui ne voit pas l'image.
        $conversations = [];

        /** @var list<array{jour: int, moment: int, lieu: string, a: int, b: int, situation: string, nature: string, libelleA: string, libelleB: string, texteA: string, texteB: string}> $paroles */
        $paroles = $reconstitution['film']['paroles'];

        foreach ($paroles as $parole) {
            $conversations[$parole['jour']][] = [
                'moment' => MomentDeLaJournee::from($parole['moment'])->libelle(),
                'lieu' => TypeDeBatiment::tryFrom($parole['lieu'])?->libelle() ?? $parole['lieu'],
                'nature' => $parole['nature'],
                'lignes' => [
                    ['qui' => $parole['libelleA'], 'texte' => $parole['texteA']],
                    ['qui' => $parole['libelleB'], 'texte' => $parole['texteB']],
                ],
            ];
        }

        return [
            'cycle' => $reconstitution['cycle'],
            'conversations' => $conversations,
            'jours' => $reconstitution['film']['jours'],
            'jourDeFete' => $reconstitution['film']['jourDeFete'],
            'fete' => $reconstitution['photo']['fete']['libelle'] ?? null,
            'evenements' => $reconstitution['film']['evenements'],
            'enCours' => $reconstitution['film']['enCours'],
        ];
    }

    /**
     * La fiche d'une figure, ou null si la clé ne désigne rien **dans cette
     * ville** : une clé forgée vers un chef d'une autre partie ne rend rien.
     *
     * Elle porte aussi **le journal de la quinzaine passée**, jour par jour, quand
     * la trace en garde une. Une figure qui n'est plus en ville — un chef parti —
     * garde une fiche minimale tant que la trace la connaît.
     *
     * @return array{
     *     titre: string,
     *     sousTitre: string,
     *     lignes: list<array{libelle: string, valeur: string}>,
     *     lien: ?array{libelle: string, onglet: string},
     *     representatif: bool, nommer?: ?array{cle: string, propositions: list<string>},
     *     journal: list<array{jour: int, lignes: list<string>, fait: bool}>,
     * }|null
     */
    public function fiche(GameSave $partie, string $cle): ?array
    {
        $fiche = $this->ficheDuPresent($partie, $cle);
        $reconstitution = $this->reconstitution($partie);
        $journal = null !== $reconstitution && isset($reconstitution['film']['pas'][$cle])
            ? Choregraphie::journal($reconstitution['film']['pas'][$cle])
            : [];

        if (null === $fiche) {
            // Une figure qui n'est plus là, mais que la quinzaine passée a vue.
            if (null === $reconstitution || [] === $journal) {
                return null;
            }

            $fiche = [
                'titre' => $this->libelleDansLaPhoto($reconstitution['photo'], $cle) ?? $cle,
                'sousTitre' => 'N\'est plus en ville',
                'lignes' => [],
                'lien' => null,
                'representatif' => 1 === preg_match('/^(maison|poste|enfant|ancien|macon)-/', $cle) || 'sans-poste' === $cle,
            ];
        }

        return $fiche + ['journal' => $journal];
    }

    /**
     * @param array<string, mixed> $photo
     */
    private function libelleDansLaPhoto(array $photo, string $cle): ?string
    {
        foreach ($photo['candidats'] as $candidat) {
            if ($candidat['cle'] === $cle) {
                return (string) $candidat['libelle'];
            }
        }

        return null;
    }

    /**
     * La reconstitution de la quinzaine passée — la photographie du début, et le
     * film qu'en tire la chorégraphie avec les faits — ou null.
     *
     * @return array{cycle: int, photo: array<string, mixed>, film: array<string, mixed>}|null
     */
    public function reconstitution(GameSave $partie): ?array
    {
        $trace = $this->trace->derniere($partie);

        if (null === $trace) {
            return null;
        }

        return [
            'cycle' => $trace['cycle'],
            'photo' => $trace['photo'],
            'film' => Choregraphie::pour($trace['photo'], $trace['faits'], (int) $partie->getId()),
        ];
    }

    /**
     * @return array{
     *     titre: string,
     *     sousTitre: string,
     *     lignes: list<array{libelle: string, valeur: string}>,
     *     lien: ?array{libelle: string, onglet: string},
     *     representatif: bool, nommer?: ?array{cle: string, propositions: list<string>},
     * }|null
     */
    private function ficheDuPresent(GameSave $partie, string $cle): ?array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();

        if ('famille' === $cle) {
            $famille = $partie->getFamille();
            $lignes = [['libelle' => 'Génération', 'valeur' => (string) $famille->getGeneration()]];

            if (null !== $famille->getTraitDeLignee()) {
                $lignes[] = ['libelle' => 'Trait de lignée', 'valeur' => $famille->getTraitDeLignee()->libelle()];
            }

            return [
                'titre' => PhotoDeLaVille::nomDuChefDeFamille($partie),
                'sousTitre' => \sprintf('Chef de la famille %s', $famille->getNom()),
                'lignes' => $lignes,
                'lien' => ['libelle' => 'Ouvrir la Résidence familiale', 'onglet' => TypeDeBatiment::ResidenceFamiliale->value],
                'representatif' => false,
            ];
        }

        if (str_starts_with($cle, 'chef-')) {
            foreach (PhotoDeLaVille::chefsEnPoste($partie) as $chef) {
                if ('chef-'.$chef->getId() === $cle) {
                    return $this->ficheDuChef($partie, $chef, $cycle);
                }
            }

            return null;
        }

        if (str_starts_with($cle, 'medjay-')) {
            foreach ($ville->getMedjays() as $medjay) {
                if ('medjay-'.$medjay->getId() === $cle) {
                    return $this->ficheDuMedjay($partie, $medjay, $cycle);
                }
            }

            return null;
        }

        if (str_starts_with($cle, 'macon-')) {
            foreach ($ville->getChantiers() as $chantier) {
                if ('macon-'.$chantier->getType()->value === $cle) {
                    return [
                        'titre' => 'Maçons du chantier',
                        'sousTitre' => \sprintf('Au chantier du %s', mb_strtolower($chantier->getType()->libelle())),
                        'lignes' => [['libelle' => 'Reste à bâtir', 'valeur' => \sprintf('%d quinzaine%s', $chantier->cyclesRestants(), $chantier->cyclesRestants() > 1 ? 's' : '')]],
                        'lien' => ['libelle' => 'Voir les chantiers', 'onglet' => TypeDeBatiment::ResidenceFamiliale->value],
                        // Le jeu compte l'avancement d'un chantier, pas ses ouvriers un à un.
                        'representatif' => true,
                    ];
                }
            }

            return null;
        }

        foreach (VisiteurDePassage::pour($ville) as $visiteur) {
            if ($visiteur['cle'] === $cle) {
                return [
                    'titre' => $visiteur['libelle'],
                    'sousTitre' => VisiteurDePassage::invitation($visiteur['role']),
                    'lignes' => [['libelle' => 'À l\'écoute', 'valeur' => \sprintf('%d question%s en suspens', $visiteur['nombre'], $visiteur['nombre'] > 1 ? 's' : '')]],
                    'lien' => ['libelle' => \sprintf('Aller à %s', $visiteur['lieu']->libelle()), 'onglet' => $visiteur['lieu']->value],
                    'representatif' => true,
                ];
            }
        }

        if ('rival' === $cle) {
            $rival = $ville->getRival();

            if (null === $rival) {
                return null;
            }

            $partenaire = $this->commerce->partenaireDe($partie, $rival->getPartenaire());

            return [
                'titre' => $rival->getNom(),
                'sousTitre' => 'Marchand rival',
                'lignes' => [
                    ['libelle' => 'Route disputée', 'valeur' => $partenaire->nom ?? $rival->getPartenaire()],
                    ['libelle' => 'Ce qu\'il prend', 'valeur' => \sprintf('%d %% du volume de chaque convoi', $rival->getMalusEnCentiemes())],
                    ['libelle' => 'Il se lassera', 'valeur' => \sprintf('dans %d quinzaines au plus', $rival->getQuinzainesRestantes())],
                ],
                'lien' => ['libelle' => 'Voir les routes et les issues', 'onglet' => TypeDeBatiment::Entrepot->value],
                'representatif' => false,
            ];
        }

        if (1 === preg_match('/^maison-(\d+)$/', $cle, $m)) {
            $maisons = Maisonnees::repartir($ville);
            $rang = (int) $m[1] - 1;

            if (!isset($maisons[$rang])) {
                return null;
            }

            return [
                'titre' => \sprintf('Maison %d', $rang + 1),
                'sousTitre' => 'Un foyer du Quartier',
                'lignes' => [['libelle' => 'Qui y vit', 'valeur' => Maisonnees::decrire($maisons[$rang])]],
                'lien' => ['libelle' => 'Ouvrir le Quartier d\'habitation', 'onglet' => TypeDeBatiment::QuartierDHabitation->value],
                'representatif' => true,
            ];
        }

        // Un enfant ou un ancien d'une maison : représentatif, comme elle.
        if (1 === preg_match('/^(enfant|ancien)-(\d+)$/', $cle, $m)) {
            $maisons = Maisonnees::repartir($ville);
            $rang = (int) $m[2] - 1;
            $age = 'enfant' === $m[1] ? Maisonnees::ENFANT : Maisonnees::ANCIEN;

            if (!isset($maisons[$rang]) || !\in_array($age, $maisons[$rang], true)) {
                return null;
            }

            return [
                'titre' => 'enfant' === $m[1] ? 'Un enfant du Quartier' : 'Un ancien du Quartier',
                'sousTitre' => \sprintf('De la maison %d', $rang + 1),
                'lignes' => [['libelle' => 'Qui y vit', 'valeur' => Maisonnees::decrire($maisons[$rang])]],
                'lien' => ['libelle' => 'Ouvrir le Quartier d\'habitation', 'onglet' => TypeDeBatiment::QuartierDHabitation->value],
                'representatif' => true,
            ];
        }

        if (str_starts_with($cle, 'poste-')) {
            $type = TypeDeBatiment::tryFrom(substr($cle, 6));
            $poste = null === $type ? null : Effectifs::repartir($ville, $cycle)[$type->value] ?? null;

            if (null === $type || null === $poste) {
                return null;
            }

            $lignes = [
                ['libelle' => 'Travailleurs en poste', 'valeur' => \sprintf('%d sur %d requis', $poste['affectes'], $poste['requis'])],
                ['libelle' => 'Rendement', 'valeur' => \sprintf('%d %%', $poste['rendement'])],
            ];

            if (0 === $poste['affectes']) {
                $lignes[] = ['libelle' => 'État', 'valeur' => 'À l\'arrêt : personne n\'y travaille'];
            }

            return [
                'titre' => $type->libelle(),
                'sousTitre' => 'Ceux qui y travaillent',
                'lignes' => $lignes,
                'lien' => ['libelle' => \sprintf('Ouvrir %s', $type->libelle()), 'onglet' => $type->value],
                'representatif' => true,
            ];
        }

        if ('sans-poste' === $cle) {
            return [
                'titre' => 'Bras sans poste',
                'sousTitre' => 'Des gens sans ouvrage',
                'lignes' => [['libelle' => 'Combien', 'valeur' => (string) Effectifs::bilan($ville, $cycle)['oisifs']]],
                'lien' => ['libelle' => 'Ouvrir la Résidence familiale', 'onglet' => TypeDeBatiment::ResidenceFamiliale->value],
                'representatif' => true,
            ];
        }

        if ('champs' === $cle) {
            return [
                'titre' => 'Aux champs',
                'sousTitre' => 'Hors de la ville',
                'lignes' => [['libelle' => 'Paysans', 'valeur' => (string) Effectifs::brasPourLesChamps($ville, $cycle)]],
                'lien' => null,
                'representatif' => true,
            ];
        }

        return null;
    }

    /**
     * @return array{titre: string, sousTitre: string, lignes: list<array{libelle: string, valeur: string}>, lien: ?array{libelle: string, onglet: string}, representatif: bool, nommer?: ?array{cle: string, propositions: list<string>}}
     */
    private function ficheDuChef(GameSave $partie, Employee $chef, int $cycle): array
    {
        $lignes = [];

        if (null !== $chef->getSpecialite()) {
            $lignes[] = ['libelle' => 'Spécialité', 'valeur' => $chef->getSpecialite()->libelle()];
        }

        // Chiffré en interne, qualitatif à l'affichage : des étoiles, jamais la
        // compétence (doc 03).
        $lignes[] = [
            'libelle' => 'Compétence',
            'valeur' => \sprintf('%d étoile%s sur 5', $chef->etoiles(), $chef->etoiles() > 1 ? 's' : ''),
        ];

        $traits = array_map(static fn ($trait): string => $trait->libelle(), $chef->traits());
        $lignes[] = ['libelle' => 'Traits', 'valeur' => [] === $traits ? 'Aucun' : implode(', ', $traits)];
        $lignes[] = [
            'libelle' => 'Maisonnée',
            'valeur' => \sprintf('%d personnes installées avec lui', $chef->getActifsAmenes() + $chef->getInactifsAmenes()),
        ];
        $lignes[] = [
            'libelle' => 'Poste',
            'valeur' => $chef->estEnPoste($cycle) ? 'À son poste' : 'Prend son poste à la quinzaine suivante',
        ];

        return [
            'titre' => $chef->getPrenom() ?? \sprintf('Le chef du %s', mb_strtolower($chef->getType()->libelle())),
            'sousTitre' => \sprintf('Chef — %s', $chef->getType()->libelle()),
            'lignes' => $lignes,
            'lien' => ['libelle' => \sprintf('Ouvrir %s', $chef->getType()->libelle()), 'onglet' => $chef->getType()->value],
            'representatif' => false,
            // Un chef sans prénom peut en recevoir un : à son choix, ou parmi ceux que l'Égypte ancienne portait.
            'nommer' => null === $chef->getPrenom() ? ['cle' => 'chef-'.$chef->getId(), 'propositions' => $this->prenoms->propositions($partie)] : null,
        ];
    }

    /**
     * @return array{titre: string, sousTitre: string, lignes: list<array{libelle: string, valeur: string}>, lien: ?array{libelle: string, onglet: string}, representatif: bool, nommer?: ?array{cle: string, propositions: list<string>}}
     */
    private function ficheDuMedjay(GameSave $partie, Medjay $medjay, int $cycle): array
    {
        $lignes = [['libelle' => 'Force', 'valeur' => (string) $medjay->force()]];

        if ($medjay->getExperience() > 0) {
            $lignes[] = ['libelle' => 'Expérience', 'valeur' => \sprintf('%d %%', $medjay->getExperience())];
        }

        $lignes[] = [
            'libelle' => 'État',
            'valeur' => $medjay->estDisponible($cycle)
                ? 'Disponible'
                : \sprintf('Blessé, de retour à la quinzaine %d', (int) $medjay->getRetabliAuCycle()),
        ];

        return [
            'titre' => $medjay->getPrenom() ?? $medjay->getSpecialisation()->libelle(),
            'sousTitre' => \sprintf('Medjaÿ — %s', $medjay->getSpecialisation()->libelle()),
            'lignes' => $lignes,
            'lien' => ['libelle' => 'Ouvrir la Caserne', 'onglet' => TypeDeBatiment::Caserne->value],
            'representatif' => false,
            'nommer' => null === $medjay->getPrenom() ? ['cle' => 'medjay-'.$medjay->getId(), 'propositions' => $this->prenoms->propositions($partie, masculin: true)] : null,
        ];
    }

    /**
     * Pose chaque silhouette devant son lot. L'orientation et le léger
     * décalage se tirent de la clé et de la partie par un hachage : **stables
     * d'une visite à l'autre**, sans graine à garder.
     *
     * @param list<array{cle: string, role: RoleDeFigure, libelle: string, lieu: ?TypeDeBatiment, couchee: bool}> $candidats
     *
     * @return list<FigureDeLaVille>
     */
    private function poser(GameSave $partie, array $candidats): array
    {
        $lots = $this->lotsDresses($partie);
        $occupation = [];
        $figures = [];

        foreach ($candidats as $rang => $candidat) {
            $type = $candidat['lieu'];
            $lot = null !== $type && isset($lots[$type->value]) ? $lots[$type->value] : $lots[TypeDeBatiment::ResidenceFamiliale->value];
            $cleDuLot = $lot['x'].'-'.$lot['y'];

            $place = $occupation[$cleDuLot] ?? 0;

            if ($place >= self::PAR_LOT_AU_PLUS) {
                continue;
            }

            $occupation[$cleDuLot] = $place + 1;

            $largeurDuLot = EmplacementsDeLaVille::LARGEUR_DES_LOTS[$lot['classe']];
            [$dx, $dy] = self::ANCRAGES[$place];
            $hachage = crc32($partie->getId().'|'.$candidat['cle'].'|'.$rang);
            $largeur = $candidat['couchee'] ? self::HAUTEUR_DEBOUT : self::LARGEUR_DEBOUT;
            $hauteur = $candidat['couchee'] ? self::LARGEUR_DEBOUT : self::HAUTEUR_DEBOUT;

            // Les pieds se posent au point d'ancrage, la silhouette monte depuis lui.
            $piedsX = $lot['x'] + $dx * $largeurDuLot + (($hachage >> 1) % 7 - 3);
            $piedsY = $lot['y'] + $dy * $largeurDuLot;

            $figures[] = new FigureDeLaVille(
                cle: $candidat['cle'],
                role: $candidat['role'],
                libelle: $candidat['libelle'],
                lieu: $type,
                gauche: $this->borner(($piedsX - $largeur / 2) / EmplacementsDeLaVille::LARGEUR * 100, 100 - $largeur / EmplacementsDeLaVille::LARGEUR * 100),
                haut: $this->borner(($piedsY - $hauteur) / EmplacementsDeLaVille::HAUTEUR * 100, 100 - $hauteur / EmplacementsDeLaVille::HAUTEUR * 100),
                largeur: round($largeur / EmplacementsDeLaVille::LARGEUR * 100, 3),
                profondeur: (int) round($piedsY),
                miroir: 1 === ($hachage & 1),
                couchee: $candidat['couchee'],
            );
        }

        return $figures;
    }

    /**
     * Les lots qui portent un bâtiment dressé, par type. La Résidence est là
     * dès le premier jour : c'est le repli de tout ce qui n'a pas de lieu.
     *
     * @return array<string, array{x: int, y: int, classe: string, type: ?TypeDeBatiment}>
     */
    public function lotsDresses(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $lots = [];

        foreach (EmplacementsDeLaVille::tous() as $lot) {
            $type = $lot['type'];

            // Un chantier en cours a son lot, même si rien n'y est encore dressé : c'est là que les maçons travaillent.
            if (null !== $type && (null !== $ville->batimentDeType($type) || $type->estLeBatimentDeDepart() || self::enChantier($ville, $type))) {
                $lots[$type->value] = $lot;
            }
        }

        return $lots;
    }

    private static function enChantier(\App\Entity\City $ville, TypeDeBatiment $type): bool
    {
        foreach ($ville->getChantiers() as $chantier) {
            if ($chantier->getType() === $type) {
                return true;
            }
        }

        return false;
    }

    private function borner(float $valeur, float $maximum): float
    {
        return round(max(0.0, min($maximum, $valeur)), 3);
    }

    /**
     * La liste complète, sans plafond : tout ce que le visuel montre, et le
     * reste, en toutes lettres.
     *
     * @param array<string, array{batiment: \App\Entity\Building, requis: int, affectes: int, rendement: int}> $postes
     * @param list<list<string>>                                                                               $maisons
     *
     * @return list<array{titre: string, lieu: ?TypeDeBatiment, lignes: list<array{texte: string, cle: ?string}>}>
     */
    private function lieux(GameSave $partie, array $postes, array $maisons, int $oisifs): array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();
        $lots = $this->lotsDresses($partie);
        $chefs = PhotoDeLaVille::chefsEnPoste($partie);
        $lieux = [];

        foreach (TypeDeBatiment::cases() as $type) {
            if (!isset($lots[$type->value])) {
                continue;
            }

            $lignes = [];

            if (TypeDeBatiment::ResidenceFamiliale === $type) {
                $nom = $partie->getFamille()->getChefDeFamille();
                $lignes[] = ['texte' => null === $nom ? 'Le chef de la famille' : $nom.' — chef de famille', 'cle' => 'famille'];
            }

            foreach ($chefs as $chef) {
                if ($chef->getType() === $type) {
                    $specialite = null !== $chef->getSpecialite() ? ', '.mb_strtolower($chef->getSpecialite()->libelle()) : '';
                    $lignes[] = ['texte' => PhotoDeLaVille::libelleDuChef($chef).$specialite, 'cle' => 'chef-'.$chef->getId()];
                }
            }

            if (isset($postes[$type->value]) && $postes[$type->value]['requis'] > 0) {
                $poste = $postes[$type->value];
                $lignes[] = [
                    'texte' => 0 === $poste['affectes']
                        ? \sprintf('À l\'arrêt : personne n\'y travaille (%d requis)', $poste['requis'])
                        : \sprintf('%d travailleur%s en poste (%d requis)', $poste['affectes'], $poste['affectes'] > 1 ? 's' : '', $poste['requis']),
                    'cle' => 'poste-'.$type->value,
                ];
            }

            if (TypeDeBatiment::QuartierDHabitation === $type) {
                $malades = $ville->malades();
                $lignes[] = [
                    'texte' => \sprintf('%d maison%s, %s', \count($maisons), \count($maisons) > 1 ? 's' : '', 0 === $malades ? 'aucun malade' : \sprintf('%d malade%s', $malades, $malades > 1 ? 's' : '')),
                    'cle' => [] === $maisons ? null : 'maison-1',
                ];

                foreach ([[Maisonnees::ENFANT, 'enfant', 'enfant', 'enfants'], [Maisonnees::ANCIEN, 'ancien', 'ancien', 'anciens']] as [$age, $prefixe, $singulier, $pluriel]) {
                    $total = Maisonnees::ENFANT === $age ? $ville->getEnfants() : $ville->getAnciens();

                    foreach (PhotoDeLaVille::maisonsMontrees($maisons) as $rang) {
                        if (\in_array($age, $maisons[$rang], true)) {
                            $lignes[] = ['texte' => \sprintf('%d %s en ville', $total, 1 === $total ? $singulier : $pluriel), 'cle' => $prefixe.'-'.($rang + 1)];

                            break;
                        }
                    }
                }

                foreach ($ville->getMedjays() as $medjay) {
                    if (!$medjay->estDisponible($cycle)) {
                        $lignes[] = ['texte' => PhotoDeLaVille::libelleDuMedjay($medjay, $cycle), 'cle' => 'medjay-'.$medjay->getId()];
                    }
                }
            }

            if (TypeDeBatiment::Caserne === $type) {
                foreach ($ville->getMedjays() as $medjay) {
                    if ($medjay->estDisponible($cycle)) {
                        $lignes[] = ['texte' => PhotoDeLaVille::libelleDuMedjay($medjay, $cycle), 'cle' => 'medjay-'.$medjay->getId()];
                    }
                }
            }

            foreach ($ville->getChantiers() as $chantier) {
                if ($chantier->getType() === $type) {
                    $lignes[] = [
                        'texte' => \sprintf('Chantier : des maçons au travail, encore %d quinzaine%s', $chantier->cyclesRestants(), $chantier->cyclesRestants() > 1 ? 's' : ''),
                        'cle' => 'macon-'.$type->value,
                    ];

                    break;
                }
            }

            foreach (VisiteurDePassage::pour($ville) as $visiteur) {
                if ($visiteur['lieu'] === $type) {
                    $lignes[] = ['texte' => $visiteur['libelle'].' — '.VisiteurDePassage::invitation($visiteur['role']), 'cle' => $visiteur['cle']];
                }
            }

            if (TypeDeBatiment::Marche === $type) {
                $rival = $ville->getRival();

                if (null !== $rival) {
                    $lignes[] = ['texte' => $rival->getNom().' — marchand rival', 'cle' => 'rival'];
                }

                if ($oisifs > 0) {
                    $lignes[] = ['texte' => \sprintf('%d bras sans poste', $oisifs), 'cle' => 'sans-poste'];
                }
            }

            if ([] !== $lignes) {
                $lieux[] = ['titre' => $type->libelle(), 'lieu' => $type, 'lignes' => $lignes];
            }
        }

        $champs = Effectifs::brasPourLesChamps($ville, $cycle);

        if ($champs > 0) {
            $lieux[] = [
                'titre' => 'Aux champs',
                'lieu' => null,
                'lignes' => [['texte' => \sprintf('%d paysan%s hors de la ville', $champs, $champs > 1 ? 's' : ''), 'cle' => 'champs']],
            ];
        }

        return $lieux;
    }
}
