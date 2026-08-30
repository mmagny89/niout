<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le mode d'essai : une partie qu'on truque pour la regarder tourner.
 *
 * Il existe pour une seule raison — **pouvoir éprouver un système sans jouer
 * les vingt heures qui y mènent**. Le commerce longue distance, le craft de
 * luxe à l'Entrepôt niveau 8, une région du Sinaï : autant de choses qu'aucun
 * test unitaire ne juge et qu'une partie normale met des heures à atteindre.
 *
 * **Ce n'est pas une fonctionnalité de jeu** : le rôle qui l'ouvre ne
 * s'accorde qu'en console (`app:users:goddess`), aucun écran ne le propose à
 * qui ne l'a pas, et une partie qui en bénéficie le dit en toutes lettres —
 * une run truquée ne doit jamais se confondre avec une vraie.
 */
final readonly class ModeDivin
{
    /**
     * De quoi ne plus jamais compter. Un million est arbitraire et c'est le
     * propos : le mode existe pour que la ressource cesse d'être la question.
     */
    public const int RICHESSE = 1_000_000;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Bascule la partie, et la comble si elle entre dans le mode.
     */
    public function basculer(GameSave $partie): bool
    {
        $ville = $partie->getVille();
        $actif = !$ville->estEnModeDivin();

        $ville->basculerLeModeDivin($actif);

        if ($actif) {
            // L'ordre compte : les plafonds ne tombent qu'une fois le mode
            // actif, et c'est ce qui laisse le million entrer.
            $partie->toutRemettreDAplomb();
            $ville->crediterRessources($this->toutesLesRessources());
        }

        $this->entityManager->flush();

        return $actif;
    }

    /**
     * Recomble une partie déjà divine, sans la faire sortir du mode.
     */
    public function combler(GameSave $partie): void
    {
        if (!$partie->estEnModeDivin()) {
            return;
        }

        $partie->getVille()->crediterRessources($this->toutesLesRessources());
        $this->entityManager->flush();
    }

    /**
     * Découvre toute la carte d'un coup.
     *
     * Reconnaître une grille du Sinaï case par case demande des dizaines de
     * quinzaines : c'est le temps de jeu qu'on veut pouvoir sauter, pas la
     * règle qu'on veut changer. Rien d'autre n'est touché — les cases révélées
     * portent ce que le tirage leur avait donné, et un gisement reste à ouvrir.
     *
     * @return int le nombre de cases qui étaient encore sous le brouillard
     */
    public function leverLeBrouillard(GameSave $partie): int
    {
        if (!$partie->estEnModeDivin()) {
            return 0;
        }

        $levees = 0;

        foreach ($partie->getVille()->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                $zone->decouvrir();
                ++$levees;
            }
        }

        $this->entityManager->flush();

        return $levees;
    }

    /**
     * Un million de chaque ressource, la monnaie comprise — mais ce qui est
     * déjà là n'est pas remis à zéro : on ajoute de quoi atteindre le compte.
     *
     * @return array<string, int>
     */
    private function toutesLesRessources(): array
    {
        $don = [];

        foreach (Ressource::cases() as $ressource) {
            $don[$ressource->value] = self::RICHESSE;
        }

        return $don;
    }
}
