<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Où un rival s'installe, et **pourquoi** (`docs/plan-pnj-palier-b.md` § 4) : le choix par score
 * d'utilité de la V1 de `simulation`, en fonction **pure**.
 *
 * `choisir(routes, caractère, identité) → décision + raisons` : aucun accès à la base, au temps ni au
 * hasard global. Tout entre par les paramètres, donc la fonction se teste seule et se rejoue.
 *
 * Deux aspirations s'appliquent à un marchand (les trois autres — sécurité, piété, loyauté — attendent
 * leurs personnes) :
 *  - la **prospérité** : aller où le marché paie, là où la famille commerce déjà ;
 *  - le **prestige** : aller où le commerce de la famille est le plus vivant.
 *
 * Le **caractère** les pondère (`TraitDeCandidat`, le vocabulaire existant, qu'on ne double pas) : un
 * *Économe* pèse la prospérité double, un *Ambitieux* le prestige. Les autres traits ne déplacent rien.
 *
 * **Inertie** : le rival ne rechoisit pas. Il tient sa route jusqu'à se lasser — c'est sa durée de vie
 * (`RivalCommercial::$quinzainesRestantes`), pas une décision à chaque quinzaine.
 */
final class DecisionDuRival
{
    /** Hasard parmi les meilleurs : les deux premiers. **Valeur inventée**, à calibrer. */
    public const int PARMI_LES_MEILLEURS = 2;

    /**
     * @param list<array{cle: string, prosperite: int, prestige: int}> $routes les routes ouvertes, aspirations de 0 à 100
     *
     * @return array{cle: string, score: int, raisons: array<string, int|string>}|null
     */
    public static function choisir(array $routes, TraitDeCandidat $caractere, int $identite): ?array
    {
        if ([] === $routes) {
            return null;
        }

        [$poidsDeLaProsperite, $poidsDuPrestige] = match ($caractere) {
            TraitDeCandidat::Econome => [2, 1],
            TraitDeCandidat::Ambitieux => [1, 2],
            default => [1, 1],
        };

        $notees = array_map(
            static fn (array $route): array => $route + ['score' => $poidsDeLaProsperite * $route['prosperite'] + $poidsDuPrestige * $route['prestige']],
            $routes,
        );

        // Le meilleur d'abord ; à score égal, l'ordre de la clé — jamais l'ordre d'arrivée, qui n'est pas stable.
        usort($notees, static fn (array $a, array $b): int => [$b['score'], $a['cle']] <=> [$a['score'], $b['cle']]);

        $retenue = $notees[$identite % min(self::PARMI_LES_MEILLEURS, \count($notees))];

        return [
            'cle' => $retenue['cle'],
            'score' => $retenue['score'],
            'raisons' => [
                'route' => $retenue['cle'],
                'prosperite' => $retenue['prosperite'],
                'prestige' => $retenue['prestige'],
                'caractere' => $caractere->value,
            ],
        ];
    }
}
