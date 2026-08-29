<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les ressources du jeu (doc 08), toutes catégories confondues.
 *
 * Le document en distingue quatre familles selon **où on peut les obtenir sans
 * passer par le commerce** — de zone, agricoles, importées, fabriquées. La
 * distinction ne change rien au stock lui-même : n'importe laquelle peut être
 * achetée ou vendue (principe de commerce universel).
 *
 * Il n'existe volontairement pas de ressource « bois » ni « pierre ». Les
 * matériaux génériques du doc 01 n'ont aucune existence en jeu : chaque coût
 * nomme le matériau qu'il réclame — des roseaux, de l'argile, du calcaire —, et
 * rien ne se substitue à rien. Voir CoutDeConstruction.
 */
enum Ressource: string
{
    /**
     * La monnaie du jeu — voir estLaMonnaie().
     */
    case Deben = 'deben';

    // Ressources de zone, minérales (doc 08).
    case Argile = 'argile';
    case Or = 'or';
    case Roseaux = 'roseaux';
    /**
     * Acacia, sycomore, palmier (doc 08) — le bois qui pousse le long du Nil,
     * bon pour une charpente modeste, une porte ou un cadre à mouler les
     * briques. **À ne pas confondre avec le cèdre**, qui vient du Levant et
     * qu'on réserve au prestige : l'Égypte manque de bois de qualité, pas de
     * bois.
     */
    case BoisLocal = 'bois_local';
    case Calcaire = 'calcaire';
    case Albatre = 'albatre';
    case Gres = 'gres';
    case Granite = 'granite';
    case Grauwacke = 'grauwacke';
    case Cuivre = 'cuivre';
    case Turquoise = 'turquoise';
    case Natron = 'natron';
    case Sel = 'sel';

    // Ressources de zone, végétales et animales.
    case BoisDeCedre = 'bois_de_cedre';
    case Encens = 'encens';
    case Myrrhe = 'myrrhe';
    case Ivoire = 'ivoire';
    case Ebene = 'ebene';
    case Poisson = 'poisson';

    // Ressources agricoles, issues des champs (doc 08).
    case Ble = 'ble';
    case Orge = 'orge';
    case Lin = 'lin';
    case Dattes = 'dattes';

    // Ressources totalement étrangères : jamais trouvables sur une carte,
    // toujours importées (doc 08).
    case LapisLazuli = 'lapis_lazuli';
    case PeauxEtPlumes = 'peaux_et_plumes';

    public function libelle(): string
    {
        return match ($this) {
            self::Deben => 'deben',
            self::Argile => 'argile',
            self::Or => 'or',
            self::Roseaux => 'roseaux',
            self::BoisLocal => 'bois local',
            self::Calcaire => 'calcaire',
            self::Albatre => 'albâtre',
            self::Gres => 'grès',
            self::Granite => 'granite',
            self::Grauwacke => 'grauwacke',
            self::Cuivre => 'cuivre',
            self::Turquoise => 'turquoise',
            self::Natron => 'natron',
            self::Sel => 'sel',
            self::BoisDeCedre => 'bois de cèdre',
            self::Encens => 'encens',
            self::Myrrhe => 'myrrhe',
            self::Ivoire => 'ivoire',
            self::Ebene => 'ébène',
            self::Poisson => 'poisson',
            self::Ble => 'blé',
            self::Orge => 'orge',
            self::Lin => 'lin',
            self::Dattes => 'dattes',
            self::LapisLazuli => 'lapis-lazuli',
            self::PeauxEtPlumes => 'peaux et plumes',
        };
    }

    /**
     * La monnaie, qui n'est pas une marchandise : elle ne se vend pas, ne se
     * récolte pas, ne se mange pas.
     *
     * **L'Égypte pharaonique n'a pas de monnaie frappée** — celle-ci n'apparaît
     * que bien plus tard, sous domination perse puis chez les Ptolémées. Les
     * échanges du Nouvel Empire se font par troc, mais avec une unité de compte
     * pondérale : le *deben* (≈ 91 g) et son dixième, le *kite*. Les ostraca de
     * Deir el-Médineh chiffrent les prix en deben de cuivre pour le quotidien.
     *
     * L'or, lui, est un **métal qu'on extrait** (mines du désert oriental et de
     * Nubie, doc 08) et qu'on convertit en deben en le vendant. Confondre les
     * deux, comme le faisait le jeu jusqu'ici, faisait de la mission 2 une
     * carrière de monnaie.
     */
    public function estLaMonnaie(): bool
    {
        return self::Deben === $this;
    }

    /**
     * Ressources produites par les champs, donc conditionnées par le Grenier
     * (doc 01) et soumises au cycle des saisons (doc 05).
     */
    public function estAgricole(): bool
    {
        return \in_array($this, [self::Ble, self::Orge, self::Lin, self::Dattes], true);
    }

    /**
     * Ressources qu'aucune région jouable ne porte : toujours importées.
     */
    public function estToujoursImportee(): bool
    {
        return \in_array($this, [self::LapisLazuli, self::PeauxEtPlumes], true);
    }

    /**
     * Ressources qui nourrissent : elles paient les provisions d'une expédition
     * (doc 04) et se rangent au Grenier.
     *
     * Le lin en est exclu bien qu'agricole — c'est un textile, jamais un vivre.
     */
    public function estNourriture(): bool
    {
        return \in_array($this, [self::Ble, self::Orge, self::Dattes, self::Poisson], true);
    }

    /**
     * Ressources qui se reconstituent d'elles-mêmes : on y puise sans jamais
     * les entamer (décision de la joueuse).
     *
     * Le poisson est le seul cas, et c'est ce qui donne au Port sa valeur
     * durable : une carrière finit par se vider, une pêcherie non. Un Port
     * coûte 50 or, 40 roseaux et 20 calcaire — le rendre inutile au bout de
     * quarante quinzaines en aurait fait un piège plutôt qu'un choix.
     */
    public function estRenouvelable(): bool
    {
        return self::Poisson === $this;
    }

    /**
     * Ressources dont une ville ne peut pas se passer : elles paient tous les
     * bâtiments d'ouverture (doc 01). La génération de carte en garantit un
     * gisement dans chaque région qui en porte.
     *
     * @return list<self>
     */
    public static function materiauxVitaux(): array
    {
        return [self::Roseaux, self::Argile, self::BoisLocal];
    }
}
