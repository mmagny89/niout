<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\User;
use App\Repository\GameSaveRepository;

/**
 * Ce qu'un pharaon lègue pour la mission suivante (doc 09).
 *
 * **Un vrai avantage, pas un ornement** (décision de la joueuse) : de quoi
 * commencer un peu mieux, et de quoi sentir qu'une mission bien menée compte
 * pour la suite.
 *
 * **Mais modeste**, et pour une raison précise : un legs qui changerait
 * l'équilibre d'une mission rendrait celle-ci dépendante de la précédente, et
 * **punirait une réussite partielle deux fois** — une fois par le score, une
 * fois par la difficulté de la suite. Il vaut donc l'ordre de grandeur d'une
 * dotation royale, jamais davantage.
 *
 * **Il suit le score, proportionnellement.** Une mission accomplie à moitié
 * lègue la moitié : c'est ce qui donne un sens aux objectifs chiffrés au-delà
 * du chiffre lui-même.
 *
 * **Il ne porte plus de renommée** (lot 9.1). Il en donnait quatre points au
 * plus, depuis zéro, et seulement d'après la mission immédiatement précédente ;
 * la renommée est désormais un acquis de `Lignee`, transmis en entier. Les deux
 * ensemble auraient compté deux fois la même réussite.
 *
 * Le legs reste donc ce qu'il a toujours été pour les deben : un vrai
 * avantage, modeste, proportionnel au score.
 */
final readonly class Legs
{
    /**
     * Le legs plein, en deben — ce que vaut une mission accomplie à cent pour
     * cent. **Valeur inventée**, calée sur la dotation royale (environ 143
     * deben) : de quoi bâtir un peu plus tôt, jamais de quoi sauter une étape.
     */
    public const int DEBEN_POUR_UNE_REUSSITE_PLEINE = 120;

    public function __construct(
        private GameSaveRepository $parties,
    ) {
    }

    /**
     * Le score de la mission qui précède celle-ci, si le joueur l'a achevée.
     * Nul pour la première mission, ou pour un joueur qui saute des étapes en
     * mode d'essai — on ne lègue que ce qu'on a gagné.
     */
    public function scoreDeLaMissionPrecedente(User $joueur, int $mission): int
    {
        $precedente = $mission - 1;
        $meilleur = 0;

        foreach ($this->parties->findPourJoueur($joueur) as $partie) {
            if (!$partie->estAchevee() || !$partie->estCampagne()) {
                continue;
            }

            if ($precedente === $partie->getMission()) {
                $meilleur = max($meilleur, $partie->getScoreDeMission());
            }
        }

        return $meilleur;
    }

    public function debenPour(User $joueur, int $mission): int
    {
        return intdiv(
            $this->scoreDeLaMissionPrecedente($joueur, $mission) * self::DEBEN_POUR_UNE_REUSSITE_PLEINE,
            100,
        );
    }
}
