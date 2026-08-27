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
 * Il n'existe volontairement pas de ressource « bois » ni « pierre » : les
 * matériaux génériques du doc 01 sont des **familles**, pas des lignes de
 * stock. Voir FamilleDeMateriau, qui porte la démonstration.
 */
enum Ressource: string
{
    case Or = 'or';

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
     * La famille de matériaux à laquelle cette ressource appartient, si elle
     * bâtit. L'or n'en a pas : il se paie tel quel.
     */
    public function familleDeMateriau(): ?FamilleDeMateriau
    {
        foreach (FamilleDeMateriau::cases() as $famille) {
            if ($famille->contient($this)) {
                return $famille;
            }
        }

        return null;
    }
}
