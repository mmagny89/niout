<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\GameMode;
use App\Form\NouvellePartieType;
use App\Game\LanceurDePartie;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\PlafondDePartiesAtteint;
use App\Repository\GameSaveRepository;
use App\Security\Voter\PartieVoter;
use Doctrine\ORM\EntityManagerInterface;
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
     * Reprise d'une partie : un récapitulatif de l'état où elle a été laissée.
     *
     * Ce n'est volontairement pas un journal d'événements. Le jeu n'a aucun
     * temps réel : rien ne se produit pendant l'absence du joueur, un « depuis
     * votre dernière visite » serait toujours vide. Ce qu'il faut lui rendre,
     * c'est le contexte : où en est le cycle, ce qu'il reste en stock.
     */
    #[Route('/{id}', name: 'app_partie_reprendre', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function reprendre(GameSave $partie, MissionCatalogue $missions, EntityManagerInterface $entityManager): Response
    {
        $partie->marquerOuverte();
        $entityManager->flush();

        return $this->render('partie/reprendre.html.twig', [
            'partie' => $partie,
            'mission' => $this->missionDe($partie, $missions),
        ]);
    }

    /**
     * Abandon d'une partie : suppression définitive, jamais un archivage.
     */
    #[Route('/{id}/abandonner', name: 'app_partie_abandonner', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(PartieVoter::SUPPRIMER, subject: 'partie')]
    public function abandonner(
        Request $request,
        GameSave $partie,
        MissionCatalogue $missions,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('abandonner-partie', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de confirmation invalide.');
            }

            $nomDeVille = $partie->getVille()->getNom();
            $entityManager->remove($partie);
            $entityManager->flush();

            $this->addFlash('succes', \sprintf('La partie de %s est abandonnée.', $nomDeVille));

            return $this->redirectToRoute('app_compte');
        }

        return $this->render('partie/abandonner.html.twig', [
            'partie' => $partie,
            'mission' => $this->missionDe($partie, $missions),
        ]);
    }

    /**
     * La commande du pharaon : mise en scène du lancement, affichée une fois la
     * partie créée (doc 09). Texte simple, pas de cinématique.
     */
    #[Route('/{id}/commande', name: 'app_partie_commande', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function commande(GameSave $partie, MissionCatalogue $missions): Response
    {
        return $this->render('partie/commande.html.twig', [
            'partie' => $partie,
            'mission' => $this->missionDe($partie, $missions),
        ]);
    }

    /**
     * La mission en cours, ou null en mode Aventure — qui suit des règnes.
     */
    private function missionDe(GameSave $partie, MissionCatalogue $missions): ?Mission
    {
        if (!$partie->estCampagne() || null === $partie->getMission()) {
            return null;
        }

        return $missions->get($partie->getMission());
    }
}
