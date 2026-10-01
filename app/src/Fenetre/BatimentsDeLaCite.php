<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\Building;
use App\Entity\Chantier;
use App\Entity\GameSave;
use App\Game\Effectifs;
use App\Game\TypeDeBatiment;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Les bâtiments de la cité, dans l'ordre du jeu — la Résidence, foyer de la
 * lignée, en tête —, chacun avec son équipage, son éventuel chantier et son
 * visuel s'il existe (`assets/images/batiments/<type>.webp`).
 *
 * Déposer l'image suffit à remplacer le monogramme : le contrôleur teste
 * l'existence du fichier, et le gabarit n'a pas à changer.
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
        $dossier = $this->racine.'/assets/images/batiments/';
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

            $cite[] = [
                'type' => $type,
                'batiment' => $batiment,
                'effectif' => $effectifs[$type->value] ?? null,
                'chantier' => $chantier,
                'visuel' => is_file($dossier.$type->value.'.webp') ? 'images/batiments/'.$type->value.'.webp' : null,
            ];
        }

        return $cite;
    }

    /**
     * Les chantiers de bâtiments qui n'existent pas encore : pas d'onglet, mais
     * le joueur doit voir que quelque chose se dresse.
     *
     * @return list<Chantier>
     */
    public function enChantier(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return array_values(array_filter(
            $ville->getChantiers()->toArray(),
            static fn (Chantier $chantier): bool => null === $ville->batimentDeType($chantier->getType()),
        ));
    }
}
