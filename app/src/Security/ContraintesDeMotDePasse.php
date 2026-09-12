<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Ce qu'on exige d'un mot de passe, en un seul endroit.
 *
 * Trois formulaires le demandent — l'inscription, la réinitialisation par
 * email, le changement depuis le compte — et les exigences y étaient recopiées.
 * Trois copies d'une même règle finissent par diverger, et la divergence est
 * silencieuse : la plus laxiste devient la vraie, puisqu'il suffit de passer par
 * elle. C'est aussi cette liste que l'écran annonce, dans
 * `_regles_de_mot_de_passe.html.twig` : la faire varier d'un formulaire à
 * l'autre rendrait cette annonce fausse quelque part.
 *
 * La longueur minimale est reprise par le contrôleur Stimulus qui coche les
 * critères en direct ; le seuil de force est celui de Symfony par défaut,
 * « moyen », et `SeuilsDeForceTest` en garde la mesure.
 */
final class ContraintesDeMotDePasse
{
    public const int LONGUEUR_MINIMALE = 12;

    /**
     * @return list<Constraint>
     */
    public static function liste(): array
    {
        return [
            new NotBlank(message: 'Choisissez un mot de passe.'),
            new Length(
                min: self::LONGUEUR_MINIMALE,
                minMessage: 'Votre mot de passe doit compter au moins {{ limit }} caractères.',
                // Longueur maximale admise par Symfony, par sécurité.
                max: 4096,
            ),
            new PasswordStrength(message: 'Ce mot de passe est trop facile à deviner.'),
            new NotCompromisedPassword(message: 'Ce mot de passe apparaît dans une fuite de données connue. Choisissez-en un autre.'),
        ];
    }
}
