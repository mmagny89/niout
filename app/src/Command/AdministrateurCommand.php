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
 * Accorde ou retire le rôle qui ouvre l'administration des comptes.
 *
 * **En console et nulle part ailleurs**, pour la même raison que le mode divin
 * (`app:users:goddess`) : l'écran ouvert par ce rôle supprime définitivement le
 * compte d'un tiers et toutes ses parties. Un écran qui permettrait de se
 * l'octroyer ne serait pas une barrière.
 */
#[AsCommand(
    name: 'app:users:admin',
    description: 'Accorde (ou retire) l\'accès à l\'administration des comptes',
)]
final class AdministrateurCommand
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
        #[Option(description: 'Retire l\'accès au lieu de l\'accorder')]
        bool $retirer = false,
    ): int {
        $compte = $this->userRepository->findOneBy(['email' => $email]);

        if (!$compte instanceof User) {
            $io->error(\sprintf('Aucun compte pour %s.', $email));

            return Command::FAILURE;
        }

        // Les rôles se reconstruisent à partir de ceux déjà portés : retirer
        // l'accès ne doit pas emporter le mode divin au passage. ROLE_USER est
        // écarté parce que getRoles() l'ajoute d'office — le persister
        // laisserait un doublon en base.
        $roles = array_values(array_filter(
            $compte->getRoles(),
            static fn (string $role): bool => User::ROLE_ADMIN !== $role && 'ROLE_USER' !== $role,
        ));

        if (!$retirer) {
            $roles[] = User::ROLE_ADMIN;
        }

        $compte->setRoles($roles);
        $this->entityManager->flush();

        $io->success(\sprintf(
            '%s %s l\'accès à l\'administration des comptes.',
            $email,
            $retirer ? 'a perdu' : 'a reçu',
        ));

        return Command::SUCCESS;
    }
}
