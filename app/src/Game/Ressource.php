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
 * `Bois` et `Pierre` sont les matériaux génériques que le doc 01 emploie dans
 * les coûts de construction. Le doc 08, lui, nomme des pierres précises —
 * calcaire, grès, granite. Le lien entre les deux n'est tranché nulle part :
 * question à régler au lot 3.5, quand les ressources de zone arriveront
 * réellement. D'ici là, les deux cohabitent sans se recouvrir.
 */
enum Ressource: string
{
    // Matériaux de construction génériques (doc 01).
    case Or = 'or';
    case Bois = 'bois';
    case Pierre = 'pierre';

    // Ressources de zone, minérales (doc 08).
    case Argile = 'argile';
    case Roseaux = 'roseaux';
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
            self::Or => 'or',
            self::Bois => 'bois',
            self::Pierre => 'pierre',
            self::Argile => 'argile',
            self::Roseaux => 'roseaux',
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
     * Matériaux dans lesquels s'expriment les coûts de construction (doc 01).
     *
     * @return list<self>
     */
    public static function materiauxDeConstruction(): array
    {
        return [self::Or, self::Bois, self::Pierre, self::Lin];
    }
}
