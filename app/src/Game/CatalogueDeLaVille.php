<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\City;

/**
 * Dresse, pour une ville donnée, ce qu'elle peut construire ou améliorer.
 *
 * Chaque empêchement porte son motif : un bâtiment grisé sans explication
 * laisserait le joueur deviner s'il s'agit d'un manque de ressources, d'une
 * contrainte géographique ou d'un défaut du jeu.
 */
final readonly class CatalogueDeLaVille
{
    /**
     * @return list<OffreDeConstruction>
     */
    public function pour(City $ville): array
    {
        $offres = [];

        foreach (TypeDeBatiment::constructibles() as $type) {
            $offres[] = $this->offrePour($ville, $type);
        }

        return $offres;
    }

    private function offrePour(City $ville, TypeDeBatiment $type): OffreDeConstruction
    {
        $existant = $ville->batimentDeType($type);

        if (null !== $existant) {
            $cout = $existant->coutDeLaMonteeDeNiveau();

            if (null === $cout) {
                return OffreDeConstruction::empechee(
                    $type, $existant, null,
                    \sprintf('Niveau maximal atteint dans cette région (%d).', $existant->niveauMaxAtteignable()),
                );
            }

            return $this->verifierLesMoyens($ville, $type, $existant, $cout);
        }

        // Le Port n'a de sens qu'au bord de l'eau (doc 01). La carte sait déjà
        // répondre, mais la pêche qui justifie le bâtiment arrive au lot 3.6 :
        // le laisser bâtir maintenant donnerait un quai sans usage.
        if ($type->exigeUnPointDEau()) {
            return OffreDeConstruction::empechee(
                $type, null, $type->coutDeBase(),
                $ville->jouxteUnPointDEau()
                    ? 'Votre ville borde bien l\'eau. La pêche et le commerce naval arrivent au prochain lot.'
                    : 'Exige un point d\'eau adjacent à la ville.',
            );
        }

        return $this->verifierLesMoyens($ville, $type, null, $type->coutDeBase()->pourNiveau(1));
    }

    private function verifierLesMoyens(
        City $ville,
        TypeDeBatiment $type,
        ?Building $existant,
        CoutDeConstruction $cout,
    ): OffreDeConstruction {
        $manques = $ville->manquesPour($cout);

        if ([] !== $manques) {
            return OffreDeConstruction::empechee(
                $type, $existant, $cout,
                \sprintf('Il vous manque %s.', implode(', ', $manques)),
            );
        }

        return OffreDeConstruction::possible($type, $existant, $cout);
    }
}
