<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\User;
use App\Repository\GameSaveRepository;

/**
 * Où en est un joueur dans la campagne (doc 09).
 *
 * **L'ordre est imposé, de 1 à 10** (décision déjà actée) : on n'ouvre la
 * mission suivante qu'en ayant accompli la précédente. On peut en revanche
 * **rejouer une mission déjà faite** — rien ne l'interdit, et cela vaut mieux
 * que d'enfermer un joueur qui voudrait refaire Avaris autrement.
 *
 * **Une réussite partielle ouvre la suite**, comme une réussite pleine : le
 * doc 09 est explicite, la mission se termine quand même. Ce serait la
 * punir deux fois que de bloquer la campagne dessus.
 */
final readonly class Progression
{
    public const int DERNIERE_MISSION = 10;

    public function __construct(
        private GameSaveRepository $parties,
    ) {
    }

    /**
     * La plus haute mission que ce joueur a menée à son terme. Zéro s'il n'en
     * a achevé aucune.
     */
    public function plusHauteAchevee(User $joueur): int
    {
        $plusHaute = 0;

        foreach ($this->parties->findPourJoueur($joueur) as $partie) {
            if (!$partie->estAchevee() || !$partie->estCampagne()) {
                continue;
            }

            $plusHaute = max($plusHaute, $partie->getMission() ?? 0);
        }

        return $plusHaute;
    }

    /**
     * La mission qu'on attend de lui. La première tant qu'il n'a rien achevé ;
     * la dernière une fois la campagne finie — on ne va pas au-delà de dix.
     */
    public function prochaineMission(User $joueur): int
    {
        return min(self::DERNIERE_MISSION, $this->plusHauteAchevee($joueur) + 1);
    }

    public function campagneAchevee(User $joueur): bool
    {
        return self::DERNIERE_MISSION === $this->plusHauteAchevee($joueur);
    }

    /**
     * Les missions qu'il peut lancer : celles qu'il a déjà faites, et la
     * suivante. **Le mode d'essai les ouvre toutes** — c'est à cela qu'il
     * sert.
     *
     * @return list<int>
     */
    public function missionsOuvertes(User $joueur): array
    {
        $derniere = $joueur->estDivinite()
            ? self::DERNIERE_MISSION
            : $this->prochaineMission($joueur);

        return range(1, $derniere);
    }

    public function peutLancer(User $joueur, int $mission): bool
    {
        return \in_array($mission, $this->missionsOuvertes($joueur), true);
    }
}
