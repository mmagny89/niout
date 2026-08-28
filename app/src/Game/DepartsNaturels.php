<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Employee;
use App\Entity\GameSave;
use Random\Randomizer;

/**
 * Les chefs qui s'en vont d'eux-mêmes, quinzaine après quinzaine (doc 05).
 *
 * Le doc 03 fait annoncer à chaque candidat une **ancienneté probable**, que
 * ses traits modulent — « Fidèle » reste, « Ambitieux » passe. Sans départs,
 * cette annonce ne voulait rien dire : un chef embauché l'était pour toujours,
 * et le trait le plus vendeur du document était décoratif.
 *
 * **Chaque chef est tiré séparément**, comme chaque habitant dans
 * `Demographie` : une chance sur son ancienneté à chaque quinzaine. Un chef
 * annoncé pour vingt quinzaines part donc au bout d'une vingtaine en moyenne,
 * parfois bien avant, parfois bien après — ce qui rend l'annonce honnête sans
 * la rendre certaine.
 *
 * **Le mécontentement précipite les départs** (doc 02) : on quitte plus vite
 * une ville où l'on a faim ou l'on n'est pas payé.
 *
 * Le foyer s'en va avec le chef, comme au renvoi (`Recrutements::renvoyer()`) :
 * sans quoi un départ laisserait derrière lui une population gratuite.
 */
final readonly class DepartsNaturels
{
    /**
     * De combien le mécontentement multiplie la chance de partir, en
     * centièmes. **Valeur inventée** : le doc 02 annonce des « départs
     * anticipés » sans les chiffrer.
     *
     * Le double : assez pour que le joueur voie ses gens s'en aller pendant
     * une disette, pas assez pour vider la ville en une saison — ce qui
     * rendrait toute reprise impossible et ferait du mécontentement un échec
     * déguisé plutôt qu'un avertissement.
     */
    public const int PRECIPITATION_PAR_MECONTENTEMENT = 200;

    public function __construct(
        private Mecontentement $mecontentement,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();
        $precipite = $this->mecontentement->pese($partie);

        $evenements = [];

        foreach ($ville->getEmployes()->toArray() as $chef) {
            if (!$chef->estEnPoste($cycle) || !$this->partIl($chef, $precipite)) {
                continue;
            }

            $ville->laisserPartir($chef->getActifsAmenes(), $chef->getInactifsAmenes());
            $ville->retirerEmploye($chef);

            $evenements[] = \sprintf(
                'Votre chef %s quitte son poste et emmène les siens.',
                null !== $chef->getSpecialite()
                    ? '('.mb_strtolower($chef->getSpecialite()->libelle()).')'
                    : 'du '.mb_strtolower($chef->getType()->libelle()),
            );
        }

        return $evenements;
    }

    /**
     * La chance qu'un chef s'en aille cette quinzaine, en pourcentage.
     *
     * `100 / ancienneté` : un chef annoncé pour vingt quinzaines a une chance
     * sur vingt de partir à chaque fois, donc une espérance de vingt. La
     * formule tient l'annonce du doc 03 sans avoir à compter les quinzaines
     * de service.
     */
    public static function chanceDeDepart(Employee $chef, bool $precipite): int
    {
        $anciennete = max(1, $chef->getAncienneteProbable());
        $chance = max(1, intdiv(100, $anciennete));

        if ($precipite) {
            $chance = intdiv($chance * self::PRECIPITATION_PAR_MECONTENTEMENT, 100);
        }

        return min(100, $chance);
    }

    private function partIl(Employee $chef, bool $precipite): bool
    {
        return $this->hasard->getInt(1, 100) <= self::chanceDeDepart($chef, $precipite);
    }
}
