<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Accorde ou retire le rôle qui ouvre le mode divin.
 *
 * **En console et nulle part ailleurs.** Le mode divin donne un million de
 * chaque ressource, ouvre les dix missions et lève les plafonds de réserve :
 * un écran qui permettrait de se l'octroyer n'aurait aucune valeur de barrière.
 * Il se donne donc à la main, sur un compte nommé.
 */
#[AsCommand(
    name: 'app:users:goddess',
    description: 'Accorde (ou retire) le mode divin à un compte, pour les essais',
)]
final class ModeDivinCommand
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse email du compte')]
        string $email,
        #[Option(description: 'Retire le mode divin au lieu de l\'accorder')]
        bool $retirer = false,
    ): int {
        $joueur = $this->userRepository->findOneBy(['email' => $email]);

        if (!$joueur instanceof User) {
            $io->error(\sprintf('Aucun compte pour %s.', $email));

            return Command::FAILURE;
        }

        $roles = array_values(array_filter(
            $joueur->getRoles(),
            static fn (string $role): bool => User::ROLE_DIVIN !== $role && 'ROLE_USER' !== $role,
        ));

        if (!$retirer) {
            $roles[] = User::ROLE_DIVIN;
        }

        $joueur->setRoles($roles);
        $this->entityManager->flush();

        $io->success(\sprintf(
            '%s %s le mode divin.',
            $email,
            $retirer ? 'a perdu' : 'a reçu',
        ));

        return Command::SUCCESS;
    }
}
