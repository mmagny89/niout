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
    private const int BOIS = 35;
    private const int ARGILE = 30;

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
     * Ce que la couronne envoie quand la région ne produit pas elle-même de
     * bois : le cèdre du Levant, que l'État faisait réellement venir de Byblos.
     */
    private const Ressource BOIS_DE_SECOURS = Ressource::BoisDeCedre;

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
                // Toujours de l'argile, jamais du calcaire : les bâtiments qui
                // ouvrent une partie sont tous en brique crue (doc 01).
                Ressource::Argile->value => self::ARGILE,
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
