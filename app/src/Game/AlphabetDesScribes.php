<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que la ville sait écrire de l'alphabet des scribes (doc 10).
 *
 * **Rien n'est persisté.** La clé de lecture stocke ce qu'une énigme apprend ;
 * l'alphabet, lui, ne s'ouvre que par le niveau de la Maison des scribes — il
 * se calcule donc, et une colonne de plus ne ferait que dupliquer l'état du
 * bâtiment.
 *
 * **Ni le Déchiffreur ni Thot n'y touchent** : leur effet est écrit pour la clé
 * de lecture, et l'étendre ici doublerait un bonus que rien ne demande.
 */
final readonly class AlphabetDesScribes
{
    /**
     * Trois par niveau, et la Maison des scribes plafonne au niveau 8 :
     * `3 × 8 = 24`, soit l'alphabet entier. La formule du doc 10 tombe juste,
     * on la garde telle quelle.
     */
    public const int SIGNES_PAR_NIVEAU = 3;

    /**
     * Les signes que la ville connaît avant d'avoir rien appris : ceux de
     * **Niout**, le nom du jeu.
     *
     * Même parti que les quatre signes d'emblée de la clé de lecture, et pour
     * la même raison — la leçon fondatrice du doc 10 doit être tentable tout de
     * suite. Ils ne suivent pas l'ordre des grammaires : ce sont les quatre du
     * mot, choisis pour lui.
     *
     * @return list<SigneAlphabetique>
     */
    public static function connusDEmblee(): array
    {
        return LeconDeNiout::SIGNES;
    }

    /**
     * Les signes que cette ville sait écrire, dans l'ordre des grammaires.
     *
     * @return list<SigneAlphabetique>
     */
    public static function pour(City $ville): array
    {
        $ouverts = self::ouvertsParLeBatiment($ville);
        $demblee = self::connusDEmblee();

        $alphabet = [];

        foreach (SigneAlphabetique::cases() as $rang => $signe) {
            if ($rang < $ouverts || \in_array($signe, $demblee, true)) {
                $alphabet[] = $signe;
            }
        }

        return $alphabet;
    }

    public static function sait(City $ville, SigneAlphabetique $signe): bool
    {
        return \in_array($signe, self::pour($ville), true);
    }

    /**
     * Combien de signes le seul niveau du bâtiment ouvre — les quatre de Niout
     * en sus, quel que soit le niveau. Le mode d'essai les ouvre tous, comme
     * pour la clé de lecture et pour la même raison : éprouver l'écran sans
     * jouer les heures qui y mènent.
     */
    public static function ouvertsParLeBatiment(City $ville): int
    {
        $total = \count(SigneAlphabetique::cases());

        if ($ville->estEnModeDivin()) {
            return $total;
        }

        $niveau = $ville->batimentDeType(TypeDeBatiment::MaisonDesScribes)?->getNiveau() ?? 0;

        return min($total, $niveau * self::SIGNES_PAR_NIVEAU);
    }

    /**
     * Le premier signe que monter la Maison des scribes ouvrirait — null quand
     * la ville les a tous. Sert à dire au joueur ce qu'il gagnerait, plutôt que
     * de le laisser deviner.
     */
    public static function prochainSigne(City $ville): ?SigneAlphabetique
    {
        $connus = self::pour($ville);

        foreach (SigneAlphabetique::cases() as $signe) {
            if (!\in_array($signe, $connus, true)) {
                return $signe;
            }
        }

        return null;
    }
}
