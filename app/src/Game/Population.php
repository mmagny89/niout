<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les règles de peuplement d'une ville (doc 01, doc 02).
 *
 * **La population est un effectif réel, jamais une formule.** Le Quartier
 * d'habitation n'engendre pas d'habitants : il dit seulement combien de
 * familles peuvent s'installer. Ce que la ville compte, c'est la somme des
 * personnes des foyers qui y vivent — voir `Foyer`.
 *
 * Trois valeurs viennent des documents et ne sont pas à inventer : la capacité
 * du Quartier (`20 × niveau` familles, doc 01), le vivier régional
 * (`20 - 1,5 × difficulté`, doc 02) et le principe que tout habitant mange à
 * chaque quinzaine (doc 02).
 */
final readonly class Population
{
    /**
     * Âge auquel on entre dans la vie active. Les enfants égyptiens aidaient
     * bien plus tôt — aux champs, au bétail, à l'atelier —, mais douze ans est
     * l'ordre de grandeur d'un vrai travail, et c'est le seuil que le jeu
     * retient pour transformer une bouche en bras.
     */
    public const int AGE_ADULTE_EN_ANNEES = 12;

    public const int AGE_ADULTE_EN_QUINZAINES = self::AGE_ADULTE_EN_ANNEES * DateDeJeu::CYCLES_PAR_ANNEE;

    /**
     * Composition d'une famille (décision de la joueuse) : deux adultes et de
     * zéro à six enfants, soit 2 à 8 personnes pour une moyenne de 5.
     *
     * L'ordre de grandeur vient des listes de maisonnées des papyrus de Kahun
     * et du village d'artisans de Deir el-Médineh : un couple, deux à quatre
     * enfants survivants — la mortalité infantile était forte —, parfois un
     * aïeul. Les maisonnées attestées vont du célibataire isolé à la famille
     * étendue ; la fourchette retenue resserre cette diversité autour de sa
     * moyenne, la variance restant une vraie donnée de jeu : à salaire égal, un
     * foyer de huit coûte quatre fois plus cher à nourrir qu'un foyer de deux.
     */
    public const int ADULTES_PAR_FOYER = 2;
    public const int ENFANTS_MAX_PAR_FOYER = 6;

    /**
     * Capacité d'accueil d'un niveau de Quartier d'habitation, en familles
     * (doc 01). La ville en héberge toujours une de plus : la famille
     * fondatrice, logée par sa Résidence familiale, offerte avec la ville.
     */
    public const int FAMILLES_PAR_NIVEAU_DE_QUARTIER = 20;

    /**
     * Vivier de main-d'œuvre de la région : `20 - 1,5 × difficulté` (doc 02),
     * soit 20 familles au Delta et 6 ou 7 au Sinaï. Une région difficile n'est
     * pas seulement plus pauvre, elle est aussi moins peuplée.
     */
    public static function famillesDisponibles(int $difficulte): int
    {
        return 20 - intdiv(3 * $difficulte, 2);
    }

    public static function enAnnees(int $quinzaines): int
    {
        return intdiv($quinzaines, DateDeJeu::CYCLES_PAR_ANNEE);
    }

    /**
     * Convertit en vivres une consommation exprimée en demi-rations
     * (`Foyer::demiRations()`), **arrondie au supérieur**.
     *
     * Au supérieur, et à l'échelle de la ville seulement : arrondir foyer par
     * foyer ferait manger gratuitement un enfant isolé, et arrondir vers le bas
     * laisserait la ville nourrir une partie des siens pour rien. La conversion
     * n'a lieu qu'ici, une fois tous les demi-rations additionnés — c'est ce
     * qui permet de ne jamais manipuler de 0,5.
     */
    public static function vivresPourDemiRations(int $demiRations): int
    {
        return intdiv($demiRations + 1, 2);
    }
}
