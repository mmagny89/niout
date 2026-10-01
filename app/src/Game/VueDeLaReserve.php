<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Une réserve — le Grenier ou l'Entrepôt — rangée en cases, pour qu'on voie ce
 * qu'elle contient et ce qui lui reste de place.
 *
 * **Une représentation, pas un état** : rien ici ne se persiste, tout se
 * déduit de ce que la ville garde (`City::quantite()`) et de son plafond
 * (`Stockage`). Chaque case vaut un `pas` de ressource — le plus petit pas
 * « rond » qui tienne la réserve en soixante cases au plus, de sorte qu'un
 * Entrepôt de niveau 8 ne devienne pas une grille de mille carreaux.
 *
 * Les vivres se rangent **ressource par ressource** (six, tout au plus : on lit
 * la réserve d'un coup d'œil) ; les matériaux, qui sont une trentaine, se
 * rangent **par famille** (`FamilleDeRessource`), le détail restant dans la
 * légende. C'est le même classement d'affichage que la barre de jeu, et il ne
 * décide de rien.
 *
 * Les cases se comptent par **arrondi au supérieur sur le cumul** : un lot
 * minuscule garde donc au moins une case, et le total ne dépasse jamais
 * l'occupation arrondie.
 */
final readonly class VueDeLaReserve
{
    public const int CASES_MAX = 60;

    /**
     * @var list<int> les pas de case admis, du plus fin au plus large
     */
    private const array PAS = [1, 2, 5, 10, 20, 25, 50, 100];

    /**
     * @return array{
     *     plafond: int,
     *     occupation: int,
     *     libre: int,
     *     pas: int,
     *     casesPleines: int,
     *     casesEnTout: int,
     *     saturee: bool,
     *     groupes: list<array{cle: string, libelle: string, quantite: int, cases: int, lignes: list<array{ressource: Ressource, quantite: int}>}>
     * }
     */
    public static function pour(City $ville, bool $vivres): array
    {
        $plafond = $vivres ? Stockage::plafondDesVivres($ville) : Stockage::plafondDesMateriaux($ville);
        $contenu = self::contenu($ville, $vivres);
        $occupation = array_sum(array_map(static fn (array $g): int => $g['quantite'], $contenu));

        // Le mode d'essai dépasse tous les plafonds : la grille s'élargit
        // plutôt que de mentir.
        $taille = max($plafond, $occupation);
        $pas = self::pasPour($taille);
        $casesEnTout = max(1, (int) ceil($taille / $pas));

        $cumul = 0;
        $dejaPosees = 0;

        foreach ($contenu as &$groupe) {
            $cumul += $groupe['quantite'];
            $jusqua = (int) ceil($cumul / $pas);
            $groupe['cases'] = $jusqua - $dejaPosees;
            $dejaPosees = $jusqua;
        }

        unset($groupe);

        return [
            'plafond' => $plafond,
            'occupation' => $occupation,
            'libre' => max(0, $plafond - $occupation),
            'pas' => $pas,
            'casesPleines' => $dejaPosees,
            'casesEnTout' => $casesEnTout,
            'saturee' => Stockage::saturationProche($occupation, $plafond),
            'groupes' => $contenu,
        ];
    }

    public static function pasPour(int $taille): int
    {
        foreach (self::PAS as $pas) {
            if ((int) ceil($taille / $pas) <= self::CASES_MAX) {
                return $pas;
            }
        }

        return self::PAS[array_key_last(self::PAS)];
    }

    /**
     * Ce que la réserve contient, groupé et trié du plus gros au plus petit.
     *
     * @return list<array{cle: string, libelle: string, quantite: int, cases: int, lignes: list<array{ressource: Ressource, quantite: int}>}>
     */
    private static function contenu(City $ville, bool $vivres): array
    {
        $groupes = [];

        foreach (Ressource::cases() as $ressource) {
            if ($ressource->estLaMonnaie() || $ressource->estNourriture() !== $vivres) {
                continue;
            }

            $quantite = $ville->quantite($ressource);

            if ($quantite < 1) {
                continue;
            }

            $famille = $ressource->famille();
            $cle = $vivres || null === $famille ? $ressource->value : $famille->value;

            $groupes[$cle] ??= [
                'cle' => $cle,
                'libelle' => $vivres || null === $famille ? $ressource->libelle() : $famille->libelle(),
                'quantite' => 0,
                'cases' => 0,
                'lignes' => [],
            ];

            $groupes[$cle]['quantite'] += $quantite;
            $groupes[$cle]['lignes'][] = ['ressource' => $ressource, 'quantite' => $quantite];
        }

        foreach ($groupes as &$groupe) {
            usort($groupe['lignes'], static fn (array $a, array $b): int => $b['quantite'] <=> $a['quantite']);
        }

        unset($groupe);

        $groupes = array_values($groupes);
        usort($groupes, static fn (array $a, array $b): int => $b['quantite'] <=> $a['quantite']);

        return $groupes;
    }
}
