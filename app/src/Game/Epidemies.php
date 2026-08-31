<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use Random\Randomizer;

/**
 * Les épidémies (doc 07).
 *
 * L'ancrage est solide et vaut d'être rappelé : les prêtres de Sekhmet, les
 * *ouabou-Sekhmet*, étaient réellement les médecins de l'Égypte, exerçant dans
 * les temples. **La déesse qui envoie la maladie est celle qui la guérit** —
 * une dualité attestée, pas une contradiction de game design.
 *
 * Deux causes, cumulables (doc 07), et la seconde referme une boucle laissée
 * ouverte au lot 4.1 : le manque de logement empêchait les naissances, il coûte
 * désormais aussi quand la ville déborde par l'embauche.
 *
 * **Des malades, jamais des morts.** Le doc y insiste, et c'est la ligne du
 * projet : la fièvre retire des bras pour quelques quinzaines, puis les rend.
 * Techniquement, elle passe par le **canal existant** — le rendement
 * d'effectif —, jamais par un multiplicateur de plus : c'est ce qui laisse
 * tenir le plancher de 50 % du lot 4.5, même en pleine épidémie.
 *
 * **Et l'on peut agir pendant.** C'est l'un des rares événements du jeu qu'on
 * ne fait pas que subir : une offrande à Sekhmet en abrège le cours.
 */
final readonly class Epidemies
{
    /**
     * Chances par quinzaine, en centièmes de pour-cent près — valeurs du
     * doc 07 : 1 % de fond, +3 % si Sekhmet est hostile, +2 % si la ville
     * déborde de son logement.
     */
    public const int RISQUE_DE_FOND = 1;
    public const int RISQUE_SEKHMET_HOSTILE = 3;
    public const int RISQUE_DE_SURPOPULATION = 2;

    /**
     * Part des bras que la fièvre couche, en centièmes (doc 07 : 20 à 40 %).
     */
    public const int PART_MINIMALE = 20;
    public const int PART_MAXIMALE = 40;

    /**
     * Durée en quinzaines (doc 07 : 2 à 4), ramenée de moitié si Sekhmet est
     * favorable ou dévouée — ses prêtres soignent.
     */
    public const int DUREE_MINIMALE = 2;
    public const int DUREE_MAXIMALE = 4;

    public function __construct(
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();

        if ($ville->estFrappeeParUneEpidemie()) {
            return $ville->guerirDUneQuinzaine()
                ? ['La fièvre se retire. Ceux qu\'elle avait couchés reprennent leur ouvrage.']
                : [];
        }

        if ($this->hasard->getInt(1, 100) > $this->risque($partie)) {
            return [];
        }

        return [$this->declarer($partie)];
    }

    /**
     * Le risque d'une quinzaine, en pour-cent. Sert aussi à l'écran : une
     * ville qui déborde de son logement doit pouvoir le lire avant que la
     * fièvre ne passe.
     */
    public function risque(GameSave $partie): int
    {
        $ville = $partie->getVille();
        $risque = self::RISQUE_DE_FOND;

        if ($ville->palierDe(Divinite::Sekhmet)->nuit()) {
            $risque += self::RISQUE_SEKHMET_HOSTILE;
        }

        if ($ville->manqueDeLogements()) {
            $risque += self::RISQUE_DE_SURPOPULATION;
        }

        return $risque;
    }

    /**
     * Ce qu'une offrande à Sekhmet obtient pendant la fièvre : une quinzaine
     * de moins, tout de suite (doc 07). Sans effet hors épidémie — on ne
     * réserve pas une guérison pour plus tard.
     */
    public function abregerParUneOffrande(GameSave $partie): bool
    {
        $ville = $partie->getVille();

        if (!$ville->estFrappeeParUneEpidemie()) {
            return false;
        }

        $ville->guerirDUneQuinzaine();

        return true;
    }

    private function declarer(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $part = $this->hasard->getInt(self::PART_MINIMALE, self::PART_MAXIMALE);
        $duree = $this->hasard->getInt(self::DUREE_MINIMALE, self::DUREE_MAXIMALE);

        // Les prêtres de Sekhmet soignent : la fièvre dure moitié moins, sans
        // jamais tomber sous une quinzaine — une épidémie qu'on ne verrait
        // pas passer ne serait pas une épidémie.
        if ($ville->palierDe(Divinite::Sekhmet)->estAuDessusDuNeutre()) {
            $duree = max(1, intdiv($duree, 2));
        }

        $ville->declarerUneEpidemie($duree, $part);

        return \sprintf(
            'La fièvre se déclare : %d %% des bras restent couchés pour %d quinzaine%s.',
            $part,
            $duree,
            $duree > 1 ? 's' : '',
        );
    }
}
