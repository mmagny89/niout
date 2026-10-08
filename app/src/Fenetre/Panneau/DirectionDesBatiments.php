<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\Building;
use App\Entity\GameSave;
use App\Game\Effectifs;
use App\Game\EffetDeChef;
use App\Game\Recrutements;
use App\Game\SpecialiteDeChef;

/**
 * La direction des bâtiments : qui les tient, qui postule.
 */
final readonly class DirectionDesBatiments
{
    public function __construct(private Recrutements $recrutements)
    {
    }

    /**
     * Ce que la plupart des panneaux ont en commun : leur direction, ce que
     * chaque bâtiment emploie, et le bilan de la main-d'œuvre.
     *
     * @return array<string, mixed>
     */
    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            'directions' => $this->directions($partie),
            'effectifs' => Effectifs::repartir($ville, $partie->getCycle()),
            // Le rendement total : travailleurs, plafond du chef et compétence ensemble (l'en-tête le montre à part).
            'rendementsTotaux' => EffetDeChef::qualitesDeDirection($ville, $partie->getCycle()),
            // Et celui de la quinzaine suivante : un chef embauché prend son poste à ce moment-là, et le plafond monte.
            'rendementsProchains' => EffetDeChef::qualitesDeDirection($ville, $partie->getCycle() + 1),
            // Embaucher un chef ouvre des postes : sans ce bilan, le joueur
            // voyait son rendement baisser ailleurs sans comprendre que ses
            // bras étaient partis tenir le nouveau bâtiment.
            'mainDoeuvre' => Effectifs::bilan($ville, $partie->getCycle()),
        ];
    }

    /**
     * L'état du recrutement, **indexé par type de bâtiment** : chaque panneau
     * de bâtiment y lit sa propre direction. Une liste obligerait le gabarit à
     * la parcourir pour retrouver la sienne.
     *
     * Les trois bâtiments sans spécialité en sont écartés — Résidence
     * familiale, Quartier d'habitation, Auberge : la famille les tient
     * elle-même, leur proposer une annonce n'aurait aucun sens.
     *
     * @return array<string, array{batiment: Building, chefs: list<\App\Entity\Employee>, postesLibres: int, offre: ?\App\Entity\JobOffer}>
     */
    public function directions(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $directions = [];

        foreach (OrdreDesBatiments::parLibelle($ville) as $batiment) {
            if ([] === SpecialiteDeChef::pour($batiment->getType())) {
                continue;
            }

            $offre = $ville->offrePour($batiment->getType());

            $directions[$batiment->getType()->value] = [
                'batiment' => $batiment,
                'chefs' => $ville->chefsDe($batiment->getType()),
                'postesLibres' => $this->recrutements->postesLibres($batiment),
                'offre' => $offre,
                // Ce que le bâtiment gagne à être dirigé, et les spécialités
                // qu'on y trouve : le joueur doit voir l'enjeu avant de choisir.
                'specialitesPossibles' => SpecialiteDeChef::pour($batiment->getType()),
                'rangDuPlusCompetent' => null === $offre ? null : $this->rangDuPlusCompetent($offre->candidats()),
                'rangDuMoinsCher' => null === $offre ? null : $this->rangDuMoinsCher($offre->candidats()),
            ];
        }

        return $directions;
    }

    /**
     * Le rang du candidat le plus compétent — à égalité, le moins cher —, ou
     * null si le meilleur est seul à l'être : un conseil n'a de sens que s'il
     * départage.
     *
     * @param list<\App\Game\Candidat> $candidats
     */
    private function rangDuPlusCompetent(array $candidats): ?int
    {
        if (\count($candidats) < 2) {
            return null;
        }

        $meilleur = 0;

        foreach ($candidats as $rang => $candidat) {
            $courant = $candidats[$meilleur];

            if ($candidat->competence > $courant->competence
                || ($candidat->competence === $courant->competence && $candidat->salaire < $courant->salaire)) {
                $meilleur = $rang;
            }
        }

        return $meilleur;
    }

    /**
     * Le rang du candidat le moins cher, ou null s'il se confond avec le plus
     * compétent (le conseil serait alors le même) ou s'ils coûtent autant.
     *
     * @param list<\App\Game\Candidat> $candidats
     */
    private function rangDuMoinsCher(array $candidats): ?int
    {
        if (\count($candidats) < 2) {
            return null;
        }

        $moinsCher = 0;

        foreach ($candidats as $rang => $candidat) {
            if ($candidat->salaire < $candidats[$moinsCher]->salaire) {
                $moinsCher = $rang;
            }
        }

        if ($moinsCher === $this->rangDuPlusCompetent($candidats)
            || $candidats[$moinsCher]->salaire === max(array_map(static fn ($c): int => $c->salaire, $candidats))) {
            return null;
        }

        return $moinsCher;
    }
}
