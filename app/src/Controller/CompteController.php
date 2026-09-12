<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\GameSaveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Le compte lui-meme : adresse, verification, date d'inscription.
 *
 * La liste des parties vivait ici et occupait les trois quarts de la page ;
 * elle a son propre ecran, PartiesController. Ne reste ici que le nombre de
 * parties, qui dit quelque chose du compte et non du jeu.
 */
final class CompteController extends AbstractController
{
    #[Route('/compte', name: 'app_compte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(GameSaveRepository $parties): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('compte/index.html.twig', [
            'user' => $user,
            'partiesEnCours' => $parties->compterEnCoursPourJoueur($user),
            // Date limite de verification, affichee tant que l'adresse ne l'est
            // pas — le compte reste utilisable jusque-la (voir User).
            'dateLimiteVerification' => $user->isVerified()
                ? null
                : $user->getCreatedAt()->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS)),
        ]);
    }
}
