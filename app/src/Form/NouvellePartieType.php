<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Enum\GameMode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class NouvellePartieType extends AbstractType
{
    /**
     * Tailles proposées en mode Aventure (doc 14).
     */
    public const array TAILLES_DE_GRILLE = [6, 8, 10];
    public const int TAILLE_DE_GRILLE_PAR_DEFAUT = 8;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', ChoiceType::class, [
                'label' => 'Mode de jeu',
                'choices' => [
                    GameMode::Campagne->libelle() => GameMode::Campagne,
                    GameMode::Aventure->libelle() => GameMode::Aventure,
                ],
                'expanded' => true,
                'multiple' => false,
                // Sans ça, les boutons radio portent l'index du choix (0, 1) au
                // lieu de la valeur de l'enum — illisible dans le HTML comme
                // dans les tests.
                'choice_value' => static fn (?GameMode $mode): ?string => $mode?->value,
                'data' => GameMode::Campagne,
            ]);

        if ([] !== $options['missionsOuvertes']) {
            $builder->add('mission', ChoiceType::class, [
                'label' => 'Mission de départ',
                'help' => 'Réservé au mode divin : ouvre les dix régions pour les essais.',
                'choices' => $options['missionsOuvertes'],
                'data' => GameSave::PREMIERE_MISSION,
            ]);
        }

        $builder
            // Le choix de mission n'existe que pour le mode divin : l'ordre
            // des missions est imposé (doc 09), et le champ n'est même pas
            // construit pour un joueur ordinaire — il ne suffirait pas de le
            // cacher, un POST forgé le rétablirait.
            ->add('nomDeFamille', TextType::class, [
                'label' => 'Nom de votre famille',
                'help' => 'Il apparaîtra dans les textes de la partie.',
                'data' => Family::NOM_PAR_DEFAUT,
                'constraints' => [
                    new NotBlank(message: 'Choisissez un nom de famille.'),
                    new Length(
                        min: 2,
                        max: 60,
                        minMessage: 'Ce nom est trop court ({{ limit }} caractères minimum).',
                        maxMessage: 'Ce nom est trop long ({{ limit }} caractères maximum).',
                    ),
                ],
            ])
            // Les deux champs suivants ne valent qu'en mode Aventure : la
            // campagne impose sa progression, région après région.
            ->add('difficulte', ChoiceType::class, [
                'label' => 'Difficulté',
                'help' => 'Ressources plus rares, commerce plus cher, routes plus dangereuses.',
                'choices' => array_combine(
                    array_map(
                        static fn (int $niveau): string => \sprintf('%d — %s', $niveau, self::libelleDeDifficulte($niveau)),
                        range(City::DIFFICULTE_MIN, City::DIFFICULTE_MAX),
                    ),
                    range(City::DIFFICULTE_MIN, City::DIFFICULTE_MAX),
                ),
                'data' => City::DIFFICULTE_MIN,
            ])
            ->add('tailleGrille', ChoiceType::class, [
                'label' => 'Taille de la carte',
                'choices' => array_combine(
                    array_map(static fn (int $t): string => \sprintf('%d × %d', $t, $t), self::TAILLES_DE_GRILLE),
                    self::TAILLES_DE_GRILLE,
                ),
                'data' => self::TAILLE_DE_GRILLE_PAR_DEFAUT,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // Les missions ouvertes au choix. Vide pour un joueur ordinaire, qui
        // n'a pas de champ « mission » du tout : l'ordre est imposé (doc 09).
        $resolver->setDefault('missionsOuvertes', []);
        $resolver->setAllowedTypes('missionsOuvertes', 'array');
    }

    private static function libelleDeDifficulte(int $niveau): string
    {
        return match (true) {
            $niveau <= 2 => 'clémente',
            $niveau <= 5 => 'exigeante',
            default => 'rude',
        };
    }
}
