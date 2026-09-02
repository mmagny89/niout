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
        private Enquetes $enquetes,
        private Prospection $prospection,
        private Medjays $medjays,
        private Combat $combat,
    ) {
    }

    /**
     * @throws ExplorationImpossible
     */
    public function envoyer(GameSave $partie, Zone $destination, RoleDExploration $role, int $charriers = 0): Expedition
    {
        $ville = $partie->getVille();

        if ($destination->getVille() !== $ville) {
            throw new ExplorationImpossible('Cette case n\'appartient pas à votre territoire.');
        }

        // Un éclaireur va vers l'inconnu ; l'émissaire et le prospecteur vont
        // vers une case déjà reconnue — il n'y a personne à qui parler, ni rien
        // à sonder, sur une case que nul n'a jamais vue.
        if ($role->viseUneCaseInconnue() && $destination->estDecouverte()) {
            throw new ExplorationImpossible('Cette case est déjà reconnue.');
        }

        if (!$role->viseUneCaseInconnue() && !$destination->estDecouverte()) {
            throw new ExplorationImpossible('Envoyez d\'abord un éclaireur : on ne parle pas à des gens qu\'on n\'a pas trouvés, et l\'on ne sonde pas une terre qu\'on n\'a pas vue.');
        }

        // **Le chef d'expédition ne part que déloger une bande**, et il est le
        // seul à pouvoir le faire (lot 10.5). Les autres rôles n'ont rien à
        // faire sur une case tenue : on n'envoie pas un scribe parlementer
        // avec des brigands.
        if ($role->meneLaTroupe()) {
            if (!$destination->estGardee()) {
                throw new ExplorationImpossible('Personne ne tient cette case : une expédition en armes n\'y a rien à faire.');
            }

            if ([] === $this->medjays->disponibles($partie)) {
                throw new ExplorationImpossible('Vous n\'avez aucun Medjaÿ en état de partir. La Caserne en lève.');
            }
        } else {
            // On ne loue pas les chars du pharaon pour aller reconnaître une
            // berge : la réquisition ne vaut que pour une sortie en armes.
            $charriers = 0;

            if ($destination->estGardee()) {
                throw new ExplorationImpossible('Des brigands tiennent cette case. Il faut les déloger avant d\'y envoyer qui que ce soit d\'autre.');
            }
        }

        if ($charriers > 0) {
            $empechement = Charrier::empechement($ville);

            if (null !== $empechement) {
                throw new ExplorationImpossible($empechement);
            }
        }

        if (RoleDExploration::Prospecteur === $role && [] === $this->prospection->filonsPossibles($partie, $destination)) {
            throw new ExplorationImpossible('Cette case n\'a plus rien à donner : aucun filon à rouvrir, et rien de neuf à y trouver.');
        }

        if ($role->exigeLaMaisonDesScribes() && !$ville->possede(TypeDeBatiment::MaisonDesScribes)) {
            throw new ExplorationImpossible('Sans Maison des scribes, personne ne consignerait ce qu\'on vous rapporterait.');
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

        // Les chars se paient à la réquisition, et par sortie : il n'y a aucun
        // entretien puisqu'il n'y a rien à entretenir (doc 03).
        if ($charriers > 0 && !$ville->debiterRessources([Ressource::Deben->value => $charriers * Charrier::COUT_PAR_EXPEDITION])) {
            throw new ExplorationImpossible(\sprintf('Réquisitionner %d char(s) demande %d deben de plus.', $charriers, $charriers * Charrier::COUT_PAR_EXPEDITION));
        }

        $expedition = new Expedition($ville, $destination, $role, $this->dureeVers($partie, $destination));
        $expedition->requisitionner($charriers);
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

            $evenements[] = match ($expedition->getRole()) {
                RoleDExploration::Emissaire => $this->rapportDeLEmissaire($partie),
                RoleDExploration::Prospecteur => $this->prospection->fouiller($partie, $zone),
                RoleDExploration::ChefDExpedition => $this->rapportDeLAssaut($partie, $zone, $expedition->getCharriers()),
                default => $this->rapportDeLaReconnaissance($zone),
            };

            $ville->retirerExpedition($expedition);
            $this->entityManager->remove($expedition);
        }

        return $evenements;
    }

    /**
     * Ce qu'un émissaire ramène. **Il peut revenir bredouille**, et l'écran le
     * dit : faire semblant d'avoir appris quelque chose serait pire que de
     * l'avouer.
     */
    private function rapportDeLEmissaire(GameSave $partie): string
    {
        $indice = $this->enquetes->recueillirUnTemoignage($partie);

        if (null === $indice) {
            return 'Votre émissaire est rentré : on lui a beaucoup parlé, et rien appris de neuf.';
        }

        return \sprintf(
            'Votre émissaire rapporte : « %s » Versé au dossier « %s ».',
            $indice->texte(),
            $indice->enquete()->libelle(),
        );
    }

    /**
     * Ce qu'une expédition en armes rapporte (doc 03, lot 10.5).
     *
     * **Le combat se résout à l'arrivée**, pas au départ : la troupe met le
     * temps du trajet à parvenir sur place, et c'est ce qui distingue un assaut
     * préparé d'un bouton sur la carte.
     *
     * La bande peut avoir été délogée entre-temps par une autre expédition ; on
     * le dit plutôt que de lever une exception dans un passage de cycle, qui
     * n'a personne à qui la présenter.
     */
    private function rapportDeLAssaut(GameSave $partie, Zone $zone, int $charriers): string
    {
        if (!$zone->estGardee()) {
            return 'Votre expédition est arrivée sur une case déjà libérée : elle rentre sans avoir eu à combattre.';
        }

        $resultat = $this->combat->livrer($partie, $zone, $this->medjays->disponibles($partie), $charriers);

        $recit = $resultat->victoire
            ? \sprintf(
                'La case est prise : vos hommes en rapportent %d deben, et chacun y a appris quelque chose.',
                $resultat->butin,
            )
            : 'Vos hommes ont dû se retirer. La bande tient toujours la case.';

        if ([] !== $resultat->blesses) {
            $recit .= \sprintf(' %d en reviennent blessés.', \count($resultat->blesses));
        }

        if ($resultat->aPerduDesHommes()) {
            $recit .= \sprintf(
                ' %s ne rentrent pas : il faudra en lever d\'autres, et tout leur réapprendre.',
                implode(', ', $resultat->tombes),
            );
        }

        return $recit;
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
    private function rapportDeLaReconnaissance(Zone $zone): string
    {
        $zone->decouvrir();
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
