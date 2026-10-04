<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Commerce;
use App\Game\Rivaux;
use App\Game\TypeDeBatiment;
use App\Game\VueDeLaReserve;

/**
 * Le panneau de l'Entrepôt : c'est lui qui tient les stocks, et c'est d'un seul
 * tableau qu'on décide de ce qu'on garde et de ce qui part.
 */
final readonly class PanneauDeLEntrepot implements FournisseurDePanneau
{
    public function __construct(
        private Commerce $commerce,
        private Rivaux $rivaux,
        private EtalsDesRoutes $etals,
        private DirectionDesBatiments $direction,
    ) {
    }

    public static function cle(): string
    {
        return TypeDeBatiment::Entrepot->value;
    }

    public function donnees(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            'aUnMarche' => $ville->possede(TypeDeBatiment::Marche),
            'repartition' => RepartitionDesReserves::pour($ville),
            'reserveDesMateriaux' => VueDeLaReserve::pour($ville, vivres: false),
            'routes' => $this->commerce->offrePour($partie),
            'etals' => $this->etals->pour($partie),
            // Le récapitulatif du territoire, rangé sous le bâtiment qui
            // gouverne chaque exploitation.
            'exploitations' => ExploitationsParGouvernant::pour($partie),
            'rival' => $ville->getRival(),
            'prixDeLAccord' => $this->rivaux->prixDeLAccord($partie),
        ] + $this->direction->donnees($partie);
    }
}
