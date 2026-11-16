<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\Employee;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Game\Commerce;
use App\Game\Effectifs;
use App\Game\EmplacementsDeLaVille;
use App\Game\Maisonnees;
use App\Game\TypeDeBatiment;

/**
 * Les gens de la ville, posés sur la ville vue d'en haut
 * (`docs/plan-pnj-ville-vivante.md` § 15, lot V1).
 *
 * **Une représentation, pas un état** — comme `Maisonnees`, dont ce service est
 * le prolongement : rien n'est persisté, rien n'est tiré au sort, et la même
 * ville donne toujours les mêmes figures. Un rechargement ne recompose pas la
 * ville sous les yeux du joueur.
 *
 * **Chaque silhouette dit une chose vraie.** Un chef en poste se voit à son
 * bâtiment, un Medjaÿ blessé est couché, un bâtiment à l'arrêt n'a personne,
 * des bras sans poste traînent au Marché. Ce que le jeu ne tient pas — il ne
 * compte que des nombres d'habitants, pas des individus — est montré comme
 * **représentatif**, et dit tel. Le service **lit** les règles
 * (`Effectifs`, `Maisonnees`, `Medjay::estDisponible()`), il n'en pose aucune.
 *
 * **Les désignés d'abord, la foule ensuite** : si le plafond est atteint, on
 * coupe la foule, jamais un chef.
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
    public const int PLAFOND_DE_FIGURES = 40;

    /**
     * Combien de travailleurs représentatifs par bâtiment. Le compte exact est
     * dans la fiche, pas dans le nombre de silhouettes.
     */
    public const int TRAVAILLEURS_PAR_BATIMENT = 3;

    public const int MAISONS_MONTREES = 6;

    public const int SANS_POSTE_MONTRES = 3;

    /**
     * Combien de silhouettes un même lot accueille avant d'être plein.
     */
    private const int PAR_LOT_AU_PLUS = 8;

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
    private const array ANCRAGES = [
        [0.00, 0.17], [-0.20, 0.12], [0.20, 0.12], [-0.10, 0.24],
        [0.10, 0.24], [-0.30, 0.06], [0.30, 0.06], [0.00, 0.31],
    ];

    public function __construct(
        private Commerce $commerce,
    ) {
    }

    /**
     * @return array{
     *     figures: list<FigureDeLaVille>,
     *     lieux: list<array{titre: string, lieu: ?TypeDeBatiment, lignes: list<array{texte: string, cle: ?string}>}>,
     *     auxChamps: int,
     * }
     */
    public function pour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();
        $postes = Effectifs::repartir($ville, $cycle);
        $bilan = Effectifs::bilan($ville, $cycle);
        $maisons = Maisonnees::repartir($ville);

        /** @var list<array{cle: string, role: RoleDeFigure, libelle: string, lieu: ?TypeDeBatiment, couchee: bool}> $candidats */
        $candidats = [];

        // 1. Le chef de famille, à la Résidence.
        $candidats[] = [
            'cle' => 'famille',
            'role' => RoleDeFigure::ChefDeFamille,
            'libelle' => $this->nomDuChefDeFamille($partie),
            'lieu' => TypeDeBatiment::ResidenceFamiliale,
            'couchee' => false,
        ];

        // 2. Les chefs en poste, un par chef, à leur bâtiment.
        foreach ($this->chefsEnPoste($partie) as $chef) {
            $candidats[] = [
                'cle' => 'chef-'.$chef->getId(),
                'role' => RoleDeFigure::Chef,
                'libelle' => $this->libelleDuChef($chef),
                'lieu' => $chef->getType(),
                'couchee' => false,
            ];
        }

        // 3. Les Medjaÿ : debout à la Caserne, couchés au Quartier s'ils sont blessés.
        foreach ($ville->getMedjays() as $medjay) {
            $disponible = $medjay->estDisponible($cycle);
            $candidats[] = [
                'cle' => 'medjay-'.$medjay->getId(),
                'role' => RoleDeFigure::Medjay,
                'libelle' => $this->libelleDuMedjay($medjay, $cycle),
                'lieu' => $disponible ? TypeDeBatiment::Caserne : TypeDeBatiment::QuartierDHabitation,
                'couchee' => !$disponible,
            ];
        }

        // 4. Le rival, au Marché.
        $rival = $ville->getRival();

        if (null !== $rival) {
            $candidats[] = [
                'cle' => 'rival',
                'role' => RoleDeFigure::Rival,
                'libelle' => $rival->getNom(),
                'lieu' => TypeDeBatiment::Marche,
                'couchee' => false,
            ];
        }

        // 5. Des travailleurs représentatifs. Un bâtiment à l'arrêt n'en a
        // aucun : c'est une information vraie, visible d'un coup d'œil.
        foreach ($postes as $cle => $poste) {
            $type = TypeDeBatiment::from($cle);

            for ($i = 0; $i < min($poste['affectes'], self::TRAVAILLEURS_PAR_BATIMENT); ++$i) {
                $candidats[] = [
                    'cle' => 'poste-'.$cle,
                    'role' => RoleDeFigure::Travailleur,
                    'libelle' => \sprintf('Travailleur — %s', $type->libelle()),
                    'lieu' => $type,
                    'couchee' => false,
                ];
            }
        }

        // 6. Les bras sans poste, au Marché.
        for ($i = 0; $i < min($bilan['oisifs'], self::SANS_POSTE_MONTRES); ++$i) {
            $candidats[] = [
                'cle' => 'sans-poste',
                'role' => RoleDeFigure::SansPoste,
                'libelle' => 'Bras sans poste',
                'lieu' => TypeDeBatiment::Marche,
                'couchee' => false,
            ];
        }

        // 7. Des maisons représentatives, au Quartier ; un malade y est couché.
        foreach ($this->maisonsMontrees($maisons) as $rang) {
            $habitants = $maisons[$rang];
            $candidats[] = [
                'cle' => 'maison-'.($rang + 1),
                'role' => RoleDeFigure::Foyer,
                'libelle' => \sprintf('Maison %d', $rang + 1),
                'lieu' => TypeDeBatiment::QuartierDHabitation,
                'couchee' => \in_array(Maisonnees::MALADE, $habitants, true),
            ];
        }

        return [
            'figures' => $this->poser($partie, \array_slice($candidats, 0, self::PLAFOND_DE_FIGURES)),
            'lieux' => $this->lieux($partie, $postes, $maisons, $bilan['oisifs']),
            'auxChamps' => Effectifs::brasPourLesChamps($ville, $cycle),
        ];
    }

    /**
     * La fiche d'une figure, ou null si la clé ne désigne rien **dans cette
     * ville** : une clé forgée vers un chef d'une autre partie ne rend rien.
     *
     * @return array{
     *     titre: string,
     *     sousTitre: string,
     *     lignes: list<array{libelle: string, valeur: string}>,
     *     lien: ?array{libelle: string, onglet: string},
     *     representatif: bool,
     * }|null
     */
    public function fiche(GameSave $partie, string $cle): ?array
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
                'titre' => $this->nomDuChefDeFamille($partie),
                'sousTitre' => \sprintf('Chef de la famille %s', $famille->getNom()),
                'lignes' => $lignes,
                'lien' => ['libelle' => 'Ouvrir la Résidence familiale', 'onglet' => TypeDeBatiment::ResidenceFamiliale->value],
                'representatif' => false,
            ];
        }

        if (str_starts_with($cle, 'chef-')) {
            foreach ($this->chefsEnPoste($partie) as $chef) {
                if ('chef-'.$chef->getId() === $cle) {
                    return $this->ficheDuChef($chef, $cycle);
                }
            }

            return null;
        }

        if (str_starts_with($cle, 'medjay-')) {
            foreach ($ville->getMedjays() as $medjay) {
                if ('medjay-'.$medjay->getId() === $cle) {
                    return $this->ficheDuMedjay($medjay, $cycle);
                }
            }

            return null;
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
     * Les chefs qui tiennent réellement leur poste : un chef embauché à la
     * quinzaine qui vient n'est pas encore à l'ouvrage.
     *
     * @return list<Employee>
     */
    private function chefsEnPoste(GameSave $partie): array
    {
        return array_values(array_filter(
            $partie->getVille()->getEmployes()->toArray(),
            static fn (Employee $chef): bool => $chef->estEnPoste($partie->getCycle()),
        ));
    }

    private function nomDuChefDeFamille(GameSave $partie): string
    {
        return $partie->getFamille()->getChefDeFamille() ?? 'Le chef de la famille';
    }

    private function libelleDuChef(Employee $chef): string
    {
        $poste = \sprintf('chef (%s)', $chef->getType()->libelle());

        return null !== $chef->getPrenom()
            ? \sprintf('%s — %s', $chef->getPrenom(), $poste)
            : ucfirst($poste);
    }

    private function libelleDuMedjay(Medjay $medjay, int $cycle): string
    {
        $base = null !== $medjay->getPrenom()
            ? \sprintf('%s — %s', $medjay->getPrenom(), $medjay->getSpecialisation()->libelle())
            : $medjay->getSpecialisation()->libelle();

        return $medjay->estDisponible($cycle)
            ? $base
            : \sprintf('%s (blessé, de retour à la quinzaine %d)', $base, (int) $medjay->getRetabliAuCycle());
    }

    /**
     * @return array{titre: string, sousTitre: string, lignes: list<array{libelle: string, valeur: string}>, lien: ?array{libelle: string, onglet: string}, representatif: bool}
     */
    private function ficheDuChef(Employee $chef, int $cycle): array
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
        ];
    }

    /**
     * @return array{titre: string, sousTitre: string, lignes: list<array{libelle: string, valeur: string}>, lien: ?array{libelle: string, onglet: string}, representatif: bool}
     */
    private function ficheDuMedjay(Medjay $medjay, int $cycle): array
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
        ];
    }

    /**
     * Quelles maisons montrer : jusqu'à six, **régulièrement réparties** parmi
     * toutes, pour que les malades du fond du quartier ne soient pas oubliés au
     * profit des premières maisons.
     *
     * @param list<list<string>> $maisons
     *
     * @return list<int> les rangs retenus
     */
    private function maisonsMontrees(array $maisons): array
    {
        $nombre = \count($maisons);

        if (0 === $nombre) {
            return [];
        }

        $montrees = min($nombre, self::MAISONS_MONTREES);
        $rangs = [];

        for ($i = 0; $i < $montrees; ++$i) {
            $rangs[] = intdiv($i * $nombre, $montrees);
        }

        return $rangs;
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
    private function lotsDresses(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $lots = [];

        foreach (EmplacementsDeLaVille::tous() as $lot) {
            $type = $lot['type'];

            if (null !== $type && (null !== $ville->batimentDeType($type) || $type->estLeBatimentDeDepart())) {
                $lots[$type->value] = $lot;
            }
        }

        return $lots;
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
        $chefs = $this->chefsEnPoste($partie);
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
                    $lignes[] = ['texte' => $this->libelleDuChef($chef).$specialite, 'cle' => 'chef-'.$chef->getId()];
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

                foreach ($ville->getMedjays() as $medjay) {
                    if (!$medjay->estDisponible($cycle)) {
                        $lignes[] = ['texte' => $this->libelleDuMedjay($medjay, $cycle), 'cle' => 'medjay-'.$medjay->getId()];
                    }
                }
            }

            if (TypeDeBatiment::Caserne === $type) {
                foreach ($ville->getMedjays() as $medjay) {
                    if ($medjay->estDisponible($cycle)) {
                        $lignes[] = ['texte' => $this->libelleDuMedjay($medjay, $cycle), 'cle' => 'medjay-'.$medjay->getId()];
                    }
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
