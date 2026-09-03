<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce qu'une partie Aventure vaut, à tout moment (doc 14, lot 11.4).
 *
 * **Pas d'objectif fermé** : le mode est pensé pour durer, et le document veut
 * « un score cumulatif continu que le joueur peut suivre à tout moment, sans
 * condition de victoire binaire ». Il n'y a donc rien à atteindre — seulement
 * quelque chose à regarder monter.
 *
 * **Le jeu savait déjà tout compter.** `ObjectifDeMission` mesure exactement
 * ces grandeurs pour la campagne : richesse, population, commerce, renommée.
 * Le score n'en est qu'une lecture continue, et rien ici ne réinvente une
 * mesure qui existe.
 *
 * **Les poids sont inventés** — le document ne les chiffre pas. Ils mettent les
 * quatre grandeurs à une échelle comparable : sans eux, la richesse écraserait
 * tout, une ville riche comptant ses deben par milliers quand elle compte ses
 * habitants par dizaines.
 */
final readonly class ScoreDAventure
{
    /**
     * Ce que vaut un point de chaque grandeur. **Valeurs inventées**, calées
     * pour qu'aucune ne domine : cent habitants, mille deben en caisse et cent
     * points de renommée pèsent le même ordre de grandeur.
     */
    public const int PAR_HABITANT = 10;
    public const int PAR_DEBEN = 1;
    public const int PAR_POINT_DE_RENOMMEE = 10;

    /**
     * Le commerce se compte en deben échangés depuis le début de la partie :
     * il monte donc bien plus vite que les autres, et pèse moins par unité.
     */
    public const int PAR_DEBEN_ECHANGE = 1;
    public const int DEBEN_ECHANGES_PAR_POINT = 10;

    public function __construct(
        private SuccessionDesRegnes $regnes,
    ) {
    }

    public function total(GameSave $partie): int
    {
        $lignes = $this->detail($partie);

        return array_sum(array_column($lignes, 'points'));
    }

    /**
     * Le détail, pour que l'écran montre **d'où vient** le score : un total nu
     * ne se joue pas, on ne sait pas quoi faire pour le faire monter.
     *
     * @return list<array{libelle: string, mesure: int, points: int}>
     */
    public function detail(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            [
                'libelle' => 'Habitants',
                'mesure' => $ville->population(),
                'points' => $ville->population() * self::PAR_HABITANT,
            ],
            [
                'libelle' => 'Deben en caisse',
                'mesure' => $ville->getDeben(),
                'points' => $ville->getDeben() * self::PAR_DEBEN,
            ],
            [
                'libelle' => 'Renommée',
                'mesure' => $partie->getFamille()->getRenommee(),
                'points' => $partie->getFamille()->getRenommee() * self::PAR_POINT_DE_RENOMMEE,
            ],
            [
                'libelle' => 'Marchandises échangées',
                'mesure' => $ville->getValeurEchangee(),
                'points' => intdiv(
                    $ville->getValeurEchangee() * self::PAR_DEBEN_ECHANGE,
                    self::DEBEN_ECHANGES_PAR_POINT,
                ),
            ],
        ];
    }

    /**
     * Les règnes traversés, sur ceux que la succession porte — la vraie mesure
     * de ce qu'une partie a duré, plus parlante qu'un compte de quinzaines.
     *
     * @return array{0: int, 1: int}
     */
    public function regnesTraverses(GameSave $partie): array
    {
        $total = \count($this->regnes->tous());
        $rang = $this->regnes->rangAuCycle($partie->getCycle());

        return [null === $rang ? $total : $rang + 1, $total];
    }
}
