<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
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
     * De chaque ressource, de quoi ne plus compter à l'échelle d'une partie d'essai : de quoi
     * bâtir, fabriquer et commercer sans y repenser, **sans écraser tous les écrans**.
     *
     * Un million de chaque ressource faisait de toute jauge un bloc plein et de chaque
     * réserve une alerte permanente — soixante mille cases au Grenier — alors que le mode existe
     * pour *regarder* un système tourner, pas pour le noyer. Les plafonds d'affichage suivent
     * d'ailleurs le stock en mode divin (`Stockage`), si bien que rien n'y paraît saturé.
     */
    public const int RICHESSE = 200;

    /**
     * La bourse, elle, n'a aucun plafond ni aucune jauge : on peut la faire large sans rien
     * écraser, et les routes lointaines coûtent cher.
     */
    public const int BOURSE = 50_000;

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
            $ville->crediterRessources($this->toutesLesRessources($ville));
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

        $partie->getVille()->crediterRessources($this->toutesLesRessources($partie->getVille()));
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
     * De quoi **atteindre** le compte pour chaque ressource, la monnaie comprise : ce qui est
     * déjà là n'est pas remis à zéro, et n'est pas non plus doublé — recombler une partie déjà
     * riche ne lui ajoute rien.
     *
     * @return array<string, int>
     */
    private function toutesLesRessources(City $ville): array
    {
        $don = [];

        foreach (Ressource::cases() as $ressource) {
            $cible = $ressource->estLaMonnaie() ? self::BOURSE : self::RICHESSE;
            $manque = $cible - $ville->quantite($ressource);

            if ($manque > 0) {
                $don[$ressource->value] = $manque;
            }
        }

        return $don;
    }
}
