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
use App\Game\CleDeLecture;
use App\Game\Commerce;
use App\Game\CommerceImpossible;
use App\Game\Culture;
use App\Game\DateDeJeu;
use App\Game\Dechiffrage;
use App\Game\DechiffrageImpossible;
use App\Game\Divinite;
use App\Game\Effectifs;
use App\Game\Enigme;
use App\Game\EnigmeImpossible;
use App\Game\Enigmes;
use App\Game\Enquete;
use App\Game\EnqueteImpossible;
use App\Game\Enquetes;
use App\Game\ExploitationImpossible;
use App\Game\Exploitations;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\Fabrication;
use App\Game\FabricationImpossible;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\Marche;
use App\Game\Mecontentement;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\ModeDivin;
use App\Game\Negligence;
use App\Game\OffrandeImpossible;
use App\Game\Offrandes;
use App\Game\PassageDeCycle;
use App\Game\PlafondDePartiesAtteint;
use App\Game\Recette;
use App\Game\RecrutementImpossible;
use App\Game\Recrutements;
use App\Game\Ressource;
use App\Game\RoleDExploration;
use App\Game\Salaires;
use App\Game\SensDEchange;
use App\Game\SpecialiteDeChef;
use App\Game\SymboleHieroglyphique;
use App\Game\Temple;
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

        // Les dix régions ne s'ouvrent qu'au mode divin : pour un joueur
        // ordinaire, le champ n'existe pas et un POST forgé ne le rétablit pas.
        $ouvertes = [];
        if ($joueur->estDivinite()) {
            foreach ($missions->toutes() as $mission) {
                $ouvertes[\sprintf('%d — %s (%s)', $mission->numero, $mission->ville, $mission->region)] = $mission->numero;
            }
        }

        $form = $this->createForm(NouvellePartieType::class, options: ['missionsOuvertes' => $ouvertes]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{mode: GameMode, nomDeFamille: string, difficulte: int, tailleGrille: int, mission?: int} $donnees */
            $donnees = $form->getData();

            $partie = GameMode::Campagne === $donnees['mode']
                ? $lanceur->lancerCampagne($joueur, $donnees['nomDeFamille'], $donnees['mission'] ?? null)
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
        Salaires $salaires,
        Mecontentement $mecontentement,
        Fabrication $fabrication,
        Commerce $commerce,
        Dechiffrage $dechiffrage,
        Enigmes $enigmes,
        Enquetes $enquetes,
    ): Response {
        $ville = $partie->getVille();
        $inscription = $ville->possede(TypeDeBatiment::MaisonDesScribes)
            ? $dechiffrage->proposition($partie)
            : null;

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
            'ateliers' => $this->ateliersDeLaVille($partie, $fabrication),
            'routes' => $commerce->offrePour($partie),
            'etals' => $this->etalsDesRoutesOuvertes($partie, $commerce),
            'effectifs' => Effectifs::repartir($ville, $partie->getCycle()),
            'brasDisponibles' => Effectifs::brasDisponibles($ville, $partie->getCycle()),
            // Les deux indicateurs de santé de la ville, côte à côte : les
            // bouches et les bras.
            'masseSalariale' => $salaires->masseSalariale($ville, $partie->getCycle()),
            'mecontentement' => $partie->getQuinzainesDeMecontentement(),
            'villeMecontente' => $mecontentement->pese($partie),
            'rendementDeLHumeur' => $mecontentement->rendementEnCentiemes($partie),
            // La clé de lecture : elle ne s'affiche qu'une fois la Maison des
            // scribes dressée — proposer l'écran d'un bâtiment qu'on n'a pas
            // ferait une porte sur du vide.
            'aUneMaisonDesScribes' => $ville->possede(TypeDeBatiment::MaisonDesScribes),
            'cleDeLecture' => CleDeLecture::pour($ville),
            'prochainSigne' => CleDeLecture::prochainSigne($ville),
            'signesEnTout' => \count(SymboleHieroglyphique::cases()),
            'inscription' => $inscription,
            // Les jetons sont mélangés **au rendu** : les laisser dans l'ordre
            // gravé donnerait la réponse par la seule lecture du HTML.
            'melange' => $inscription instanceof Inscription ? $this->melanger($inscription) : [],
            'inscriptionsLues' => \count($ville->inscriptionsDechiffrees()),
            'dossiers' => array_map(
                static fn ($dossier): array => [
                    'dossier' => $dossier,
                    'peutConclure' => $dossier->peutConclure($partie->getCycle()),
                    // Mélangées au rendu : la bonne conclusion est la première
                    // du catalogue, et se lirait sinon dans la source.
                    'conclusions' => self::melangerLesConclusions($dossier->getEnquete()),
                ],
                $enquetes->dossiers($partie),
            ),
            // Les propositions sont mélangées au rendu, comme les jetons du
            // déchiffrage : la bonne réponse est toujours la première dans le
            // catalogue, et se lirait sinon dans la source de la page.
            'enigmes' => array_map(
                static fn (Enigme $enigme): array => [
                    'enigme' => $enigme,
                    'propositions' => self::melangerLesPropositions($enigme),
                ],
                $ville->possede(TypeDeBatiment::MaisonDesScribes) ? $enigmes->disponibles($partie) : [],
            ),
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
     * Lance un ordre de fabrication à l'Atelier.
     *
     * Les matières sont débitées ici, à l'engagement — on ne réserve pas, on
     * paie. Les pièces n'arriveront qu'à l'achèvement, au fil des quinzaines.
     */
    #[Route('/{id}/ville/fabriquer', name: 'app_partie_fabriquer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function fabriquer(Request $request, GameSave $partie, Fabrication $fabrication): Response
    {
        if (!$this->isCsrfTokenValid('fabriquer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $recette = Recette::tryFrom((string) $request->request->get('recette'));

        if (null === $recette) {
            throw $this->createNotFoundException('Recette inconnue.');
        }

        try {
            $ordre = $fabrication->lancer($partie, $recette, $request->request->getInt('lots', 1));
            $this->addFlash('succes', \sprintf(
                'L\'Atelier s\'attelle à %s : %d pièces dans %d quinzaine%s.',
                mb_strtolower($recette->libelle()),
                $ordre->piecesAttendues(),
                $ordre->cyclesRestants(),
                $ordre->cyclesRestants() > 1 ? 's' : '',
            ));
        } catch (FabricationImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Ouvre une route commerciale en y envoyant une première caravane.
     *
     * Le coût est débité ici ; la route ne s'ouvrira qu'à l'arrivée du convoi,
     * au fil des quinzaines.
     */
    #[Route('/{id}/ville/commercer', name: 'app_partie_commercer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function commercer(Request $request, GameSave $partie, Commerce $commerce): Response
    {
        if (!$this->isCsrfTokenValid('commercer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $route = $commerce->ouvrir($partie, (string) $request->request->get('partenaire'));
            $this->addFlash('succes', \sprintf(
                'Votre %s prend la route : %d quinzaine%s avant qu\'elle ne soit ouverte.',
                $route->getRoute()->convoi(),
                $route->getQuinzainesAvantOuverture(),
                $route->getQuinzainesAvantOuverture() > 1 ? 's' : '',
            ));
        } catch (CommerceImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Pose ou retire un ordre permanent sur une route ouverte.
     *
     * Rien n'est débité : un ordre est une annonce, pas une transaction. Ce
     * sont les convois qui l'exécuteront.
     */
    #[Route('/{id}/ville/etal', name: 'app_partie_etal', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function etal(Request $request, GameSave $partie, Commerce $commerce): Response
    {
        if (!$this->isCsrfTokenValid('etal', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $cle = (string) $request->request->get('partenaire');
        $ressource = Ressource::tryFrom((string) $request->request->get('ressource'));

        if (null === $ressource) {
            throw $this->createNotFoundException('Ressource inconnue.');
        }

        $route = $partie->getVille()->routeVers($cle);
        $existant = $route?->ordrePour($ressource);

        if ($request->request->has('retirer')) {
            if (null !== $existant) {
                $commerce->retirerUnOrdre($existant);
                $this->addFlash('succes', \sprintf('Votre étal ne propose plus de %s.', $ressource->libelle()));
            }

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        $sens = SensDEchange::tryFrom((string) $request->request->get('sens'));

        if (null === $sens) {
            throw $this->createNotFoundException('Sens inconnu.');
        }

        try {
            $commerce->poserUnOrdre(
                $partie,
                $cle,
                $ressource,
                $sens,
                $request->request->getInt('prix'),
                $request->request->getInt('quantite', 1),
            );
            $this->addFlash('succes', \sprintf(
                '%s annoncé à %d deben l\'unité.',
                $ressource->libelle(),
                $request->request->getInt('prix'),
            ));
        } catch (CommerceImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Bascule une partie en mode divin, ou l'en fait sortir.
     *
     * **Deux écarts délibérés, tous deux nécessaires au propos du mode.**
     *
     * Le premier : la route passe par `PartieVoter::VOIR` et non par `JOUER`,
     * alors qu'elle modifie l'état. `JOUER` refuse une partie échouée — or
     * c'est justement celle qu'on veut souvent pouvoir remettre debout pour
     * l'examiner. La propriété de la partie reste vérifiée, elle.
     *
     * Le second : `ROLE_DIVIN` en plus, accordé en console seulement. C'est
     * la vraie barrière ; l'absence de bouton n'en serait pas une.
     */
    #[Route('/{id}/divin', name: 'app_partie_divin', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(User::ROLE_DIVIN)]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function modeDivin(Request $request, GameSave $partie, ModeDivin $modeDivin): Response
    {
        if (!$this->isCsrfTokenValid('divin', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if ($partie->estEnModeDivin() && $request->request->has('combler')) {
            $modeDivin->combler($partie);
            $this->addFlash('succes', 'Les réserves sont de nouveau pleines.');

            return $this->redirectToRoute($this->routeDeRetour($request), ['id' => $partie->getId()]);
        }

        if ($partie->estEnModeDivin() && $request->request->has('brouillard')) {
            $levees = $modeDivin->leverLeBrouillard($partie);
            $this->addFlash('succes', 0 === $levees
                ? 'La carte était déjà entièrement reconnue.'
                : \sprintf('Le brouillard se lève sur %d case%s.', $levees, $levees > 1 ? 's' : ''));

            return $this->redirectToRoute($this->routeDeRetour($request), ['id' => $partie->getId()]);
        }

        $this->addFlash('succes', $modeDivin->basculer($partie)
            ? 'Cette partie devient une partie d\'essai : un million de chaque ressource, aucun plafond.'
            : 'Cette partie retrouve les règles ordinaires. Ce qui a été donné reste.');

        return $this->redirectToRoute($this->routeDeRetour($request), ['id' => $partie->getId()]);
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
     * Soumet une lecture d'inscription.
     *
     * **Se tromper ne coûte rien** (décision de la joueuse) : ni ressource, ni
     * cycle. Le coût d'une énigme est le temps qu'on y passe — une énigme qui
     * punit est une énigme qu'on cesse de tenter.
     */
    #[Route('/{id}/scribes/dechiffrer', name: 'app_partie_dechiffrer', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function dechiffrer(Request $request, GameSave $partie, Dechiffrage $dechiffrage): Response
    {
        if (!$this->isCsrfTokenValid('dechiffrer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $inscription = Inscription::tryFrom((string) $request->request->get('inscription'));

        if (null === $inscription) {
            throw $this->createNotFoundException('Inscription inconnue.');
        }

        $ordre = array_values(array_filter(explode(',', (string) $request->request->get('ordre'))));

        try {
            $lecture = $dechiffrage->verifier($partie, $inscription, $ordre);
        } catch (DechiffrageImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        if (!$lecture['juste']) {
            $this->addFlash('erreur', 'Ce n\'est pas ce que disent ces signes. Reprenez la clé et recommencez.');

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        $this->addFlash('succes', \sprintf(
            '« %s » %s',
            $inscription->lecture(),
            null === $lecture['apprend']
                ? 'Vos scribes n\'apprennent rien de neuf : ils lisent déjà tout.'
                : \sprintf('Vos scribes apprennent un signe de plus : %s.', $lecture['apprend']->libelle()),
        ));

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Répond à une énigme courte.
     *
     * **Une seule tentative** : c'est ce qui en fait une question. Juste ou
     * faux, l'explication tombe — le vrai gain d'une énigme est ce qu'elle
     * apprend, pas ce qu'elle rapporte.
     */
    #[Route('/{id}/scribes/enigme', name: 'app_partie_enigme', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function repondreALEnigme(Request $request, GameSave $partie, Enigmes $enigmes): Response
    {
        if (!$this->isCsrfTokenValid('enigme', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $enigme = Enigme::tryFrom((string) $request->request->get('enigme'));

        if (null === $enigme) {
            throw $this->createNotFoundException('Énigme inconnue.');
        }

        try {
            $verdict = $enigmes->repondre($partie, $enigme, (string) $request->request->get('reponse'));
        } catch (EnigmeImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        $this->addFlash(
            $verdict['juste'] ? 'succes' : 'erreur',
            $verdict['juste']
                ? \sprintf('%s Vous recevez %d deben.', $verdict['explication'], $verdict['recompense'])
                : \sprintf('Ce n\'était pas la réponse. %s', $verdict['explication']),
        );

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Fouille une case où quelque chose se trame, et verse au dossier
     * l'indice qu'on y trouve.
     */
    #[Route('/{id}/carte/fouiller', name: 'app_partie_fouiller', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function fouiller(Request $request, GameSave $partie, Enquetes $enquetes): Response
    {
        if (!$this->isCsrfTokenValid('fouiller', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $zones = $this->zonesTrieesPourLIsometrie($partie->getVille());
        $zone = $this->zoneDemandee($zones, $request->request->get('zone'));

        if (null === $zone) {
            throw $this->createNotFoundException('Case inconnue.');
        }

        try {
            $indice = $enquetes->fouiller($partie, $zone);
            $this->addFlash('succes', \sprintf(
                '%s Versé au dossier : « %s ».',
                $indice->texte(),
                $indice->enquete()->libelle(),
            ));
        } catch (EnqueteImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_carte', [
            'id' => $partie->getId(),
            'zone' => $zone->getX().'-'.$zone->getY(),
        ]);
    }

    /**
     * Conclut une enquête.
     *
     * **Se tromper ne se paie pas de la même façon selon l'enquête** : une
     * principale se rejoue après deux cycles, une secondaire se perd. Aucune
     * ne retire de ressource.
     */
    #[Route('/{id}/scribes/conclure', name: 'app_partie_conclure', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function conclure(Request $request, GameSave $partie, Enquetes $enquetes): Response
    {
        if (!$this->isCsrfTokenValid('conclure', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $enquete = Enquete::tryFrom((string) $request->request->get('enquete'));

        if (null === $enquete) {
            throw $this->createNotFoundException('Enquête inconnue.');
        }

        try {
            $verdict = $enquetes->conclure($partie, $enquete, (string) $request->request->get('conclusion'));
        } catch (EnqueteImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());

            return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
        }

        $this->addFlash(
            $verdict['juste'] ? 'succes' : 'erreur',
            match (true) {
                $verdict['juste'] => \sprintf(
                    'Affaire close. %s Vous recevez %d deben, et l\'on parle de vous.',
                    $verdict['denouement'],
                    $verdict['recompense'],
                ),
                $verdict['definitif'] => \sprintf(
                    'Vous vous êtes trompé, et l\'affaire s\'enterre. %s',
                    $verdict['denouement'],
                ),
                default => \sprintf(
                    'Ce n\'est pas cela. Vos scribes reprennent le dossier : %d quinzaines de perdues.',
                    Enquetes::RETARD_DUNE_ERREUR,
                ),
            },
        );

        return $this->redirectToRoute('app_partie_ville', ['id' => $partie->getId()]);
    }

    /**
     * Le Temple : à qui l'on donne, et ce que les dieux en pensent.
     *
     * Écran à part plutôt qu'une section de la ville : huit divinités, leurs
     * paliers et leurs effets ne tiennent pas dans la marge d'une liste de
     * bâtiments, et le geste d'offrande mérite qu'on s'y arrête.
     */
    #[Route('/{id}/temple', name: 'app_partie_temple', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function temple(GameSave $partie, Offrandes $offrandes): Response
    {
        $ville = $partie->getVille();
        $temple = $ville->batimentDeType(TypeDeBatiment::Temple);

        $pantheon = [];

        foreach (Divinite::pantheon() as $divinite) {
            $suivie = $ville->faveurDe($divinite);
            $pantheon[] = [
                'divinite' => $divinite,
                'faveur' => $ville->faveurEnvers($divinite),
                'palier' => $ville->palierDe($divinite),
                // Un dieu qui commence à se détourner doit le dire avant que
                // son effet ne cesse, sinon le joueur ne l'apprend qu'une fois
                // le palier perdu.
                'seDetourne' => null !== $suivie
                    && $suivie->getQuinzainesSansOffrande() > Negligence::QUINZAINES_DE_GRACE
                    && $suivie->getFaveur() > Negligence::PLANCHER,
                'quinzainesSansOffrande' => $suivie?->getQuinzainesSansOffrande() ?? 0,
                // Le supplément de fête se lit **avant** de donner, comme le
                // prix d'un ordre commercial montre son effet avant
                // l'engagement.
                'supplementDeFete' => Offrandes::supplementDeFete($partie->dateDeJeu(), $divinite),
            ];
        }

        return $this->render('partie/temple.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'pantheon' => $pantheon,
            'aUnTemple' => null !== $temple,
            'niveauDuTemple' => $temple?->getNiveau() ?? 0,
            'divinitesPortables' => Temple::divinitesPortables($ville),
            'plafond' => Temple::plafondDeFaveur($ville),
            'honorees' => $ville->divinitesHonorees(),
            'corbeille' => $offrandes->corbeillePour($partie),
            'pointsParOffrande' => Offrandes::POINTS_PAR_OFFRANDE,
            'debenParOffrande' => Offrandes::DEBEN_PAR_OFFRANDE,
            'fete' => $partie->feteEnCours(),
            'pointsDeFete' => Offrandes::POINTS_DE_FETE,
        ]);
    }

    /**
     * Porte une offrande au Temple.
     *
     * Le seul geste du jeu sans contrepartie immédiate : on donne, la faveur
     * monte, et ce qu'elle change se verra plus tard.
     */
    #[Route('/{id}/temple/offrir', name: 'app_partie_offrir', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function offrir(Request $request, GameSave $partie, Offrandes $offrandes): Response
    {
        if (!$this->isCsrfTokenValid('offrir', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $divinite = Divinite::tryFrom((string) $request->request->get('divinite'));
        $ressource = Ressource::tryFrom((string) $request->request->get('ressource'));

        if (null === $divinite || null === $ressource) {
            throw $this->createNotFoundException('Offrande inconnue.');
        }

        try {
            $points = $offrandes->offrir($partie, $divinite, $ressource, $request->request->getInt('quantite'));
            $this->addFlash('succes', \sprintf(
                'L\'offrande est portée à %s : %d point%s de faveur.',
                $divinite->libelle(),
                $points,
                $points > 1 ? 's' : '',
            ));
        } catch (OffrandeImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->redirectToRoute('app_partie_temple', ['id' => $partie->getId()]);
    }

    /**
     * La carte d'exploration : une grille isométrique, brouillard compris.
     *
     * La case détaillée est choisie côté serveur plutôt qu'en JavaScript — le
     * jeu se joue sans, et un lien reste partageable.
     */
    #[Route('/{id}/carte', name: 'app_partie_carte', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function carte(
        Request $request,
        GameSave $partie,
        Explorations $explorations,
        Enquetes $enquetes,
    ): Response {
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
            'peutFouiller' => $detaillee instanceof Zone && $enquetes->peutFouiller($ville, $detaillee),
            // L'émissaire ne va que là où l'on sait déjà qu'il y a quelqu'un,
            // et il lui faut des scribes pour consigner ce qu'il rapporte.
            'peutEnvoyerUnEmissaire' => $detaillee instanceof Zone
                && $detaillee->estDecouverte()
                && !$detaillee->porteLaVille()
                && $ville->possede(TypeDeBatiment::MaisonDesScribes)
                && !$ville->aUneExpeditionVers($detaillee),
            'coutDeLEmissaire' => $detaillee instanceof Zone
                ? $explorations->coutVers($partie, $detaillee, RoleDExploration::Emissaire)
                : RoleDExploration::Emissaire->cout(),
            'provisionsDeLEmissaire' => $detaillee instanceof Zone
                ? $explorations->provisionsVers($partie, $detaillee, RoleDExploration::Emissaire)
                : RoleDExploration::Emissaire->provisions(),
            // Sans Port, aucune barque n'appareille : la case poissonneuse
            // s'affiche, mais le bouton laisse la place au motif.
            'aUnPort' => $ville->possede(TypeDeBatiment::Port),
            // Les équipages du territoire, indexés par « x:y:ressource » —
            // c'est ce qui dit au joueur qu'une carrière tourne à moitié faute
            // de bras, plutôt que de le lui laisser deviner au stock.
            'equipages' => Effectifs::repartirLeTerritoire($ville, $partie->getCycle()),
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

        $role = RoleDExploration::tryFrom((string) $request->request->get('role')) ?? RoleDExploration::Eclaireur;

        try {
            $expedition = $explorations->envoyer($partie, $destination, $role);
            $this->addFlash('succes', \sprintf(
                '%s part%s. Il sera sur place dans %d cycle%s.',
                $role->libelle(),
                RoleDExploration::Emissaire === $role ? '' : ' en reconnaissance',
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
        Enquetes $enquetes,
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
     * @return list<string>
     */
    private static function melangerLesConclusions(Enquete $enquete): array
    {
        $conclusions = $enquete->conclusions();
        shuffle($conclusions);

        return $conclusions;
    }

    /**
     * @return list<string>
     */
    private static function melangerLesPropositions(Enigme $enigme): array
    {
        $propositions = $enigme->propositions();
        shuffle($propositions);

        return $propositions;
    }

    /**
     * Les signes d'une inscription, mélangés pour le rendu. Le tirage n'a
     * aucune conséquence de jeu : il empêche seulement de lire la réponse dans
     * l'ordre du HTML.
     *
     * @return list<SymboleHieroglyphique>
     */
    private function melanger(Inscription $inscription): array
    {
        $signes = $inscription->signes();
        shuffle($signes);

        return $signes;
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
     * L'étal de chaque route ouverte : ce qui peut s'y annoncer, dans quelle
     * fourchette, et l'empressement que le prix posé produit.
     *
     * @return array<string, list<array{ressource: Ressource, sens: SensDEchange, ordre: ?\App\Entity\OrdreCommercial, plancher: int, plafond: int, empressement: int}>>
     */
    private function etalsDesRoutesOuvertes(GameSave $partie, Commerce $commerce): array
    {
        $etals = [];

        foreach ($partie->getVille()->getRoutesCommerciales() as $route) {
            if ($route->estOuverte()) {
                $etals[$route->getPartenaire()] = $commerce->etalDe($partie, $route);
            }
        }

        return $etals;
    }

    /**
     * Ce que chaque bâtiment qui fabrique sait faire, et ce qu'il fait déjà.
     *
     * L'Atelier et la Forge partagent tout : un seul gabarit les rend, une
     * seule boucle les prépare.
     *
     * @return list<array{type: TypeDeBatiment, niveau: int, lotsMaximum: int, recettes: list<array{recette: Recette, matieres: array<string, int>, realisable: bool, empechement: ?string}>, ordre: ?\App\Entity\OrdreDeFabrication}>
     */
    private function ateliersDeLaVille(GameSave $partie, Fabrication $fabrication): array
    {
        $ville = $partie->getVille();
        $ateliers = [];

        foreach (Recette::batimentsQuiFabriquent() as $type) {
            $batiment = $ville->batimentDeType($type);

            if (null === $batiment) {
                continue;
            }

            $ateliers[] = [
                'type' => $type,
                'niveau' => $batiment->getNiveau(),
                'lotsMaximum' => Fabrication::lotsMaximum($batiment->getNiveau()),
                'recettes' => $fabrication->offrePour($partie, $type),
                'ordre' => $ville->ordreDeFabricationDe($type),
            ];
        }

        return $ateliers;
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
