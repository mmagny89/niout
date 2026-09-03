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
    public function __construct(
        private SuccessionDesRegnes $regnes,
        private Lignees $lignees,
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
            // La succession s'épuise : c'est une fin, pas un avènement. Le lot
            // 11.4 la portera ; rien à annoncer ici.
            return [];
        }

        $this->lignees->encaisser($partie);

        return [\sprintf('%s monte sur le trône. %s', $regne->pharaon, $regne->avenement)];
    }
}
