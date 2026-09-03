<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
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
     * À partir de quelle marge la ville trouve qu'on abuse, en centièmes du
     * cours de base. **Valeur inventée.**.
     *
     * Trente pour cent au-dessus du cours : de quoi laisser une vraie latitude
     * — on peut se faire une marge confortable sans fâcher personne — mais pas
     * de quoi pressurer les gens en silence.
     */
    public const int MARGE_QUI_FACHE = 130;

    /**
     * À partir de quel salaire les bras s'estiment bien traités, et apaisent
     * la ville deux fois plus vite. Sans cette contrepartie, il n'existerait
     * qu'une seule bonne valeur de salaire : zéro.
     */
    public const int SALAIRE_GENEREUX = 2;

    /**
     * La quinzaine s'est-elle mal passée ?
     *
     * **Quatre causes, un seul mécanisme.** La faim, les salaires impayés, un
     * prix abusif à l'étal et un salaire de misère mènent à la même colère,
     * comptée une seule fois : ce n'est pas parce qu'on cumule deux griefs que
     * la ville se fâche deux fois plus vite. C'est ce qui garde le compteur
     * lisible, et ce qui permet à la spirale de se redresser.
     */
    public function enregistrer(GameSave $partie, bool $famine, bool $impayes): void
    {
        $ville = $partie->getVille();

        if ($famine || $impayes || self::prixAbusif($ville) || self::salaireDeMisere($ville)) {
            $partie->aggraverLeMecontentement(self::PLAFOND);

            return;
        }

        $partie->apaiserLeMecontentement();

        // Bien payer ne fait pas que ne pas fâcher : cela répare plus vite ce
        // qui a été abîmé. C'est la seule raison qu'un joueur puisse avoir de
        // payer au-delà du juste salaire.
        if ($ville->getSalaireDeBase() >= self::SALAIRE_GENEREUX) {
            $partie->apaiserLeMecontentement();
        }
    }

    /**
     * Vendre au-dessus de ce que les gens acceptent de payer.
     */
    public static function prixAbusif(City $ville): bool
    {
        return $ville->getMargeDuMarche() > self::MARGE_QUI_FACHE;
    }

    /**
     * Payer les bras en dessous du salaire d'usage.
     */
    public static function salaireDeMisere(City $ville): bool
    {
        return $ville->getSalaireDeBase() < City::SALAIRE_JUSTE;
    }

    /**
     * Ce qui fâche la ville en ce moment, dit en clair — pour que l'écran
     * nomme la cause **et** le geste, plutôt que d'afficher un compteur qu'on
     * subit sans comprendre.
     *
     * @return list<string>
     */
    public static function griefs(City $ville): array
    {
        $griefs = [];

        if (self::prixAbusif($ville)) {
            $griefs[] = \sprintf(
                'On trouve vos prix excessifs : %d %% du cours, quand la ville en supporte %d.',
                $ville->getMargeDuMarche(),
                self::MARGE_QUI_FACHE,
            );
        }

        if (self::salaireDeMisere($ville)) {
            $griefs[] = \sprintf(
                'Vos travailleurs sont payés %d deben la quinzaine, en dessous de l\'usage.',
                $ville->getSalaireDeBase(),
            );
        }

        return $griefs;
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
