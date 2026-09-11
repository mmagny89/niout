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
 * Accorde ou retire le rôle des comptes privilégiés : administration des
 * comptes **et** mode divin.
 *
 * **En console et nulle part ailleurs.** Le rôle ouvre un écran qui supprime
 * définitivement le compte d'un tiers, et un mode qui donne un million de
 * chaque ressource et ouvre les dix missions. Un écran qui permettrait de se
 * l'octroyer ne serait pas une barrière.
 *
 * Cette commande remplace `app:users:goddess` : les deux rôles n'en font plus
 * qu'un (voir `User::ROLE_ADMIN`).
 */
#[AsCommand(
    name: 'app:users:admin',
    description: 'Accorde (ou retire) l\'administration des comptes et le mode divin',
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

        // Les rôles se reconstruisent à partir de ceux déjà portés : accorder
        // deux fois ne doit pas laisser le rôle en double. ROLE_USER est
        // écarté parce que getRoles() l'ajoute d'office — le persister
        // laisserait, lui aussi, un doublon en base.
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
            '%s %s l\'administration des comptes et le mode divin.',
            $email,
            $retirer ? 'a perdu' : 'a reçu',
        ));

        return Command::SUCCESS;
    }
}
