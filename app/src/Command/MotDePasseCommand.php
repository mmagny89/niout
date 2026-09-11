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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Change le mot de passe d'un compte.
 *
 * **Le mot de passe ne se passe jamais en argument** : il resterait en clair
 * dans l'historique du shell, et de là dans le premier copier-coller
 * malheureux. Il se saisit à l'invite, masqué — ou se fait engendrer par
 * `--generer` quand il s'agit de rendre la main à quelqu'un.
 *
 * Les exigences sont **celles de l'inscription** (`RegistrationFormType`) :
 * longueur, robustesse, absence des fuites connues. Une porte de service qui
 * accepterait « azerty » rendrait ces règles décoratives.
 */
#[AsCommand(
    name: 'app:users:password',
    description: 'Change le mot de passe d\'un compte',
)]
final class MotDePasseCommand
{
    /**
     * Longueur du mot de passe engendré par --generer, en octets avant encodage
     * (24 octets donnent 32 caractères en base64url, très au-delà du minimum).
     */
    private const int OCTETS_ENGENDRES = 24;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $hacheur,
        private readonly ValidatorInterface $validateur,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Adresse email du compte')]
        string $email,
        #[Option(description: 'Engendre un mot de passe fort et l\'affiche, au lieu de le demander')]
        bool $generer = false,
    ): int {
        $compte = $this->userRepository->findOneBy(['email' => $email]);

        if (!$compte instanceof User) {
            $io->error(\sprintf('Aucun compte pour %s.', $email));

            return Command::FAILURE;
        }

        $motDePasse = $generer ? $this->engendrer() : $this->demander($io);

        if (null === $motDePasse) {
            $io->warning('Mot de passe inchangé.');

            return Command::FAILURE;
        }

        $fautes = $this->fautes($motDePasse, $io);

        if ([] !== $fautes) {
            $io->error($fautes);

            return Command::FAILURE;
        }

        $compte->setPassword($this->hacheur->hashPassword($compte, $motDePasse));
        $this->entityManager->flush();

        $io->success(\sprintf('Le mot de passe de %s est changé.', $email));

        if ($generer) {
            // Affiché une fois, et nulle part ailleurs : seul le haché est
            // conservé, il n'y a pas de second moyen de le relire.
            $io->writeln(\sprintf('Mot de passe engendré : <info>%s</info>', $motDePasse));
            $io->note('Notez-le maintenant : il n\'est stocké que haché, et ne pourra pas être relu.');
        }

        return Command::SUCCESS;
    }

    /**
     * Saisie masquée, puis confirmation : une faute de frappe invisible
     * enfermerait dehors le titulaire du compte, sans autre recours que de
     * rejouer cette commande.
     */
    private function demander(SymfonyStyle $io): ?string
    {
        $motDePasse = $io->askHidden('Nouveau mot de passe');

        if (null === $motDePasse || '' === $motDePasse) {
            return null;
        }

        if ($motDePasse !== $io->askHidden('Confirmez le mot de passe')) {
            $io->error('Les deux saisies ne correspondent pas.');

            return null;
        }

        return $motDePasse;
    }

    private function engendrer(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(self::OCTETS_ENGENDRES)), '+/', '-_'), '=');
    }

    /**
     * @return list<string> vide quand le mot de passe convient
     */
    private function fautes(string $motDePasse, SymfonyStyle $io): array
    {
        $contraintes = [
            new NotBlank(message: 'Choisissez un mot de passe.'),
            new Length(
                min: 12,
                minMessage: 'Le mot de passe doit compter au moins {{ limit }} caractères.',
                max: 4096,
            ),
            new PasswordStrength(message: 'Ce mot de passe est trop facile à deviner.'),
        ];

        $fautes = $this->messages($this->validateur->validate($motDePasse, $contraintes));

        if ([] !== $fautes) {
            return $fautes;
        }

        // La vérification des fuites interroge un service distant. Hors ligne,
        // elle lève une exception : mieux vaut le dire et laisser passer un
        // mot de passe déjà jugé fort que refuser tout changement — cette
        // commande est aussi ce qu'on lance quand plus rien ne va.
        try {
            return $this->messages($this->validateur->validate($motDePasse, [
                new NotCompromisedPassword(message: 'Ce mot de passe apparaît dans une fuite de données connue. Choisissez-en un autre.'),
            ]));
        } catch (\Throwable $panne) {
            $io->warning(\sprintf(
                'Impossible de vérifier les fuites connues (%s). Le mot de passe est accepté sur ses autres critères.',
                $panne->getMessage(),
            ));

            return [];
        }
    }

    /**
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface<\Symfony\Component\Validator\ConstraintViolationInterface> $violations
     *
     * @return list<string>
     */
    private function messages(\Symfony\Component\Validator\ConstraintViolationListInterface $violations): array
    {
        $messages = [];

        foreach ($violations as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
