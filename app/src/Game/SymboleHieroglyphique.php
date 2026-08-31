<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les vingt signes que la clé de lecture peut contenir (doc 10).
 *
 * Tirés de la **liste de Gardiner**, la classification académique de référence
 * des hiéroglyphes — quelque sept cents signes rangés par catégories (ciel et
 * terre, hommes, bâtiments, navires…). Le jeu n'en retient qu'un sous-ensemble
 * très réduit, choisi sur deux critères : la lisibilité du dessin, et le fait
 * que le signe parle de ce dont le jeu parle — l'eau, la route, le désert, le
 * bateau, le grain, l'or.
 *
 * **Chaque signe porte son vrai code de Gardiner et son vrai sens.** C'est
 * l'objectif pédagogique du doc 10 : ce que le joueur apprend en déchiffrant
 * doit être vrai. Un signe inventé pour les besoins d'une énigme trahirait le
 * propos du projet.
 *
 * Le sens donné est celui du **signe-mot** ou du déterminatif, jamais la
 * valeur phonétique : on ne fait pas apprendre l'égyptien, on fait lire des
 * images.
 */
enum SymboleHieroglyphique: string
{
    // Les quatre premiers sont connus d'emblée : l'eau, l'homme, la maison et
    // la marche. De quoi lire « quelqu'un est allé quelque part », c'est-à-dire
    // la moitié des inscriptions qui comptent.
    case Eau = 'eau';
    case Homme = 'homme';
    case Maison = 'maison';
    case Marcher = 'marcher';

    case Pain = 'pain';
    case Roseau = 'roseau';
    case Canal = 'canal';
    case Route = 'route';
    case Bateau = 'bateau';
    case Desert = 'desert';
    case Soleil = 'soleil';
    case Femme = 'femme';
    case Bouche = 'bouche';
    case Visage = 'visage';
    case Pays = 'pays';
    case Or = 'or';
    case Enceinte = 'enceinte';
    case Panier = 'panier';
    case Dieu = 'dieu';
    case Vie = 'vie';

    /**
     * Le code de la liste de Gardiner. Affiché : un joueur curieux doit
     * pouvoir vérifier le signe dans une vraie grammaire.
     */
    public function codeDeGardiner(): string
    {
        return match ($this) {
            self::Eau => 'N35',
            self::Homme => 'A1',
            self::Maison => 'O1',
            self::Marcher => 'D54',
            self::Pain => 'X1',
            self::Roseau => 'M17',
            self::Canal => 'N23',
            self::Route => 'N31',
            self::Bateau => 'P1',
            self::Desert => 'N25',
            self::Soleil => 'N5',
            self::Femme => 'B1',
            self::Bouche => 'D21',
            self::Visage => 'D2',
            self::Pays => 'N16',
            self::Or => 'S12',
            self::Enceinte => 'O6',
            self::Panier => 'V30',
            self::Dieu => 'R8',
            self::Vie => 'S34',
        };
    }

    /**
     * Le signe lui-même. Unicode couvre les hiéroglyphes égyptiens depuis 2009 ;
     * aucune image à découper, et le texte reste sélectionnable et lisible par
     * un lecteur d'écran.
     */
    public function signe(): string
    {
        return match ($this) {
            self::Eau => '𓈖',
            self::Homme => '𓀀',
            self::Maison => '𓉐',
            self::Marcher => '𓂻',
            self::Pain => '𓏏',
            self::Roseau => '𓇋',
            self::Canal => '𓈇',
            self::Route => '𓈐',
            self::Bateau => '𓊛',
            self::Desert => '𓈉',
            self::Soleil => '𓇳',
            self::Femme => '𓁐',
            self::Bouche => '𓂋',
            self::Visage => '𓁷',
            self::Pays => '𓇾',
            self::Or => '𓋞',
            self::Enceinte => '𓉗',
            self::Panier => '𓎟',
            self::Dieu => '𓊹',
            self::Vie => '𓋹',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Eau => 'Eau',
            self::Homme => 'Homme',
            self::Maison => 'Maison',
            self::Marcher => 'Marcher',
            self::Pain => 'Pain',
            self::Roseau => 'Roseau',
            self::Canal => 'Canal',
            self::Route => 'Route',
            self::Bateau => 'Bateau',
            self::Desert => 'Désert',
            self::Soleil => 'Soleil',
            self::Femme => 'Femme',
            self::Bouche => 'Bouche',
            self::Visage => 'Visage',
            self::Pays => 'Pays',
            self::Or => 'Or',
            self::Enceinte => 'Enceinte',
            self::Panier => 'Panier',
            self::Dieu => 'Dieu',
            self::Vie => 'Vie',
        };
    }

    /**
     * Ce que le signe veut dire, dans les termes qu'un scribe emploierait. Ces
     * gloses sont réelles — elles seront la clé de lecture affichée au joueur,
     * et c'est sur elles qu'il déduira le sens d'une inscription.
     */
    public function sens(): string
    {
        return match ($this) {
            self::Eau => 'L\'eau, le fleuve, ce qui coule. Trois ondulations, le signe le plus courant de l\'écriture.',
            self::Homme => 'Un homme assis : l\'homme, celui qui parle, le scribe lui-même.',
            self::Maison => 'Le plan d\'une maison vue d\'en haut : la demeure, le bâtiment.',
            self::Marcher => 'Deux jambes en marche : aller, venir, se rendre quelque part.',
            self::Pain => 'Un pain rond : le pain, la nourriture, ce qu\'on offre.',
            self::Roseau => 'Un roseau fleuri : le roseau des berges, et le premier signe de l\'alphabet des scribes.',
            self::Canal => 'Un bassin d\'irrigation : la terre qu\'on arrose, le canal, le champ.',
            self::Route => 'Un chemin bordé d\'arbustes : la route, le passage, la voie.',
            self::Bateau => 'Une barque de papyrus : le bateau, le voyage par l\'eau.',
            self::Desert => 'Trois collines de sable : le désert, la terre rouge, l\'étranger.',
            self::Soleil => 'Le disque de Rê : le soleil, le jour, le temps qui passe.',
            self::Femme => 'Une femme assise : la femme, l\'épouse, la maisonnée.',
            self::Bouche => 'Une bouche : parler, dire, la parole donnée.',
            self::Visage => 'Un visage de face : ce qui est devant, ce qu\'on affronte.',
            self::Pays => 'Une bande de terre : le pays, la contrée, l\'Égypte elle-même.',
            self::Or => 'Un collier d\'or : l\'or, ce qui brille, ce qui vaut.',
            self::Enceinte => 'Un mur d\'enceinte : le temple, le domaine clos, le sanctuaire.',
            self::Panier => 'Un panier tressé : le maître, le seigneur — et aussi « tout ».',
            self::Dieu => 'Une hampe à banderole plantée devant un sanctuaire : le dieu, le divin.',
            self::Vie => 'La croix ansée : la vie, le souffle, ce qui dure.',
        };
    }

    /**
     * L'ordre dans lequel la Maison des scribes les ouvre.
     *
     * Les quatre premiers sont ceux qu'on connaît sans rien apprendre ; le
     * reste suit l'ordre de déclaration, du plus concret au plus abstrait —
     * on lit une route avant de lire la vie.
     *
     * @return list<self>
     */
    public static function ordreDApprentissage(): array
    {
        return self::cases();
    }
}
