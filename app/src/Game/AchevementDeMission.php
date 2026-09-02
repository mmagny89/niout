<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Clore une mission (doc 09).
 *
 * **Le fil rouge résolu suffit.** Le document tranche la réussite partielle :
 * la mission se termine quand même si les objectifs chiffrés ne sont pas tous
 * atteints, et la reconnaissance du pharaon est alors proportionnelle. Pas de
 * blocage, pas de « game over » — c'est la ligne du projet depuis le doc 00.
 *
 * **Le score est celui du moment où le fil rouge se résout.** Il ne se
 * recalcule pas ensuite : une ville qui perdrait ses habitants après coup ne
 * doit pas voir sa réussite se dégrader, et une ville qui continuerait à
 * enrichir ne doit pas la voir monter — la mission est finie.
 *
 * **C'est aussi ici que la renommée passe la mission** (lot 9.1) : l'acquis de
 * la lignée se relève sur la jauge de la famille au moment de la clôture. Ce
 * point-là est le seul de tout le jeu qui écrit dans la lignée.
 */
final readonly class AchevementDeMission
{
    public function __construct(
        private MissionCatalogue $missions,
        private Lignees $lignees,
    ) {
    }

    /**
     * Vérifie si la mission peut être close, et la clôt le cas échéant.
     *
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function verifier(GameSave $partie): array
    {
        if (!$partie->estEnCours() || !FilRouge::court($partie)) {
            return [];
        }

        if (ActeDuFilRouge::Accompli !== FilRouge::acte($partie)) {
            return [];
        }

        $score = $this->score($partie);
        $partie->achever($score);

        // Ce que la famille a de renommée au moment où la mission se clôt
        // rejoint l'acquis de la lignée, et le suivra dans les missions
        // suivantes (doc 13). L'acquis ne peut que monter.
        $acquisAvant = $this->lignees->pour($partie->getJoueur())->getRenommeeAcquise();
        $this->lignees->encaisser($partie);
        $acquisApres = $this->lignees->pour($partie->getJoueur())->getRenommeeAcquise();

        $rapport = [$this->reconnaissance($partie, $score)];

        if ($acquisApres > $acquisAvant) {
            $rapport[] = \sprintf(
                'Le nom des %s vaut désormais %d de renommée : vos prochaines missions commenceront avec.',
                $partie->getFamille()->getNom(),
                $acquisApres,
            );
        }

        return $rapport;
    }

    /**
     * La part des objectifs chiffrés atteints, en centièmes. Cent quand une
     * mission n'en porte aucun — le fil rouge est alors tout ce qu'on
     * demandait.
     */
    public function score(GameSave $partie): int
    {
        $objectifs = $this->objectifs($partie);

        if ([] === $objectifs) {
            return 100;
        }

        $atteints = 0;

        foreach ($objectifs as $objectif) {
            $atteints += $objectif->estAtteint($partie) ? 1 : 0;
        }

        return intdiv($atteints * 100, \count($objectifs));
    }

    /**
     * @return list<ObjectifDeMission>
     */
    public function objectifs(GameSave $partie): array
    {
        $numero = $partie->getMission();

        if (!$partie->estCampagne() || null === $numero) {
            return [];
        }

        return ObjectifsDeMission::pour($this->missions->get($numero));
    }

    /**
     * Ce que le pharaon en dit. Le ton suit le score : une réussite partielle
     * est une réussite, pas un reproche.
     */
    private function reconnaissance(GameSave $partie, int $score): string
    {
        $numero = $partie->getMission();
        $pharaon = null !== $numero ? $this->missions->get($numero)->pharaon : 'Le pharaon';

        return match (true) {
            100 === $score => \sprintf(
                '%s vous reconnaît une mission pleinement accomplie : tout ce qu\'il attendait est là.',
                $pharaon,
            ),
            $score >= 50 => \sprintf(
                '%s tient sa volonté pour accomplie, et note ce qui manque : %d %% de ce qu\'il demandait.',
                $pharaon,
                $score,
            ),
            default => \sprintf(
                '%s vous sait gré d\'avoir dénoué l\'affaire, mais la ville n\'a pas tenu ses promesses : %d %%.',
                $pharaon,
                $score,
            ),
        };
    }
}
