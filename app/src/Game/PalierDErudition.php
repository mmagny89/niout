<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que la ville sait de l'écriture, en quatre degrés, et ce qu'elle y gagne.
 *
 * Le doc 10 veut que le jeu *apprenne* à lire les hiéroglyphes ; encore faut-il
 * que savoir lire rapporte quelque chose, sans quoi la Maison des scribes
 * n'est qu'un décor que le joueur apprend à ignorer. **Les scribes tenaient
 * l'administration** : plus ils sont lettrés, mieux ils lèvent l'impôt
 * (`Impots`). C'est le seul effet — il ne passe ni par la clé de lecture ni
 * par l'alphabet, qui restent des pistes d'enseignement, jamais des leviers de
 * production.
 *
 * Objet de calcul, jamais persisté : le degré se déduit des signes que la
 * ville connaît déjà, clé de lecture et alphabet confondus.
 *
 * **Valeurs inventées**, à calibrer en playtest : le doc 10 ne chiffre aucune
 * récompense. Les seuils tiennent compte des huit signes connus d'emblée (quatre
 * de la clé, quatre de Niout) : un néophyte ne peut donc pas sauter un degré
 * sans avoir rien bâti.
 */
enum PalierDErudition: string
{
    case Neophyte = 'neophyte';
    case Lettre = 'lettre';
    case Confirme = 'confirme';
    case Maitre = 'maitre';

    /**
     * Le degré d'une ville, d'après le nombre de signes qu'elle connaît.
     */
    public static function pour(City $ville, int $cycle = 0): self
    {
        return self::pourUnNombreDeSignes(self::signesConnus($ville, $cycle));
    }

    public static function pourUnNombreDeSignes(int $signes): self
    {
        return match (true) {
            $signes < self::Lettre->seuilDEntree() => self::Neophyte,
            $signes < self::Confirme->seuilDEntree() => self::Lettre,
            $signes < self::Maitre->seuilDEntree() => self::Confirme,
            default => self::Maitre,
        };
    }

    /**
     * Les signes que la ville maîtrise : ceux de la clé de lecture et ceux de
     * l'alphabet, deux tables qui ne se mélangent pas mais qui s'additionnent
     * ici — apprendre l'une ou l'autre fait avancer.
     */
    public static function signesConnus(City $ville, int $cycle = 0): int
    {
        return \count(CleDeLecture::pour($ville, $cycle)) + \count(AlphabetDesScribes::pour($ville));
    }

    /**
     * Combien de signes il existe à apprendre en tout.
     */
    public static function signesEnTout(): int
    {
        return \count(SymboleHieroglyphique::cases()) + \count(SigneAlphabetique::cases());
    }

    public function seuilDEntree(): int
    {
        return match ($this) {
            self::Neophyte => 0,
            self::Lettre => 14,
            self::Confirme => 26,
            self::Maitre => 40,
        };
    }

    /**
     * Ce que le degré ajoute à l'impôt du mois, en pourcents.
     */
    public function bonusDImpot(): int
    {
        return match ($this) {
            self::Neophyte => 0,
            self::Lettre => 10,
            self::Confirme => 20,
            self::Maitre => 30,
        };
    }

    public function suivant(): ?self
    {
        return match ($this) {
            self::Neophyte => self::Lettre,
            self::Lettre => self::Confirme,
            self::Confirme => self::Maitre,
            self::Maitre => null,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Neophyte => 'Néophyte',
            self::Lettre => 'Lettré',
            self::Confirme => 'Scribe confirmé',
            self::Maitre => 'Maître des écritures',
        };
    }

    /**
     * Ce que le degré change, dit en une phrase pour l'écran.
     */
    public function description(): string
    {
        return match ($this) {
            self::Neophyte => 'Vos scribes comptent encore sur leurs doigts : ils lèvent l\'impôt au tarif de base.',
            self::Lettre => 'Vos scribes tiennent des registres lisibles : l\'impôt du mois rapporte 10 % de plus.',
            self::Confirme => 'Vos scribes écrivent et lisent couramment : l\'impôt du mois rapporte 20 % de plus.',
            self::Maitre => 'Rien n\'échappe à vos scribes : l\'impôt du mois rapporte 30 % de plus.',
        };
    }
}
