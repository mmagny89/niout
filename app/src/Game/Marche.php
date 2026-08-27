<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La vente au Marché : échanger son surplus contre de l'or (doc 01, doc 08).
 *
 * Version minimale et volontairement incomplète — l'achat, les prix fluctuants
 * selon l'offre et la demande, les lots simultanés et les caravanes de
 * l'Entrepôt relèvent de la Phase 5. Seule la vente est avancée ici, parce
 * qu'elle règle un blocage de fond : sans elle, **l'or n'a aucune source**. La
 * dotation royale en donne une fois pour toutes, chaque bâtiment en consomme,
 * et toute partie finissait donc par se figer, faute de pouvoir en gagner.
 *
 * C'est aussi ce qui donne enfin un sens à l'exploitation d'un gisement au-delà
 * de la construction : un filon de calcaire devient un revenu.
 */
final readonly class Marche
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vend une quantité d'une ressource et crédite l'or correspondant.
     *
     * @return int l'or effectivement encaissé
     *
     * @throws VenteImpossible
     */
    public function vendre(GameSave $partie, Ressource $ressource, int $quantite): int
    {
        $ville = $partie->getVille();

        if (!$ville->possede(TypeDeBatiment::Marche)) {
            throw new VenteImpossible('Il vous faut un Marché pour écouler quoi que ce soit.');
        }

        if ($quantite < 1) {
            throw new VenteImpossible('Il faut vendre au moins une unité.');
        }

        $prix = PrixDuMarche::pour($ressource);

        if (null === $prix) {
            throw new VenteImpossible(\sprintf('L\'%s ne se négocie pas : c\'est la monnaie.', $ressource->libelle()));
        }

        if (!$ville->debiterRessources([$ressource->value => $quantite])) {
            throw new VenteImpossible(\sprintf('Vous n\'avez pas %d %s à vendre.', $quantite, $ressource->libelle()));
        }

        $recette = $prix * $quantite;
        $ville->crediterRessources([Ressource::Or->value => $recette]);

        $this->entityManager->flush();

        return $recette;
    }

    /**
     * Ce que la ville peut mettre en vente : ses lignes de stock non vides qui
     * ont un cours. L'or en est naturellement exclu.
     *
     * @return list<array{ressource: Ressource, quantite: int, prix: int}>
     */
    public function etalPour(GameSave $partie): array
    {
        $etal = [];

        foreach ($partie->getVille()->getStock() as $ligne) {
            $ressource = $ligne->getRessource();
            $prix = PrixDuMarche::pour($ressource);

            if (null === $prix || $ligne->getQuantite() < 1) {
                continue;
            }

            $etal[] = [
                'ressource' => $ressource,
                'quantite' => $ligne->getQuantite(),
                'prix' => $prix,
            ];
        }

        usort($etal, static fn (array $a, array $b): int => $b['prix'] <=> $a['prix']);

        return $etal;
    }
}
