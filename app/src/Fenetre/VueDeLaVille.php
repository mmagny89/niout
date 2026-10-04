<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Game\EmplacementsDeLaVille;
use App\Game\TypeDeBatiment;

/**
 * La ville vue d'en haut : un visuel, des bâtiments posés sur leurs enclos, et
 * un clic qui ouvre le bon panneau.
 *
 * Remplace la carte quand on clique la ville (`carte?vue=ville`). Un bâtiment
 * dressé montre son sprite — le palier suit son niveau — et ouvre sa fenêtre ;
 * un enclos dont le bâtiment n'existe pas encore reste vide, et mène à la
 * Résidence familiale, où se lit ce qu'il reste à bâtir.
 *
 * **Tout est en pourcentages du visuel** : le gabarit n'a ni pixel ni échelle à
 * connaître, la vue tient sur un téléphone comme sur un grand écran.
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

        foreach (EmplacementsDeLaVille::tous() as $enclos) {
            $type = $enclos['type'];
            $batiment = null === $type ? null : $ville->batimentDeType($type);
            // La Résidence est là dès le premier jour, sans chantier ni ligne en
            // base : le foyer de la lignée.
            $dresse = null !== $type && (null !== $batiment || $type->estLeBatimentDeDepart());
            $niveau = $batiment?->getNiveau() ?? 1;

            $emplacements[] = [
                'type' => $type,
                'libre' => null === $type,
                'dresse' => $dresse,
                'niveau' => $dresse ? $niveau : null,
                // Un enclos : le losange, en pourcentages du visuel.
                'gauche' => self::pourcent($enclos['x'] - $enclos['demiLargeur'], EmplacementsDeLaVille::LARGEUR),
                'haut' => self::pourcent($enclos['y'] - $enclos['demiHauteur'], EmplacementsDeLaVille::HAUTEUR),
                'largeur' => self::pourcent(2 * $enclos['demiLargeur'], EmplacementsDeLaVille::LARGEUR),
                'hauteur' => self::pourcent(2 * $enclos['demiHauteur'], EmplacementsDeLaVille::HAUTEUR),
                // Le sprite : centré sur l'enclos, le pied un peu sous son centre.
                'spriteX' => self::pourcent($enclos['x'], EmplacementsDeLaVille::LARGEUR),
                'spriteY' => self::pourcent(
                    $enclos['y'] + $enclos['demiHauteur'] * EmplacementsDeLaVille::DECALAGE_DU_PIED,
                    EmplacementsDeLaVille::HAUTEUR,
                ),
                'spriteLargeur' => self::pourcent(
                    2 * $enclos['demiLargeur'] * EmplacementsDeLaVille::FACTEUR_DE_LARGEUR,
                    EmplacementsDeLaVille::LARGEUR,
                ),
                'sprite' => $dresse
                    ? \sprintf('images/ville/batiments/%s_%d.webp', $type->value, EmplacementsDeLaVille::palierDeSprite($niveau))
                    : null,
                // Les bâtiments du fond se peignent avant ceux de devant.
                'profondeur' => $enclos['y'],
                // Où mène le clic : le bâtiment s'il existe, sinon la liste de
                // ce qu'il reste à bâtir.
                'onglet' => $dresse ? $type->value : TypeDeBatiment::ResidenceFamiliale->value,
                'libelle' => null === $type ? null : $type->libelle(),
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
