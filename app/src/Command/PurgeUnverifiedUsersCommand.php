<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Supprime définitivement les comptes dont l'adresse email n'a pas été vérifiée
 * dans le délai de grâce. Destinée à une tâche planifiée quotidienne.
 */
#[AsCommand(
    name: 'app:users:purge-unverified',
    description: 'Supprime les comptes non vérifiés dont le délai de grâce est écoulé',
)]
final class PurgeUnverifiedUsersCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Liste les comptes concernés sans rien supprimer')]
        bool $dryRun = false,
    ): int {
        $maintenant = new \DateTimeImmutable();
        $comptes = $this->userRepository->findPurgeable($maintenant);

        if ([] === $comptes) {
            $io->success(\sprintf(
                'Aucun compte à purger (délai de grâce : %d jours).',
                User::DELAI_VERIFICATION_JOURS,
            ));

            return Command::SUCCESS;
        }

        $io->table(
            ['Adresse', 'Inscrit le'],
            array_map(
                static fn (User $user): array => [$user->getEmail(), $user->getCreatedAt()->format('d/m/Y H:i')],
                $comptes,
            ),
        );

        if ($dryRun) {
            $io->note(\sprintf('%d compte(s) seraient supprimés. Aucune suppression effectuée.', \count($comptes)));

            return Command::SUCCESS;
        }

        foreach ($comptes as $user) {
            // Les demandes de réinitialisation référencent l'utilisateur par une
            // clé étrangère : sans ce retrait préalable, la suppression échoue.
            $this->resetPasswordRequestRepository->removeRequests($user);
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $io->success(\sprintf('%d compte(s) non vérifié(s) supprimé(s).', \count($comptes)));

        return Command::SUCCESS;
    }
}
