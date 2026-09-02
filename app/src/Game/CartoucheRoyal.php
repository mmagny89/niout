<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le cartouche du pharaon qui commandite une mission (doc 10).
 *
 * « Chaque pharaon commanditaire est présenté avec son cartouche réel en
 * hiéroglyphes lors de l'introduction de sa mission — l'alphabet apparaît alors
 * en contexte authentique, pas seulement en exercice isolé. »
 *
 * **C'est le nom de trône**, celui qu'on proclamait au couronnement et qu'on
 * écrivait toujours dans un cartouche. Pas le nom de naissance : « Ramsès » ou
 * « Thoutmôsis » sont des formes grecques du second, et c'est le premier qui
 * identifie un roi dans les listes royales.
 *
 * **Un cartouche ne s'écrit pas avec le seul alphabet**, et c'est le meilleur
 * enseignement de tout le lot : il mêle des unilitères — le filet d'eau pour
 * *n* — à des **bilitères** et à des **logogrammes** entiers, le disque solaire
 * valant à lui seul « Rê ». L'alphabet des scribes est une porte d'entrée, pas
 * la langue.
 *
 * **Deux pharaons n'ont pas leur cartouche**, et n'en affichent donc aucun :
 * les noms de trône composés d'Akhenaton et de Ramsès IV mêlent des signes dont
 * la lecture linéaire ne s'établit pas sûrement d'une source secondaire — leur
 * notation porte des opérateurs de disposition dont l'ordre de lecture est
 * ambigu. La règle du projet est nette : **jamais un signe sans son code ni son
 * sens attesté**. Un cartouche approximatif affiché comme réel serait
 * exactement ce qu'elle interdit ; l'absence, elle, ne trompe personne.
 */
enum CartoucheRoyal: string
{
    case Nebpehtyre = 'nebpehtyre';
    case Aakheperkare = 'aakheperkare';
    case Maatkare = 'maatkare';
    case Menkheperre = 'menkheperre';
    case Nebmaatre = 'nebmaatre';
    case Menmaatre = 'menmaatre';
    case OusermaatreMeryamon = 'ousermaatre_meryamon';

    /**
     * Le cartouche du pharaon qui commandite cette mission, s'il est établi.
     */
    public static function pourLePharaon(string $pharaon): ?self
    {
        return match ($pharaon) {
            'Ahmôsis Ier' => self::Nebpehtyre,
            'Thoutmôsis Ier' => self::Aakheperkare,
            'Hatchepsout' => self::Maatkare,
            'Thoutmôsis III' => self::Menkheperre,
            'Amenhotep III' => self::Nebmaatre,
            'Séthi Ier' => self::Menmaatre,
            'Ramsès III' => self::OusermaatreMeryamon,
            default => null,
        };
    }

    /**
     * Les signes du cartouche, dans l'ordre de lecture.
     *
     * **Le disque solaire ouvre le nom sans se lire en premier** : par respect
     * pour le dieu, « Rê » s'écrit en tête et se prononce à la fin —
     * *Nebpehtyré*, et non *Rênebpehty*. C'est l'antéposition honorifique, et
     * elle vaut pour les six noms qui portent Rê.
     */
    public function signes(): string
    {
        return match ($this) {
            self::Nebpehtyre => '𓇳𓎟𓄇',
            self::Aakheperkare => '𓇳𓉻𓆣𓂓',
            self::Maatkare => '𓇳𓁧𓂓',
            self::Menkheperre => '𓇳𓏠𓆣',
            self::Nebmaatre => '𓇳𓎟𓁦',
            self::Menmaatre => '𓇳𓁧𓏠',
            self::OusermaatreMeryamon => '𓇳𓄊𓁦𓈘𓇋𓏠𓈖',
        };
    }

    /**
     * Les codes de Gardiner, dans le même ordre — le joueur doit pouvoir
     * vérifier chaque signe dans une vraie grammaire.
     *
     * @return list<string>
     */
    public function codesDeGardiner(): array
    {
        return match ($this) {
            self::Nebpehtyre => ['N5', 'V30', 'F9'],
            self::Aakheperkare => ['N5', 'O29', 'L1', 'D28'],
            self::Maatkare => ['N5', 'C10A', 'D28'],
            self::Menkheperre => ['N5', 'Y5', 'L1'],
            self::Nebmaatre => ['N5', 'V30', 'C10'],
            self::Menmaatre => ['N5', 'C10A', 'Y5'],
            self::OusermaatreMeryamon => ['N5', 'F12', 'C10', 'N36', 'M17', 'Y5', 'N35'],
        };
    }

    /**
     * La translittération égyptologique du nom de trône.
     */
    public function translitteration(): string
    {
        return match ($this) {
            self::Nebpehtyre => 'nb-pḥtj-rꜥ',
            self::Aakheperkare => 'ꜥꜣ-ḫpr-kꜣ-rꜥ',
            self::Maatkare => 'mꜣꜥt-kꜣ-rꜥ',
            self::Menkheperre => 'mn-ḫpr-rꜥ',
            self::Nebmaatre => 'nb-mꜣꜥt-rꜥ',
            self::Menmaatre => 'mn-mꜣꜥt-rꜥ',
            self::OusermaatreMeryamon => 'wsr-mꜣꜥt-rꜥ mrj-jmn',
        };
    }

    /**
     * Comment on le lit, en français.
     */
    public function lecture(): string
    {
        return match ($this) {
            self::Nebpehtyre => 'Nebpehtyré',
            self::Aakheperkare => 'Aâkhéperkaré',
            self::Maatkare => 'Maâtkaré',
            self::Menkheperre => 'Menkhéperré',
            self::Nebmaatre => 'Nebmaâtré',
            self::Menmaatre => 'Menmaâtré',
            self::OusermaatreMeryamon => 'Ousermaâtré-Méryamon',
        };
    }

    /**
     * Ce que le nom veut dire. C'est là qu'un roi disait ce qu'il prétendait
     * être, et c'est ce qui rend le cartouche autre chose qu'une décoration.
     */
    public function sens(): string
    {
        return match ($this) {
            self::Nebpehtyre => 'Le maître de la force de Rê',
            self::Aakheperkare => 'Grande est la manifestation du ka de Rê',
            self::Maatkare => 'Maât est le ka de Rê',
            self::Menkheperre => 'Stable est la manifestation de Rê',
            self::Nebmaatre => 'Le maître de la Maât de Rê',
            self::Menmaatre => 'Stable est la Maât de Rê',
            self::OusermaatreMeryamon => 'Puissante est la Maât de Rê, aimé d\'Amon',
        };
    }
}
