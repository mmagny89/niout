<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\City;
use App\Game\PrixDuMarche;
use App\Game\Ressource;

/**
 * Ce que la ville a en réserve, à l'Entrepôt.
 */
final class RepartitionDesReserves
{
    /**
     * Ce que la ville a en réserve, ce qu'elle en garde, ce qui part — une
     * ligne par ressource négociable et non vide.
     *
     * Le seuil est `null` tant qu'aucune consigne n'est posée : le silence se
     * lit « je garde tout », et l'écran doit le dire ainsi plutôt que d'
     * afficher un zéro qui voudrait dire l'inverse.
     *
     * @return list<array{ressource: Ressource, enReserve: int, prix: int, seuil: ?int, surplus: int}>
     */
    public static function pour(City $ville): array
    {
        $lignes = [];

        foreach ($ville->getStock() as $ligne) {
            $ressource = $ligne->getRessource();
            $prix = PrixDuMarche::pour($ressource);

            if (null === $prix || $ligne->getQuantite() < 1) {
                continue;
            }

            $reserve = $ville->reserveGardeeDe($ressource);

            $lignes[] = [
                'ressource' => $ressource,
                'enReserve' => $ligne->getQuantite(),
                'prix' => $prix,
                'seuil' => $reserve?->getQuantiteGardee(),
                'surplus' => $reserve?->surplusDans($ville) ?? 0,
            ];
        }

        usort($lignes, static fn (array $a, array $b): int => $a['ressource']->libelle() <=> $b['ressource']->libelle());

        return $lignes;
    }
}
