<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le don du pharaon au lancement d'une mission (doc 13).
 *
 * Toujours accordé, quel que soit le parcours du joueur : c'est la base de
 * départ, distincte de l'héritage familial qui viendra s'y superposer. Une
 * mission plus difficile appelle un soutien plus consistant, sans quoi elle ne
 * serait pas viable.
 */
final readonly class DotationRoyale
{
    private const int OR_DE_BASE = 50;
    private const int OR_PAR_NIVEAU_DE_DIFFICULTE = 10;

    /**
     * De quoi couvrir le premier bâtiment, quelle que soit la région.
     */
    private const int BOIS = 20;
    private const int PIERRE = 10;

    /**
     * De quoi tenir jusqu'à la première moisson.
     *
     * Valeur inventée, mais la dotation ne peut pas s'en passer : une expédition
     * se paie en partie en vivres (doc 04), et les champs ne donnent rien avant
     * Chémou. Sans ce grain de départ, le joueur ne pourrait pas envoyer son
     * premier éclaireur — donc jamais trouver la terre où semer.
     */
    private const int PROVISIONS = 40;

    /**
     * Ce que la couronne envoie quand la région ne produit pas elle-même le
     * matériau : le cèdre du Levant et le calcaire de Tourah, les deux
     * matériaux que l'État faisait réellement circuler dans tout le pays.
     */
    private const Ressource BOIS_DE_SECOURS = Ressource::BoisDeCedre;
    private const Ressource PIERRE_DE_SECOURS = Ressource::Calcaire;

    private function __construct(
        public int $or,
        public int $provisions,
        /** @var array<string, int> valeur de Ressource => quantité */
        private array $materiaux,
    ) {
    }

    /**
     * La dotation exprimée en ressources, prête à créditer un stock.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function enRessources(): array
    {
        return [
            Ressource::Or->value => $this->or,
            Ressource::Ble->value => $this->provisions,
            ...$this->materiaux,
        ];
    }

    /**
     * Le pharaon envoie de préférence ce que la région travaille elle-même —
     * les roseaux du Delta plutôt qu'un cèdre venu de Byblos. Il complète
     * seulement là où la région ne produit rien de la famille voulue.
     */
    public static function pour(int $difficulte, GeographieDeRegion $geographie): self
    {
        return new self(
            or: self::OR_DE_BASE + self::OR_PAR_NIVEAU_DE_DIFFICULTE * $difficulte,
            provisions: self::PROVISIONS,
            materiaux: [
                self::materiauLocal($geographie, FamilleDeMateriau::Bois, self::BOIS_DE_SECOURS)->value => self::BOIS,
                self::materiauLocal($geographie, FamilleDeMateriau::Pierre, self::PIERRE_DE_SECOURS)->value => self::PIERRE,
            ],
        );
    }

    private static function materiauLocal(
        GeographieDeRegion $geographie,
        FamilleDeMateriau $famille,
        Ressource $defaut,
    ): Ressource {
        foreach ($geographie->ressourcesDeZone as $ressource) {
            if ($famille->contient($ressource)) {
                return $ressource;
            }
        }

        return $defaut;
    }
}
