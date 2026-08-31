<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que devient la faveur d'un dieu qu'on cesse d'honorer (doc 07).
 *
 * **Une décroissance lente et naturelle, pas une chute punitive** — c'est le
 * document qui le dit, et c'est ce qui distingue la négligence d'une faute.
 * Deux propriétés en découlent, et il ne faut défaire ni l'une ni l'autre :
 *
 * **Elle s'arrête au neutre.** Un dieu délaissé se détourne, il ne se retourne
 * pas contre vous : seules une quête ratée ou une malédiction feront descendre
 * une faveur sous la valeur de départ. Sans ce plancher, une partie qu'on mène
 * sans jamais mettre les pieds au Temple finirait avec huit dieux hostiles,
 * c'est-à-dire punie pour n'avoir pas joué à ce système-là.
 *
 * **Elle laisse le temps de revenir.** Cinq quinzaines de grâce, puis un point
 * par quinzaine : redescendre du plafond d'un Temple de niveau 1 jusqu'au
 * neutre demande une quinzaine de cycles, plus de six mois de jeu. Entretenir
 * un dieu est un geste occasionnel, jamais un abonnement.
 *
 * La négligence se compte **dieu par dieu** : on peut couvrir Ptah d'offrandes
 * en laissant Sekhmet s'éloigner, ce qui est exactement le genre d'arbitrage
 * que le doc 07 cherche.
 */
final readonly class Negligence
{
    /**
     * Quinzaines sans offrande avant que la faveur ne commence à retomber.
     * Valeur du doc 07.
     */
    public const int QUINZAINES_DE_GRACE = 5;

    /**
     * Ce qu'on perd par quinzaine passé ce délai. Valeur du doc 07.
     */
    public const int PERTE_PAR_QUINZAINE = 1;

    /**
     * Le plancher : la faveur d'un dieu qu'on n'a jamais honoré. On y revient,
     * on ne passe pas dessous.
     */
    public const int PLANCHER = Divinite::FAVEUR_DE_DEPART;

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $evenements = [];
        $ville = $partie->getVille();

        // Un chef **pieux** fait dire les rites quotidiens par sa maisonnée :
        // la ville oublie ses dieux moins vite. Le trait n'est pas une
        // spécialité du Temple — un contremaître dévot vaut ici autant qu'un
        // prêtre (lot 6.7).
        $grace = self::QUINZAINES_DE_GRACE
            + EffetDeChef::chefsPieux($ville, $partie->getCycle()) * EffetDeChef::REPIT_DUN_CHEF_PIEUX;

        foreach ($ville->getFaveurs() as $faveur) {
            $faveur->attendreUneQuinzaine();

            if ($faveur->getQuinzainesSansOffrande() <= $grace) {
                continue;
            }

            if ($faveur->getFaveur() <= self::PLANCHER) {
                continue;
            }

            $palierAvant = $faveur->getPalier();
            $faveur->ajuster(-min(
                self::PERTE_PAR_QUINZAINE,
                $faveur->getFaveur() - self::PLANCHER,
            ));

            // On ne raconte pas chaque point perdu — ce serait un message par
            // dieu et par quinzaine. Seul le changement de palier compte : c'est
            // le moment où l'effet cesse.
            if ($faveur->getPalier() !== $palierAvant) {
                $evenements[] = \sprintf(
                    'Faute d\'offrandes, %s n\'est plus que %s.',
                    $faveur->getDivinite()->libelle(),
                    mb_strtolower($faveur->getPalier()->libelle()),
                );
            }
        }

        return $evenements;
    }
}
