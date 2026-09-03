<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSave;
use App\Entity\User;
use App\Game\MissionCatalogue;
use App\Repository\GameSaveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CompteController extends AbstractController
{
    #[Route('/compte', name: 'app_compte', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(GameSaveRepository $parties, MissionCatalogue $missions): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sesParties = $parties->findPourJoueur($user);

        return $this->render('compte/index.html.twig', [
            'user' => $user,
            'parties' => $sesParties,
            // Le libellé de la mission est résolu ici plutôt que dans le
            // gabarit : le catalogue est une donnée de référence, pas quelque
            // chose qu'une vue devrait aller interroger.
            'missionsParPartie' => $this->libellesDeMission($sesParties, $missions),
            'plafondAtteint' => $parties->plafondAtteintPour($user),
            'maxParties' => GameSave::MAX_PAR_COMPTE,
            // **Le plafond ne compte que les parties en cours** : une partie
            // close reste consultable et n'occupe aucune place. L'écran doit
            // donc compter comme lui, sinon « 5 sur 5 » s'afficherait à côté
            // d'un bouton « Commencer une partie » bien actif.
            'partiesEnCours' => $parties->compterEnCoursPourJoueur($user),
            // Date limite de vérification, affichée tant que l'adresse ne l'est
            // pas — le compte reste utilisable jusque-là (voir User).
            'dateLimiteVerification' => $user->isVerified()
                ? null
                : $user->getCreatedAt()->modify(\sprintf('+%d days', User::DELAI_VERIFICATION_JOURS)),
        ]);
    }

    /**
     * @param GameSave[] $parties
     *
     * @return array<int, string>
     */
    private function libellesDeMission(array $parties, MissionCatalogue $missions): array
    {
        $libelles = [];

        foreach ($parties as $partie) {
            $numero = $partie->getMission();
            $id = $partie->getId();

            if (null === $id || null === $numero) {
                continue;
            }

            $mission = $missions->get($numero);
            $libelles[$id] = \sprintf('Mission %d sur %d — %s', $numero, GameSave::DERNIERE_MISSION, $mission->region);
        }

        return $libelles;
    }
}
