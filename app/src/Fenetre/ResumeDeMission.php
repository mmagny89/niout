<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Game\FilRouge;
use App\Game\MissionCatalogue;
use App\Game\ObjectifsDeMission;

/**
 * Ce que le pharaon attend, en résumé — pour l'en-tête du jeu, où il doit se voir **en permanence**.
 *
 * Le décret complet vit à la Résidence familiale ; on y renvoie. Ici, ce qu'il faut pour savoir d'un coup
 * d'œil où l'on en est : la mission, les objectifs atteints, le fil rouge, et la requête du pharaon quand il
 * en a une — avec son délai, parce qu'elle expire. Aucune règle n'est posée : tout est lu sur l'état.
 *
 * @phpstan-type Resume array{
 *     numero: int,
 *     ville: string,
 *     pharaon: string,
 *     atteints: int,
 *     total: int,
 *     objectifs: list<array{libelle: string, avancement: int, seuil: int, atteint: bool, pourcent: int}>,
 *     filRouge: ?array{libelle: string, accompli: bool},
 *     quete: ?array{texte: string, restantes: int, urgent: bool},
 * }
 */
final readonly class ResumeDeMission
{
    public function __construct(private MissionCatalogue $missions)
    {
    }

    /**
     * Null sans mission : en mode Aventure, il n'y a pas de pharaon à qui répondre ; et une partie
     * achevée ou perdue n'attend plus rien.
     *
     * @return Resume|null
     */
    public function pour(GameSave $partie): ?array
    {
        $mission = $this->missions->de($partie);

        if (null === $mission || !$partie->estEnCours()) {
            return null;
        }

        $objectifs = [];
        $atteints = 0;

        foreach (ObjectifsDeMission::pour($mission) as $objectif) {
            $avancement = $objectif->avancement($partie);
            $atteint = $objectif->estAtteint($partie);
            $atteints += $atteint ? 1 : 0;
            $objectifs[] = [
                'libelle' => $objectif->libelle(),
                'avancement' => $avancement,
                'seuil' => $objectif->seuilMesure(),
                'atteint' => $atteint,
                'pourcent' => min(100, intdiv($avancement * 100, max(1, $objectif->seuilMesure()))),
            ];
        }

        $acte = FilRouge::court($partie) ? FilRouge::acte($partie) : null;
        $quete = $partie->getVille()->getQueteDeChantier();

        return [
            'numero' => $mission->numero,
            'ville' => $mission->ville,
            'pharaon' => $mission->pharaon,
            'atteints' => $atteints,
            'total' => \count($objectifs),
            'objectifs' => $objectifs,
            'filRouge' => null === $acte ? null : ['libelle' => $acte->libelle(), 'accompli' => 'accompli' === $acte->value],
            'quete' => null === $quete ? null : [
                'texte' => \sprintf('%s réclame %d %s pour %s.', $mission->pharaon, $quete->getQuantite(), $quete->getRessource()->libelle(), $quete->getChantier()->libelle()),
                'restantes' => $quete->getQuinzainesRestantes(),
                'urgent' => $quete->getQuinzainesRestantes() <= 1,
            ],
        ];
    }
}
