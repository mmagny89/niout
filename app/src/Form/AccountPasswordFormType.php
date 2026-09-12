<?php

declare(strict_types=1);

namespace App\Form;

use App\Security\ContraintesDeMotDePasse;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Changer son mot de passe depuis son compte, en le connaissant déjà.
 *
 * À la différence de la réinitialisation par email, l'ancien mot de passe est
 * exigé : la session peut avoir été laissée ouverte sur une machine partagée,
 * et sans cette preuve n'importe qui la trouvant s'approprierait le compte.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class AccountPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ancienMotDePasse', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => ['autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(message: 'Indiquez votre mot de passe actuel.'),
                    new UserPassword(message: 'Ce n\'est pas votre mot de passe actuel.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'options' => ['attr' => ['autocomplete' => 'new-password']],
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    // La liste est partagée avec l'inscription et la
                    // réinitialisation : voir ContraintesDeMotDePasse.
                    'constraints' => ContraintesDeMotDePasse::liste(),
                ],
                'second_options' => ['label' => 'Confirmez le nouveau mot de passe'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
