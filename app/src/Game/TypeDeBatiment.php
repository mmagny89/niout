<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les douze bâtiments de la ville (doc 01).
 *
 * Toutes les valeurs chiffrées viennent du document de conception : coûts de
 * base, plafonds de niveau, durées de chantier, travailleurs par chef. Ne pas
 * les ajuster ici sans trancher d'abord dans le document.
 */
enum TypeDeBatiment: string
{
    case ResidenceFamiliale = 'residence_familiale';
    case QuartierDHabitation = 'quartier_habitation';
    case Grenier = 'grenier';
    case Entrepot = 'entrepot';
    case Marche = 'marche';
    case Forge = 'forge';
    case Atelier = 'atelier';
    case Temple = 'temple';
    case MaisonDesScribes = 'maison_des_scribes';
    case Caserne = 'caserne';
    case Auberge = 'auberge';
    case Port = 'port';

    public function libelle(): string
    {
        return match ($this) {
            self::ResidenceFamiliale => 'Résidence familiale',
            self::QuartierDHabitation => 'Quartier d\'habitation',
            self::Grenier => 'Grenier',
            self::Entrepot => 'Entrepôt',
            self::Marche => 'Marché',
            self::Forge => 'Forge',
            self::Atelier => 'Atelier',
            self::Temple => 'Temple',
            self::MaisonDesScribes => 'Maison des scribes',
            self::Caserne => 'Caserne',
            self::Auberge => 'Auberge',
            self::Port => 'Port',
        };
    }

    public function fonction(): string
    {
        return match ($this) {
            self::ResidenceFamiliale => 'Le foyer de votre lignée. Améliore l\'héritage familial et ouvre des emplacements de Medjaÿ.',
            self::QuartierDHabitation => 'Détermine combien de familles peuvent s\'installer dans la ville.',
            self::Grenier => 'Stocke la nourriture. Sans lui, les champs ne produisent rien d\'exploitable.',
            self::Entrepot => 'Stocke les ressources non alimentaires et ouvre le commerce par caravanes.',
            self::Marche => 'Achat et vente sur place, à prix fluctuants.',
            self::Forge => 'Outils et armes, à partir du cuivre.',
            self::Atelier => 'Poterie, papyrus, tissus : les recettes se débloquent avec le niveau.',
            self::Temple => 'Offrandes et faveur divine. Le nombre de dieux honorés croît avec le niveau.',
            self::MaisonDesScribes => 'Déchiffrage des inscriptions et conduite des enquêtes.',
            self::Caserne => 'Recrutement des Medjaÿ, protection des caravanes et des zones dangereuses.',
            self::Auberge => 'Voyageurs de passage, rumeurs et quêtes secondaires.',
            self::Port => 'Pêche et commerce naval. Exige un point d\'eau adjacent à la ville.',
        };
    }

    public function coutDeBase(): CoutDeConstruction
    {
        return match ($this) {
            // Bâtiment de départ, offert avec la ville.
            self::ResidenceFamiliale => new CoutDeConstruction(),
            // Les quantités viennent du tableau de fondation du doc 01 révisé,
            // reprises telles quelles. Le « bois » générique de l'ancienne
            // version y a laissé place au **bois local** nommé — acacia,
            // sycomore, palmier —, distinct du cèdre importé : l'Égypte manque
            // de bois de qualité, pas de bois.
            self::QuartierDHabitation => CoutDeConstruction::de(debenParNiveau: 10, roseaux: 15, boisLocal: 10, argile: 20),
            self::Grenier => CoutDeConstruction::de(debenParNiveau: 8, roseaux: 10, boisLocal: 8, argile: 15),
            self::Entrepot => CoutDeConstruction::de(debenParNiveau: 8, roseaux: 15, boisLocal: 10, argile: 15),
            self::Marche => CoutDeConstruction::de(debenParNiveau: 8, roseaux: 10, boisLocal: 5, argile: 10),
            self::Forge => CoutDeConstruction::de(debenParNiveau: 15, roseaux: 10, boisLocal: 8, argile: 20),
            self::Atelier => CoutDeConstruction::de(debenParNiveau: 10, roseaux: 10, boisLocal: 8, argile: 15),
            self::MaisonDesScribes => CoutDeConstruction::de(debenParNiveau: 18, roseaux: 15, boisLocal: 10, argile: 15),
            self::Caserne => CoutDeConstruction::de(debenParNiveau: 20, roseaux: 15, boisLocal: 10, argile: 20),
            self::Auberge => CoutDeConstruction::de(debenParNiveau: 10, roseaux: 10, boisLocal: 8, argile: 15),
            // Les deux seuls à payer du deben dès la fondation (doc 01) : le
            // Temple pour son rituel de dédicace, le Port pour l'assemblage de
            // ses pontons par des spécialistes. Le calcaire de Tourah remontait
            // et descendait réellement le fleuve : une région qui n'en porte
            // pas devra l'importer.
            self::Temple => CoutDeConstruction::de(deben: 10, debenParNiveau: 15, boisLocal: 5, argile: 10, calcaire: 20, lin: 5),
            self::Port => CoutDeConstruction::de(deben: 10, debenParNiveau: 20, roseaux: 15, boisLocal: 20, calcaire: 10),
        };
    }

    /**
     * Plafond propre au bâtiment, indépendant de la région (doc 01). Se combine
     * avec le plafond régional : niveauAtteignable = min(les deux).
     */
    public function niveauMax(): int
    {
        return match ($this) {
            self::ResidenceFamiliale, self::Auberge => 5,
            self::Grenier, self::Marche, self::Forge => 6,
            self::QuartierDHabitation, self::Atelier, self::MaisonDesScribes, self::Port => 8,
            self::Caserne => 9,
            self::Entrepot, self::Temple => 10,
        };
    }

    /**
     * Durée de chantier de base, en cycles. Varie selon le matériau : la brique
     * crue impose déjà une quinzaine de séchage, la pierre bien davantage.
     */
    public function dureeDeBase(): int
    {
        return match ($this) {
            self::ResidenceFamiliale => 0,
            self::QuartierDHabitation, self::Grenier, self::Entrepot, self::Marche, self::Atelier => 1,
            self::Auberge, self::Forge, self::MaisonDesScribes, self::Caserne => 2,
            self::Temple, self::Port => 3,
        };
    }

    /**
     * Travailleurs par chef au niveau 1 (doc 01).
     */
    public function travailleursDeBase(): int
    {
        return match ($this) {
            self::ResidenceFamiliale => 0,
            self::Grenier, self::Entrepot, self::Temple, self::MaisonDesScribes => 1,
            self::QuartierDHabitation, self::Marche, self::Forge, self::Atelier, self::Auberge => 2,
            self::Caserne => 3,
            // Le doc 01 en donne trois. **Un seul** (décision de la joueuse) :
            // un chef et un homme suffisent à tenir un quai, et à trois
            // l'équipage du Port mangeait tout ce que la pêche rapportait.
            self::Port => 1,
        };
    }

    /**
     * Durée totale d'un chantier, en cycles : `dureeBase + niveau` (doc 01).
     */
    public function dureeDeChantier(int $niveauVise): int
    {
        return $this->dureeDeBase() + $niveauVise;
    }

    /**
     * Les quatre étapes du chantier, selon le matériau dominant.
     *
     * Le document ne détaille que deux familles : la brique crue pour la
     * majorité, la pierre pour le Temple. Le Port n'y figure pas ; sa lourdeur
     * étant déjà portée par sa durée de base, il suit la brique par défaut.
     *
     * @return list<EtapeDeChantier>
     */
    public function etapesDeChantier(): array
    {
        return self::Temple === $this
            ? EtapeDeChantier::enPierre()
            : EtapeDeChantier::enBriqueCrue();
    }

    /**
     * Le Port est le seul bâtiment conditionné par la géographie (doc 01).
     */
    public function exigeUnPointDEau(): bool
    {
        return self::Port === $this;
    }

    /**
     * Bâtiment présent d'emblée, jamais construit ni démoli.
     */
    public function estLeBatimentDeDepart(): bool
    {
        return self::ResidenceFamiliale === $this;
    }

    /**
     * @return list<self> Tous les bâtiments hors celui de départ
     */
    public static function constructibles(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => !$type->estLeBatimentDeDepart(),
        ));
    }
}
