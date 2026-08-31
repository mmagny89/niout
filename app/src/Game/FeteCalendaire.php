<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les fêtes du calendrier, et le dieu qu'on y honore (doc 07).
 *
 * Le document écarte explicitement la construction éditoriale d'un
 * « dieu-maître du mois » et lui préfère des **fêtes réellement attestées** :
 * c'est ce qui donne au calendrier pharaonique une raison d'être regardé pour
 * autre chose que la saison agricole.
 *
 * Les trois retenues sont datées par les sources égyptiennes elles-mêmes, et
 * le calendrier du jeu portait déjà leurs mois :
 *
 * - **Opet**, aux 2ᵉ et 3ᵉ mois de l'inondation (Menhèt et Hout-Herou) : la
 *   barque d'Amon remonte de Karnak à Louxor. Le doc 07 la cite nommément.
 * - **Les mystères d'Osiris**, au 4ᵉ mois de l'inondation — *Ka-her-ka*, dont
 *   les Grecs ont fait *Khoiak* : on y rejoue la mort et le relèvement du dieu.
 *   Le mois portait déjà son nom dans le calendrier du jeu, sans qu'on l'ait
 *   fait exprès.
 * - **La Belle Fête de la Vallée**, au 10ᵉ mois (Khent-khéti) : Amon traverse
 *   vers la rive des morts, et les familles vont banqueter auprès des leurs.
 *   Citée elle aussi par le doc 07.
 *
 * Deux tombent pendant l'inondation, la troisième au 2ᵉ mois de Chémou : les
 * fêtes suivent le calendrier réel, jamais un étalement commode sur l'année.
 */
enum FeteCalendaire: string
{
    case Opet = 'opet';
    case MysteresDOsiris = 'mysteres_osiris';
    case BelleFeteDeLaVallee = 'belle_fete_vallee';

    /**
     * La fête en cours à cette date, s'il y en a une. Les jours épagomènes
     * n'appartiennent à aucun mois, donc à aucune fête.
     */
    public static function pour(DateDeJeu $date): ?self
    {
        foreach (self::cases() as $fete) {
            if (null !== $date->numeroDeMois && \in_array($date->numeroDeMois, $fete->mois(), true)) {
                return $fete;
            }
        }

        return null;
    }

    /**
     * Les mois qu'elle occupe, numérotés de 1 à 12.
     *
     * @return list<int>
     */
    public function mois(): array
    {
        return match ($this) {
            self::Opet => [2, 3],
            self::MysteresDOsiris => [4],
            self::BelleFeteDeLaVallee => [10],
        };
    }

    /**
     * Le dieu qu'on y honore — et lui seul : une offrande à Ptah pendant Opet
     * reste une offrande ordinaire. C'est ce qui fait de la fête un rendez-vous
     * plutôt qu'une saison faste où tout coûte moins cher.
     */
    public function divinite(): Divinite
    {
        return match ($this) {
            self::Opet, self::BelleFeteDeLaVallee => Divinite::AmonRe,
            self::MysteresDOsiris => Divinite::Osiris,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Opet => 'Fête d\'Opet',
            self::MysteresDOsiris => 'Mystères d\'Osiris',
            self::BelleFeteDeLaVallee => 'Belle Fête de la Vallée',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Opet => 'La barque d\'Amon remonte le fleuve de Karnak à Louxor. Ce qu\'on lui donne en ces jours compte double.',
            self::MysteresDOsiris => 'On rejoue la mort et le relèvement d\'Osiris, et l\'on dresse le pilier djed. Le grain qu\'on lui offre lève mieux.',
            self::BelleFeteDeLaVallee => 'Amon traverse vers la rive des morts, et les familles banquettent auprès des leurs.',
        };
    }
}
