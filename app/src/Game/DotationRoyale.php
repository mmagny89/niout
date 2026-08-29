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
    /**
     * Les quatre bâtiments que le pharaon finance d'emblée (décision de la
     * joueuse). Ils ouvrent chacun une boucle du jeu, et l'ensemble suffit à
     * « faire rouler la ville » :
     *
     * - le **Quartier d'habitation** loge les volontaires envoyés, sans quoi
     *   la ville manque de logements dès la première quinzaine et ne peut ni
     *   croître ni embaucher ;
     * - le **Grenier** rend l'agriculture utile — sans lui, un champ travaille
     *   pour rien ;
     * - le **Marché** est la seule entrée de deben du jeu ;
     * - l'**Entrepôt** ouvrira les routes commerciales (Phase 5).
     *
     * Champs et carrières, eux, ne coûtent rien à ouvrir : les matériaux
     * vitaux sont garantis dans l'anneau des huit cases autour de la ville
     * (`GenerateurDeCarte`), et les reconnaître y est gratuit. Rien ne
     * s'oppose donc à ce que la ville produise dès la première quinzaine.
     */
    private const array BATIMENTS_DOUVERTURE = [
        TypeDeBatiment::QuartierDHabitation,
        TypeDeBatiment::Grenier,
        TypeDeBatiment::Marche,
        TypeDeBatiment::Entrepot,
    ];

    /**
     * Marge en deben par niveau de difficulté : une mission plus rude appelle
     * un soutien plus consistant, sans quoi elle ne serait pas viable.
     */
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
     * Le pharaon envoie de quoi ouvrir la partie — exactement de quoi dresser
     * les quatre bâtiments d'ouverture, **calculé sur leurs coûts réels**
     * plutôt que recopié. Un coût qui changerait dans le catalogue changerait
     * la dotation avec lui ; l'inverse laisserait une partie bloquée sans
     * qu'aucun test ne le dise.
     *
     * La dotation ne laisse **aucune marge en matériaux** : elle couvre les
     * quatre bâtiments et rien de plus. Une dépense malheureuse ne bloque pas
     * pour autant — roseaux et argile sont garantis autour de la ville, et
     * les rouvrir ne coûte rien.
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
        $ouverture = self::coutDesBatimentsDouverture();
        $deben = $ouverture[Ressource::Deben->value] ?? 0;
        unset($ouverture[Ressource::Deben->value]);

        return new self(
            deben: $deben + self::anneeDeSalaires() + self::DEBEN_PAR_NIVEAU_DE_DIFFICULTE * $difficulte,
            provisions: $consommationParQuinzaine * DateDeJeu::CYCLES_PAR_ANNEE,
            materiaux: $ouverture,
        );
    }

    /**
     * Ce que coûtent, ensemble, les quatre bâtiments d'ouverture au niveau 1.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public static function coutDesBatimentsDouverture(): array
    {
        $total = [];

        foreach (self::BATIMENTS_DOUVERTURE as $type) {
            foreach ($type->coutDeBase()->enRessources() as $valeur => $quantite) {
                $total[$valeur] = ($total[$valeur] ?? 0) + $quantite;
            }
        }

        return $total;
    }
}
