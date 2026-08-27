<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Enum\GameMode;
use App\Form\NouvellePartieType;
use App\Game\CatalogueDeLaVille;
use App\Game\ChantierImpossible;
use App\Game\Chantiers;
use App\Game\DateDeJeu;
use App\Game\LanceurDePartie;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\PlafondDePartiesAtteint;
use App\Game\TypeDeBatiment;
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
     * La vue de la ville : ce qui est dressé, ce qui peut l'être.
     *
     * Une liste, jamais un placement libre sur une grille (doc 15) — la ville
     * se gère, elle ne se dessine pas.
     */
    #[Route('/{id}/ville', name: 'app_partie_ville', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function ville(GameSave $partie, CatalogueDeLaVille $catalogue): Response
    {
        $ville = $partie->getVille();

        return $this->render('partie/ville.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'chantiers' => $ville->getChantiers(),
            'batimentsDresses' => $this->batimentsTriesParLibelle($ville),
            'offres' => $catalogue->pour($ville),
        ]);
    }

    /**
     * La carte d'exploration : une grille isométrique, brouillard compris.
     *
     * La case détaillée est choisie côté serveur plutôt qu'en JavaScript — le
     * jeu se joue sans, et un lien reste partageable.
     */
    #[Route('/{id}/carte', name: 'app_partie_carte', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function carte(Request $request, GameSave $partie): Response
    {
        $ville = $partie->getVille();
        $zones = $this->zonesTrieesPourLIsometrie($ville);

        return $this->render('partie/carte.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'zones' => $zones,
            'zoneDetaillee' => $this->zoneDemandee($zones, $request->query->get('zone')),
        ]);
    }

    /**
     * Engage un chantier. Les ressources sont payées ici, les travaux
     * avanceront au fil des cycles.
     */
    #[Route('/{id}/ville/batir', name: 'app_partie_batir', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function batir(Request $request, GameSave $partie, Chantiers $chantiers): Response
    {
        if (!$this->isCsrfTokenValid('batir', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $type = TypeDeBatiment::tryFrom((string) $request->request->get('type'));

        if (null === $type) {
            throw $this->createNotFoundException('Bâtiment inconnu.');
        }

        try {
            $chantier = $chantiers->lancer($partie, $type);
            $this->addFlash('succes', \sprintf(
                'Chantier engagé : le %s sera prêt dans %d cycles.',
                $type->libelle(),
                $chantier->getDureeEnCycles(),
            ));
        } catch (ChantierImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Fait passer une quinzaine. Le seul geste qui fasse avancer le temps.
     */
    #[Route('/{id}/cycle', name: 'app_partie_cycle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function passerUnCycle(Request $request, GameSave $partie, Chantiers $chantiers): Response
    {
        if (!$this->isCsrfTokenValid('cycle', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $evenements = $chantiers->passerUnCycle($partie);

        $this->addFlash('succes', \sprintf(
            'Une quinzaine passe. Nous voici en %s.',
            DateDeJeu::pourCycle($partie->getCycle())->libelle(),
        ));

        foreach ($evenements as $evenement) {
            $this->addFlash('succes', $evenement);
        }

        return $this->redirectToRoute($this->routeDeRetour($request), ['id' => $partie->getId()]);
    }

    /**
     * Où renvoyer le joueur après une action déclenchée depuis la barre de jeu.
     *
     * La liste blanche n'est pas une précaution de style : sans elle, une valeur
     * soumise deviendrait un nom de route arbitraire.
     */
    private function routeDeRetour(Request $request): string
    {
        $demande = $request->request->get('retour');

        return \in_array($demande, ['app_partie_carte', 'app_partie_ville'], true)
            ? $demande
            : 'app_partie_carte';
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

            $designation = $partie->getVille()->avecPreposition();
            $entityManager->remove($partie);
            $entityManager->flush();

            $this->addFlash('succes', \sprintf('La partie %s est abandonnée.', $designation));

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
     * Zones ordonnées de l'arrière-plan vers le premier plan.
     *
     * En vue isométrique, une tuile en recouvre partiellement d'autres : elles
     * doivent être peintes par somme x+y croissante, sinon les roseaux d'une
     * case se retrouvent derrière la case qu'ils devraient masquer.
     *
     * @return list<Zone>
     */
    private function zonesTrieesPourLIsometrie(City $ville): array
    {
        $zones = array_values($ville->getZones()->toArray());

        usort($zones, static function (Zone $a, Zone $b): int {
            $profondeur = ($a->getX() + $a->getY()) <=> ($b->getX() + $b->getY());

            return 0 !== $profondeur ? $profondeur : $a->getX() <=> $b->getX();
        });

        return $zones;
    }

    /**
     * La zone désignée par « x-y » dans l'URL, si elle existe et a été
     * reconnue. Le brouillard ne se détaille pas.
     *
     * @param list<Zone> $zones
     */
    private function zoneDemandee(array $zones, mixed $coordonnees): ?Zone
    {
        if (!\is_string($coordonnees) || 1 !== preg_match('/^(\d+)-(\d+)$/', $coordonnees, $trouve)) {
            return null;
        }

        foreach ($zones as $zone) {
            if ($zone->getX() === (int) $trouve[1] && $zone->getY() === (int) $trouve[2]) {
                return $zone->estDecouverte() ? $zone : null;
            }
        }

        return null;
    }

    /**
     * Bâtiments dressés, dans un ordre stable et lisible plutôt que celui,
     * arbitraire, de leur insertion en base.
     *
     * @return list<Building>
     */
    private function batimentsTriesParLibelle(City $ville): array
    {
        $batiments = array_values($ville->getBatiments()->toArray());
        usort(
            $batiments,
            static fn (Building $a, Building $b): int => $a->getType()->libelle() <=> $b->getType()->libelle(),
        );

        return $batiments;
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
