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

        $distance = $this->distanceVers($partie, $destination);
        $cout = $role->coutPourUneDistance($distance);
        $provisions = $role->provisionsPourUneDistance($distance);

        if ($ville->getNourriture() < $provisions) {
            throw new ExplorationImpossible(\sprintf('Il vous faut %d de vivres pour envoyer un %s. Vos réserves n\'en comptent que %d.', $provisions, mb_strtolower($role->libelle()), $ville->getNourriture()));
        }

        if (!$ville->debiterRessources([Ressource::Deben->value => $cout])) {
            throw new ExplorationImpossible(\sprintf('Il vous faut %d deben pour envoyer un %s.', $cout, mb_strtolower($role->libelle())));
        }

        // Les deben sont déjà retirés ; les vivres sont garanties par le contrôle
        // ci-dessus, donc ce débit ne peut plus échouer.
        $ville->debiterNourriture($provisions);

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
        return Expedition::dureeDuTrajet(
            $this->distanceVers($partie, $destination),
            $destination->getTerrain(),
            $partie->dateDeJeu()->saison,
        );
    }

    /**
     * Solde en deben dû pour reconnaître cette case — nul à moins de trois cases
     * de la ville. Exposé pour que l'écran annonce le vrai prix avant l'envoi.
     */
    public function coutVers(GameSave $partie, Zone $destination, RoleDExploration $role): int
    {
        return $role->coutPourUneDistance($this->distanceVers($partie, $destination));
    }

    /**
     * Vivres dus pour reconnaître cette case — nuls dans le même rayon que
     * `coutVers()` : une case proche ne coûte rien du tout.
     */
    public function provisionsVers(GameSave $partie, Zone $destination, RoleDExploration $role): int
    {
        return $role->provisionsPourUneDistance($this->distanceVers($partie, $destination));
    }

    private function distanceVers(GameSave $partie, Zone $destination): int
    {
        $centre = $partie->getVille()->zoneDeLaVille();

        return null === $centre ? 1 : $destination->distanceDepuis($centre);
    }

    /**
     * Ce que l'éclaireur rapporte : le terrain, et ce qu'il y a trouvé.
     */
    private function rapportDe(Zone $zone): string
    {
        $lieu = \sprintf('%s (%d, %d)', $zone->getTerrain()->libelle(), $zone->getX(), $zone->getY());

        if ($zone->porteUnGisement()) {
            $trouvailles = [];

            foreach ($zone->getGisements() as $gisement) {
                $trouvailles[] = $gisement->getRessource()->libelle();
            }

            return \sprintf('%s : gisement de %s.', $lieu, implode(' et de ', $trouvailles));
        }

        return match ($zone->getContenu()) {
            ContenuDeZone::ChampEligible => \sprintf('%s : de la terre cultivable.', $lieu),
            ContenuDeZone::Evenement => \sprintf('%s : quelque chose s\'y trame.', $lieu),
            default => \sprintf('%s : rien de notable.', $lieu),
        };
    }
}
