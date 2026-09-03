<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que la succession des règnes change à une partie en cours (doc 14).
 *
 * `SuccessionDesRegnes` porte la liste — du contenu ; celle-ci porte les
 * règles : qui règne sur cette partie, quand un roi succède à un autre, et ce
 * que le joueur en lit.
 *
 * **Le mode Aventure seul.** La campagne a son pharaon commanditaire, un par
 * mission : une succession y serait un second maître pour le même règne.
 */
final readonly class Successions
{
    /**
     * Ce qu'il faut de points de score pour un centième de réussite. **Valeur
     * inventée** : le score d'une partie Aventure n'a pas de plafond naturel,
     * et il faut pourtant le ranger dans le même champ que le score d'une
     * mission, qui se compte sur cent.
     */
    public const int POINTS_PAR_CENTIEME = 100;

    public function __construct(
        private SuccessionDesRegnes $regnes,
        private Lignees $lignees,
        private ScoreDAventure $score,
    ) {
    }

    /**
     * Le pharaon sous lequel la ville vit en ce moment. Nul en campagne — la
     * mission en désigne un — et nul une fois la succession épuisée.
     */
    public function regneEnCours(GameSave $partie): ?Regne
    {
        if ($partie->estCampagne()) {
            return null;
        }

        return $this->regnes->auCycle($partie->getCycle());
    }

    /**
     * Le rang du règne en cours, à partir de un — ce que l'écran montre. Nul
     * en campagne ou une fois la succession épuisée.
     */
    public function rangEnCours(GameSave $partie): ?int
    {
        if ($partie->estCampagne()) {
            return null;
        }

        $rang = $this->regnes->rangAuCycle($partie->getCycle());

        return null === $rang ? null : $rang + 1;
    }

    public function nombreDeRegnes(): int
    {
        return \count($this->regnes->tous());
    }

    /**
     * Ce qu'on annonce au joueur quand un roi succède à un autre.
     *
     * **Un règne achevé relève l'acquis de la lignée** (arbitrage 11.0) : la
     * renommée appartient à la famille, pas au mode, et l'Aventure n'avait
     * jusqu'ici aucun jalon où la verser faute de mission à terminer.
     *
     * @return list<string>
     */
    public function avenementAuCycle(GameSave $partie): array
    {
        if ($partie->estCampagne() || !$this->regnes->estUneAnneeDAvenement($partie->getCycle())) {
            return [];
        }

        $regne = $this->regnes->auCycle($partie->getCycle());

        if (null === $regne) {
            return $this->clore($partie);
        }

        $this->lignees->encaisser($partie);

        return [\sprintf('%s monte sur le trône. %s', $regne->pharaon, $regne->avenement)];
    }

    /**
     * La succession s'épuise : la partie s'achève (arbitrage 11.0).
     *
     * **Le mode est un bac à sable *long*, il n'est pas *sans fin*.** Le
     * dernier règne clôt la partie, qui reste consultable avec ce qu'elle a
     * accompli — comme une mission de campagne achevée, et jamais supprimée.
     *
     * Le score final se range dans le même champ que le score de mission : les
     * deux disent la même chose, ce qu'une run a valu, et l'écran de reprise
     * n'a pas à connaître deux notions pour une seule idée.
     *
     * @return list<string>
     */
    private function clore(GameSave $partie): array
    {
        if (!$partie->estEnCours()) {
            return [];
        }

        // Le dernier règne compte comme les autres : son acquis rejoint la
        // lignée avant que la partie ne se ferme.
        $this->lignees->encaisser($partie);
        $partie->achever(min(100, intdiv($this->score->total($partie), self::POINTS_PAR_CENTIEME)));

        return [\sprintf(
            'Le dernier règne que vos scribes connaissent s\'achève. Votre ville a traversé %d règnes, et l\'histoire s\'arrête là où s\'arrêtent les listes royales.',
            \count($this->regnes->tous()),
        )];
    }
}
