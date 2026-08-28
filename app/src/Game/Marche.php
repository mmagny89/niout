<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La vente au Marché : échanger son surplus contre des deben (doc 01, doc 08).
 *
 * Version minimale et volontairement incomplète — l'achat, les prix fluctuants
 * selon l'offre et la demande, les lots simultanés et les caravanes de
 * l'Entrepôt relèvent de la Phase 5. Seule la vente est avancée ici, parce
 * qu'elle règle un blocage de fond : sans elle, **la monnaie n'a aucune source**. La
 * dotation royale en donne une fois pour toutes, chaque bâtiment en consomme,
 * et toute partie finissait donc par se figer, faute de pouvoir en gagner.
 *
 * C'est aussi ce qui donne enfin un sens à l'exploitation d'un gisement au-delà
 * de la construction : un filon de calcaire devient un revenu.
 *
 * Le Marché est enfin la **seule source de renommée** aujourd'hui branchée :
 * le doc 13 accorde +1 pour un « gros contrat commercial conclu », et sans lui
 * la renommée resterait à zéro pour toujours — ce qui rendrait inertes le prix
 * d'un appel d'habitants et la migration spontanée, tous deux indexés dessus.
 */
final readonly class Marche
{
    /**
     * À partir de combien de deben une vente est un « gros contrat » au sens du
     * doc 13, qui accorde alors +1 de renommée. **Valeur inventée** : le
     * document nomme le fait, jamais le seuil.
     *
     * Calibrée sur l'économie du Delta, où une vente courante rapporte une
     * poignée de deben : il faut accumuler puis écouler un vrai lot, pas
     * revendre trois roseaux. Les cent points de renommée demandent donc une
     * centaine de gros contrats — c'est l'affaire d'une partie entière, ce qui
     * est le propos du doc 13 : la renommée est un héritage, pas un compteur
     * qu'on remplit en une saison.
     */
    public const int RECETTE_DUN_GROS_CONTRAT = 40;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vend une quantité d'une ressource et crédite les deben correspondants.
     *
     * @return int les deben effectivement encaissés
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
            throw new VenteImpossible(\sprintf('Le %s ne se négocie pas : c\'est la monnaie.', $ressource->libelle()));
        }

        if (!$ville->debiterRessources([$ressource->value => $quantite])) {
            throw new VenteImpossible(\sprintf('Vous n\'avez pas %d %s à vendre.', $quantite, $ressource->libelle()));
        }

        $recette = $prix * $quantite;
        $ville->crediterRessources([Ressource::Deben->value => $recette]);

        if ($recette >= self::RECETTE_DUN_GROS_CONTRAT) {
            $partie->getFamille()->ajusterRenommee(1);
        }

        $this->entityManager->flush();

        return $recette;
    }

    /**
     * Ce que la ville peut mettre en vente : ses lignes de stock non vides qui
     * ont un cours. Le deben en est naturellement exclu : il est la monnaie.
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
