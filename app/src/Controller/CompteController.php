<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CompteController extends AbstractController
{
    #[Route('/compte', name: 'app_compte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('compte/index.html.twig', [
            'user' => $user,
            // Date limite de vérification, affichée tant que l'adresse ne l'est
            // pas — le compte reste utilisable jusque-là (voir User).
            'dateLimiteVerification' => $user->isVerified()
                ? null
                : $user->getCreatedAt()->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS)),
        ]);
    }
}
