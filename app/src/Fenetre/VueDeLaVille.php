<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Game\AncragesDesSprites;
use App\Game\EmplacementsDeLaVille;
use App\Game\TypeDeBatiment;

/**
 * La ville vue d'en haut : un visuel, des sprites posés sur leurs lots, et un
 * clic qui ouvre le bon panneau.
 *
 * Remplace la carte quand on clique la ville (`carte?vue=ville`). Un bâtiment
 * dressé montre son sprite — le palier suit son niveau — et ouvre sa fenêtre ;
 * un lot dont le bâtiment n'existe pas encore montre un lot vide, et mène à la
 * Résidence familiale, où se lit ce qu'il reste à bâtir. Les lots que le jeu
 * n'emploie pas sont du décor.
 *
 * **L'échelle vient du lot** : chaque sprite est posé pour que son lot ait la
 * largeur de la clairière du plan, et que le centre de l'un tombe sur le centre
 * de l'autre (`AncragesDesSprites`). **Tout est en pourcentages du visuel** : le
 * gabarit n'a ni pixel ni échelle à connaître.
 */
final readonly class VueDeLaVille
{
    /**
     * @return array{visuel: string, avecPort: bool, emplacements: list<array<string, mixed>>}
     */
    public function pour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $avecPort = $ville->jouxteUnPointDEau();
        $emplacements = [];

        foreach (EmplacementsDeLaVille::tous() as $lot) {
            $type = $lot['type'];
            $batiment = null === $type ? null : $ville->batimentDeType($type);
            // La Résidence est là dès le premier jour, sans chantier ni ligne en
            // base : le foyer de la lignée.
            $dresse = null !== $type && (null !== $batiment || $type->estLeBatimentDeDepart());
            $niveau = $batiment?->getNiveau() ?? 1;

            if ($dresse) {
                $ancrage = AncragesDesSprites::BATIMENTS[$type->value];
                $sprite = \sprintf('images/ville/batiments/%s_%d.webp', $type->value, EmplacementsDeLaVille::palierDeSprite($niveau));
            } else {
                // Un lot vide : un bâtiment pas encore bâti, ou du décor.
                $ancrage = AncragesDesSprites::LOTS[$lot['classe']];
                $sprite = \sprintf('images/ville/lots/lot_%s.webp', $lot['classe']);
            }

            // Le sprite est mis à l'échelle qui donne à son lot la largeur de la
            // clairière, puis posé pour que les deux centres coïncident.
            $echelle = EmplacementsDeLaVille::LARGEUR_DES_LOTS[$lot['classe']] * EmplacementsDeLaVille::FACTEUR_DE_LARGEUR
                / $ancrage['largeurDuLot'];

            $emplacements[] = [
                'type' => $type,
                'decor' => null === $type,
                'dresse' => $dresse,
                'niveau' => $dresse ? $niveau : null,
                'sprite' => $sprite,
                'gauche' => self::pourcent($lot['x'] - $ancrage['centreX'] * $echelle, EmplacementsDeLaVille::LARGEUR),
                'haut' => self::pourcent($lot['y'] - $ancrage['centreY'] * $echelle, EmplacementsDeLaVille::HAUTEUR),
                'largeur' => self::pourcent($ancrage['largeur'] * $echelle, EmplacementsDeLaVille::LARGEUR),
                // Les sprites du fond se peignent avant ceux de devant.
                'profondeur' => $lot['y'],
                // Où mène le clic : le bâtiment s'il existe, sinon la liste de
                // ce qu'il reste à bâtir.
                'onglet' => $dresse ? $type->value : TypeDeBatiment::ResidenceFamiliale->value,
                'libelle' => $type?->libelle(),
            ];
        }

        usort($emplacements, static fn (array $a, array $b): int => $a['profondeur'] <=> $b['profondeur']);

        return [
            'visuel' => $avecPort ? 'images/ville/ville_port.webp' : 'images/ville/ville.webp',
            'avecPort' => $avecPort,
            'emplacements' => $emplacements,
        ];
    }

    private static function pourcent(float|int $valeur, int $total): float
    {
        return round($valeur / $total * 100, 3);
    }
}
