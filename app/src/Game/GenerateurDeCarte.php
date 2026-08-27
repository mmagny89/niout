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
     * @param list<Zone> $grille
     */
    private function placerLaVille(array $grille, GeographieDeRegion $geographie): void
    {
        $candidates = [];

        if ($geographie->aUnPointDEau()) {
            $eaux = array_values(array_filter(
                $grille,
                static fn (Zone $z): bool => $z->getTerrain()->estUnPointDEau(),
            ));

            foreach ($grille as $zone) {
                // Toucher l'eau ne suffit pas : le doc 02 interdit aussi le
                // plein désert, et une case de sable bordant le Nil en reste.
                if ($zone->getTerrain()->estUnPointDEau() || TypeDeTerrain::Desert === $zone->getTerrain()) {
                    continue;
                }

                foreach ($eaux as $eau) {
                    if ($zone->estAdjacenteA($eau)) {
                        $candidates[] = $zone;
                        break;
                    }
                }
            }
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
     * Tirage pondéré du contenu, case par case (doc 02).
     *
     * @param list<Zone> $grille
     */
    private function remplirLeContenu(array $grille, GeographieDeRegion $geographie, int $difficulte): void
    {
        $poids = PoidsDeTirage::pourDifficulte($difficulte);
        $quantite = PoidsDeTirage::quantiteParGisement($difficulte);

        foreach ($grille as $zone) {
            if ($zone->porteLaVille()) {
                continue;
            }

            $this->tirerLeContenu($zone, $geographie, $poids, $quantite);
        }

        foreach (self::MATERIAUX_DE_ZONE_HUMIDE as $materiau) {
            $this->garantirUnGisementRiverain($grille, $geographie, $materiau, $quantite);
        }
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
     * Assure au moins un gisement de ce matériau en bordure d'eau, si la région
     * en porte. Une région qui n'en porte pas devra l'importer : le cas est
     * signalé au plan de bataille plutôt que corrigé en douce ici.
     *
     * Une case pouvant porter deux gisements, la berge choisie n'a pas à être
     * vierge : l'argile et les roseaux cohabitent volontiers sur un même marais.
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

        $berges = [];

        foreach ($grille as $zone) {
            if ($zone->porteLaVille() || $zone->getTerrain()->estUnPointDEau() || !$this->estRiveraine($grille, $zone)) {
                continue;
            }

            // Un gisement déjà tiré en bordure d'eau suffit : c'est bien là
            // qu'on le cherche.
            if (null !== $zone->gisementDe($materiau)) {
                return;
            }

            if ($zone->peutPorterUnGisementDePlus()) {
                $berges[] = $zone;
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

    private function tirerLeContenu(Zone $zone, GeographieDeRegion $geographie, PoidsDeTirage $poids, int $quantite): void
    {
        $terrain = $zone->getTerrain();
        $champPossible = $terrain->accepteUnChamp();
        $ressourcesPossibles = $terrain->estUnPointDEau()
            ? [Ressource::Poisson]
            : $geographie->ressourcesDeZone;

        $options = [];
        if ([] !== $ressourcesPossibles) {
            $options['ressource'] = $poids->ressource;
        }
        if ($champPossible) {
            $options['champ'] = $poids->champ;
        }
        $options['evenement'] = $poids->evenement;
        $options['vide'] = $poids->vide;

        match ($this->tirerParmi($options)) {
            'ressource' => $zone->poserUnGisement(
                $ressourcesPossibles[$this->hasard->getInt(0, \count($ressourcesPossibles) - 1)],
                $quantite,
            ),
            'champ' => $zone->poserUnContenu(ContenuDeZone::ChampEligible),
            'evenement' => $zone->poserUnContenu(ContenuDeZone::Evenement),
            default => $zone->poserUnContenu(ContenuDeZone::Rien),
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
