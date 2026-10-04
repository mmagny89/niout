<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use App\Entity\Zone;
use App\Game\Bandits;
use App\Game\Charrier;
use App\Game\Culture;
use App\Game\Effectifs;
use App\Game\Enquetes;
use App\Game\Explorations;
use App\Game\Medjays;
use App\Game\Prospection;
use App\Game\RoleDExploration;
use App\Game\TypeDeBatiment;

/**
 * Ce que la fenêtre d'une case doit savoir pour s'afficher.
 *
 * Ces valeurs vivaient dans `PartieController::carte()` : la carte les calculait
 * à chaque affichage, même sans case regardée. Depuis que la case est une
 * fenêtre (`docs/plan-fenetres.md`, phase 5), seule la fenêtre les demande.
 */
final readonly class DetailDeCase
{
    public function __construct(
        private Explorations $explorations,
        private Enquetes $enquetes,
        private Prospection $prospection,
        private Medjays $medjays,
    ) {
    }

    /**
     * La case désignée par « x-y », ou null si elle n'existe pas dans cette
     * partie.
     */
    public function zone(GameSave $partie, string $coordonnees): ?Zone
    {
        if (1 !== preg_match('/^(\d{1,3})-(\d{1,3})$/', $coordonnees, $trouve)) {
            return null;
        }

        foreach ($partie->getVille()->getZones() as $zone) {
            if ($zone->getX() === (int) $trouve[1] && $zone->getY() === (int) $trouve[2]) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function pour(GameSave $partie, Zone $zone): array
    {
        $ville = $partie->getVille();
        $filons = $zone->estDecouverte() ? $this->prospection->filonsPossibles($partie, $zone) : [];

        return [
            'zoneDetaillee' => $zone,
            // Ce que la case oppose réellement, renforts de la région compris
            // (lot 10.1) : c'est ce chiffre-là qui décide d'y aller ou non.
            'defenseDeLaZone' => Bandits::defenseDe($ville, $zone),
            'medjaysDisponibles' => \count($this->medjays->disponibles($partie)),
            // La réquisition de chars (lot 10.6) : ce qui l'empêche se dit
            // avant la demande, jamais par un refus.
            'charriersOuverts' => Charrier::disponiblePour($ville),
            'empechementDesCharriers' => Charrier::empechement($ville),
            'coutDunCharrier' => Charrier::COUT_PAR_EXPEDITION,
            'expeditionEnCours' => $ville->aUneExpeditionVers($zone),
            // Le prix dépend de la case : reconnaître ses propres abords ne
            // coûte pas d'or. L'écran doit donc annoncer celui de cette case-là.
            'coutDeReconnaissance' => $this->explorations->coutVers($partie, $zone, RoleDExploration::Eclaireur),
            // Même logique que le coût en or : nul à moins de trois cases.
            'provisionsDeReconnaissance' => $this->explorations->provisionsVers($partie, $zone, RoleDExploration::Eclaireur),
            'dureeDeReconnaissance' => !$zone->estDecouverte() ? $this->explorations->dureeVers($partie, $zone) : null,
            'cultures' => Culture::cases(),
            'champsMax' => Zone::CHAMPS_MAX,
            'aUnGrenier' => $ville->possede(TypeDeBatiment::Grenier),
            'peutFouiller' => $this->enquetes->peutFouiller($ville, $zone),
            // L'émissaire ne va que là où l'on sait déjà qu'il y a quelqu'un,
            // et il lui faut des scribes pour consigner ce qu'il rapporte.
            // **On ne propose pas un départ qui ne peut rien rapporter** :
            // tous les témoignages versés, l'émissaire ne ramènerait qu'un
            // « rien appris de neuf » payé trente deben. Même règle que la
            // prospection — le bouton disparaît plutôt que de mentir.
            'peutEnvoyerUnEmissaire' => $zone->estDecouverte()
                && !$zone->porteLaVille()
                && $ville->possede(TypeDeBatiment::MaisonDesScribes)
                && !$ville->aUneExpeditionVers($zone)
                && $this->enquetes->resteUnTemoignageARecueillir($partie),
            'coutDeLEmissaire' => $this->explorations->coutVers($partie, $zone, RoleDExploration::Emissaire),
            'provisionsDeLEmissaire' => $this->explorations->provisionsVers($partie, $zone, RoleDExploration::Emissaire),
            // Sans Port, aucune barque n'appareille : la case poissonneuse
            // s'affiche, mais le bouton laisse la place au motif.
            'aUnPort' => $ville->possede(TypeDeBatiment::Port),
            // Les équipages du territoire, indexés par « x:y:ressource » —
            // c'est ce qui dit au joueur qu'une carrière tourne à moitié faute
            // de bras, plutôt que de le lui laisser deviner au stock.
            'equipages' => Effectifs::repartirLeTerritoire($ville, $partie->getCycle()),
            // Le prospecteur sonde une case déjà reconnue. On ne propose le
            // départ que si quelque chose peut en sortir : un filon épuisé à
            // rouvrir, ou de la place pour un nouveau que le terrain accepte.
            // Annoncer un départ qui ne peut rien rapporter serait un piège.
            'filonsAProspecter' => $filons,
            'peutProspecter' => $zone->estDecouverte()
                && !$ville->aUneExpeditionVers($zone)
                && [] !== $filons,
            'coutDuProspecteur' => $this->explorations->coutVers($partie, $zone, RoleDExploration::Prospecteur),
            'provisionsDuProspecteur' => $this->explorations->provisionsVers($partie, $zone, RoleDExploration::Prospecteur),
            'dureeDuProspecteur' => $this->explorations->dureeVers($partie, $zone),
            // Toutes les cases ne se valent pas : une veine encore exploitée
            // se retrouve à coup sûr, du sable vierge tient du pari. L'écran
            // dit lequel des deux **avant** l'engagement.
            'chancesDeProspecter' => $this->prospection->chancesSur($partie, $zone),
        ];
    }
}
