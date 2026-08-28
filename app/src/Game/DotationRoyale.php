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
    private const int DEBEN_DE_BASE = 50;
    private const int DEBEN_PAR_NIVEAU_DE_DIFFICULTE = 10;

    /**
     * **Le pharaon avance aussi une année de salaires** (décision de la
     * joueuse), en plus de l'année de vivres : de quoi employer les bras qu'il
     * envoie pendant vingt-cinq quinzaines. Il finance le démarrage, pas la
     * suite — passé la première année, la ville paie ses gens sur ce qu'elle
     * gagne, ou renvoie.
     *
     * Sans cette avance, la charge salariale du lot 4.6 tombait sur une bourse
     * calibrée pour deux bâtiments : la partie se figeait avant d'avoir pu
     * ouvrir la moindre carrière.
     */
    private static function anneeDeSalaires(): int
    {
        return Population::ACTIFS_AU_DEPART
            * Salaires::SALAIRE_DUN_TRAVAILLEUR
            * DateDeJeu::CYCLES_PAR_ANNEE;
    }

    /**
     * De quoi couvrir le premier bâtiment, quelle que soit la région.
     *
     * Calibré pour couvrir **d'emblée le Grenier et le Marché** (15/15/15 et
     * 15/5/15 au niveau 1, doc 01), qui ouvrent les deux boucles du jeu : le
     * Grenier rend l'agriculture utile, le Marché est la seule entrée de deben.
     *
     * Les couvrir tous les deux n'est pas une largesse mais une condition de
     * jouabilité : une partie qui n'atteindrait pas le Marché ne pourrait plus
     * jamais gagner un or, et se figerait au deuxième bâtiment. La marge
     * au-delà laisse le droit à une dépense malheureuse.
     */
    private const int ROSEAUX = 35;
    private const int ARGILE = 30;

    private function __construct(
        public int $deben,
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
            Ressource::Deben->value => $this->deben,
            Ressource::Ble->value => $this->provisions,
            ...$this->materiaux,
        ];
    }

    /**
     * Le pharaon envoie de quoi ouvrir la partie, les mêmes matériaux partout :
     * roseaux et argile, dont sont faits tous les bâtiments de départ.
     *
     * **Les vivres, eux, dépendent de la famille qu'il envoie** : une année
     * complète de rations pour ce foyer-là, calculée sur sa consommation
     * réelle. Le pharaon ne dote pas une population moyenne mais celle qu'il
     * expédie — un couple avec six enfants part avec davantage de grain qu'un
     * couple sans enfant. Sans cette année de vivres, une partie qui tarderait
     * à bâtir son Grenier ou à établir ses champs tomberait en famine avant
     * d'en avoir eu la chance.
     *
     * @param int $consommationParQuinzaine ce que mange la famille fondatrice
     */
    public static function pour(int $difficulte, int $consommationParQuinzaine): self
    {
        return new self(
            deben: self::DEBEN_DE_BASE + self::DEBEN_PAR_NIVEAU_DE_DIFFICULTE * $difficulte + self::anneeDeSalaires(),
            provisions: $consommationParQuinzaine * DateDeJeu::CYCLES_PAR_ANNEE,
            materiaux: [
                // Les deux matériaux de la brique crue et de sa toiture : ce
                // dont tous les bâtiments d'ouverture sont faits (doc 01).
                Ressource::Roseaux->value => self::ROSEAUX,
                Ressource::Argile->value => self::ARGILE,
            ],
        );
    }
}
