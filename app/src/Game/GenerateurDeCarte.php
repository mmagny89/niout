<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\Zone;
use Random\Randomizer;

/**
 * Produit la carte d'une région (doc 02).
 *
 * Une région n'est pas une carte figée mais un jeu de paramètres : même
 * mission, carte différente à chaque partie. Le hasard passe par un
 * `Randomizer` injecté, ce qui permet de le semer en test et d'obtenir une
 * génération reproductible sans rien changer au code de production.
 */
final readonly class GenerateurDeCarte
{
    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Peuple la ville de ses zones. La grille et la difficulté sont déjà
     * portées par la ville, décidées au lancement de la partie.
     */
    public function peupler(City $ville, GeographieDeRegion $geographie): void
    {
        $taille = $ville->getTailleGrille();

        $grille = $this->poserLaGeographie($ville, $geographie, $taille);
        $this->placerLaVille($grille, $geographie);
        $this->remplirLeContenu($grille, $geographie, $ville->getDifficulte());

        foreach ($grille as $zone) {
            $ville->ajouterZone($zone);
        }
    }

    /**
     * Bords spéciaux d'abord, terre fertile pour le reste (doc 02).
     *
     * @return list<Zone>
     */
    private function poserLaGeographie(City $ville, GeographieDeRegion $geographie, int $taille): array
    {
        $terrains = [];
        for ($y = 0; $y < $taille; ++$y) {
            for ($x = 0; $x < $taille; ++$x) {
                $terrains[$y][$x] = TypeDeTerrain::Fertile;
            }
        }

        $bordsOccupes = [];

        // La Méditerranée borde toujours le nord.
        if ($geographie->mediterranee) {
            for ($x = 0; $x < $taille; ++$x) {
                $terrains[0][$x] = TypeDeTerrain::Mediterranee;
            }
            $bordsOccupes[] = 'haut';
        }

        // La mer Rouge, toujours à l'est — simplification actée du doc 02.
        if ($geographie->merRouge) {
            for ($y = 0; $y < $taille; ++$y) {
                $terrains[$y][$taille - 1] = TypeDeTerrain::MerRouge;
            }
            $bordsOccupes[] = 'droite';
        }

        // Le Nil descend en colonne, sur un bord vertical encore libre.
        if ($geographie->nil) {
            $colonne = \in_array('droite', $bordsOccupes, true) ? 0 : ($this->hasard->getInt(0, 1) > 0 ? $taille - 1 : 0);
            for ($y = 0; $y < $taille; ++$y) {
                if (TypeDeTerrain::Fertile === $terrains[$y][$colonne]) {
                    $terrains[$y][$colonne] = TypeDeTerrain::Nil;
                }
            }
            $bordsOccupes[] = 0 === $colonne ? 'gauche' : 'droite';
        }

        if ($geographie->desert) {
            $this->poserLeDesert($terrains, $taille, $bordsOccupes);
        }

        if ($geographie->desertDominant) {
            $this->ensablerLeReste($terrains, $taille);
        }

        if ($geographie->foret) {
            $this->poserLaForet($terrains, $taille);
        }

        if ($geographie->oasis) {
            $this->poserUneOasis($terrains, $taille);
        }

        $zones = [];
        for ($y = 0; $y < $taille; ++$y) {
            for ($x = 0; $x < $taille; ++$x) {
                $zones[] = new Zone($ville, $x, $y, $terrains[$y][$x]);
            }
        }

        return $zones;
    }

    /**
     * @param array<int, array<int, TypeDeTerrain>> $terrains
     * @param list<string>                          $bordsOccupes
     */
    private function poserLeDesert(array &$terrains, int $taille, array $bordsOccupes): void
    {
        $libres = array_values(array_diff(['bas', 'gauche', 'droite'], $bordsOccupes));

        if ([] === $libres) {
            // Aucun bord disponible : le désert se disperse (doc 02).
            for ($y = 0; $y < $taille; ++$y) {
                for ($x = 0; $x < $taille; ++$x) {
                    if (TypeDeTerrain::Fertile === $terrains[$y][$x] && $this->hasard->getInt(1, 100) <= 25) {
                        $terrains[$y][$x] = TypeDeTerrain::Desert;
                    }
                }
            }

            return;
        }

        $bord = $libres[$this->hasard->getInt(0, \count($libres) - 1)];

        for ($i = 0; $i < $taille; ++$i) {
            [$y, $x] = match ($bord) {
                'bas' => [$taille - 1, $i],
                'gauche' => [$i, 0],
                default => [$i, $taille - 1],
            };

            if (TypeDeTerrain::Fertile === $terrains[$y][$x]) {
                $terrains[$y][$x] = TypeDeTerrain::Desert;
            }
        }
    }

    /**
     * Dans les régions où le sable domine, la terre fertile ne subsiste qu'en
     * lisière du fleuve — ailleurs, tout est désert (doc 11).
     *
     * @param array<int, array<int, TypeDeTerrain>> $terrains
     */
    private function ensablerLeReste(array &$terrains, int $taille): void
    {
        for ($y = 0; $y < $taille; ++$y) {
            for ($x = 0; $x < $taille; ++$x) {
                if (TypeDeTerrain::Fertile !== $terrains[$y][$x]) {
                    continue;
                }

                // Une bande fertile survit le long de l'eau : sans elle, la
                // ville n'aurait nulle part où s'installer.
                if ($this->jouxteDeLEau($terrains, $taille, $x, $y)) {
                    continue;
                }

                $terrains[$y][$x] = TypeDeTerrain::Desert;
            }
        }
    }

    /**
     * @param array<int, array<int, TypeDeTerrain>> $terrains
     */
    private function jouxteDeLEau(array $terrains, int $taille, int $x, int $y): bool
    {
        for ($dy = -1; $dy <= 1; ++$dy) {
            for ($dx = -1; $dx <= 1; ++$dx) {
                $vy = $y + $dy;
                $vx = $x + $dx;

                if ($vy < 0 || $vx < 0 || $vy >= $taille || $vx >= $taille) {
                    continue;
                }

                if ($terrains[$vy][$vx]->estUnPointDEau()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Bloc de forêt du Levant, seule région à en porter (doc 02).
     *
     * @param array<int, array<int, TypeDeTerrain>> $terrains
     */
    private function poserLaForet(array &$terrains, int $taille): void
    {
        $poses = 0;
        for ($y = $taille - 1; $y >= 0 && $poses < 3; --$y) {
            for ($x = 0; $x < $taille && $poses < 3; ++$x) {
                if (TypeDeTerrain::Fertile === $terrains[$y][$x]) {
                    $terrains[$y][$x] = TypeDeTerrain::Foret;
                    ++$poses;
                }
            }
        }
    }

    /**
     * Oasis entourée de désert, jamais posée à l'air libre (doc 02).
     *
     * @param array<int, array<int, TypeDeTerrain>> $terrains
     */
    private function poserUneOasis(array &$terrains, int $taille): void
    {
        for ($y = 0; $y < $taille; ++$y) {
            for ($x = 0; $x < $taille; ++$x) {
                if (TypeDeTerrain::Desert === $terrains[$y][$x]) {
                    $terrains[$y][$x] = TypeDeTerrain::Oasis;

                    return;
                }
            }
        }
    }

    /**
     * La ville touche l'eau s'il y en a, sinon elle s'installe en terre
     * fertile. Jamais en plein désert, jamais isolée (doc 02).
     *
     * Le Nil a priorité sur la Méditerranée et la mer Rouge quand la région
     * en porte : c'est lui qui a fait naître les villes égyptiennes
     * réelles, la crue et le limon avant tout le reste.
     *
     * @param list<Zone> $grille
     */
    private function placerLaVille(array $grille, GeographieDeRegion $geographie): void
    {
        $candidates = $geographie->nil
            ? $this->candidatsAdjacentsAUneBerge($grille, static fn (TypeDeTerrain $t): bool => TypeDeTerrain::Nil === $t)
            : [];

        if ([] === $candidates && $geographie->aUnPointDEau()) {
            $candidates = $this->candidatsAdjacentsAUneBerge(
                $grille,
                static fn (TypeDeTerrain $t): bool => $t->estUnPointDEau(),
            );
        }

        if ([] === $candidates) {
            $candidates = array_values(array_filter(
                $grille,
                static fn (Zone $z): bool => TypeDeTerrain::Fertile === $z->getTerrain(),
            ));
        }

        // Dernier recours : une oasis, sinon n'importe quelle case hors de
        // l'eau. Ne devrait arriver sur aucune des dix régions, mais mieux vaut
        // une ville mal placée qu'une partie sans ville.
        if ([] === $candidates) {
            $candidates = array_values(array_filter(
                $grille,
                static fn (Zone $z): bool => TypeDeTerrain::Oasis === $z->getTerrain(),
            ));
        }

        if ([] === $candidates) {
            $candidates = array_values(array_filter(
                $grille,
                static fn (Zone $z): bool => !$z->getTerrain()->estUnPointDEau(),
            ));
        }

        $candidates[$this->hasard->getInt(0, \count($candidates) - 1)]->yPlacerLaVille();
    }

    /**
     * Cases hors désert et hors eau, adjacentes à au moins une berge du type
     * retenu par le prédicat — le cœur commun aux deux passes de
     * `placerLaVille()`.
     *
     * @param list<Zone>                   $grille
     * @param callable(TypeDeTerrain):bool $estUneBerge
     *
     * @return list<Zone>
     */
    private function candidatsAdjacentsAUneBerge(array $grille, callable $estUneBerge): array
    {
        $berges = array_values(array_filter(
            $grille,
            static fn (Zone $z): bool => $estUneBerge($z->getTerrain()),
        ));

        $candidats = [];

        foreach ($grille as $zone) {
            // Toucher l'eau ne suffit pas : le doc 02 interdit aussi le
            // plein désert, et une case de sable bordant le Nil en reste.
            if ($zone->getTerrain()->estUnPointDEau() || TypeDeTerrain::Desert === $zone->getTerrain()) {
                continue;
            }

            foreach ($berges as $berge) {
                if ($zone->estAdjacenteA($berge)) {
                    $candidats[] = $zone;
                    break;
                }
            }
        }

        return $candidats;
    }

    /**
     * La case de la ville, une fois placée. Utile aux passes qui suivent
     * `placerLaVille()` mais n'ont pas encore de `City` peuplée pour la
     * retrouver — `City::ajouterZone()` n'a lieu qu'à la toute fin de
     * `peupler()`.
     *
     * @param list<Zone> $grille
     */
    private function zoneDeLaVille(array $grille): ?Zone
    {
        foreach ($grille as $zone) {
            if ($zone->porteLaVille()) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Les 8 cases qui touchent la ville, orthogonales et diagonales — celles
     * qu'on reconnaît gratuitement (`RoleDExploration::coutPourUneDistance()`)
     * et qu'on privilégie donc pour les garanties de gisement : un seul
     * exemplaire de chaque matériau y suffit, pas besoin d'aller l'y chercher
     * plus loin (décision de la joueuse).
     *
     * @param list<Zone> $grille
     *
     * @return list<Zone>
     */
    private function anneauDeLaVille(array $grille): array
    {
        $ville = $this->zoneDeLaVille($grille);

        if (null === $ville) {
            return [];
        }

        return array_values(array_filter(
            $grille,
            static fn (Zone $z): bool => !$z->porteLaVille() && $z->estAdjacenteA($ville),
        ));
    }

    /**
     * Tirage pondéré du contenu, case par case (doc 02).
     *
     * @param list<Zone> $grille
     */
    private function remplirLeContenu(array $grille, GeographieDeRegion $geographie, int $difficulte): void
    {
        $poids = PoidsDeTirage::pourDifficulte($difficulte);
        $quantite = PoidsDeTirage::quantiteParGisement($difficulte);
        $ville = $this->zoneDeLaVille($grille);
        $anneau = $this->anneauDeLaVille($grille);

        // Un seul exemplaire de chaque matériau non alimentaire dans
        // l'anneau proche : sans ce plafond, le tirage aléatoire pourrait à
        // lui seul poser calcaire, cuivre et turquoise sur les huit cases qui
        // touchent la ville, qui n'aurait alors plus rien à explorer
        // (décision de la joueuse — « éviter d'avoir directement tout »).
        /** @var array<string, true> $materiauxDeLAnneau */
        $materiauxDeLAnneau = [];

        foreach ($grille as $zone) {
            if ($zone->porteLaVille()) {
                continue;
            }

            $dansLAnneau = \in_array($zone, $anneau, true);
            $distance = null === $ville ? 1 : $zone->distanceDepuis($ville);

            $this->tirerLeContenu($zone, $geographie, $poids, $quantite, $distance, $dansLAnneau ? $materiauxDeLAnneau : null);

            if ($dansLAnneau) {
                foreach ($zone->getGisements() as $gisement) {
                    if (!$gisement->getRessource()->estNourriture()) {
                        $materiauxDeLAnneau[$gisement->getRessource()->value] = true;
                    }
                }
            }
        }

        $this->garantirLesMinimums($grille, $geographie, $quantite);
    }

    /**
     * Les deux matériaux que le tirage ne doit jamais oublier, tous deux nés de
     * l'eau (doc 08) : le limon des berges, déposé par la crue, dont on fait la
     * brique crue ; et les roseaux des marais du Delta.
     *
     * Ce sont aussi les deux seuls dont la ville ne peut pas se passer : presque
     * tous les bâtiments sont en brique et en roseau, et **rien ne s'y
     * substitue**. Un tirage qui n'en poserait aucun rendait la partie
     * imbâtissable au deuxième bâtiment — ce qui s'est produit en jeu.
     */
    private const array MATERIAUX_DE_ZONE_HUMIDE = [Ressource::Argile, Ressource::Roseaux];

    /**
     * Minimum de champs sur une carte. Sans terre à semer, le Grenier et tout
     * le cycle agricole restent lettre morte.
     *
     * Valeur invention, volontairement modeste : sur le Delta (3×3, la plus
     * petite carte du jeu), les cases riveraines se disputent déjà entre
     * matériaux vitaux, poisson et champs. Un minimum de 2 s'est révélé
     * irréalisable sur cette carte — le tirage pouvait épuiser toute la terre
     * disponible avant que la garantie de champs ne s'exécute, laissant la
     * partie sans terre à semer. 1 suffit à rendre l'agriculture possible ;
     * les régions plus grandes en offriront naturellement davantage.
     */
    private const int CHAMPS_MINIMUM = 1;

    /**
     * Minimum de cases poissonneuses, là où il y a de l'eau. Le Port n'aurait
     * sinon rien à pêcher. Même calibrage modeste, pour la même raison.
     */
    private const int POISSON_MINIMUM = 1;

    /**
     * Le tirage seul laisse trop de place au hasard : une carte peut sortir
     * sans champ, sans poisson, ou sans l'une des ressources de sa région. On
     * complète donc après coup, **sans jamais rien retirer** de ce que le
     * tirage a produit.
     *
     * « En fonction de la région » : on ne garantit que ce que la région porte
     * réellement. Une région sans roseaux n'en verra pas apparaître.
     *
     * @param list<Zone> $grille
     */
    private function garantirLesMinimums(array $grille, GeographieDeRegion $geographie, int $quantite): void
    {
        // Les champs d'abord : une case cultivable garde sa vocation même si
        // un gisement s'y ajoute ensuite (`Zone::poserUnGisement()` ne touche
        // jamais un contenu déjà posé), alors que l'inverse est impossible —
        // `garantirDesChamps()` ne convertit qu'une case sans gisement. La
        // faire passer en dernier pouvait donc, sur une petite carte, laisser
        // les garanties de matériaux consommer les trois seules terres
        // cultivables avant que celle des champs n'ait pu s'exécuter.
        $this->garantirDesChamps($grille);

        // Puis les vitaux, sur une berge : ils commandent la jouabilité,
        // regroupés sur le moins de cases possible pour laisser de la place au
        // reste — une petite carte ne peut pas se permettre de les disperser.
        foreach (self::MATERIAUX_DE_ZONE_HUMIDE as $materiau) {
            $this->garantirUnGisementRiverain($grille, $geographie, $materiau, $quantite);
        }

        // Au moins un gisement de chaque autre ressource de la région.
        foreach ($geographie->ressourcesDeZone as $materiau) {
            if (\in_array($materiau, self::MATERIAUX_DE_ZONE_HUMIDE, true)) {
                continue;
            }

            $this->garantirUnGisement($grille, $materiau, $quantite);
        }

        $this->garantirDuPoisson($grille, $quantite);
    }

    /**
     * Pose un gisement de ce matériau si la carte n'en porte aucun, en
     * priorité dans l'anneau proche de la ville — un seul exemplaire y suffit
     * (décision de la joueuse) — et seulement à défaut ailleurs sur la carte.
     *
     * @param list<Zone> $grille
     */
    private function garantirUnGisement(array $grille, Ressource $materiau, int $quantite): void
    {
        $anneau = $this->anneauDeLaVille($grille);
        $candidates = [];
        $candidatsAnneau = [];

        foreach ($grille as $zone) {
            if (null !== $zone->gisementDe($materiau)) {
                return;
            }

            if ($zone->porteLaVille() || $zone->getTerrain()->estUnPointDEau() || !$zone->peutPorterUnGisementDePlus()) {
                continue;
            }

            $candidates[] = $zone;

            if (\in_array($zone, $anneau, true)) {
                $candidatsAnneau[] = $zone;
            }
        }

        $choix = [] !== $candidatsAnneau ? $candidatsAnneau : $candidates;

        if ([] === $choix) {
            return;
        }

        $choix[$this->hasard->getInt(0, \count($choix) - 1)]
            ->poserUnGisement($materiau, $quantite);
    }

    /**
     * Le poisson est la seule ressource des cases d'eau (doc 08). Une carte qui
     * borde l'eau doit en porter, sans quoi le Port serait un quai désert.
     *
     * @param list<Zone> $grille
     */
    private function garantirDuPoisson(array $grille, int $quantite): void
    {
        $eaux = [];
        $poissonneuses = 0;

        foreach ($grille as $zone) {
            if (!$zone->getTerrain()->estUnPointDEau()) {
                continue;
            }

            if (null !== $zone->gisementDe(Ressource::Poisson)) {
                ++$poissonneuses;
            } else {
                $eaux[] = $zone;
            }
        }

        while ($poissonneuses < self::POISSON_MINIMUM && [] !== $eaux) {
            $rang = $this->hasard->getInt(0, \count($eaux) - 1);
            array_splice($eaux, $rang, 1)[0]->poserUnGisement(Ressource::Poisson, $quantite);
            ++$poissonneuses;
        }
    }

    /**
     * Des terres cultivables en nombre suffisant, en commençant par les cases
     * les plus proches de la ville — plus logique et plus conforme à
     * l'histoire qu'une terre dispersée n'importe où sur la carte (décision
     * de la joueuse). On ne convertit que des cases vides ou déjà marquées
     * non cultivables : un gisement déjà tiré vaut mieux qu'un champ de plus.
     *
     * @param list<Zone> $grille
     */
    private function garantirDesChamps(array $grille): void
    {
        $ville = $this->zoneDeLaVille($grille);
        $vides = [];
        $champs = 0;

        foreach ($grille as $zone) {
            if ($zone->porteLaVille() || !$zone->getTerrain()->accepteUnChamp()) {
                continue;
            }

            if (ContenuDeZone::ChampEligible === $zone->getContenu()) {
                ++$champs;
            } elseif (!$zone->porteUnGisement()) {
                $vides[] = $zone;
            }
        }

        if (null !== $ville) {
            usort($vides, static fn (Zone $a, Zone $b): int => $a->distanceDepuis($ville) <=> $b->distanceDepuis($ville));
        }

        while ($champs < self::CHAMPS_MINIMUM && [] !== $vides) {
            array_shift($vides)->poserUnContenu(ContenuDeZone::ChampEligible);
            ++$champs;
        }
    }

    /**
     * Assure au moins un gisement de ce matériau en bordure d'eau, si la région
     * en porte. Une région qui n'en porte pas devra l'importer : le cas est
     * signalé au plan de bataille plutôt que corrigé en douce ici.
     *
     * Une case pouvant porter deux gisements, la berge choisie n'a pas à être
     * vierge : l'argile et les roseaux cohabitent volontiers sur un même marais.
     * Priorité à l'anneau proche de la ville, pour la même raison que
     * `garantirUnGisement()` : un seul exemplaire suffit, et il vaut mieux
     * l'avoir sous la main qu'au bout de la carte — sauf si le tirage
     * aléatoire en a déjà posé un exemplaire ailleurs dans l'anneau (hors
     * berge) : ajouter la berge obligatoire au même endroit y créerait le
     * doublon que l'anneau doit justement éviter. On la cherche alors hors de
     * l'anneau en priorité, et on n'accepte le doublon que si aucune autre
     * berge n'existe nulle part ailleurs sur la carte.
     *
     * @param list<Zone> $grille
     */
    private function garantirUnGisementRiverain(
        array $grille,
        GeographieDeRegion $geographie,
        Ressource $materiau,
        int $quantite,
    ): void {
        if (!\in_array($materiau, $geographie->ressourcesDeZone, true)) {
            return;
        }

        $anneau = $this->anneauDeLaVille($grille);

        $dejaDansLAnneau = false;
        foreach ($anneau as $zone) {
            if (null !== $zone->gisementDe($materiau)) {
                $dejaDansLAnneau = true;
                break;
            }
        }

        // Quatre listes : berges déjà pourvues / vierges, chacune scindée
        // entre l'anneau proche et le reste. Consolider sur les gisements
        // existants avant d'entamer une case neuve — c'est ce qui permet à
        // l'argile et aux roseaux de tenir sur une seule case plutôt que deux,
        // et laisse de la place aux champs et au poisson sur une petite carte.
        $avecGisementAnneau = [];
        $avecGisementHorsAnneau = [];
        $viergesAnneau = [];
        $viergesHorsAnneau = [];

        foreach ($grille as $zone) {
            if ($zone->porteLaVille() || $zone->getTerrain()->estUnPointDEau() || !$this->estRiveraine($grille, $zone)) {
                continue;
            }

            // Un gisement déjà tiré en bordure d'eau suffit : c'est bien là
            // qu'on le cherche.
            if (null !== $zone->gisementDe($materiau)) {
                return;
            }

            if (!$zone->peutPorterUnGisementDePlus()) {
                continue;
            }

            $dansLAnneau = \in_array($zone, $anneau, true);

            if ($zone->porteUnGisement()) {
                $dansLAnneau ? $avecGisementAnneau[] = $zone : $avecGisementHorsAnneau[] = $zone;
            } else {
                $dansLAnneau ? $viergesAnneau[] = $zone : $viergesHorsAnneau[] = $zone;
            }
        }

        $ordreDePriorite = $dejaDansLAnneau
            ? [$avecGisementHorsAnneau, $viergesHorsAnneau, $avecGisementAnneau, $viergesAnneau]
            : [$avecGisementAnneau, $viergesAnneau, $avecGisementHorsAnneau, $viergesHorsAnneau];

        $berges = [];
        foreach ($ordreDePriorite as $liste) {
            if ([] !== $liste) {
                $berges = $liste;
                break;
            }
        }

        if ([] === $berges) {
            return;
        }

        $berges[$this->hasard->getInt(0, \count($berges) - 1)]
            ->poserUnGisement($materiau, $quantite);
    }

    /**
     * @param list<Zone> $grille
     */
    private function estRiveraine(array $grille, Zone $zone): bool
    {
        foreach ($grille as $voisine) {
            if ($voisine->getTerrain()->estUnPointDEau() && $voisine->estAdjacenteA($zone)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ?array<string, true> $materiauxExclus matériaux non alimentaires
     *                                              déjà posés dans l'anneau proche de la ville — null hors de l'anneau,
     *                                              où rien n'est exclu
     */
    private function tirerLeContenu(
        Zone $zone,
        GeographieDeRegion $geographie,
        PoidsDeTirage $poids,
        int $quantite,
        int $distanceDeLaVille,
        ?array $materiauxExclus,
    ): void {
        $terrain = $zone->getTerrain();
        $champPossible = $terrain->accepteUnChamp();
        $ressourcesPossibles = $terrain->estUnPointDEau()
            ? [Ressource::Poisson]
            : $geographie->ressourcesDeZone;

        if (null !== $materiauxExclus) {
            $ressourcesPossibles = array_values(array_filter(
                $ressourcesPossibles,
                static fn (Ressource $r): bool => !isset($materiauxExclus[$r->value]),
            ));
        }

        // Plus la case est loin de la ville, moins elle a de chances de
        // tirer un champ : les terres cultivables se resserrent autour de la
        // ville plutôt que de se disperser sur toute la carte (décision de
        // la joueuse), sans pour autant les en exclure — `garantirDesChamps()`
        // complète au besoin. Le poids perdu rejoint « vide » et non
        // « ressource » : l'éloignement rend une case moins arable, pas plus
        // riche en gisements — sans ce transfert, le total du tirage
        // rétrécit et gonfle mécaniquement la part de « ressource », au
        // point de saturer de gisements les rares cases cultivables d'une
        // petite carte et de ne plus rien laisser à `garantirDesChamps()`.
        $poidsChamp = $champPossible ? max(1, intdiv($poids->champ, $distanceDeLaVille)) : 0;

        $options = [];
        if ([] !== $ressourcesPossibles) {
            $options['ressource'] = $poids->ressource;
        }
        if ($champPossible) {
            $options['champ'] = $poidsChamp;
        }
        $options['evenement'] = $poids->evenement;
        $options['vide'] = $poids->vide + ($champPossible ? $poids->champ - $poidsChamp : 0);

        match ($this->tirerParmi($options)) {
            'ressource' => $zone->poserUnGisement(
                $ressourcesPossibles[$this->hasard->getInt(0, \count($ressourcesPossibles) - 1)],
                $quantite,
            ),
            'champ' => $zone->poserUnContenu(ContenuDeZone::ChampEligible),
            'evenement' => $zone->poserUnContenu(ContenuDeZone::Evenement),
            // Une case qui aurait pu porter un champ mais n'en tire pas un
            // reste identifiable comme telle plutôt que de se fondre dans le
            // « rien » générique : c'est une terre fertile ou une berge du
            // Nil, simplement pas cultivable (doc 02 — toutes les berges ne
            // le sont pas).
            default => $zone->poserUnContenu($champPossible ? ContenuDeZone::TerreNonCultivable : ContenuDeZone::Rien),
        };
    }

    /**
     * @param array<string, int> $options clé => poids
     */
    private function tirerParmi(array $options): string
    {
        $total = array_sum($options);
        $tirage = $this->hasard->getInt(1, $total);

        foreach ($options as $cle => $poids) {
            $tirage -= $poids;
            if ($tirage <= 0) {
                return $cle;
            }
        }

        return array_key_last($options) ?? 'vide';
    }
}
