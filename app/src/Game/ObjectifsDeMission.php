<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que chaque pharaon attend de sa mission (doc 09).
 *
 * **Les seuils ont été recalibrés sur l'économie du jeu**, pas recopiés du
 * document (décision de la joueuse) : celui-ci les a chiffrés avant les
 * Phases 4 et 5, et compte encore en or comme si c'était la monnaie — ce que
 * le lot 4.0 a défait. Les documents du Drive seront repris ensuite.
 *
 * Ce qui a servi d'étalon, ce sont les mesures déjà faites :
 *
 * - **La trésorerie** : la ville d'exemple du lot 4.6 dégage une trentaine de
 *   deben nets par quinzaine, salaires payés. Deux cent cinquante deben en
 *   caisse représentent donc une dizaine de quinzaines mises de côté — et le
 *   joueur a mieux à faire de son argent, ce qui rend l'objectif exigeant sans
 *   être absurde.
 * - **La population** : mesurée sur deux cents parties de vingt ans, une ville
 *   pourvue d'un Quartier de niveau 1 monte à treize habitants. Douze est donc
 *   un objectif qui demande de bâtir, pas d'attendre.
 * - **L'infrastructure** : le niveau visé reste sous `niveauMaxRegional`
 *   (`5 + difficulté`), sans quoi l'objectif serait littéralement hors
 *   d'atteinte. Un test le vérifie région par région.
 *
 * **Le reste attend la mesure en conditions réelles**, comme la dotation
 * royale l'a attendue : ces nombres sont une première proposition cohérente,
 * pas un résultat.
 */
final readonly class ObjectifsDeMission
{
    /**
     * Seuils par type, croissants avec la difficulté (0 à 9).
     */
    public const int RICHESSE_DE_BASE = 250;
    public const int RICHESSE_PAR_DIFFICULTE = 75;

    public const int POPULATION_DE_BASE = 12;
    public const int POPULATION_PAR_DIFFICULTE = 4;

    public const int COMMERCE_DE_BASE = 400;
    public const int COMMERCE_PAR_DIFFICULTE = 120;

    public const int RESSOURCE_DE_BASE = 60;
    public const int RESSOURCE_PAR_DIFFICULTE = 15;

    /**
     * Le doc 09 pose `4 + difficulté / 3`. Conservé tel quel : c'est le seul
     * seuil du document qui tienne encore, la borne régionale étant plus
     * généreuse que lui à toutes les missions.
     */
    public const int INFRASTRUCTURE_DE_BASE = 4;
    public const int INFRASTRUCTURE_PAR_TROIS_DIFFICULTES = 1;

    /**
     * Palier de renommée, de 1 (Inconnue) à 5 (Illustre).
     */
    public const int RENOMMEE_DE_BASE = 2;

    /**
     * @return list<ObjectifDeMission>
     */
    public static function pour(Mission $mission): array
    {
        $d = $mission->difficulte;

        return match ($mission->numero) {
            // Ahmôsis sort d'un conflit : ce qu'il veut, c'est que le commerce
            // reparte, et un entrepôt qui tienne la route.
            1 => [self::commerce($d), self::infrastructure($d, TypeDeBatiment::Entrepot)],
            // Thoutmôsis Ier étend le contrôle vers le sud : il faut du monde
            // sur place, et qu'on en parle.
            2 => [self::population($d), self::renommee($d)],
            // Hatchepsout veut ses expéditions vers Pount : de l'encens, et un
            // commerce qui le porte.
            3 => [self::ressource($d, Ressource::Encens), self::commerce($d)],
            // Megiddo est une place tenue : la réputation et les murs.
            4 => [self::renommee($d), self::infrastructure($d, TypeDeBatiment::Entrepot)],
            // Amenhotep III bâtit une cité de prestige : de l'argent et du grès.
            5 => [self::richesse($d), self::ressource($d, Ressource::Gres)],
            // Akhenaton fonde une capitale sur du sable : des bras et des murs.
            6 => [self::population($d), self::infrastructure($d, TypeDeBatiment::QuartierDHabitation)],
            // Séthi Ier tient la frontière et l'or qui remonte du sud.
            7 => [self::ressource($d, Ressource::Or), self::infrastructure($d, TypeDeBatiment::Entrepot)],
            // Ramsès III fait des donations au temple de Sobek : de l'or et du
            // renom.
            8 => [self::richesse($d), self::renommee($d)],
            // Ramsès IV au Ouadi Hammamat : la plus vaste expédition de pierre
            // du Nouvel Empire. On y vient pour extraire, et pour rien d'autre.
            9 => [self::ressource($d, Ressource::Grauwacke), self::richesse($d)],
            // Et au Sinaï, les deux mines qui font le site.
            10 => [self::ressource($d, Ressource::Turquoise), self::ressource($d, Ressource::Cuivre)],
            default => [],
        };
    }

    private static function richesse(int $difficulte): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Richesse,
            self::RICHESSE_DE_BASE + $difficulte * self::RICHESSE_PAR_DIFFICULTE,
        );
    }

    private static function population(int $difficulte): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Population,
            self::POPULATION_DE_BASE + $difficulte * self::POPULATION_PAR_DIFFICULTE,
        );
    }

    private static function commerce(int $difficulte): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Commerce,
            self::COMMERCE_DE_BASE + $difficulte * self::COMMERCE_PAR_DIFFICULTE,
        );
    }

    private static function ressource(int $difficulte, Ressource $ressource): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Ressource,
            self::RESSOURCE_DE_BASE + $difficulte * self::RESSOURCE_PAR_DIFFICULTE,
            ressource: $ressource,
        );
    }

    private static function infrastructure(int $difficulte, TypeDeBatiment $batiment): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Infrastructure,
            self::INFRASTRUCTURE_DE_BASE + intdiv($difficulte, 3) * self::INFRASTRUCTURE_PAR_TROIS_DIFFICULTES,
            batiment: $batiment,
        );
    }

    private static function renommee(int $difficulte): ObjectifDeMission
    {
        return new ObjectifDeMission(
            TypeDObjectif::Renommee,
            self::RENOMMEE_DE_BASE + intdiv($difficulte, 4),
        );
    }
}
