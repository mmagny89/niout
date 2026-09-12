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

/**
 * La liste des parties, sur un écran à elle.
 *
 * Elle vivait dans « Mon compte », où elle occupait les trois quarts de la
 * page : c'est pourtant là qu'on arrive pour jouer, pas pour relire son
 * adresse email. Deux gabarits le disaient déjà sans qu'on l'entende, en
 * intitulant « Retour à mes parties » un lien qui menait au compte.
 *
 * Le préfixe est `/parties` et non `/partie` : ce dernier appartient à
 * PartieController, qui sert **une** partie donnée.
 */
final class PartiesController extends AbstractController
{
    #[Route('/parties', name: 'app_parties', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function index(GameSaveRepository $parties, MissionCatalogue $missions): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $sesParties = $parties->findPourJoueur($user);

        return $this->render('parties/index.html.twig', [
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
