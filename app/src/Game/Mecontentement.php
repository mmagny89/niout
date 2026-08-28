<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Le mécontentement de la ville, et ce qu'il coûte (doc 02, lot 4.6).
 *
 * **Deux causes, un seul mécanisme** : on ne mange pas, ou on n'est pas payé.
 * Écrire deux spirales séparées aurait donné deux fois la même chose, avec
 * deux jeux de seuils à équilibrer — et deux occasions de diverger.
 *
 * Il s'accumule quinzaine après quinzaine tant que la cause dure, et **se
 * résorbe au même rythme** : une ville qu'on affame huit quinzaines met huit
 * quinzaines à se calmer. La symétrie est délibérée — elle interdit le
 * yo-yo (affamer, redresser, réaffamer) sans rendre la remontée désespérée,
 * ce qu'une résorption plus lente ferait.
 *
 * **La spirale se redresse**, et c'est la propriété qui compte : mesuré sur
 * une ville affamée huit quinzaines puis ravitaillée, le calme revient en huit
 * quinzaines sans que la partie soit perdue. Un mécanisme de ce genre se casse
 * précisément là — quand le malus de production empêche de produire de quoi
 * lever la cause du malus.
 *
 * Trois effets, tous progressifs (doc 02) :
 *
 * - **la production ralentit** — des gens fâchés travaillent mal ;
 * - **les départs s'anticipent** — on quitte plus vite une ville où l'on a
 *   faim (`DepartsNaturels`) ;
 * - **la renommée baisse** — la réputation d'une famille se fait aussi sur le
 *   sort de ses gens (doc 13).
 *
 * Ne persiste rien lui-même : le compteur vit sur `GameSave`, comme celui de
 * la famine.
 */
final readonly class Mecontentement
{
    /**
     * À partir de combien de quinzaines de mécontentement les effets se font
     * sentir. **Valeur inventée** : le doc 02 décrit un palier avant l'échec,
     * jamais son rythme.
     *
     * Une quinzaine de grâce : un accident isolé — une paie manquée, un hiver
     * sans récolte — ne doit pas se payer immédiatement, sans quoi le joueur
     * n'aurait aucune marge de manœuvre.
     */
    public const int SEUIL = 2;

    /**
     * Le compteur ne monte pas indéfiniment : au-delà, la ville est déjà au
     * plus mal et la partie se joue ailleurs (famine prolongée, départs).
     */
    public const int PLAFOND = 12;

    /**
     * Ce que le mécontentement retire à la production, en centièmes, une fois
     * le seuil franchi. **Valeur inventée.**.
     *
     * C'est un **malus délibéré**, distinct du rendement d'effectif : le
     * plancher de 50 % vaut pour le manque de bras (« rien ne s'éteint faute
     * d'employés »), pas pour une ville en colère, qui peut descendre plus
     * bas. Les deux se multiplient, ce qui est assumé — mais le malus reste
     * borné pour que la spirale se redresse encore.
     */
    public const int MALUS_DE_PRODUCTION = 30;

    /**
     * Tous les combien de quinzaines de mécontentement la renommée perd un
     * point (doc 13 : la réputation se fait aussi sur le sort des gens).
     */
    public const int QUINZAINES_PAR_POINT_DE_RENOMMEE = 4;

    /**
     * La quinzaine s'est-elle mal passée ? Deux causes, jamais davantage.
     */
    public function enregistrer(GameSave $partie, bool $famine, bool $impayes): void
    {
        if ($famine || $impayes) {
            $partie->aggraverLeMecontentement(self::PLAFOND);

            return;
        }

        $partie->apaiserLeMecontentement();
    }

    /**
     * Ce qui reste de la production, en centièmes.
     */
    public function rendementEnCentiemes(GameSave $partie): int
    {
        if (!$this->pese($partie)) {
            return Effectifs::RENDEMENT_PLEIN;
        }

        return Effectifs::RENDEMENT_PLEIN - self::MALUS_DE_PRODUCTION;
    }

    public function pese(GameSave $partie): bool
    {
        return $partie->getQuinzainesDeMecontentement() >= self::SEUIL;
    }

    /**
     * Applique ce que le mécontentement coûte à la réputation, et dit au
     * joueur où il en est.
     *
     * @return list<string>
     */
    public function raconter(GameSave $partie): array
    {
        $quinzaines = $partie->getQuinzainesDeMecontentement();

        if (!$this->pese($partie)) {
            return [];
        }

        $evenements = [];

        if (self::SEUIL === $quinzaines) {
            $evenements[] = 'Le mécontentement gagne la ville : on travaille moins bien, et l\'on parle de partir.';
        }

        // Un point de renommée perdu de loin en loin, pas à chaque quinzaine :
        // la réputation d'une famille se défait moins vite qu'une récolte.
        if (0 === $quinzaines % self::QUINZAINES_PAR_POINT_DE_RENOMMEE) {
            $partie->getFamille()->ajusterRenommee(-1);
            $evenements[] = 'On raconte au loin comment vous traitez vos gens : votre renom en souffre.';
        }

        return $evenements;
    }
}
