<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Ce que la ville sait lire (doc 10).
 *
 * La clé s'enrichit par **deux voies distinctes**, et le doc les nomme toutes
 * les deux : la montée de la Maison des scribes, et les énigmes réussies. La
 * première est calculée — elle découle du niveau et n'a rien à faire en base ;
 * la seconde est persistée, puisque rien d'autre ne la retrouverait.
 *
 * **Quatre signes sont connus sans rien apprendre** : l'eau, l'homme, la
 * maison et la marche. De quoi lire « quelqu'un est allé quelque part », ce
 * qui est la moitié des inscriptions qui comptent — et surtout de quoi tenter
 * la première énigme avant d'avoir bâti quoi que ce soit.
 *
 * **Une contradiction du doc 10, tranchée.** Il annonce « 4 symboles de base
 * aux niveaux 1-2, puis 2 par niveau, jusqu'à 20 au niveau 8 » : les trois
 * nombres ne s'accordent pas — 4 puis 2 par niveau donne 16 au niveau 8, pas
 * 20. On garde les deux bornes, qui sont les affirmations fortes (quatre au
 * départ, vingt au bout), et la progression s'en déduit : `4 + 2 × niveau`.
 */
final readonly class CleDeLecture
{
    public const int SIGNES_CONNUS_DEMBLEE = 4;
    public const int SIGNES_PAR_NIVEAU = 2;

    /**
     * Les signes que cette ville sait lire, dans l'ordre d'apprentissage.
     *
     * @return list<SymboleHieroglyphique>
     */
    public static function pour(City $ville, int $cycle = 0): array
    {
        $ouverts = self::ouvertsParLeBatiment($ville, $cycle);
        $appris = $ville->symbolesAppris();

        $cle = [];

        foreach (SymboleHieroglyphique::ordreDApprentissage() as $rang => $symbole) {
            if ($rang < $ouverts || \in_array($symbole, $appris, true)) {
                $cle[] = $symbole;
            }
        }

        return $cle;
    }

    public static function sait(City $ville, SymboleHieroglyphique $symbole, int $cycle = 0): bool
    {
        return \in_array($symbole, self::pour($ville, $cycle), true);
    }

    /**
     * Combien de signes le seul niveau du bâtiment ouvre. Le mode d'essai les
     * ouvre tous : on s'en sert justement pour éprouver une énigme sans jouer
     * les heures qui y mènent.
     */
    public static function ouvertsParLeBatiment(City $ville, int $cycle = 0): int
    {
        $total = \count(SymboleHieroglyphique::cases());

        if ($ville->estEnModeDivin()) {
            return $total;
        }

        $niveau = $ville->batimentDeType(TypeDeBatiment::MaisonDesScribes)?->getNiveau() ?? 0;

        return min(
            $total,
            self::SIGNES_CONNUS_DEMBLEE
                + $niveau * self::SIGNES_PAR_NIVEAU
                + self::signesDuDechiffreur($ville, $cycle)
                + self::signesDeThot($ville),
        );
    }

    /**
     * Ce qu'un **Déchiffreur** en poste ajoute. Son effet n'est pas une
     * production : il passe donc par `chefSpecialise()`, comme le Négociateur
     * et le Dévot, jamais par la qualité de direction.
     */
    public static function signesDuDechiffreur(City $ville, int $cycle): int
    {
        return EffetDeChef::chefSpecialise($ville, TypeDeBatiment::MaisonDesScribes, SpecialiteDeChef::ScribesDechiffreur, $cycle)
            ? EffetDeChef::SIGNES_DU_DECHIFFREUR
            : 0;
    }

    /**
     * **Thot éclaire les écrits** (doc 07) : sa faveur ouvre des signes que le
     * bâtiment ne porte pas encore. C'est le canal par lequel il cesse d'être
     * un dieu offrable et inerte.
     */
    public static function signesDeThot(City $ville): int
    {
        return match ($ville->palierDe(Divinite::Thot)) {
            PalierDeFaveur::Devoue => 2,
            PalierDeFaveur::Favorable => 1,
            default => 0,
        };
    }

    /**
     * Le prochain signe que la montée du bâtiment ouvrirait — ce que l'écran
     * montre pour donner une raison de bâtir. Nul quand la clé est complète.
     */
    public static function prochainSigne(City $ville, int $cycle = 0): ?SymboleHieroglyphique
    {
        $ordre = SymboleHieroglyphique::ordreDApprentissage();

        foreach ($ordre as $rang => $symbole) {
            if ($rang >= self::ouvertsParLeBatiment($ville, $cycle) && !\in_array($symbole, $ville->symbolesAppris(), true)) {
                return $symbole;
            }
        }

        return null;
    }
}
