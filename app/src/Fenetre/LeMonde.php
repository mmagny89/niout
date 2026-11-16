<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Game\Commerce;
use App\Game\ConjonctureDeMarche;
use App\Game\EtatsDesCites;
use App\Game\EvenementDeMarche;
use App\Game\PartenaireCommercial;
use App\Game\Ressource;
use App\Game\Sollicitations;
use App\Game\TypeDeBatiment;
use App\Game\TypeDeRoute;

/**
 * La fenêtre « Le monde » : une carte par cité atteignable (`docs/plan-pnj-palier-a.md` § 6.3).
 *
 * **L'information se mérite** — c'est ce qui donne un emploi neuf à des bâtiments existants :
 *
 * | Cité | Ce qu'on en sait |
 * |---|---|
 * | route ouverte | tout, pour la quinzaine en cours |
 * | route ouverte + Négociateur à l'Entrepôt | et la quinzaine suivante : il voit loin |
 * | route fermée, Auberge (ou Port pour l'eau) | une rumeur, **en retard de deux quinzaines** |
 * | route fermée, rien de tel | rien |
 *
 * Ne calcule que ce qu'il montre ; aucune décision ne se prend ici.
 */
final readonly class LeMonde
{
    /** Le retard d'une rumeur, en quinzaines. **Valeur inventée.** */
    public const int RETARD_DES_RUMEURS = 2;

    /** Combien de marchandises une carte détaille : les plus marquées, pas toutes. */
    private const int MARCHANDISES_PAR_CARTE = 4;

    public function __construct(
        private Commerce $commerce,
        private ConjonctureDeMarche $conjoncture,
        private EtatsDesCites $cites,
        private Sollicitations $sollicitations,
    ) {
    }

    /**
     * @return array{
     *     cartes: list<array<string, mixed>>,
     *     voitLoin: bool
     * }
     */
    public function pour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $voitLoin = $this->commerce->avantageDuNegociateur($ville, $partie->getCycle()) > 0;
        $attente = [];

        foreach ($this->sollicitations->enAttente($partie) as $sollicitation) {
            $attente[$sollicitation->getCite()] = $sollicitation;
        }

        $cartes = [];

        foreach ($this->commerce->partenairesDe($partie) as $partenaire) {
            $route = $ville->routeVers($partenaire->cle);
            $statut = null === $route ? 'a-ouvrir' : ($route->estOuverte() ? 'ouverte' : 'en-chemin');

            $carte = [
                'partenaire' => $partenaire,
                'statut' => $statut,
                'relation' => $this->cites->de($partie, $partenaire)?->getRelation(),
                'maintenant' => null,
                'demain' => null,
                'rumeur' => null,
                'sollicitation' => 'ouverte' === $statut ? ($attente[$partenaire->cite()] ?? null) : null,
            ];

            if ('ouverte' === $statut) {
                $carte['maintenant'] = $this->lire($partie, $partenaire, $partie->getCycle());

                if ($voitLoin) {
                    $carte['demain'] = $this->lire($partie, $partenaire, $partie->getCycle() + 1);
                }
            } else {
                $carte['rumeur'] = $this->rumeur($partie, $partenaire);
            }

            $cartes[] = $carte;
        }

        return ['cartes' => $cartes, 'voitLoin' => $voitLoin];
    }

    /**
     * Ce que la cité vit à cette quinzaine : son événement, et ses marchandises les plus marquées.
     *
     * @return array{evenement: ?EvenementDeMarche, marchandises: list<array{ressource: Ressource, points: int}>}
     */
    private function lire(GameSave $partie, PartenaireCommercial $partenaire, int $cycle): array
    {
        $lignes = [];

        foreach ([...$partenaire->achete, ...$partenaire->vend] as $ressource) {
            $c = $this->conjoncture->calculer($partie->getGraine(), $partie->getCrue(), $partenaire, $ressource, $cycle);
            $lignes[] = ['ressource' => $ressource, 'points' => $c->points, 'saturation' => 0];
        }

        usort($lignes, static fn (array $a, array $b): int => abs($b['points']) <=> abs($a['points']));

        return [
            'evenement' => $this->conjoncture->evenementEnCours($partie->getGraine(), $partenaire->cle, $cycle),
            'marchandises' => array_map(
                static fn (array $l): array => ['ressource' => $l['ressource'], 'points' => $l['points']],
                \array_slice(array_filter($lignes, static fn (array $l): bool => 0 !== $l['points']), 0, self::MARCHANDISES_PAR_CARTE),
            ),
        ];
    }

    /**
     * Ce qu'on entend dire d'une cité dont la route n'est pas ouverte — ou rien, faute de voyageurs.
     *
     * @return array{evenement: ?EvenementDeMarche, source: string}|null
     */
    private function rumeur(GameSave $partie, PartenaireCommercial $partenaire): ?array
    {
        $ville = $partie->getVille();
        $auberge = $ville->batimentDeType(TypeDeBatiment::Auberge)?->getNiveau() ?? 0;
        $port = $ville->batimentDeType(TypeDeBatiment::Port)?->getNiveau() ?? 0;
        $parLEau = TypeDeRoute::Terrestre !== $partenaire->route;

        $source = match (true) {
            $auberge >= 1 => 'les voyageurs de l\'Auberge',
            $parLEau && $port >= 1 => 'les bateliers du Port',
            default => null,
        };

        if (null === $source) {
            return null;
        }

        return [
            'evenement' => $this->conjoncture->evenementEnCours($partie->getGraine(), $partenaire->cle, max(0, $partie->getCycle() - self::RETARD_DES_RUMEURS)),
            'source' => $source,
        ];
    }
}
