<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\GameSaveRepository;
use App\Repository\LigneeRepository;
use App\Repository\ResetPasswordRequestRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprime un compte et tout ce qui s'y rattache, définitivement.
 *
 * **L'ordre n'est pas négociable** : tout ce qui référence l'utilisateur par
 * une clé étrangère part d'abord, sinon la base refuse la suppression du
 * compte. Les parties emportent leur famille et leur ville par cascade ; la
 * lignée, elle, appartient au joueur et non à ses parties (CLAUDE.md) — aucune
 * cascade ne l'emporte, elle se supprime explicitement ici.
 *
 * Ce service existe pour que cette séquence n'ait qu'un seul exemplaire : la
 * purge des comptes non vérifiés (`app:users:purge-unverified`) et l'écran
 * d'administration suppriment la même chose de la même façon. Toute entité
 * neuve qui référencera `User` directement s'ajoute **ici**, et les deux
 * appelants en profitent — les laisser diverger, c'est se donner une purge qui
 * marche et un écran qui échoue sur une contrainte, ou l'inverse.
 */
final class SuppressionDeCompte
{
    public function __construct(
        private readonly ResetPasswordRequestRepository $demandesDeReinitialisation,
        private readonly GameSaveRepository $parties,
        private readonly LigneeRepository $lignees,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Marque le compte et ses dépendances pour suppression, **sans flush** :
     * l'appelant décide du moment — la purge en traite des dizaines d'un coup,
     * l'écran d'administration un seul.
     */
    public function programmer(User $compte): void
    {
        $this->demandesDeReinitialisation->removeRequests($compte);

        foreach ($this->parties->findPourJoueur($compte) as $partie) {
            $this->entityManager->remove($partie);
        }

        $lignee = $this->lignees->findPourJoueur($compte);

        if (null !== $lignee) {
            $this->entityManager->remove($lignee);
        }

        $this->entityManager->remove($compte);
    }

    /**
     * Supprime un compte séance tenante.
     */
    public function supprimer(User $compte): void
    {
        $this->programmer($compte);
        $this->entityManager->flush();
    }
}
