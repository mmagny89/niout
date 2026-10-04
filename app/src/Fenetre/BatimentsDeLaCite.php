<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Game\Effectifs;
use App\Game\EmplacementsDeLaVille;
use App\Game\TypeDeBatiment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les bâtiments de la cité, dans l'ordre du jeu — la Résidence, foyer de la
 * lignée, en tête —, chacun avec son équipage, son éventuel chantier et son
 * visuel : **le sprite de son palier** (`assets/images/ville/batiments/<type>_<palier>.webp`),
 * le même que celui posé sur la ville vue d'en haut — l'illustration suit le
 * niveau du bâtiment.
 *
 * Le contrôleur teste l'existence du fichier : sans image, le gabarit retombe sur
 * un monogramme.
 */
final readonly class BatimentsDeLaCite
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $racine,
    ) {
    }

    /**
     * @return list<array{type: TypeDeBatiment, batiment: ?Building, effectif: mixed, chantier: ?Chantier, visuel: ?string}>
     */
    public function pour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $effectifs = Effectifs::repartir($ville, $partie->getCycle());
        $dossier = $this->racine.'/assets/images/ville/batiments/';
        $cite = [];

        foreach (TypeDeBatiment::cases() as $type) {
            $batiment = $ville->batimentDeType($type);

            if (null === $batiment && !$type->estLeBatimentDeDepart()) {
                continue;
            }

            $chantier = null;

            foreach ($ville->getChantiers() as $candidat) {
                if ($candidat->getType() === $type) {
                    $chantier = $candidat;
                }
            }

            // Le foyer de la lignée n'a pas de ligne en base : il est au niveau un.
            $palier = \sprintf('%s_%d.webp', $type->value, EmplacementsDeLaVille::palierDeSprite($batiment?->getNiveau() ?? 1));

            $cite[] = [
                'type' => $type,
                'batiment' => $batiment,
                'effectif' => $effectifs[$type->value] ?? null,
                'chantier' => $chantier,
                'visuel' => is_file($dossier.$palier) ? 'images/ville/batiments/'.$palier : null,
            ];
        }

        return $cite;
    }
}
