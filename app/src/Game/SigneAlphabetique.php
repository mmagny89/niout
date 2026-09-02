<?php

declare(strict_types=1);

namespace App\Game;

/**
 * L'alphabet des scribes : les vingt-quatre signes unilitères (doc 10).
 *
 * **Ce sont des phonogrammes, pas des logogrammes.** Chacun note un *son*, et
 * c'est par eux qu'on apprend réellement à lire les hiéroglyphes — c'est la
 * porte d'entrée de toute grammaire, aujourd'hui encore.
 *
 * **Une piste distincte de `SymboleHieroglyphique`**, et les deux ne se
 * mélangent jamais, alors même que six dessins leur sont communs : un même
 * signe **n'y veut pas dire la même chose**. `N35` est « l'eau » dans la clé de
 * lecture, et le son *n* ici ; `X1` est « le pain », et le son *t*. Les
 * confondre enseignerait le contraire de ce que le doc 10 veut faire
 * comprendre — que l'écriture égyptienne est **mixte**, un signe s'y lisant
 * tantôt comme une chose, tantôt comme un son.
 *
 * Conséquence à ne pas défaire : le même glyphe paraît dans les deux tables,
 * avec deux lectures. **Ne pas dédupliquer.**
 *
 * L'ordre des cas est celui du document, qui est l'ordre conventionnel des
 * grammaires — jamais un ordre de difficulté inventé : c'est celui que le
 * joueur retrouvera dans n'importe quel manuel.
 */
enum SigneAlphabetique: string
{
    case VautourPercnoptere = 'vautour_percnoptere';
    case RoseauFleuri = 'roseau_fleuri';
    case DeuxTraits = 'deux_traits';
    case AvantBras = 'avant_bras';
    case PoussinDeCaille = 'poussin_de_caille';
    case Jambe = 'jambe';
    case Natte = 'natte';
    case VipereACornes = 'vipere_a_cornes';
    case Chouette = 'chouette';
    case FiletDEau = 'filet_d_eau';
    case Bouche = 'bouche';
    case AbriEnRoseaux = 'abri_en_roseaux';
    case MecheDeLin = 'meche_de_lin';
    case Tamis = 'tamis';
    case VentreDAnimal = 'ventre_d_animal';
    case LingePlie = 'linge_plie';
    case BassinDEau = 'bassin_d_eau';
    case FlancDeColline = 'flanc_de_colline';
    case CorbeilleAAnse = 'corbeille_a_anse';
    case SupportDeJarre = 'support_de_jarre';
    case Pain = 'pain';
    case CordeDAttache = 'corde_d_attache';
    case Main = 'main';
    case Cobra = 'cobra';

    /**
     * Le vrai code de la liste de Gardiner, affiché tel quel : le joueur doit
     * pouvoir vérifier le signe dans une vraie grammaire.
     */
    public function codeDeGardiner(): string
    {
        return match ($this) {
            self::VautourPercnoptere => 'G1',
            self::RoseauFleuri => 'M17',
            self::DeuxTraits => 'Z4',
            self::AvantBras => 'D36',
            self::PoussinDeCaille => 'G43',
            self::Jambe => 'D58',
            self::Natte => 'Q3',
            self::VipereACornes => 'I9',
            self::Chouette => 'G17',
            self::FiletDEau => 'N35',
            self::Bouche => 'D21',
            self::AbriEnRoseaux => 'O4',
            self::MecheDeLin => 'V28',
            self::Tamis => 'Aa1',
            self::VentreDAnimal => 'F32',
            self::LingePlie => 'S29',
            self::BassinDEau => 'N37',
            self::FlancDeColline => 'N29',
            self::CorbeilleAAnse => 'V31',
            self::SupportDeJarre => 'W11',
            self::Pain => 'X1',
            self::CordeDAttache => 'V13',
            self::Main => 'D46',
            self::Cobra => 'I10',
        };
    }

    /**
     * Le signe lui-même. Unicode couvre les hiéroglyphes égyptiens depuis 2009,
     * et le jeu embarque la police qui les dessine — le texte reste
     * sélectionnable et lisible par un lecteur d'écran, ce qu'une image ne
     * serait pas.
     */
    public function signe(): string
    {
        return match ($this) {
            self::VautourPercnoptere => '𓄿',
            self::RoseauFleuri => '𓇋',
            self::DeuxTraits => '𓏭',
            self::AvantBras => '𓂝',
            self::PoussinDeCaille => '𓅱',
            self::Jambe => '𓃀',
            self::Natte => '𓊪',
            self::VipereACornes => '𓆑',
            self::Chouette => '𓅓',
            self::FiletDEau => '𓈖',
            self::Bouche => '𓂋',
            self::AbriEnRoseaux => '𓉔',
            self::MecheDeLin => '𓎛',
            self::Tamis => '𓐍',
            self::VentreDAnimal => '𓄡',
            self::LingePlie => '𓋴',
            self::BassinDEau => '𓈙',
            self::FlancDeColline => '𓈎',
            self::CorbeilleAAnse => '𓎡',
            self::SupportDeJarre => '𓎼',
            self::Pain => '𓏏',
            self::CordeDAttache => '𓍿',
            self::Main => '𓂧',
            self::Cobra => '𓆓',
        };
    }

    /**
     * Ce que le dessin représente. C'est ce qui rend le signe mémorable : on
     * retient une chouette, pas un « G17 ».
     */
    public function objet(): string
    {
        return match ($this) {
            self::VautourPercnoptere => 'Vautour percnoptère',
            self::RoseauFleuri => 'Roseau fleuri',
            self::DeuxTraits => 'Deux traits obliques',
            self::AvantBras => 'Avant-bras',
            self::PoussinDeCaille => 'Poussin de caille',
            self::Jambe => 'Jambe',
            self::Natte => 'Natte de roseau',
            self::VipereACornes => 'Vipère à cornes',
            self::Chouette => 'Chouette',
            self::FiletDEau => 'Filet d\'eau',
            self::Bouche => 'Bouche',
            self::AbriEnRoseaux => 'Abri en roseaux',
            self::MecheDeLin => 'Mèche de lin tressée',
            self::Tamis => 'Objet incertain, parfois identifié comme un tamis',
            self::VentreDAnimal => 'Ventre d\'animal',
            self::LingePlie => 'Linge plié',
            self::BassinDEau => 'Bassin d\'eau',
            self::FlancDeColline => 'Flanc de colline',
            self::CorbeilleAAnse => 'Corbeille à anse',
            self::SupportDeJarre => 'Support de jarre',
            self::Pain => 'Pain',
            self::CordeDAttache => 'Corde d\'attache',
            self::Main => 'Main',
            self::Cobra => 'Cobra',
        };
    }

    /**
     * La translittération conventionnelle des égyptologues — celle qu'on trouve
     * dans les grammaires et les catalogues de musée.
     */
    public function translitteration(): string
    {
        return match ($this) {
            self::VautourPercnoptere => 'Ȝ',
            self::RoseauFleuri => 'i',
            self::DeuxTraits => 'y',
            self::AvantBras => 'ʿ',
            self::PoussinDeCaille => 'w',
            self::Jambe => 'b',
            self::Natte => 'p',
            self::VipereACornes => 'f',
            self::Chouette => 'm',
            self::FiletDEau => 'n',
            self::Bouche => 'r',
            self::AbriEnRoseaux => 'h',
            self::MecheDeLin => 'ḥ',
            self::Tamis => 'ḫ',
            self::VentreDAnimal => 'ẖ',
            self::LingePlie => 's',
            self::BassinDEau => 'š',
            self::FlancDeColline => 'q',
            self::CorbeilleAAnse => 'k',
            self::SupportDeJarre => 'g',
            self::Pain => 't',
            self::CordeDAttache => 'ṯ',
            self::Main => 'd',
            self::Cobra => 'ḏ',
        };
    }

    /**
     * Le son approché, dit en français. Approché : l'égyptien ne notait pas les
     * voyelles, et la prononciation réelle nous échappe en grande partie.
     */
    public function son(): string
    {
        return match ($this) {
            self::VautourPercnoptere => 'Coup de glotte, l\'« aleph »',
            self::RoseauFleuri => 'i',
            self::DeuxTraits => 'y',
            self::AvantBras => 'L\'« ayin », proche d\'un a long',
            self::PoussinDeCaille => 'w, ou ou',
            self::Jambe => 'b',
            self::Natte => 'p',
            self::VipereACornes => 'f',
            self::Chouette => 'm',
            self::FiletDEau => 'n',
            self::Bouche => 'r',
            self::AbriEnRoseaux => 'h',
            self::MecheDeLin => 'h emphatique',
            self::Tamis => 'kh dur',
            self::VentreDAnimal => 'kh doux',
            self::LingePlie => 's',
            self::BassinDEau => 'ch français',
            self::FlancDeColline => 'q',
            self::CorbeilleAAnse => 'k',
            self::SupportDeJarre => 'g',
            self::Pain => 't',
            self::CordeDAttache => 'tch',
            self::Main => 'd',
            self::Cobra => 'dj',
        };
    }

    /**
     * **Ce que le dessin représente est parfois débattu**, et l'écran le dit
     * plutôt que de trancher à la place des égyptologues : `Aa1` est le seul
     * cas, et le doc 10 le signale explicitement. Même discipline que
     * `Enigme::sourceAttestee()`.
     */
    public function objetIncertain(): bool
    {
        return self::Tamis === $this;
    }
}
