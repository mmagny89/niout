<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les partenaires commerciaux de chaque mission (doc 12).
 *
 * Toutes les routes sont **réellement attestées** : le Chemin d'Horus qui
 * longe la côte du Sinaï, le Bahr Yousef qui relie le Fayoum au Nil, le Ouadi
 * Hammamat qui va du fleuve à la mer Rouge, le Darb el-Arbain qui monte de
 * Nubie. Elles ne sont pas des cases sur la carte mais des **débouchés**, hors
 * grille, ouverts par le niveau des bâtiments (doc 02, doc 12).
 *
 * **Les distances sont inventées** : le doc 12 nomme les routes sans les
 * chiffrer. Elles restent lisibles les unes par rapport aux autres — une
 * remontée du fleuve est courte, Byblos est loin, Pount est au bout du monde.
 */
final readonly class CataloguePartenaires
{
    /**
     * @return list<PartenaireCommercial>
     */
    public function pourLaMission(int $numero): array
    {
        return self::tous()[$numero] ?? [];
    }

    public function partenaire(int $numeroDeMission, string $cle): ?PartenaireCommercial
    {
        foreach ($this->pourLaMission($numeroDeMission) as $partenaire) {
            if ($partenaire->cle === $cle) {
                return $partenaire;
            }
        }

        return null;
    }

    /**
     * @return array<int, list<PartenaireCommercial>>
     */
    private static function tous(): array
    {
        return [
            1 => [
                self::partenaireDe('memphis', 'Memphis', TypeDeRoute::Fluviale, 2,
                    [Ressource::Calcaire, Ressource::Natron],
                    [Ressource::Ble, Ressource::Poterie, Ressource::Papyrus],
                    'Le Nil descend jusqu\'à vous : deux quinzaines de barque, et les carrières de Tourah répondent.'),
                self::partenaireDe('canaan', 'Canaan', TypeDeRoute::Terrestre, 4,
                    [Ressource::Cuivre, Ressource::BoisDeCedre],
                    [Ressource::Ble, Ressource::Lin, Ressource::Poterie],
                    'Le Chemin d\'Horus, ses forts et ses puits, jusqu\'aux villes de Canaan.'),
                self::partenaireDe('byblos', 'Byblos', TypeDeRoute::Maritime, 5,
                    [Ressource::BoisDeCedre, Ressource::Cuivre],
                    [Ressource::Ble, Ressource::Lin, Ressource::Papyrus],
                    'La Méditerranée, et le cèdre du Liban qu\'aucune terre d\'Égypte ne donne.'),
            ],
            2 => [
                self::partenaireDe('kerma', 'Kerma', TypeDeRoute::Fluviale, 3,
                    [Ressource::Ivoire, Ressource::Ebene, Ressource::PeauxEtPlumes],
                    [Ressource::Poterie, Ressource::Tissus, Ressource::Biere],
                    'Le fleuve poursuit vers le sud, jusqu\'à l\'ancien royaume de Koush.'),
                self::partenaireDe('allaqi', 'Ouadi el-Allaqi', TypeDeRoute::Terrestre, 4,
                    [Ressource::Or],
                    [Ressource::Ble, Ressource::Outils],
                    'La route de l\'or nubien, gardée de forteresses jusqu\'aux mines.'),
            ],
            3 => [
                self::partenaireDe('pount', 'Pount', TypeDeRoute::Maritime, 8,
                    [Ressource::Encens, Ressource::Myrrhe, Ressource::Ebene, Ressource::Ivoire, Ressource::PeauxEtPlumes],
                    [Ressource::Tissus, Ressource::Poterie],
                    'La traversée de la mer Rouge, la plus longue du jeu — et la seule qui rapporte l\'encens.'),
                self::partenaireDe('qena', 'Qena', TypeDeRoute::Terrestre, 3,
                    [Ressource::Ble, Ressource::Orge, Ressource::Biere],
                    [Ressource::Encens, Ressource::Myrrhe],
                    'La piste qui rejoint le Nil : c\'est par elle qu\'arrivent les vivres.'),
            ],
            4 => [
                self::partenaireDe('byblos-levant', 'Byblos', TypeDeRoute::Maritime, 3,
                    [Ressource::BoisDeCedre],
                    [Ressource::Ble, Ressource::Or, Ressource::Poterie],
                    'Le port levantin le plus proche, et le cèdre à sa source.'),
                self::partenaireDe('damas', 'Damas', TypeDeRoute::Terrestre, 4,
                    [Ressource::Cuivre, Ressource::LapisLazuli],
                    [Ressource::Lin, Ressource::Tissus, Ressource::Papyrus],
                    'La Via Maris vers l\'intérieur syrien, par où passe le lapis venu de bien plus loin.'),
            ],
            5 => [
                self::partenaireDe('coptos', 'Coptos', TypeDeRoute::Fluviale, 2,
                    [Ressource::Ble, Ressource::Argile],
                    [Ressource::Gres, Ressource::Tissus],
                    'Le fleuve, artère de la vallée : Thèbes est un carrefour avant d\'être une productrice.'),
                self::partenaireDe('hammamat', 'Ouadi Hammamat', TypeDeRoute::Terrestre, 4,
                    [Ressource::Grauwacke, Ressource::Or],
                    [Ressource::Ble, Ressource::Biere, Ressource::Outils],
                    'La route minière vers la mer Rouge, tête de pont du désert oriental.'),
            ],
            6 => [
                self::partenaireDe('hatnub', 'Hatnub', TypeDeRoute::Terrestre, 2,
                    [Ressource::Albatre],
                    [Ressource::Ble, Ressource::Outils],
                    'La piste des carrières, la mieux documentée d\'Égypte.'),
                self::partenaireDe('memphis-amont', 'Memphis', TypeDeRoute::Fluviale, 3,
                    [Ressource::Roseaux, Ressource::Poterie],
                    [Ressource::Albatre, Ressource::Calcaire],
                    'Le Nil vers le nord, et tout ce que le Delta sait faire.'),
            ],
            7 => [
                self::partenaireDe('nubie', 'Nubie', TypeDeRoute::Fluviale, 3,
                    [Ressource::Or, Ressource::Ivoire, Ressource::Ebene],
                    [Ressource::Ble, Ressource::Tissus, Ressource::Poterie],
                    'Le fleuve au-delà de la cataracte, avec sa rupture de charge obligatoire.'),
                self::partenaireDe('darb-el-arbain', 'Darb el-Arbain', TypeDeRoute::Terrestre, 5,
                    [Ressource::Encens, Ressource::PeauxEtPlumes],
                    [Ressource::Granite, Ressource::Biere],
                    'La grande route de Nubie, empruntée depuis l\'Ancien Empire.'),
            ],
            8 => [
                self::partenaireDe('bahr-yousef', 'Bahr Yousef', TypeDeRoute::Fluviale, 2,
                    [Ressource::Roseaux, Ressource::Lin, Ressource::BoisLocal],
                    [Ressource::Ble, Ressource::Poisson, Ressource::Natron],
                    'Le canal naturel qui relie le Fayoum au fleuve : une région intérieure, mais pas enclavée.'),
            ],
            9 => [
                self::partenaireDe('coptos-desert', 'Coptos', TypeDeRoute::Terrestre, 3,
                    [Ressource::Ble, Ressource::Orge, Ressource::Biere, Ressource::BoisLocal],
                    [Ressource::Grauwacke, Ressource::Or],
                    'La seule route du camp : sans elle, rien à manger. Le Ouadi Hammamat est structuré autour d\'elle.'),
            ],
            10 => [
                self::partenaireDe('ayn-sukhna', 'Ayn Sukhna', TypeDeRoute::Maritime, 4,
                    [Ressource::Ble, Ressource::Biere, Ressource::BoisLocal],
                    [Ressource::Turquoise, Ressource::Cuivre],
                    'Le port du golfe de Suez, par où tout arrive et tout repart.'),
                self::partenaireDe('horus', 'Chemin d\'Horus', TypeDeRoute::Terrestre, 5,
                    [Ressource::Ble, Ressource::Orge],
                    [Ressource::Turquoise, Ressource::Cuivre],
                    'La piste minière qui rejoint la route côtière, loin au nord.'),
            ],
        ];
    }

    /**
     * @param list<Ressource> $vend
     * @param list<Ressource> $achete
     */
    private static function partenaireDe(
        string $cle,
        string $nom,
        TypeDeRoute $route,
        int $distance,
        array $vend,
        array $achete,
        string $description,
    ): PartenaireCommercial {
        return new PartenaireCommercial($cle, $nom, $route, $distance, $vend, $achete, $description);
    }
}
