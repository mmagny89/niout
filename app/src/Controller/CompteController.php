<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\AccountPasswordFormType;
use App\Repository\GameSaveRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
        return $this->ecran($parties, $this->createForm(AccountPasswordFormType::class));
    }

    /**
     * Changer son mot de passe en connaissant l'ancien.
     *
     * Une route a elle plutot qu'un POST sur `/compte` : l'ecran de compte est
     * une page qu'on rafraichit, et un formulaire soumis sur sa propre adresse
     * en GET ferait rejouer l'envoi au rechargement.
     *
     * En cas d'erreur, l'ecran complet est rendu avec le formulaire tel quel —
     * pas de redirection : les messages de validation se perdraient, et l'on ne
     * saurait pas ce qui a ete refuse.
     */
    #[Route('/compte/mot-de-passe', name: 'app_compte_mot_de_passe', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function changerLeMotDePasse(
        Request $request,
        GameSaveRepository $parties,
        UserPasswordHasherInterface $hacheur,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $formulaire = $this->createForm(AccountPasswordFormType::class);
        $formulaire->handleRequest($request);

        if (!$formulaire->isSubmitted() || !$formulaire->isValid()) {
            return $this->ecran($parties, $formulaire, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        /** @var string $nouveau */
        $nouveau = $formulaire->get('plainPassword')->getData();
        $user->setPassword($hacheur->hashPassword($user, $nouveau));
        $entityManager->flush();

        // **Reconnexion explicite.** Le jeton en session porte l'utilisateur
        // tel qu'il etait ; changer son mot de passe le rend obsolete, et le
        // joueur se retrouverait deconnecte au prochain clic — apres une action
        // reussie, ce qui se lit comme un echec.
        $security->login($user);

        $this->addFlash('succes', 'Votre mot de passe est changé.');

        return $this->redirectToRoute('app_compte');
    }

    /**
     * @param FormInterface<mixed> $formulaireDeMotDePasse
     */
    private function ecran(
        GameSaveRepository $parties,
        FormInterface $formulaireDeMotDePasse,
        int $statut = Response::HTTP_OK,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('compte/index.html.twig', [
            'user' => $user,
            'partiesEnCours' => $parties->compterEnCoursPourJoueur($user),
            'formulaireDeMotDePasse' => $formulaireDeMotDePasse,
            // Date limite de verification, affichee tant que l'adresse ne l'est
            // pas — le compte reste utilisable jusque-la (voir User).
            'dateLimiteVerification' => $user->isVerified()
                ? null
                : $user->getCreatedAt()->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS)),
        ], new Response(status: $statut));
    }
}
