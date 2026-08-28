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

    /**
     * Modifier l'état d'une partie — chantier, cycle, exploration, vente…
     * Une partie échouée (`Subsistance`) reste consultable mais ne se joue
     * plus : ses écrans de jeu se ferment, comme celui d'abandon.
     */
    public const string JOUER = 'PARTIE_JOUER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::SUPPRIMER, self::JOUER], true)
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

        // Les trois actions supposent d'abord d'être le propriétaire.
        if ($subject->getJoueur()->getId() !== $utilisateur->getId()) {
            return false;
        }

        // Jouer suppose en plus que la partie ne soit pas déjà terminée.
        return self::JOUER !== $attribute || $subject->estEnCours();
    }
}
