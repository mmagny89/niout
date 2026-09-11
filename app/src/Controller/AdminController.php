<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSave;
use App\Entity\User;
use App\Enum\StatutDePartie;
use App\Game\MissionCatalogue;
use App\Repository\GameSaveRepository;
use App\Repository\UserRepository;
use App\Security\SuppressionDeCompte;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * L'administration des comptes : qui s'est inscrit, combien de parties chacun
 * mène, et où elles en sont.
 *
 * Le rôle qui l'ouvre ne s'accorde qu'en console (`app:users:admin`), et le
 * pare-feu garde le préfixe `/admin` en plus des attributs de chaque action :
 * une action ajoutée ici sans `IsGranted` reste protégée.
 */
#[Route('/admin')]
#[IsGranted(User::ROLE_ADMIN)]
final class AdminController extends AbstractController
{
    #[Route('/comptes', name: 'app_admin_comptes', methods: ['GET'])]
    public function comptes(
        UserRepository $comptes,
        GameSaveRepository $parties,
        MissionCatalogue $missions,
    ): Response {
        $tous = $comptes->findPourAdministration();
        $partiesParJoueur = $parties->findGroupeesParJoueur();

        return $this->render('admin/comptes.html.twig', [
            'lignes' => array_map(
                fn (User $compte): array => $this->ligne(
                    $compte,
                    $partiesParJoueur[$compte->getId()] ?? [],
                    $missions,
                ),
                $tous,
            ),
            'maxParties' => GameSave::MAX_PAR_COMPTE,
            // Le délai de grâce est affiché à côté des comptes non vérifiés :
            // cet écran sert aussi à savoir lesquels la purge emportera.
            'delaiDeVerification' => User::DELAI_VERIFICATION_JOURS,
        ]);
    }

    /**
     * La suppression demande une confirmation sur un écran à elle, où figure
     * ce qui va disparaître.
     *
     * **Rien ne se supprime en GET** : un lien suffirait sinon à détruire un
     * compte depuis n'importe quelle page qui le pointerait, et un aspirateur
     * de liens ferait le tour de la table.
     */
    #[Route('/comptes/{id}/supprimer', name: 'app_admin_compte_supprimer', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function supprimer(
        Request $request,
        User $compte,
        GameSaveRepository $parties,
        MissionCatalogue $missions,
        SuppressionDeCompte $suppression,
    ): Response {
        // Se supprimer soi-même déconnecterait la session en cours sur un
        // utilisateur qui n'existe plus, et retirerait au passage le seul
        // accès à cet écran s'il n'y a qu'une administratrice. Le compte se
        // supprime depuis la console, pas d'ici.
        if ($compte === $this->getUser()) {
            throw $this->createAccessDeniedException('Un compte ne se supprime pas lui-même depuis cet écran.');
        }

        $sesParties = $parties->findPourJoueur($compte);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('supprimer-compte', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de confirmation invalide.');
            }

            // L'adresse est relevée avant la suppression : après le flush,
            // l'entité détachée ne sert plus à rédiger le message.
            $adresse = $compte->getEmail();
            $nombreDeParties = \count($sesParties);

            $suppression->supprimer($compte);

            $this->addFlash('succes', \sprintf(
                'Le compte %s et ses %d partie%s sont supprimés.',
                $adresse,
                $nombreDeParties,
                $nombreDeParties > 1 ? 's' : '',
            ));

            return $this->redirectToRoute('app_admin_comptes');
        }

        return $this->render('admin/supprimer.html.twig', [
            'ligne' => $this->ligne($compte, $sesParties, $missions),
        ]);
    }

    /**
     * Le récapitulatif d'un compte, monté ici et non dans le gabarit : le
     * libellé d'une mission vient du catalogue, une donnée de référence qu'une
     * vue n'a pas à aller interroger.
     *
     * @param GameSave[] $sesParties
     *
     * @return array{compte: User, parties: list<array{partie: GameSave, mission: string}>, enCours: int, closes: int}
     */
    private function ligne(User $compte, array $sesParties, MissionCatalogue $missions): array
    {
        $enCours = 0;
        $decrites = [];

        foreach ($sesParties as $partie) {
            if (StatutDePartie::EnCours === $partie->getStatut()) {
                ++$enCours;
            }

            $decrites[] = ['partie' => $partie, 'mission' => $this->ouEnEst($partie, $missions)];
        }

        return [
            'compte' => $compte,
            'parties' => $decrites,
            'enCours' => $enCours,
            'closes' => \count($sesParties) - $enCours,
        ];
    }

    /**
     * Où en est une partie, en une ligne.
     *
     * Le mode Aventure ne suit pas de missions mais une succession de règnes
     * (doc 14) : il n'a pas de numéro à afficher, et en inventer un mentirait
     * sur ce qui s'y joue.
     */
    private function ouEnEst(GameSave $partie, MissionCatalogue $missions): string
    {
        $numero = $partie->getMission();

        if (null === $numero) {
            return 'Succession des règnes';
        }

        return \sprintf(
            'Mission %d sur %d — %s',
            $numero,
            GameSave::DERNIERE_MISSION,
            $missions->get($numero)->region,
        );
    }
}
