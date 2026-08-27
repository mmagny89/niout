<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * Mot de passe hashé (jamais le mot de passe en clair).
     */
    #[ORM\Column]
    private string $password;

    /**
     * Un compte non vérifié reste pleinement utilisable : la vérification
     * n'est jamais bloquante. Elle conditionne en revanche la purge décrite
     * ci-dessous avec $createdAt.
     */
    #[ORM\Column]
    private bool $verified = false;

    /**
     * Date d'inscription, base du délai de grâce de vérification : passé
     * self::DELAI_VERIFICATION_JOURS sans vérification, le compte est
     * définitivement supprimé (commande app:users:purge-unverified).
     */
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * Délai de grâce, en jours, laissé au joueur pour vérifier son adresse.
     */
    public const int DELAI_VERIFICATION_JOURS = 7;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        // Invariant garanti en amont par la validation du formulaire
        // (NotBlank + Email) : une adresse vide ne peut pas être persistée.
        \assert('' !== $this->email);

        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Vrai quand le délai de grâce de vérification est écoulé sans que
     * l'adresse ait été vérifiée — le compte est alors purgeable.
     */
    public function isPurgeable(\DateTimeImmutable $maintenant): bool
    {
        if ($this->verified) {
            return false;
        }

        return $this->createdAt->modify(\sprintf('+%d days', self::DELAI_VERIFICATION_JOURS)) <= $maintenant;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }
}
