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

        // Le Port dépend de la géographie de la ville, qui n'existera qu'avec
        // la carte (Phase 3).
        if ($type->exigeUnPointDEau()) {
            return OffreDeConstruction::empechee(
                $type, null, $type->coutDeBase(),
                'Exige un point d\'eau adjacent à la ville. La carte arrive en Phase 3.',
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
        // Le lin est une ressource agricole : il n'existera qu'avec les champs.
        if ($cout->lin > 0) {
            return OffreDeConstruction::empechee(
                $type, $existant, $cout,
                'Réclame du lin en offrande. L\'agriculture arrive en Phase 3.',
            );
        }

        $manques = [];
        foreach ($cout->enRessources() as $valeur => $exige) {
            $ressource = Ressource::from($valeur);
            $possede = $ville->quantite($ressource);

            if ($exige > $possede) {
                $manques[] = \sprintf('%d %s', $exige - $possede, $ressource->libelle());
            }
        }

        if ([] !== $manques) {
            return OffreDeConstruction::empechee(
                $type, $existant, $cout,
                \sprintf('Il vous manque %s.', implode(', ', $manques)),
            );
        }

        return OffreDeConstruction::possible($type, $existant, $cout);
    }
}
