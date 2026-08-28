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
use App\Game\AppelDHabitants;
use App\Game\AppelImpossible;
use App\Game\CatalogueDeLaVille;
use App\Game\ChantierImpossible;
use App\Game\Chantiers;
use App\Game\Culture;
use App\Game\DateDeJeu;
use App\Game\Effectifs;
use App\Game\ExploitationImpossible;
use App\Game\Exploitations;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\PassageDeCycle;
use App\Game\PlafondDePartiesAtteint;
use App\Game\RecrutementImpossible;
use App\Game\Recrutements;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\SpecialiteDeChef;
use App\Game\TypeDeBatiment;
use App\Game\VenteImpossible;
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
    public function ville(
        GameSave $partie,
        CatalogueDeLaVille $catalogue,
        Marche $marche,
        AppelDHabitants $appels,
        Recrutements $recrutements,
    ): Response {
        $ville = $partie->getVille();

        return $this->render('partie/ville.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'chantiers' => $ville->getChantiers(),
            'batimentsDresses' => $this->batimentsTriesParLibelle($ville),
            'offres' => $catalogue->pour($ville),
            'aUnMarche' => $ville->possede(TypeDeBatiment::Marche),
            'etal' => $ville->possede(TypeDeBatiment::Marche) ? $marche->etalPour($partie) : [],
            'palier' => $partie->getFamille()->palier(),
            'coutDUnAppel' => $appels->cout($partie),
            'directions' => $this->directionsDesBatiments($partie, $recrutements),
            'effectifs' => Effectifs::repartir($ville, $partie->getCycle()),
            'brasDisponibles' => Effectifs::brasDisponibles($ville, $partie->getCycle()),
        ]);
    }

    /**
     * Vend une ressource au Marché. La seule façon de gagner de l'or à ce stade
     * du développement.
     */
    #[Route('/{id}/ville/vendre', name: 'app_partie_vendre', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function vendre(Request $request, GameSave $partie, Marche $marche): Response
    {
        if (!$this->isCsrfTokenValid('vendre', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $ressource = Ressource::tryFrom((string) $request->request->get('ressource'));

        if (null === $ressource) {
            throw $this->createNotFoundException('Ressource inconnue.');
        }

        try {
            $recette = $marche->vendre($partie, $ressource, $request->request->getInt('quantite'));
            $this->addFlash('succes', \sprintf(
                '%d %s vendu%s : %d deben entrent en caisse.',
                $request->request->getInt('quantite'),
                $ressource->libelle(),
                $request->request->getInt('quantite') > 1 ? 's' : '',
                $recette,
            ));
        } catch (VenteImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Poste une offre pour diriger un bâtiment.
     *
     * Action libre (doc 05) : elle ne consomme pas de quinzaine et ne coûte
     * rien. Elle fige en revanche son tirage de candidats, pour qu'un
     * rechargement de page ne relance pas les dés.
     */
    #[Route('/{id}/ville/poster', name: 'app_partie_poster', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function poster(Request $request, GameSave $partie, Recrutements $recrutements): Response
    {
        if (!$this->isCsrfTokenValid('poster', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $type = TypeDeBatiment::tryFrom((string) $request->request->get('batiment'));

        if (null === $type) {
            throw $this->createNotFoundException('Bâtiment inconnu.');
        }

        try {
            $recrutements->poster($partie, $type);
        } catch (RecrutementImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Retient un candidat, ou retire l'annonce sans embaucher personne.
     */
    #[Route('/{id}/ville/embaucher', name: 'app_partie_embaucher', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function embaucher(Request $request, GameSave $partie, Recrutements $recrutements): Response
    {
        if (!$this->isCsrfTokenValid('embaucher', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $type = TypeDeBatiment::tryFrom((string) $request->request->get('batiment'));
        $offre = null === $type ? null : $partie->getVille()->offrePour($type);

        if (null === $offre) {
            throw $this->createNotFoundException('Aucune annonce affichée pour ce bâtiment.');
        }

        if ($request->request->has('retirer')) {
            $recrutements->retirer($offre);
            $this->addFlash('succes', 'L\'annonce est retirée. Les candidats sont repartis.');

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        try {
            $employe = $recrutements->embaucher($partie, $offre, $request->request->getInt('rang', -1));
            $this->addFlash('succes', \sprintf(
                'Votre nouveau chef %s s\'installe avec les siens. Il prendra son poste à la prochaine quinzaine.',
                null !== $employe->getSpecialite() ? '('.mb_strtolower($employe->getSpecialite()->libelle()).')' : '',
            ));
        } catch (RecrutementImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Renvoie un chef. Sa maisonnée s'en va avec lui.
     */
    #[Route('/{id}/ville/renvoyer', name: 'app_partie_renvoyer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function renvoyer(Request $request, GameSave $partie, Recrutements $recrutements): Response
    {
        if (!$this->isCsrfTokenValid('renvoyer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $employe = null;

        foreach ($partie->getVille()->getEmployes() as $candidat) {
            if ($candidat->getId() === $request->request->getInt('employe')) {
                $employe = $candidat;
            }
        }

        if (null === $employe) {
            throw $this->createNotFoundException('Ce chef n\'est pas à votre service.');
        }

        $recrutements->renvoyer($employe);
        $this->addFlash('succes', 'Le chef et les siens ont quitté la ville.');

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Fait venir une maisonnée dans la ville.
     *
     * Le prix suit la renommée de la famille (doc 13) et le logement borne
     * l'action : c'est ce qui fait du Quartier d'habitation autre chose qu'un
     * bâtiment décoratif.
     */
    #[Route('/{id}/ville/appeler', name: 'app_partie_appeler', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function appeler(Request $request, GameSave $partie, AppelDHabitants $appels): Response
    {
        if (!$this->isCsrfTokenValid('appeler', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $maisonnee = $appels->appeler($partie);
            $this->addFlash('succes', \sprintf(
                'Une maisonnée s\'installe : %d bras et %d bouches de plus.',
                $maisonnee['actifs'],
                $maisonnee['inactifs'],
            ));
        } catch (AppelImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * La carte d'exploration : une grille isométrique, brouillard compris.
     *
     * La case détaillée est choisie côté serveur plutôt qu'en JavaScript — le
     * jeu se joue sans, et un lien reste partageable.
     */
    #[Route('/{id}/carte', name: 'app_partie_carte', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function carte(Request $request, GameSave $partie, Explorations $explorations): Response
    {
        $ville = $partie->getVille();
        $zones = $this->zonesTrieesPourLIsometrie($ville);
        $detaillee = $this->zoneDemandee($zones, $request->query->get('zone'));

        return $this->render('partie/carte.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'zones' => $zones,
            'zoneDetaillee' => $detaillee,
            'expeditionEnCours' => null !== $detaillee ? $ville->aUneExpeditionVers($detaillee) : false,
            // Le prix dépend de la case : reconnaître ses propres abords ne
            // coûte pas d'or. L'écran doit donc annoncer celui de cette case-là.
            'coutDeReconnaissance' => null !== $detaillee
                ? $explorations->coutVers($partie, $detaillee, RoleDExploration::Eclaireur)
                : null,
            // Même logique que le coût en or : nul à moins de trois cases.
            'provisionsDeReconnaissance' => null !== $detaillee
                ? $explorations->provisionsVers($partie, $detaillee, RoleDExploration::Eclaireur)
                : RoleDExploration::Eclaireur->provisions(),
            'dureeDeReconnaissance' => null !== $detaillee && !$detaillee->estDecouverte()
                ? $explorations->dureeVers($partie, $detaillee)
                : null,
            'cultures' => Culture::cases(),
            'aUnGrenier' => $ville->possede(TypeDeBatiment::Grenier),
            // Sans Port, aucune barque n'appareille : la case poissonneuse
            // s'affiche, mais le bouton laisse la place au motif.
            'aUnPort' => $ville->possede(TypeDeBatiment::Port),
        ]);
    }

    /**
     * Ouvre une carrière sur une case reconnue qui porte un gisement.
     */
    #[Route('/{id}/carte/exploiter', name: 'app_partie_exploiter', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function exploiter(Request $request, GameSave $partie, Exploitations $exploitations): Response
    {
        $zone = $this->zonePostee($request, $partie, 'exploiter');
        $ressource = Ressource::tryFrom((string) $request->request->get('ressource'));

        if (null === $ressource) {
            throw $this->createNotFoundException('Ressource inconnue.');
        }

        try {
            $exploitations->exploiter($partie, $zone, $ressource);
            $this->addFlash('succes', \sprintf(
                'L\'extraction commence. Le gisement de %s alimentera vos réserves à chaque quinzaine.',
                $ressource->libelle(),
            ));
        } catch (ExploitationImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->retourALaCarte($partie, $zone);
    }

    /**
     * Établit un champ sur une case cultivable et y sème.
     */
    #[Route('/{id}/carte/semer', name: 'app_partie_semer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function semer(Request $request, GameSave $partie, Exploitations $exploitations): Response
    {
        $zone = $this->zonePostee($request, $partie, 'semer');
        $culture = Culture::tryFrom((string) $request->request->get('culture'));

        if (null === $culture) {
            throw $this->createNotFoundException('Culture inconnue.');
        }

        try {
            $exploitations->semer($partie, $zone, $culture);
            $this->addFlash('succes', $partie->getVille()->possede(TypeDeBatiment::Grenier)
                ? \sprintf('Le champ est établi. On y sème %s.', $culture->libelle())
                : \sprintf(
                    'Le champ est établi et semé de %s. Sans Grenier, rien de ce qu\'il donnera ne se conservera.',
                    $culture->libelle(),
                ));
        } catch (ExploitationImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->retourALaCarte($partie, $zone);
    }

    /**
     * La case visée par un formulaire de la carte, jeton vérifié.
     */
    private function zonePostee(Request $request, GameSave $partie, string $jeton): Zone
    {
        if (!$this->isCsrfTokenValid($jeton, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $zones = $this->zonesTrieesPourLIsometrie($partie->getVille());

        return $this->zoneDemandee($zones, $request->request->get('zone'))
            ?? throw $this->createNotFoundException('Case inconnue.');
    }

    private function retourALaCarte(GameSave $partie, Zone $zone): Response
    {
        return $this->redirectToRoute('app_partie_carte', [
            'id' => $partie->getId(),
            'zone' => $zone->getX().'-'.$zone->getY(),
        ]);
    }

    /**
     * Envoie un éclaireur reconnaître une case.
     */
    #[Route('/{id}/carte/explorer', name: 'app_partie_explorer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function explorer(Request $request, GameSave $partie, Explorations $explorations): Response
    {
        if (!$this->isCsrfTokenValid('explorer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $zones = $this->zonesTrieesPourLIsometrie($partie->getVille());
        $destination = $this->zoneDemandee($zones, $request->request->get('zone'));

        if (null === $destination) {
            throw $this->createNotFoundException('Case inconnue.');
        }

        try {
            $expedition = $explorations->envoyer($partie, $destination, RoleDExploration::Eclaireur);
            $this->addFlash('succes', \sprintf(
                'Un éclaireur part en reconnaissance. Il sera sur place dans %d cycle%s.',
                $expedition->getDureeEnCycles(),
                $expedition->getDureeEnCycles() > 1 ? 's' : '',
            ));
        } catch (ExplorationImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_carte', [
            'id' => $partie->getId(),
            'zone' => $destination->getX().'-'.$destination->getY(),
        ]);
    }

    /**
     * Engage un chantier. Les ressources sont payées ici, les travaux
     * avanceront au fil des cycles.
     */
    #[Route('/{id}/ville/batir', name: 'app_partie_batir', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
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
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function passerUnCycle(Request $request, GameSave $partie, PassageDeCycle $cycle): Response
    {
        if (!$this->isCsrfTokenValid('cycle', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $evenements = $cycle->passer($partie);

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
                return $zone;
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
     * L'état du recrutement, bâtiment par bâtiment : qui le dirige, combien
     * de postes restent, quelle annonce est affichée.
     *
     * Les trois bâtiments sans spécialité en sont écartés — Résidence
     * familiale, Quartier d'habitation, Auberge : la famille les tient
     * elle-même, leur proposer une annonce n'aurait aucun sens.
     *
     * @return list<array{batiment: Building, chefs: list<\App\Entity\Employee>, postesLibres: int, offre: ?\App\Entity\JobOffer}>
     */
    private function directionsDesBatiments(GameSave $partie, Recrutements $recrutements): array
    {
        $ville = $partie->getVille();
        $directions = [];

        foreach ($this->batimentsTriesParLibelle($ville) as $batiment) {
            if ([] === SpecialiteDeChef::pour($batiment->getType())) {
                continue;
            }

            $directions[] = [
                'batiment' => $batiment,
                'chefs' => $ville->chefsDe($batiment->getType()),
                'postesLibres' => $recrutements->postesLibres($batiment),
                'offre' => $ville->offrePour($batiment->getType()),
            ];
        }

        return $directions;
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
