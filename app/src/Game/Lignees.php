<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Lignee;
use App\Entity\User;
use App\Repository\EtatDePartenaireRepository;
use App\Repository\LigneeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * L'accès à la lignée d'un joueur, et les deux seuls gestes qu'on lui fait
 * subir : la lire au lancement d'une partie, la relever à la fin d'une mission
 * (doc 13).
 *
 * **Créée paresseusement.** Un compte qui n'a jamais joué n'a pas de lignée :
 * la fabriquer à l'inscription obligerait à la rattraper pour tous les comptes
 * existants, et à la maintenir dans le tunnel d'inscription, pour un objet qui
 * ne sert qu'au premier lancement.
 */
final readonly class Lignees
{
    public function __construct(
        private LigneeRepository $lignees,
        private EtatDePartenaireRepository $etatsDesCites,
        private EntityManagerInterface $entityManager,
        private ?Visages $visages = null,
    ) {
    }

    /**
     * La lignée du joueur, créée si c'est sa première. **Non flushée** : elle
     * l'est par l'appelant, avec la partie qu'elle sert.
     */
    public function pour(User $joueur): Lignee
    {
        $lignee = $this->lignees->findPourJoueur($joueur) ?? $this->enAttente($joueur);

        if (null === $lignee) {
            $lignee = new Lignee($joueur);
            $this->entityManager->persist($lignee);
        }

        return $lignee;
    }

    /**
     * Une lignée créée plus tôt dans la même requête, **pas encore flushée** : sans elle, deux appels avant le
     * flush en créeraient deux, et la contrainte d'unicité (`joueur_id`) refuserait le second. C'est le cas
     * d'une première partie, qui lit l'acquis de départ puis y sème un visage.
     */
    private function enAttente(User $joueur): ?Lignee
    {
        foreach ($this->entityManager->getUnitOfWork()->getScheduledEntityInsertions() as $entite) {
            if ($entite instanceof Lignee && $entite->getJoueur() === $joueur) {
                return $entite;
            }
        }

        return null;
    }

    /**
     * Ce avec quoi une nouvelle partie démarre : tout l'acquis, sans
     * proportion ni plafond. C'est l'inverse exact de l'ancien legs de
     * renommée, qui repartait de zéro et n'ajoutait que quatre points.
     */
    public function renommeeDeDepart(User $joueur): int
    {
        return $this->pour($joueur)->getRenommeeAcquise();
    }

    /**
     * Verse à la lignée la renommée que la famille a en ce moment.
     *
     * **Les deux modes l'alimentent** (arbitrage 11.0) : la campagne à
     * l'achèvement d'une mission, l'Aventure à chaque fin de règne. La
     * renommée appartient à la famille, pas au mode — l'asymétrie d'avant ne se
     * justifiait par aucun document, et l'Aventure n'avait simplement aucun
     * jalon où la verser tant que les règnes n'existaient pas.
     *
     * **C'est l'appelant qui décide du jalon, jamais cette méthode** : elle ne
     * sait pas ce qui vient de s'achever, seulement qu'il faut relever
     * l'acquis. L'acquis ne peut que monter, ici comme ailleurs.
     */
    public function encaisser(GameSave $partie): void
    {
        $lignee = $this->pour($partie->getJoueur());
        $lignee->relever($partie->getFamille()->getRenommee());

        // Les cités suivent la renommée : ce qu'on a bâti avec elles se retrouve à la partie suivante.
        foreach ($this->etatsDesCites->deLaPartie($partie) as $etat) {
            $lignee->releverLaRelation($etat->getCite(), $etat->getRelation());
        }

        // Les personnes rencontrées se retiennent aussi : on les reverra, au plus douze.
        $this->visages?->retenir($partie, $lignee);
    }
}
