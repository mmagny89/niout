<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Fait tourner le monde sans joueur et rapporte ce qu'il a produit
 * (`docs/plan-pnj-autonomes.md` § 7, `docs/plan-pnj-palier-a.md` § 10).
 *
 * L'équivalent des « scénarios d'acceptation » de la V1 de `simulation` : sur tout le
 * catalogue, plusieurs graines et des centaines de quinzaines, il dit si les **bornes**
 * tiennent et à quoi ressemble la **dispersion** — c'est sur elle que se calibrent les
 * seuils, pas sur des valeurs recopiées.
 *
 * @phpstan-type Rapport array{
 *     mesures: int,
 *     minimum: int,
 *     maximum: int,
 *     nonNulles: int,
 *     evenements: array<string, int>,
 *     avantageMaximal: int,
 *     avantageMinimal: int,
 *     plancherDAchat: int,
 *     sommeDesEcarts: int,
 *     marquees: int,
 *     quinzainesAvecEvenement: int,
 *     quinzaines: int,
 *     arbitrageMaximal: int,
 *     arbitrageDeReference: int,
 *     violations: list<string>
 * }
 */
final readonly class BancDEssaiDuMonde
{
    public function __construct(
        private CataloguePartenaires $catalogue,
        private ConjonctureDeMarche $conjoncture,
    ) {
    }

    /**
     * @param list<int> $graines
     *
     * @return Rapport
     */
    public function mesurer(array $graines, int $quinzaines): array
    {
        /** @var Rapport $rapport */
        $rapport = [
            'mesures' => 0,
            'minimum' => 0,
            'maximum' => 0,
            'nonNulles' => 0,
            'evenements' => [],
            'avantageMaximal' => 0,
            'avantageMinimal' => 0,
            'plancherDAchat' => PHP_INT_MAX,
            'sommeDesEcarts' => 0,
            'marquees' => 0,
            'quinzainesAvecEvenement' => 0,
            'quinzaines' => 0,
            'arbitrageMaximal' => 0,
            'arbitrageDeReference' => 0,
            'violations' => [],
        ];

        foreach ($this->partenaires() as $partenaire) {
            foreach ($graines as $graine) {
                for ($cycle = 0; $cycle < $quinzaines; ++$cycle) {
                    ++$rapport['quinzaines'];

                    if (null !== $this->conjoncture->evenementEnCours($graine, $partenaire->cle, $cycle)) {
                        ++$rapport['quinzainesAvecEvenement'];
                    }

                    foreach (QualiteDeCrue::cases() as $crue) {
                        $this->mesurerUne($rapport, $graine, $crue, $partenaire, $cycle);
                    }
                }
            }
        }

        $this->mesurerLArbitrage($rapport, $graines, $quinzaines);

        if (PHP_INT_MAX === $rapport['plancherDAchat']) {
            $rapport['plancherDAchat'] = 0;
        }

        return $rapport;
    }

    /**
     * @return list<PartenaireCommercial>
     */
    public function partenaires(): array
    {
        $tous = $this->catalogue->pourMemphis(null);

        for ($mission = GameSave::PREMIERE_MISSION; $mission <= GameSave::DERNIERE_MISSION; ++$mission) {
            foreach ($this->catalogue->pourLaMission($mission) as $partenaire) {
                $tous[$partenaire->cle] = $partenaire;
            }
        }

        foreach (['Ahmôsis Ier', 'Thoutmôsis III', 'Hatchepsout', 'Amenhotep III', 'Akhenaton'] as $pharaon) {
            foreach ($this->catalogue->pourMemphis(new Regne($pharaon, 18, 20, 20, '')) as $partenaire) {
                $tous[$partenaire->cle] = $partenaire;
            }
        }

        return array_values($tous);
    }

    /**
     * **Pas d'arbitrage nouveau** (`docs/plan-pnj-palier-a.md` § 10) : pour chaque couple (cité qui
     * vend, cité qui achète) d'une même marchandise, la marge d'un aller-retour — ce que l'acheteuse
     * paie au plus, moins ce que la vendeuse réclame au moins. La référence est la marge **au plafond
     * d'avantage sans monde** : le monde ne doit jamais l'élargir. Sur les couples du catalogue, Thèbes
     * (qui vend des tissus) et Pount (qui en achète) en sont le témoin.
     *
     * @param Rapport   $rapport
     * @param list<int> $graines
     */
    private function mesurerLArbitrage(array &$rapport, array $graines, int $quinzaines): void
    {
        $partenaires = $this->partenaires();

        foreach ($partenaires as $vendeuse) {
            foreach ($partenaires as $acheteuse) {
                if ($vendeuse->cite() === $acheteuse->cite()) {
                    continue;
                }

                foreach (Ressource::cases() as $ressource) {
                    if (!$vendeuse->vendDe($ressource) || !$acheteuse->acheteDe($ressource)) {
                        continue;
                    }

                    $reference = $this->marge($vendeuse, $acheteuse, $ressource, AvantageDeNegoce::PLAFOND_TOTAL, AvantageDeNegoce::PLAFOND_TOTAL);
                    $rapport['arbitrageDeReference'] = max($rapport['arbitrageDeReference'], $reference);

                    foreach ($graines as $graine) {
                        for ($cycle = 0; $cycle < $quinzaines; ++$cycle) {
                            $v = $this->conjoncture->calculer($graine, QualiteDeCrue::Normale, $vendeuse, $ressource, $cycle)->points;
                            $a = $this->conjoncture->calculer($graine, QualiteDeCrue::Normale, $acheteuse, $ressource, $cycle)->points;
                            // La famille la mieux outillée : renommée pleine et Négociateur, monde compris.
                            $marge = $this->marge($vendeuse, $acheteuse, $ressource, AvantageDeNegoce::total(100, 25, $v), AvantageDeNegoce::total(100, 25, $a));
                            $rapport['arbitrageMaximal'] = max($rapport['arbitrageMaximal'], $marge);

                            if ($marge > $reference) {
                                $rapport['violations'][] = \sprintf('Arbitrage élargi par le monde : %d > %d (%s vers %s, %s, quinzaine %d)', $marge, $reference, $vendeuse->cle, $acheteuse->cle, $ressource->value, $cycle);

                                return;
                            }
                        }
                    }
                }
            }
        }
    }

    private function marge(PartenaireCommercial $vendeuse, PartenaireCommercial $acheteuse, Ressource $ressource, int $avantageDeLaVendeuse, int $avantageDeLAcheteuse): int
    {
        return ($acheteuse->prixMaximumALaVente($ressource, $avantageDeLAcheteuse) ?? 0)
            - ($vendeuse->prixMinimumALAchat($ressource, $avantageDeLaVendeuse) ?? 0);
    }

    /**
     * @param Rapport $rapport
     */
    private function mesurerUne(array &$rapport, int $graine, QualiteDeCrue $crue, PartenaireCommercial $partenaire, int $cycle): void
    {
        foreach (Ressource::cases() as $ressource) {
            if (!$partenaire->acheteDe($ressource) && !$partenaire->vendDe($ressource)) {
                continue;
            }

            $c = $this->conjoncture->calculer($graine, $crue, $partenaire, $ressource, $cycle);
            ++$rapport['mesures'];
            $rapport['minimum'] = min($rapport['minimum'], $c->points);
            $rapport['maximum'] = max($rapport['maximum'], $c->points);

            if (0 !== $c->points) {
                ++$rapport['nonNulles'];
            }

            $rapport['sommeDesEcarts'] += abs($c->points);

            if (abs($c->points) >= 10) {
                ++$rapport['marquees'];
            }

            if (null !== $c->evenement) {
                $rapport['evenements'][$c->evenement->value] = ($rapport['evenements'][$c->evenement->value] ?? 0) + 1;
            }

            $haut = AvantageDeNegoce::total(100, 25, $c->points);
            $bas = AvantageDeNegoce::total(0, 0, $c->points);
            $rapport['avantageMaximal'] = max($rapport['avantageMaximal'], $haut);
            $rapport['avantageMinimal'] = min($rapport['avantageMinimal'], $bas);

            if (abs($c->points) > ConjonctureDeMarche::AMPLITUDE) {
                $rapport['violations'][] = \sprintf('Conjoncture hors bornes : %d (%s, %s, quinzaine %d)', $c->points, $partenaire->cle, $ressource->value, $cycle);
            }

            if ($partenaire->vendDe($ressource)) {
                $cours = PrixDuMarche::pour($ressource);
                $minimum = $partenaire->prixMinimumALAchat($ressource, $haut);

                if (null !== $cours && null !== $minimum) {
                    // Exact : le prix entier ne descend jamais sous 110 % du cours, arrondi compris.
                    if ($minimum < intdiv($cours * 110, 100)) {
                        $rapport['violations'][] = \sprintf('Plancher d\'achat sous 110 %% : %d pour un cours de %d (%s, %s)', $minimum, $cours, $partenaire->cle, $ressource->value);
                    }

                    $rapport['plancherDAchat'] = min($rapport['plancherDAchat'], intdiv($minimum * 100, $cours));
                }
            }
        }
    }
}
