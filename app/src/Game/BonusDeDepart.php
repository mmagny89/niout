<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce qu'une famille qui a déjà servi apporte avec elle (doc 13, lot 9.5).
 *
 * Le document accorde **20 deben et 5 unités par mission accomplie**. Ce n'est
 * pas le legs du pharaon précédent (`Legs`), qui suit le score de la seule
 * mission d'avant : c'est ce que la maisonnée a accumulé sur toute la campagne,
 * et il compte **toutes** les missions menées à leur terme.
 *
 * **Il se superpose à la dotation royale, jamais à sa place.** C'est l'invariant
 * qui garde chaque mission jouable seule : une première mission et une neuvième
 * démarrent sur le même socle, et ce qui vient en plus vient vraiment en plus.
 *
 * **Il ne dépasse pas la dotation non plus** (arbitrage 9.0, qui veut un
 * plafond sur le cumul). Neuf missions accomplies vaudraient cent quatre-vingts
 * deben, davantage que ce que le pharaon envoie : le don du roi cesserait d'être
 * le socle de la partie pour n'en être plus que l'appoint. Le plafond se lit
 * donc sur la dotation elle-même, ressource par ressource — il n'y a rien à
 * calibrer, et il suit tout changement de coût des bâtiments d'ouverture.
 */
final readonly class BonusDeDepart
{
    /**
     * Ce qu'une mission accomplie ajoute, en deben. Du doc 13.
     */
    public const int DEBEN_PAR_MISSION = 20;

    /**
     * Et en matériaux, par matériau que la dotation porte. Du doc 13, qui dit
     * « 5 unités » sans nommer la ressource : ce sont les matériaux de
     * construction, seuls biens que la dotation envoie en nature.
     *
     * **Les vivres en sont exclus** : la dotation les calcule sur la
     * consommation réelle de la maisonnée envoyée, et y ajouter un forfait
     * casserait ce calcul.
     */
    public const int UNITES_PAR_MISSION = 5;

    public function __construct(
        private Progression $progression,
    ) {
    }

    /**
     * Le nombre de missions qui comptent : toutes celles menées à leur terme,
     * hors celle qu'on lance. Comme pour le carnet de contacts, rejouer une
     * mission déjà faite ne la compte pas deux fois.
     */
    public function missionsQuiComptent(GameSave $partie): int
    {
        $enCours = $partie->getMission();
        $accomplies = $this->progression->missionsAccomplies($partie->getJoueur());

        return \count(array_filter(
            $accomplies,
            static fn (int $numero): bool => $numero !== $enCours,
        ));
    }

    /**
     * Ce qui s'ajoute au stock, par-dessus la dotation.
     *
     * @param array<string, int> $dotation ce que le pharaon envoie, qui sert
     *                                     aussi de plafond ressource par ressource
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function pour(GameSave $partie, array $dotation): array
    {
        $missions = $this->missionsQuiComptent($partie);

        if ($missions < 1) {
            return [];
        }

        $bonus = [];

        foreach ($dotation as $valeur => $envoye) {
            // Les vivres restent à la dotation seule : elle les taille sur la
            // maisonnée réellement expédiée.
            if (Ressource::Ble->value === $valeur) {
                continue;
            }

            $part = Ressource::Deben->value === $valeur
                ? self::DEBEN_PAR_MISSION
                : self::UNITES_PAR_MISSION;

            $accorde = min($missions * $part, $envoye);

            if ($accorde > 0) {
                $bonus[$valeur] = $accorde;
            }
        }

        return $bonus;
    }
}
