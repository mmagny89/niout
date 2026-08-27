<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\GameMode;
use App\Form\NouvellePartieType;
use App\Game\LanceurDePartie;
use App\Game\MissionCatalogue;
use App\Game\PlafondDePartiesAtteint;
use App\Repository\GameSaveRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/partie')]
#[IsGranted('ROLE_USER')]
final class PartieController extends AbstractController
{
    #[Route('/nouvelle', name: 'app_partie_nouvelle')]
    public function nouvelle(
        Request $request,
        LanceurDePartie $lanceur,
        GameSaveRepository $parties,
        MissionCatalogue $missions,
    ): Response {
        /** @var User $joueur */
        $joueur = $this->getUser();

        if ($parties->plafondAtteintPour($joueur)) {
            $this->addFlash('erreur', (new PlafondDePartiesAtteint())->getMessage());

            return $this->redirectToRoute('app_compte');
        }

        $form = $this->createForm(NouvellePartieType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{mode: GameMode, nomDeFamille: string, difficulte: int, tailleGrille: int} $donnees */
            $donnees = $form->getData();

            $partie = GameMode::Campagne === $donnees['mode']
                ? $lanceur->lancerCampagne($joueur, $donnees['nomDeFamille'])
                : $lanceur->lancerAventure(
                    $joueur,
                    $donnees['nomDeFamille'],
                    $donnees['difficulte'],
                    $donnees['tailleGrille'],
                );

            return $this->redirectToRoute('app_partie_commande', ['id' => $partie->getId()]);
        }

        return $this->render('partie/nouvelle.html.twig', [
            'form' => $form,
            'premiereMission' => $missions->get(1),
            'villeAventure' => LanceurDePartie::VILLE_DU_MODE_AVENTURE,
        ]);
    }

    /**
     * La commande du pharaon : mise en scène du lancement, affichée une fois la
     * partie créée (doc 09). Texte simple, pas de cinématique.
     */
    #[Route('/{id}/commande', name: 'app_partie_commande', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted('PARTIE_VOIR', subject: 'partie')]
    public function commande(GameSave $partie, MissionCatalogue $missions): Response
    {
        return $this->render('partie/commande.html.twig', [
            'partie' => $partie,
            'mission' => $partie->estCampagne() && null !== $partie->getMission()
                ? $missions->get($partie->getMission())
                : null,
        ]);
    }
}
