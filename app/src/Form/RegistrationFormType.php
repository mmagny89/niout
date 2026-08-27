<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['autocomplete' => 'email'],
                'constraints' => [
                    new NotBlank(message: 'Indiquez votre adresse email.'),
                    new Email(message: 'Cette adresse email n\'est pas valide.'),
                ],
            ])
            // Les mêmes exigences qu'à la réinitialisation (ChangePasswordFormType) :
            // un mot de passe faible à l'inscription rendrait ces règles inutiles.
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'Mot de passe',
                    'constraints' => [
                        new NotBlank(message: 'Choisissez un mot de passe.'),
                        new Length(
                            min: 12,
                            minMessage: 'Votre mot de passe doit compter au moins {{ limit }} caractères.',
                            // Longueur maximale admise par Symfony, par sécurité.
                            max: 4096,
                        ),
                        new PasswordStrength(message: 'Ce mot de passe est trop facile à deviner.'),
                        new NotCompromisedPassword(message: 'Ce mot de passe apparaît dans une fuite de données connue. Choisissez-en un autre.'),
                    ],
                ],
                'second_options' => ['label' => 'Confirmez le mot de passe'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
