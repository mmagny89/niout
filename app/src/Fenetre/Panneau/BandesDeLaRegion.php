<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\ChefsDeBande;
use App\Game\Commerce;
use App\Game\Medjays;

/**
 * Ce que les bandes de la région pèsent sur les routes, tel que les panneaux qui portent des routes le montrent
 * (l'Entrepôt et le Port) : le risque **avant** l'engagement, les chefs, et le droit de passage.
 * `null` quand aucune bande ne tient de case : il n'y a alors rien à dire.
 */
final readonly class BandesDeLaRegion
{
    public function __construct(
        private ChefsDeBande $bandes,
        private Commerce $commerce,
        private Medjays $medjays,
    ) {
    }

    /**
     * @return array{liste: list<array{nom: string, inquietes: int, x: int, y: int}>, risque: int, droitActif: bool, droitJusqua: int, prixDuDroit: int, garnison: int}|null
     */
    public function pour(GameSave $partie): ?array
    {
        $liste = $this->bandes->lesBandes($partie);

        if ([] === $liste) {
            return null;
        }

        return [
            'liste' => $liste,
            'risque' => $this->commerce->risqueDePillage($partie),
            'droitActif' => $this->bandes->droitActif($partie),
            'droitJusqua' => $partie->getDroitDePassageJusqua(),
            'prixDuDroit' => $this->bandes->prixDuDroit($partie),
            'garnison' => \count($this->medjays->disponibles($partie)),
        ];
    }
}
