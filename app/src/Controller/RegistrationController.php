<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $expediteur,
    ) {
    }

    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectToRoute('app_compte');
        }

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $motDePasseEnClair */
            $motDePasseEnClair = $form->get('plainPassword')->getData();
            $user->setPassword($hasher->hashPassword($user, $motDePasseEnClair));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->envoyerEmailDeVerification($user);

            // Le compte est utilisable immédiatement : la vérification d'adresse
            // n'est jamais bloquante, elle conditionne seulement la purge à
            // J+7 (voir User::isPurgeable()).
            $security->login($user);

            $this->addFlash('succes', \sprintf(
                'Bienvenue. Un message de vérification vient de partir vers %s : votre compte sera supprimé s\'il n\'est pas vérifié sous %d jours.',
                $user->getEmail(),
                User::DELAI_VERIFICATION_JOURS,
            ));

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'delaiVerification' => User::DELAI_VERIFICATION_JOURS,
        ]);
    }

    #[Route('/verification-email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        TranslatorInterface $translator,
        UserRepository $userRepository,
    ): Response {
        $id = $request->query->get('id');
        $user = null === $id ? null : $userRepository->find($id);

        if (!$user instanceof User) {
            $this->addFlash('erreur', 'Ce lien de vérification n\'est pas valide.');

            return $this->redirectToRoute('app_accueil');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('erreur', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_accueil');
        }

        $this->addFlash('succes', 'Votre adresse est vérifiée. Votre compte est définitivement conservé.');

        return $this->redirectToRoute('app_compte');
    }

    /**
     * Renvoi du message de vérification. Réservé à l'utilisateur connecté et
     * envoyé à sa propre adresse : impossible d'en faire un outil d'envoi
     * massif vers l'adresse d'un tiers.
     */
    #[Route('/verification-email/renvoyer', name: 'app_resend_verification', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    #[IsCsrfTokenValid('renvoyer-verification', tokenKey: '_token')]
    public function resendVerification(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isVerified()) {
            return $this->redirectToRoute('app_compte');
        }

        $this->envoyerEmailDeVerification($user);
        $this->addFlash('succes', \sprintf('Un nouveau message de vérification est parti vers %s.', $user->getEmail()));

        return $this->redirectToRoute('app_compte');
    }

    private function envoyerEmailDeVerification(User $user): void
    {
        $this->emailVerifier->sendEmailConfirmation(
            'app_verify_email',
            $user,
            (new TemplatedEmail())
                ->from(new Address($this->expediteur, 'Niout'))
                ->to($user->getEmail())
                ->subject('Vérifiez votre adresse — Niout')
                ->htmlTemplate('registration/confirmation_email.html.twig')
                ->context(['delaiVerification' => User::DELAI_VERIFICATION_JOURS]),
        );
    }
}
