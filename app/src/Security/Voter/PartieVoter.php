<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\GameSave;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Une partie n'appartient qu'à son joueur.
 *
 * Un simple contrôle de rôle ne suffirait pas : tout compte porte ROLE_USER,
 * et pourrait donc lire ou supprimer la partie d'un autre en changeant
 * l'identifiant dans l'URL.
 *
 * @extends Voter<string, GameSave>
 */
final class PartieVoter extends Voter
{
    public const string VOIR = 'PARTIE_VOIR';
    public const string SUPPRIMER = 'PARTIE_SUPPRIMER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::SUPPRIMER], true)
            && $subject instanceof GameSave;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $utilisateur = $token->getUser();

        // Le type de $subject est déjà garanti par supports() : Symfony
        // n'appelle cette méthode qu'après lui.
        if (!$utilisateur instanceof User) {
            return false;
        }

        // Voir et supprimer supposent la même chose : être le propriétaire.
        return $subject->getJoueur()->getId() === $utilisateur->getId();
    }
}
