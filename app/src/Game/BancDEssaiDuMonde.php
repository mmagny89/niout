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
            'violations' => [],
        ];

        foreach ($this->partenaires() as $partenaire) {
            foreach ($graines as $graine) {
                for ($cycle = 0; $cycle < $quinzaines; ++$cycle) {
                    foreach (QualiteDeCrue::cases() as $crue) {
                        $this->mesurerUne($rapport, $graine, $crue, $partenaire, $cycle);
                    }
                }
            }
        }

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
