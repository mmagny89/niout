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
 * **Ramsès IV changea de nom de trône en cours de règne** : *Ousermaâtré*
 * d'abord, *Héqamaâtré* ensuite. C'est le second qui est montré, parce que ses
 * deux missions se jouent à l'an 3 — celle de la grande expédition de l'Ouadi
 * Hammamat, que le doc 09 date ainsi.
 *
 * **Akhenaton porte deux fois le disque solaire**, et c'est voulu : son nom dit
 * Rê deux fois — « belles sont les manifestations de Rê, l'unique de Rê ».
 * L'ordre de ses signes a demandé **deux sources concordantes** avant d'être
 * retenu ; jusque-là il n'affichait rien, la règle du projet étant nette :
 * jamais un signe sans son code ni son sens attesté. Un cartouche approximatif
 * donné pour réel serait exactement ce qu'elle interdit, et l'absence ne trompe
 * personne.
 *
 * Sa graphie **antérieure au changement de nom** employait le faucon plutôt que
 * le disque — les deux se lisent Rê. C'est la seconde qui est montrée, celle de
 * la fondation d'Akhetaton, sujet même de sa mission.
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
    case HeqamaatreSetepenamon = 'heqamaatre_setepenamon';
    case NeferkheperoureOuaenre = 'neferkheperoure_ouaenre';
    case Djeserkare = 'djeserkare';
    case Aakheperenre = 'aakheperenre';
    case Aakheperoure = 'aakheperoure';
    case Menkheperoure = 'menkheperoure';
    case Nebkheperoure = 'nebkheperoure';
    case Kheperkheperoure = 'kheperkheperoure';
    case DjeserkheperoureSetepenre = 'djeserkheperoure_setepenre';

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
            'Akhenaton' => self::NeferkheperoureOuaenre,
            'Séthi Ier' => self::Menmaatre,
            'Ramsès III' => self::OusermaatreMeryamon,
            'Ramsès IV' => self::HeqamaatreSetepenamon,
            // Les sept que la succession du mode Aventure a demandés (lot
            // 11.3) : la campagne n'en commandite aucun, mais une partie à
            // Memphis les traverse tous.
            'Amenhotep Ier' => self::Djeserkare,
            'Thoutmôsis II' => self::Aakheperenre,
            'Amenhotep II' => self::Aakheperoure,
            'Thoutmôsis IV' => self::Menkheperoure,
            'Toutânkhamon' => self::Nebkheperoure,
            'Aÿ' => self::Kheperkheperoure,
            'Horemheb' => self::DjeserkheperoureSetepenre,
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
            self::HeqamaatreSetepenamon => '𓇳𓋾𓁧𓏠𓈖𓍉𓈖',
            self::NeferkheperoureOuaenre => '𓇳𓄤𓆣𓇳𓏦𓌡𓈖',
            self::Djeserkare => '𓇳𓂦𓂓',
            self::Aakheperenre => '𓇳𓉻𓆣𓈖',
            self::Aakheperoure => '𓇳𓉻𓆣𓏦',
            self::Menkheperoure => '𓇳𓏠𓆣𓏦',
            self::Nebkheperoure => '𓇳𓎟𓆣𓏦',
            self::Kheperkheperoure => '𓇳𓆣𓆣𓏦',
            self::DjeserkheperoureSetepenre => '𓇳𓂦𓆣𓏦𓍉𓈖',
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
            self::HeqamaatreSetepenamon => ['N5', 'S38', 'C10A', 'Y5', 'N35', 'U21', 'N35'],
            self::NeferkheperoureOuaenre => ['N5', 'F35', 'L1', 'N5', 'Z2A', 'T21', 'N35'],
            self::Djeserkare => ['N5', 'D45', 'D28'],
            self::Aakheperenre => ['N5', 'O29', 'L1', 'N35'],
            self::Aakheperoure => ['N5', 'O29', 'L1', 'Z2A'],
            self::Menkheperoure => ['N5', 'Y5', 'L1', 'Z2A'],
            self::Nebkheperoure => ['N5', 'V30', 'L1', 'Z2A'],
            self::Kheperkheperoure => ['N5', 'L1', 'L1', 'Z2A'],
            self::DjeserkheperoureSetepenre => ['N5', 'D45', 'L1', 'Z2A', 'U21', 'N35'],
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
            self::HeqamaatreSetepenamon => 'ḥqꜣ-mꜣꜥt-rꜥ stp.n-jmn',
            self::NeferkheperoureOuaenre => 'nfr-ḫprw-rꜥ wꜥ-n-rꜥ',
            self::Djeserkare => 'ḏsr-kꜣ-rꜥ',
            self::Aakheperenre => 'ꜥꜣ-ḫpr-n-rꜥ',
            self::Aakheperoure => 'ꜥꜣ-ḫprw-rꜥ',
            self::Menkheperoure => 'mn-ḫprw-rꜥ',
            self::Nebkheperoure => 'nb-ḫprw-rꜥ',
            self::Kheperkheperoure => 'ḫpr-ḫprw-rꜥ',
            self::DjeserkheperoureSetepenre => 'ḏsr-ḫprw-rꜥ stp.n-rꜥ',
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
            self::HeqamaatreSetepenamon => 'Héqamaâtré-Sétepenamon',
            self::NeferkheperoureOuaenre => 'Néferkhéperourê-Ouâenrê',
            self::Djeserkare => 'Djéserkaré',
            self::Aakheperenre => 'Aâkhéperenré',
            self::Aakheperoure => 'Aâkhéperourê',
            self::Menkheperoure => 'Menkhéperourê',
            self::Nebkheperoure => 'Nebkhéperourê',
            self::Kheperkheperoure => 'Khéperkhéperourê',
            self::DjeserkheperoureSetepenre => 'Djéserkhéperourê-Sétepenrê',
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
            self::HeqamaatreSetepenamon => 'Rê est celui qui gouverne par la Maât, élu d\'Amon',
            self::NeferkheperoureOuaenre => 'Belles sont les manifestations de Rê, l\'unique de Rê',
            self::Djeserkare => 'Sacré est le ka de Rê',
            self::Aakheperenre => 'Grande est la manifestation de Rê',
            self::Aakheperoure => 'Grandes sont les manifestations de Rê',
            self::Menkheperoure => 'Stables sont les manifestations de Rê',
            self::Nebkheperoure => 'Le maître des manifestations de Rê',
            self::Kheperkheperoure => 'La manifestation des manifestations de Rê',
            self::DjeserkheperoureSetepenre => 'Sacrées sont les manifestations de Rê, élu de Rê',
        };
    }
}
