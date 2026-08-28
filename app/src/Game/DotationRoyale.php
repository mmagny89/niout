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
     *
     * Calibré pour couvrir **d'emblée le Grenier et le Marché** (15/15/15 et
     * 15/5/15 au niveau 1, doc 01), qui ouvrent les deux boucles du jeu : le
     * Grenier rend l'agriculture utile, le Marché est la seule entrée d'or.
     *
     * Les couvrir tous les deux n'est pas une largesse mais une condition de
     * jouabilité : une partie qui n'atteindrait pas le Marché ne pourrait plus
     * jamais gagner un or, et se figerait au deuxième bâtiment. La marge
     * au-delà laisse le droit à une dépense malheureuse.
     */
    private const int ROSEAUX = 35;
    private const int ARGILE = 30;

    /**
     * De quoi nourrir la famille fondatrice pendant un an complet — le cadeau
     * du pharaon couvre la première année de vivres, pas seulement le temps
     * d'attendre la première moisson : sans lui, une partie qui tarderait à
     * bâtir son Grenier ou à établir ses champs tomberait en famine avant
     * d'en avoir eu la chance. Le Quartier d'habitation, lui, n'existe pas
     * encore à l'arrivée : seule la population de base compte ici
     * (`Population::HABITANTS_DE_BASE`).
     *
     * Couvre aussi, comme avant, la marge nécessaire au premier éclaireur : à
     * ce niveau de population, une année de vivres dépasse largement les
     * quelques vivres qu'exige une expédition (doc 04).
     */
    private const int PROVISIONS = Population::HABITANTS_DE_BASE * Population::RATION_PAR_HABITANT * DateDeJeu::CYCLES_PAR_ANNEE;

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
     * Le pharaon envoie de quoi ouvrir la partie, les mêmes matériaux partout :
     * roseaux et argile, dont sont faits tous les bâtiments de départ.
     */
    public static function pour(int $difficulte): self
    {
        return new self(
            or: self::OR_DE_BASE + self::OR_PAR_NIVEAU_DE_DIFFICULTE * $difficulte,
            provisions: self::PROVISIONS,
            materiaux: [
                // Les deux matériaux de la brique crue et de sa toiture : ce
                // dont tous les bâtiments d'ouverture sont faits (doc 01).
                Ressource::Roseaux->value => self::ROSEAUX,
                Ressource::Argile->value => self::ARGILE,
            ],
        );
    }
}
