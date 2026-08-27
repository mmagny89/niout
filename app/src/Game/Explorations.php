<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Expedition;
use App\Entity\GameSave;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Envoi et progression des expéditions (doc 04).
 *
 * Toute case inconnue commence par une reconnaissance. Ce que l'éclaireur y
 * trouve dicte ensuite l'action complémentaire éventuelle — le joueur découvre
 * le besoin après coup, jamais avant.
 */
final readonly class Explorations
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @throws ExplorationImpossible
     */
    public function envoyer(GameSave $partie, Zone $destination, RoleDExploration $role): Expedition
    {
        $ville = $partie->getVille();

        if ($destination->getVille() !== $ville) {
            throw new ExplorationImpossible('Cette case n\'appartient pas à votre territoire.');
        }

        if ($destination->estDecouverte()) {
            throw new ExplorationImpossible('Cette case est déjà reconnue.');
        }

        if ($ville->aUneExpeditionVers($destination)) {
            throw new ExplorationImpossible('Une expédition est déjà en route vers cette case.');
        }

        if ($ville->getNourriture() < $role->provisions()) {
            throw new ExplorationImpossible(\sprintf('Il vous faut %d de vivres pour envoyer un %s. Vos réserves n\'en comptent que %d.', $role->provisions(), mb_strtolower($role->libelle()), $ville->getNourriture()));
        }

        if (!$ville->debiterRessources([Ressource::Or->value => $role->cout()])) {
            throw new ExplorationImpossible(\sprintf('Il vous faut %d or pour envoyer un %s.', $role->cout(), mb_strtolower($role->libelle())));
        }

        // L'or est déjà retiré ; les vivres sont garantis par le contrôle
        // ci-dessus, donc ce débit ne peut plus échouer.
        $ville->debiterNourriture($role->provisions());

        $expedition = new Expedition($ville, $destination, $role, $this->dureeVers($partie, $destination));
        $ville->ajouterExpedition($expedition);

        $this->entityManager->flush();

        return $expedition;
    }

    /**
     * Fait avancer les expéditions d'un cycle. Comme les chantiers, elle ne
     * persiste rien : PassageDeCycle réunit tout en une seule écriture.
     *
     * @return list<string>
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $evenements = [];
        $ville = $partie->getVille();

        foreach ($ville->getExpeditions() as $expedition) {
            $expedition->avancerDUnCycle();

            if (!$expedition->estArrivee()) {
                continue;
            }

            $zone = $expedition->getDestination();
            $zone->decouvrir();
            $evenements[] = $this->rapportDe($zone);

            $ville->retirerExpedition($expedition);
            $this->entityManager->remove($expedition);
        }

        return $evenements;
    }

    /**
     * Durée du trajet, en tenant compte de la saison en cours.
     */
    public function dureeVers(GameSave $partie, Zone $destination): int
    {
        $centre = $partie->getVille()->zoneDeLaVille();
        $distance = null === $centre ? 1 : $destination->distanceDepuis($centre);

        return Expedition::dureeDuTrajet(
            $distance,
            $destination->getTerrain(),
            $partie->dateDeJeu()->saison,
        );
    }

    /**
     * Ce que l'éclaireur rapporte : le terrain, et ce qu'il y a trouvé.
     */
    private function rapportDe(Zone $zone): string
    {
        $lieu = \sprintf('%s (%d, %d)', $zone->getTerrain()->libelle(), $zone->getX(), $zone->getY());
        $ressource = $zone->getRessource();

        if (null !== $ressource) {
            return \sprintf('%s : gisement de %s.', $lieu, $ressource->libelle());
        }

        return match ($zone->getContenu()) {
            ContenuDeZone::ChampEligible => \sprintf('%s : de la terre cultivable.', $lieu),
            ContenuDeZone::Evenement => \sprintf('%s : quelque chose s\'y trame.', $lieu),
            default => \sprintf('%s : rien de notable.', $lieu),
        };
    }
}
