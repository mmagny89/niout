<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Enum\GameMode;
use App\Fenetre\BatimentsDeLaCite;
use App\Fenetre\DetailDeCase;
use App\Fenetre\OuvertureDeFenetre;
use App\Fenetre\Panneau\PanneauxDeLaVille;
use App\Fenetre\VueDeLaVille;
use App\Form\NouvellePartieType;
use App\Game\AppelDHabitants;
use App\Game\AppelImpossible;
use App\Game\CartoucheRoyal;
use App\Game\ChantierImpossible;
use App\Game\Chantiers;
use App\Game\Commerce;
use App\Game\CommerceImpossible;
use App\Game\Culture;
use App\Game\Dechiffrage;
use App\Game\DechiffrageImpossible;
use App\Game\Divinite;
use App\Game\Enigme;
use App\Game\EnigmeImpossible;
use App\Game\Enigmes;
use App\Game\Enquete;
use App\Game\EnqueteImpossible;
use App\Game\Enquetes;
use App\Game\EtatDeLaVille;
use App\Game\ExerciceDesSons;
use App\Game\ExploitationImpossible;
use App\Game\Exploitations;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\Fabrication;
use App\Game\FabricationImpossible;
use App\Game\GeographieDeLaPartie;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\LeconDeNiout;
use App\Game\LectureDeCartouche;
use App\Game\Legs;
use App\Game\Marche;
use App\Game\Mecontentement;
use App\Game\MedjayImpossible;
use App\Game\Medjays;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\MissionFermee;
use App\Game\ModeDivin;
use App\Game\OffrandeImpossible;
use App\Game\Offrandes;
use App\Game\PassageDeCycle;
use App\Game\PlafondDePartiesAtteint;
use App\Game\PrixDuMarche;
use App\Game\Progression;
use App\Game\QueteImpossible;
use App\Game\QuetesDeChantier;
use App\Game\RecapitulatifDeQuinzaine;
use App\Game\Recette;
use App\Game\RecrutementImpossible;
use App\Game\Recrutements;
use App\Game\Ressource;
use App\Game\Rivaux;
use App\Game\RoleDExploration;
use App\Game\SensDEchange;
use App\Game\SpecialisationMedjay;
use App\Game\SuccessionFamiliale;
use App\Game\SuccessionImpossible;
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
        Progression $progression,
        Legs $legs,
    ): Response {
        /** @var User $joueur */
        $joueur = $this->getUser();

        if ($parties->plafondAtteintPour($joueur)) {
            $this->addFlash('erreur', (new PlafondDePartiesAtteint())->getMessage());

            return $this->redirectToRoute('app_parties');
        }

        // Ce que le joueur a ouvert : ses missions accomplies, et la
        // suivante (doc 09). Le mode d'essai les ouvre toutes. Le lanceur
        // refait le contrôle de son côté — un POST forgé n'ouvre pas le Sinaï
        // à qui sort du Delta.
        $ouvertes = [];
        foreach ($progression->missionsOuvertes($joueur) as $numero) {
            $mission = $missions->get($numero);
            $ouvertes[\sprintf('%d — %s (%s)', $mission->numero, $mission->ville, $mission->region)] = $mission->numero;
        }

        $form = $this->createForm(NouvellePartieType::class, options: ['missionsOuvertes' => $ouvertes]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{mode: GameMode, nomDeFamille: string, difficulte: int, tailleGrille: int, mission?: int} $donnees */
            $donnees = $form->getData();

            try {
                $partie = GameMode::Campagne === $donnees['mode']
                    ? $lanceur->lancerCampagne($joueur, $donnees['nomDeFamille'], $donnees['mission'] ?? null)
                    : $lanceur->lancerAventure(
                        $joueur,
                        $donnees['nomDeFamille'],
                        $donnees['difficulte'],
                        $donnees['tailleGrille'],
                    );

                // La commande du pharaon s'ouvre d'office, en fenêtre, au-dessus
                // de la carte : on n'arrive plus sur une page à part.
                return $this->redirectToRoute('app_partie_carte', [
                    'id' => $partie->getId(),
                    'ouvre' => $this->generateUrl('app_partie_commande', ['id' => $partie->getId()]),
                ]);
            } catch (MissionFermee $fermee) {
                $this->addFlash('erreur', $fermee->getMessage());
            }
        }

        return $this->render('partie/nouvelle.html.twig', [
            'form' => $form,
            'premiereMission' => $missions->get($progression->prochaineMission($joueur)),
            'legsEnDeben' => $legs->debenPour($joueur, $progression->prochaineMission($joueur)),
            'campagneAchevee' => $progression->campagneAchevee($joueur),
            'villeAventure' => LanceurDePartie::VILLE_DU_MODE_AVENTURE,
        ]);
    }

    /**
     * Reprendre une partie, c'est se retrouver **sur la carte**.
     *
     * Il n'y a plus d'écran de reprise : la carte est la seule page de jeu
     * (`docs/plan-fenetres.md`), et ce que le récapitulatif disait — le cycle,
     * les deben, les vivres, la renommée — la barre de jeu le dit en
     * permanence. Rien ne se produit en l'absence du joueur : un « depuis votre
     * dernière visite » serait toujours vide.
     *
     * La route reste, et garde son rôle : dater l'ouverture, qui ordonne
     * « Mes parties » de la plus récemment jouée à la plus ancienne.
     */
    #[Route('/{id}', name: 'app_partie_reprendre', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function reprendre(GameSave $partie, EntityManagerInterface $entityManager): Response
    {
        $partie->marquerOuverte();
        $entityManager->flush();

        return $this->redirectToRoute('app_partie_carte', ['id' => $partie->getId()]);
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
        Request $request,
        GameSave $partie,
        PanneauxDeLaVille $panneaux,
        EtatDeLaVille $etat,
        GeographieDeLaPartie $geographies,
        BatimentsDeLaCite $cite,
    ): Response {
        // **La ville est une fenêtre, pas une page** (`docs/plan-fenetres.md`).
        // Sans l'en-tête `Turbo-Frame`, on rend la carte avec la fenêtre déjà
        // ouverte sur cette adresse : une adresse tapée, un lien partagé ou une
        // redirection après action retombent sur le bon écran. Le contenu de la
        // fenêtre est alors calculé une seule fois, par la sous-requête que la
        // carte lance vers cette même route.
        if (!OuvertureDeFenetre::estUneRequeteDeCadre($request)) {
            return $this->forward(self::class.'::carte', ['id' => $partie->getId()], ['ouvre' => $request->getRequestUri()]);
        }

        $ville = $partie->getVille();
        $onglets = $this->ongletsDeLaVille($ville);
        // L'onglet ouvert au chargement. Une action de la ville se solde par une
        // redirection, donc par un rechargement complet : sans cette reprise,
        // vendre au Marché renvoyait sur la Résidence familiale, et il fallait
        // rouvrir son onglet à chaque geste.
        $ongletActif = $this->ongletDemande($onglets, $request->query->get('onglet'));

        return $this->render('fenetre/ville.html.twig', [
            // Le rail des carrés : la cité, qui permet de passer d'un bâtiment à
            // l'autre sans fermer la fenêtre.
            'rail' => $cite->pour($partie),
            'partie' => $partie,
            'ville' => $ville,
            'onglets' => $onglets,
            'ongletActif' => $ongletActif,
            // Le bon comme le mauvais, sur les deux écrans : un joueur ne doit
            // pas changer de page pour savoir où en est sa ville.
            'signaux' => $etat->signaux($partie),
            // Sans Nil, il n'y a ni crue ni saison d'inondation (doc 02) : la
            // barre de jeu n'annonce pas une crue dans un désert.
            'connaitLaCrue' => $geographies->connaitLaCrue($partie),
            // **Le panneau ouvert, et lui seul** : chaque bâtiment calcule ce
            // qu'il affiche (`App\Fenetre\Panneau`), plutôt que la fenêtre de
            // tous les préparer pour n'en rendre qu'un.
        ] + $panneaux->pour($ongletActif, $partie));
    }

    /**
     * Vend une ressource au Marché — aux gens de la ville et aux passants, dans
     * la limite de ce que la place absorbe en une quinzaine. Les vrais volumes
     * passent par les routes commerciales.
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

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Pose, corrige ou lève le seuil en deçà duquel la ville ne vend jamais
     * une ressource. Tout ce qui dépasse part au Marché, jour de marché après
     * jour de marché.
     *
     * **Rien n'est débité ici** : c'est un seuil, pas un dépôt. La marchandise
     * ne quitte la réserve qu'au moment d'être vendue.
     */
    #[Route('/{id}/ville/reserve', name: 'app_partie_reserve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function reserve(Request $request, GameSave $partie, EntityManagerInterface $gestionnaire): Response
    {
        if (!$this->isCsrfTokenValid('reserve', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $ressource = Ressource::tryFrom((string) $request->request->get('ressource'));

        if (null === $ressource) {
            throw $this->createNotFoundException('Ressource inconnue.');
        }

        $ville = $partie->getVille();

        if ($request->request->has('retirer')) {
            $reserve = $ville->reserveGardeeDe($ressource);

            if (null !== $reserve) {
                $ville->cesserDeGarder($reserve);
                $gestionnaire->remove($reserve);
                $gestionnaire->flush();
                $this->addFlash('succes', \sprintf(
                    'Le %s ne part plus au Marché de lui-même : vous gardez tout.',
                    $ressource->libelle(),
                ));
            }

            return $this->retourALaVille($request, $partie);
        }

        $quantite = $request->request->getInt('quantite');

        if ($quantite < 0) {
            $this->addFlash('erreur', 'On ne garde pas une quantité négative.');

            return $this->retourALaVille($request, $partie);
        }

        if (null === PrixDuMarche::pour($ressource)) {
            $this->addFlash('erreur', \sprintf('Le %s ne se négocie pas : c\'est la monnaie.', $ressource->libelle()));

            return $this->retourALaVille($request, $partie);
        }

        $ville->garderEnReserve($ressource, $quantite);
        $gestionnaire->flush();

        $surplus = max(0, $ville->quantite($ressource) - $quantite);

        $this->addFlash('succes', \sprintf(
            'Vous gardez désormais %d %s. %s',
            $quantite,
            $ressource->libelle(),
            $surplus > 0
                ? \sprintf('Le surplus — %d pour l\'instant — partira au Marché au fil des quinzaines.', $surplus)
                : 'Rien ne dépasse ce seuil pour le moment.',
        ));

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Règle ce que la ville fait payer ses habitants, et ce qu'elle paie ses
     * travailleurs.
     *
     * **Les deux ont une contrepartie**, sans quoi il n'existerait qu'une
     * bonne valeur pour chacun : un prix abusif et un salaire de misère
     * mécontentent la ville (`Mecontentement`), et un salaire généreux
     * l'apaise deux fois plus vite.
     */
    #[Route('/{id}/ville/train-de-vie', name: 'app_partie_train_de_vie', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function trainDeVie(Request $request, GameSave $partie, EntityManagerInterface $gestionnaire): Response
    {
        if (!$this->isCsrfTokenValid('train-de-vie', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $ville = $partie->getVille();

        if ($request->request->has('marge')) {
            $ville->fixerLaMargeDuMarche($request->request->getInt('marge'));
            $this->addFlash('succes', \sprintf(
                'Vos habitants paieront %d %% du cours. %s',
                $ville->getMargeDuMarche(),
                Mecontentement::prixAbusif($ville)
                    ? 'On trouvera cela cher, et on le fera savoir.'
                    : 'Un prix que la ville accepte.',
            ));
        }

        if ($request->request->has('salaire')) {
            $ville->fixerLeSalaireDeBase($request->request->getInt('salaire'));
            $this->addFlash('succes', \sprintf(
                'Vos travailleurs toucheront %d deben la quinzaine. %s',
                $ville->getSalaireDeBase(),
                match (true) {
                    Mecontentement::salaireDeMisere($ville) => 'C\'est sous l\'usage, et cela se paiera en mécontentement.',
                    $ville->getSalaireDeBase() >= Mecontentement::SALAIRE_GENEREUX => 'De quoi apaiser la ville plus vite.',
                    default => 'Le salaire d\'usage.',
                },
            ));
        }

        $gestionnaire->flush();

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Pose, réoriente ou lève la consigne permanente d'un atelier : ce qu'il
     * refait de lui-même à chaque fois qu'il se libère.
     *
     * **Rien n'est débité ici** : c'est une consigne, pas un ordre. Les
     * matières seront prises à chaque relance, et seulement si elles sont là.
     */
    #[Route('/{id}/ville/consigne', name: 'app_partie_consigne', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function consigne(Request $request, GameSave $partie, EntityManagerInterface $gestionnaire): Response
    {
        if (!$this->isCsrfTokenValid('consigne', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $ville = $partie->getVille();

        if ($request->request->has('lever')) {
            $type = TypeDeBatiment::tryFrom((string) $request->request->get('batiment'));

            if (null === $type) {
                throw $this->createNotFoundException('Bâtiment inconnu.');
            }

            $consigne = $ville->consigneDeFabricationDe($type);

            if (null !== $consigne) {
                $ville->leverLaConsigne($consigne);
                $gestionnaire->remove($consigne);
                $gestionnaire->flush();
                $this->addFlash('succes', \sprintf(
                    '%s ne se remettra plus à l\'ouvrage de lui-même.',
                    $type->libelle(),
                ));
            }

            return $this->retourALaVille($request, $partie);
        }

        $recette = Recette::tryFrom((string) $request->request->get('recette'));

        if (null === $recette) {
            throw $this->createNotFoundException('Recette inconnue.');
        }

        $atelier = $ville->batimentDeType($recette->batiment());

        if (null === $atelier) {
            $this->addFlash('erreur', \sprintf('Il vous faut %s pour cela.', $recette->batiment()->libelle()));

            return $this->retourALaVille($request, $partie);
        }

        $lots = max(1, min($request->request->getInt('lots', 1), Fabrication::lotsMaximum($atelier->getNiveau())));

        $ville->consigner($recette, $lots);
        $gestionnaire->flush();

        $this->addFlash('succes', \sprintf(
            '%s se remettra désormais à %s — %d lot%s — dès qu\'il se libère, tant que les matières suivent.',
            $recette->batiment()->libelle(),
            mb_strtolower($recette->libelle()),
            $lots,
            $lots > 1 ? 's' : '',
        ));

        return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Lève un Medjaÿ à la Caserne (doc 03).
     *
     * Le contrôle vit dans `Medjays`, pas seulement dans le gabarit : un POST
     * forgé ne doit pas lever un archer dans une Caserne de niveau 1.
     */
    #[Route('/{id}/ville/medjay', name: 'app_partie_lever_medjay', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function leverUnMedjay(Request $request, GameSave $partie, Medjays $medjays): Response
    {
        if (!$this->isCsrfTokenValid('lever-medjay', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $specialisation = SpecialisationMedjay::tryFrom((string) $request->request->get('specialisation'));

        if (null === $specialisation) {
            throw $this->createNotFoundException('Spécialisation inconnue.');
        }

        try {
            $medjays->lever($partie, $specialisation);
        } catch (MedjayImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());

            return $this->retourALaVille($request, $partie);
        }

        $this->addFlash('succes', \sprintf(
            'Un %s rejoint votre troupe.',
            mb_strtolower($specialisation->libelle()),
        ));

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Choisit l'héritier qui prend la suite (doc 13).
     *
     * **Le contrôle vit dans le domaine** : la succession doit être ouverte, et
     * l'héritier se présenter — un POST forgé ne change pas de chef de famille
     * au milieu d'une génération.
     */
    #[Route('/{id}/ville/heritier', name: 'app_partie_heritier', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function choisirUnHeritier(Request $request, GameSave $partie, SuccessionFamiliale $successions): Response
    {
        if (!$this->isCsrfTokenValid('heritier', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $heritier = $successions->choisir($partie, (int) $request->request->get('heritier'));
        } catch (SuccessionImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());

            return $this->retourALaVille($request, $partie);
        }

        $this->addFlash('succes', \sprintf(
            '%s prend la tête de la maison. La ville, elle, ne change pas de mains.',
            $heritier->prenom,
        ));

        return $this->retourALaVille($request, $partie);
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

            return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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
     * Le second : `ROLE_ADMIN` en plus, accordé en console seulement. C'est
     * la vraie barrière ; l'absence de bouton n'en serait pas une.
     */
    #[Route('/{id}/divin', name: 'app_partie_divin', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(User::ROLE_ADMIN)]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function modeDivin(Request $request, GameSave $partie, ModeDivin $modeDivin): Response
    {
        if (!$this->isCsrfTokenValid('divin', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if ($partie->estEnModeDivin() && $request->request->has('combler')) {
            $modeDivin->combler($partie);
            $this->addFlash('succes', 'Les réserves sont de nouveau pleines.');

            return $this->retourDemande($request, $partie);
        }

        if ($partie->estEnModeDivin() && $request->request->has('brouillard')) {
            $levees = $modeDivin->leverLeBrouillard($partie);
            $this->addFlash('succes', 0 === $levees
                ? 'La carte était déjà entièrement reconnue.'
                : \sprintf('Le brouillard se lève sur %d case%s.', $levees, $levees > 1 ? 's' : ''));

            return $this->retourDemande($request, $partie);
        }

        $this->addFlash('succes', $modeDivin->basculer($partie)
            ? 'Cette partie devient une partie d\'essai : un million de chaque ressource, aucun plafond.'
            : 'Cette partie retrouve les règles ordinaires. Ce qui a été donné reste.');

        return $this->retourDemande($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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

            return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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

        return $this->retourALaVille($request, $partie);
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

            return $this->retourALaVille($request, $partie);
        }

        if (!$lecture['juste']) {
            $this->addFlash('erreur', 'Ce n\'est pas ce que disent ces signes. Reprenez la clé et recommencez.');

            return $this->retourALaVille($request, $partie);
        }

        $this->addFlash('succes', \sprintf(
            '« %s » %s',
            $inscription->lecture(),
            null === $lecture['apprend']
                ? 'Vos scribes n\'apprennent rien de neuf : ils lisent déjà tout.'
                : \sprintf('Vos scribes apprennent un signe de plus : %s.', $lecture['apprend']->libelle()),
        ));

        return $this->retourALaVille($request, $partie);
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

            return $this->retourALaVille($request, $partie);
        }

        $this->addFlash(
            $verdict['juste'] ? 'succes' : 'erreur',
            $verdict['juste']
                ? \sprintf(
                    '%s Vous recevez %d deben%s.',
                    $verdict['explication'],
                    $verdict['recompense'],
                    self::etLaRenommee($verdict['renommee']),
                )
                : \sprintf('Ce n\'était pas la réponse. %s', $verdict['explication']),
        );

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Fouille une case où quelque chose se trame, et verse au dossier
     * l'indice qu'on y trouve.
     */
    /**
     * Répond à la leçon fondatrice : écrire « Niout ».
     *
     * **Elle se retente**, contrairement aux énigmes à choix multiple : remettre
     * quatre signes dans l'ordre est un exercice, pas une devinette. La
     * récompense, elle, ne tombe qu'une fois.
     */
    #[Route('/{id}/scribes/niout', name: 'app_partie_niout', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function ecrireNiout(Request $request, GameSave $partie, LeconDeNiout $lecon): Response
    {
        if (!$this->isCsrfTokenValid('niout', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $ordre = array_values(array_filter(explode(',', (string) $request->request->get('ordre'))));
        $reponse = $lecon->repondre($partie, $ordre);

        if ($reponse['juste']) {
            $this->addFlash('succes', \sprintf(
                'C\'est bien ainsi qu\'on écrit %s. %s%s',
                LeconDeNiout::MOT,
                $reponse['explication'],
                $reponse['recompense'] > 0
                    ? \sprintf(' Vos scribes reçoivent %d deben pour la leçon.', $reponse['recompense'])
                    : '',
            ));
        } else {
            $this->addFlash('erreur', \sprintf(
                'Ce n\'est pas l\'ordre juste. %s Reprenez : rien ne vous en empêche.',
                $reponse['explication'],
            ));
        }

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Corrige une série d'exercices sur les sons. La série n'est pas stockée :
     * la graine revient avec les réponses, et le serveur recompose les mêmes
     * questions (`ExerciceDesSons`).
     */
    #[Route('/{id}/scribes/exercice-sons', name: 'app_partie_exercice_sons', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function exerciceDesSons(Request $request, GameSave $partie, ExerciceDesSons $exercice): Response
    {
        if (!$this->isCsrfTokenValid('exercice-sons', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if (!$partie->getVille()->possede(TypeDeBatiment::MaisonDesScribes)) {
            throw $this->createAccessDeniedException('Il faut une Maison des scribes.');
        }

        $reponses = $request->request->all('reponse');
        $bilan = $exercice->repondre($partie, (int) $request->request->get('graine'), $reponses);

        $this->addFlash($bilan['reussie'] ? 'succes' : 'erreur', \sprintf(
            '%d bonne%s réponse%s sur %d.%s%s%s',
            $bilan['bonnes'],
            $bilan['bonnes'] > 1 ? 's' : '',
            $bilan['bonnes'] > 1 ? 's' : '',
            $bilan['total'],
            $bilan['recompense'] > 0 ? \sprintf(' Vos scribes reçoivent %d deben pour la série.', $bilan['recompense']) : '',
            $bilan['reussie'] ? '' : \sprintf(' Il en fallait %d pour être récompensé : on peut recommencer, la série change.', ExerciceDesSons::SEUIL_DE_REUSSITE),
            [] === $bilan['corrections'] ? '' : ' À retenir : '.implode(' ', $bilan['corrections']),
        ));

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Corrige la lecture d'un cartouche (`LectureDeCartouche`).
     */
    #[Route('/{id}/scribes/lecture-cartouche', name: 'app_partie_lecture_cartouche', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function lectureDeCartouche(Request $request, GameSave $partie, LectureDeCartouche $lecture): Response
    {
        if (!$this->isCsrfTokenValid('lecture-cartouche', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        if (!$partie->getVille()->possede(TypeDeBatiment::MaisonDesScribes)) {
            throw $this->createAccessDeniedException('Il faut une Maison des scribes.');
        }

        $bilan = $lecture->repondre($partie, (int) $request->request->get('graine'), $request->request->all('reponse'));

        $this->addFlash($bilan['juste'] ? 'succes' : 'erreur', \sprintf(
            '%s %s%s %s',
            $bilan['juste'] ? 'Bien lu.' : \sprintf('%d signe%s lu%s juste sur %d.', $bilan['bonnes'], $bilan['bonnes'] > 1 ? 's' : '', $bilan['bonnes'] > 1 ? 's' : '', $bilan['total']),
            $bilan['lecon'],
            $bilan['recompense'] > 0 ? \sprintf(' Vos scribes reçoivent %d deben pour la lecture.', $bilan['recompense']) : '',
            implode(' ', $bilan['details']),
        ));

        return $this->retourALaVille($request, $partie);
    }

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

        return $this->retourALaCarte($partie, $zone);
    }

    /**
     * Ce qu'on ajoute au verdict d'une énigme ou d'une enquête quand elle a
     * rapporté de la renommée. **Muet quand elle n'a rien rapporté** : le
     * plafond de la mission est atteint, et annoncer « et zéro de renommée »
     * transformerait une réussite en reproche.
     */
    private static function etLaRenommee(int $renommee): string
    {
        return $renommee > 0
            ? \sprintf(' et %d de renommée', $renommee)
            : '';
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

            return $this->retourALaVille($request, $partie);
        }

        $this->addFlash(
            $verdict['juste'] ? 'succes' : 'erreur',
            match (true) {
                $verdict['juste'] => \sprintf(
                    'Affaire close. %s Vous recevez %d deben%s.',
                    $verdict['denouement'],
                    $verdict['recompense'],
                    self::etLaRenommee($verdict['renommee']),
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

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Passe un accord avec le marchand rival : la plus rapide des trois
     * issues du doc 08, et la seule qui coûte des deben plutôt que du temps.
     */
    #[Route('/{id}/ville/accord', name: 'app_partie_accord', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function passerUnAccord(Request $request, GameSave $partie, Rivaux $rivaux): Response
    {
        if (!$this->isCsrfTokenValid('accord', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $this->addFlash('succes', $rivaux->passerUnAccord($partie));
        } catch (CommerceImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Répond à une requête du pharaon : livrer, ou décliner.
     *
     * **Jamais obligatoire** (doc 09) : refuser coûte deux points de renommée
     * et rien d'autre.
     */
    #[Route('/{id}/ville/quete', name: 'app_partie_quete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function repondreALaQuete(Request $request, GameSave $partie, QuetesDeChantier $quetes): Response
    {
        if (!$this->isCsrfTokenValid('quete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        try {
            $this->addFlash('succes', $request->request->has('refuser')
                ? $quetes->refuser($partie)
                : $quetes->livrer($partie));
        } catch (QueteImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->retourALaVille($request, $partie);
    }

    /**
     * L'ancienne adresse du Temple. Il est désormais un onglet de la ville —
     * **un onglet, un bâtiment** (décision de la joueuse) —, mais la route
     * survit : un lien mis de côté ou un signet ne doit pas tomber sur du vide.
     */
    #[Route('/{id}/temple', name: 'app_partie_temple', requirements: ['id' => '\\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function temple(GameSave $partie): Response
    {
        return $this->redirectToRoute('app_partie_ville', [
            'id' => $partie->getId(),
            'onglet' => TypeDeBatiment::Temple->value,
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

        return $this->retourALaVille($request, $partie);
    }

    /**
     * La carte d'exploration : une grille isométrique, brouillard compris.
     *
     * **La carte est la seule page de jeu** : le détail d'une case, les
     * expéditions, la ville, la commande s'ouvrent en fenêtre par-dessus
     * (`docs/plan-fenetres.md`). Le serveur rend la fenêtre que l'adresse
     * demande (`ouvre`), pour qu'un lien reste partageable et qu'un
     * rechargement la retrouve.
     *
     * Une adresse `?zone=x-y`, d'avant les fenêtres, ouvre la fenêtre de cette
     * case : les anciens liens continuent de marcher.
     */
    #[Route('/{id}/carte', name: 'app_partie_carte', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function carte(
        Request $request,
        GameSave $partie,
        EtatDeLaVille $etat,
        GeographieDeLaPartie $geographies,
        OuvertureDeFenetre $fenetres,
        VueDeLaVille $vueDeLaVille,
    ): Response {
        $ville = $partie->getVille();
        $zones = $this->zonesTrieesPourLIsometrie($ville);
        // La ville vue d'en haut remplace le territoire (`?vue=ville`).
        $enVille = 'ville' === $request->query->get('vue');

        $ancienne = $this->zoneDemandee($zones, $request->query->get('zone'));
        if (null !== $ancienne && !$request->query->has('ouvre')) {
            $request->query->set('ouvre', $this->generateUrl('app_partie_case', [
                'id' => $partie->getId(),
                'coordonnees' => $ancienne->getX().'-'.$ancienne->getY(),
            ]));
        }

        $chemin = $fenetres->chemin($request, $partie);

        return $this->render('partie/carte.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            // La fenêtre ouverte au-dessus de la carte, telle que l'URL la dit :
            // son contenu est rendu ici, une fois, pour qu'un rechargement la
            // retrouve au même endroit.
            'ouvre' => $chemin,
            'fenetre' => null === $chemin ? null : $fenetres->rendre($request, $chemin),
            'zones' => $zones,
            'vue' => $enVille,
            'vueDeLaVille' => $enVille ? $vueDeLaVille->pour($partie) : null,
            // La case dont la fenêtre est ouverte, pour la surligner.
            'zoneDetaillee' => $this->zoneDeLOuverture($zones, $chemin),
            // Les signaux et les expéditions en route se lisent dans la barre
            // de jeu : on n'a plus de panneau où les ranger.
            'signaux' => $etat->signaux($partie),
            'connaitLaCrue' => $geographies->connaitLaCrue($partie),
        ]);
    }

    /**
     * Le détail d'une case, en fenêtre : ce qu'elle est, ce qu'on peut y faire.
     *
     * Remplace le panneau de droite de la carte, qui prenait un tiers de la
     * largeur pour un détail qu'on ne lit qu'un instant. La fenêtre est une
     * feuille posée à droite : la carte reste visible à côté.
     */
    #[Route('/{id}/case/{coordonnees}', name: 'app_partie_case', requirements: ['id' => '\d+', 'coordonnees' => '\d{1,3}-\d{1,3}'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function detailDeLaCase(Request $request, GameSave $partie, string $coordonnees, DetailDeCase $details): Response
    {
        $zone = $details->zone($partie, $coordonnees) ?? throw $this->createNotFoundException('Case inconnue.');

        // Une fenêtre, pas une page : sans l'en-tête `Turbo-Frame`, on rend la
        // carte avec la case déjà ouverte (voir `ville()`).
        if (!OuvertureDeFenetre::estUneRequeteDeCadre($request)) {
            return $this->forward(self::class.'::carte', ['id' => $partie->getId()], ['ouvre' => $request->getRequestUri()]);
        }

        return $this->render('fenetre/case.html.twig', [
            'partie' => $partie,
            'ville' => $partie->getVille(),
        ] + $details->pour($partie, $zone));
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

        // Les quatre places de la case, d'un coup : chacune porte sa culture,
        // ou rien. C'est un seul formulaire, donc une seule décision — semer,
        // changer et arracher se disent ensemble.
        $semees = [];
        $arrachees = 0;

        for ($rang = 1; $rang <= Zone::CHAMPS_MAX; ++$rang) {
            $demande = (string) $request->request->get('culture-'.$rang, '');

            try {
                if ('' === $demande) {
                    if (null !== $zone->parcelleAuRang($rang)) {
                        $exploitations->arracher($partie, $zone, $rang);
                        ++$arrachees;
                    }

                    continue;
                }

                $culture = Culture::tryFrom($demande);

                if (null === $culture) {
                    throw $this->createNotFoundException('Culture inconnue.');
                }

                $deja = $zone->parcelleAuRang($rang)?->getCulture();
                $exploitations->semer($partie, $zone, $rang, $culture);

                if ($deja !== $culture) {
                    $semees[] = $culture->libelle();
                }
            } catch (ExploitationImpossible $impossible) {
                $this->addFlash('erreur', $impossible->getMessage());

                return $this->retourALaCarte($partie, $zone);
            }
        }

        $dit = [];

        if ([] !== $semees) {
            $dit[] = \sprintf(
                'On sème %s',
                implode(', ', array_map(mb_strtolower(...), $semees)),
            );
        }

        if ($arrachees > 0) {
            $dit[] = \sprintf('%d parcelle%s arrachée%s', $arrachees, $arrachees > 1 ? 's' : '', $arrachees > 1 ? 's' : '');
        }

        if ([] === $dit) {
            $this->addFlash('succes', 'Rien de changé sur cette terre.');
        } else {
            $this->addFlash('succes', \sprintf(
                '%s. La case compte %d champ%s, soit autant de bras.%s',
                implode(', et ', $dit),
                $zone->getChamps(),
                $zone->getChamps() > 1 ? 's' : '',
                $partie->getVille()->possede(TypeDeBatiment::Grenier)
                    ? ''
                    : ' Sans Grenier, rien de ce qu\'ils donneront ne se conservera.',
            ));
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

    /**
     * Après une action sur une case, on rend la fenêtre de cette case : la
     * redirection tombe dans le cadre, ou rouvre la carte avec la case ouverte
     * pour qui n'a pas de cadre.
     */
    private function retourALaCarte(GameSave $partie, Zone $zone): Response
    {
        return $this->redirectToRoute('app_partie_case', [
            'id' => $partie->getId(),
            'coordonnees' => $zone->getX().'-'.$zone->getY(),
        ]);
    }

    /**
     * La case dont la fenêtre ouverte est le détail, ou null.
     *
     * @param list<Zone> $zones
     */
    private function zoneDeLOuverture(array $zones, ?string $chemin): ?Zone
    {
        if (null === $chemin || 1 !== preg_match('#/case/(\d{1,3}-\d{1,3})(?:\?|$)#', $chemin, $trouve)) {
            return null;
        }

        return $this->zoneDemandee($zones, $trouve[1]);
    }

    /**
     * Envoie quelqu'un sur une case : un éclaireur pour la reconnaître, un
     * émissaire pour parler à ses gens, un prospecteur pour y chercher un filon.
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

        // Les chars du pharaon ne se louent que pour une sortie en armes, et
        // le domaine les remet à zéro pour tout autre rôle.
        $charriers = max(0, (int) $request->request->get('charriers', 0));

        try {
            $expedition = $explorations->envoyer($partie, $destination, $role, $charriers);
            $this->addFlash('succes', \sprintf(
                '%s part%s. Il sera sur place dans %d cycle%s.',
                $role->libelle(),
                match (true) {
                    RoleDExploration::Emissaire === $role => '',
                    RoleDExploration::Prospecteur === $role => ' sonder la case',
                    $expedition->getCharriers() > 0 => \sprintf(
                        ' en armes, avec %d char(s) du pharaon',
                        $expedition->getCharriers(),
                    ),
                    RoleDExploration::ChefDExpedition === $role => ' en armes',
                    default => ' en reconnaissance',
                },
                $expedition->getDureeEnCycles(),
                $expedition->getDureeEnCycles() > 1 ? 's' : '',
            ));
        } catch (ExplorationImpossible $impossible) {
            $this->addFlash('erreur', $impossible->getMessage());
        }

        return $this->retourALaCarte($partie, $destination);
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

        return $this->retourALaVille($request, $partie);
    }

    /**
     * Fait passer une quinzaine. Le seul geste qui fasse avancer le temps.
     */
    #[Route('/{id}/cycle', name: 'app_partie_cycle', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(PartieVoter::JOUER, subject: 'partie')]
    public function passerUnCycle(
        Request $request,
        GameSave $partie,
        PassageDeCycle $cycle,
        RecapitulatifDeQuinzaine $recapitulatif,
    ): Response {
        if (!$this->isCsrfTokenValid('cycle', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton invalide.');
        }

        $avant = $recapitulatif->photographier($partie);
        $evenements = $cycle->passerEnDetail($partie);

        // Un récapitulatif, et non une pile de messages : ce que la quinzaine a changé
        // (écarts), puis le journal rangé par catégorie. Il voyage en message flash.
        $this->addFlash('quinzaine', $recapitulatif->composer($avant, $partie, $evenements));

        return $this->retourDemande($request, $partie);
    }

    /**
     * Où renvoyer le joueur après une action déclenchée depuis la barre de jeu.
     *
     * La liste blanche n'est pas une précaution de style : sans elle, une valeur
     * soumise deviendrait un nom de route arbitraire.
     */
    /**
     * Renvoie le joueur là d'où il vient — la carte ou la ville —, **et
     * exactement où il en était** : l'onglet qu'il avait ouvert dans la ville,
     * la case qu'il avait sélectionnée sur la carte.
     *
     * La quinzaine se passe souvent plusieurs fois de suite depuis le même
     * écran, en surveillant une expédition, un chantier ou un champ : avancer
     * le temps ne doit ni éjecter le joueur, ni lui faire retrouver sa place à
     * chaque cycle.
     */
    private function retourDemande(Request $request, GameSave $partie): Response
    {
        $route = $this->routeDeRetour($request);
        $onglet = (string) $request->request->get('onglet', '');
        $zone = (string) $request->request->get('zone', '');

        return $this->redirectToRoute($route, array_filter([
            'id' => $partie->getId(),
            'onglet' => 'app_partie_ville' === $route && '' !== $onglet ? $onglet : null,
            // La case détaillée survit à la quinzaine, comme l'onglet ouvert :
            // on avance souvent le temps en surveillant une expédition, un
            // champ ou une carrière, et repartir sur une carte sans sélection
            // obligeait à retrouver sa case à chaque cycle.
            'zone' => 'app_partie_carte' === $route && '' !== $zone ? $zone : null,
            // La fenêtre ouverte survit à la quinzaine : la carte la valide de
            // nouveau, rien n'est suivi sur parole.
            'ouvre' => 'app_partie_carte' === $route && '' !== (string) $request->request->get('ouvre') ? (string) $request->request->get('ouvre') : null,
            // La ville vue d'en haut aussi : on avance le temps en la regardant.
            'vue' => 'app_partie_carte' === $route && 'ville' === $request->request->get('vue') ? 'ville' : null,
        ], static fn (mixed $valeur): bool => null !== $valeur));
    }

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

            return $this->redirectToRoute('app_parties');
        }

        return $this->render('partie/abandonner.html.twig', [
            'partie' => $partie,
            'mission' => $this->missionDe($partie, $missions),
        ]);
    }

    /**
     * La commande du pharaon : mise en scène du lancement, ouverte d'office en
     * fenêtre une fois la partie créée (doc 09). Texte simple, pas de
     * cinématique.
     */
    #[Route('/{id}/commande', name: 'app_partie_commande', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function commande(Request $request, GameSave $partie, MissionCatalogue $missions): Response
    {
        // Une fenêtre, pas une page : sans l'en-tête `Turbo-Frame`, on rend la
        // carte avec la commande déjà ouverte (voir `ville()`).
        if (!OuvertureDeFenetre::estUneRequeteDeCadre($request)) {
            return $this->forward(self::class.'::carte', ['id' => $partie->getId()], ['ouvre' => $request->getRequestUri()]);
        }

        $mission = $this->missionDe($partie, $missions);

        return $this->render('fenetre/commande.html.twig', [
            'partie' => $partie,
            'mission' => $mission,
            // Le cartouche du pharaon qui commandite — null quand il n'est pas
            // établi, auquel cas l'écran n'en montre aucun plutôt qu'un
            // approximatif donné pour réel.
            'cartouche' => null !== $mission ? CartoucheRoyal::pourLePharaon($mission->pharaon) : null,
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
     * L'onglet demandé par l'adresse, s'il existe encore — le premier sinon.
     *
     * **Une clé venue de la requête ne s'affiche jamais telle quelle** : elle
     * est confrontée aux onglets réellement rendus. Un bâtiment peut avoir
     * disparu entre deux gestes, et une clé forgée ouvrirait un panneau qui
     * n'existe pas — la barre montrerait alors tous ses onglets fermés.
     *
     * @param list<array{cle: string, libelle: string, type: ?TypeDeBatiment, batiment: ?Building}> $onglets
     */
    private function ongletDemande(array $onglets, mixed $demande): string
    {
        $cles = array_column($onglets, 'cle');

        return \is_string($demande) && \in_array($demande, $cles, true)
            ? $demande
            : ($cles[0] ?? '');
    }

    /**
     * Ramène à l'écran de ville, **sur l'onglet d'où l'action est partie**.
     *
     * Toute action de la ville se solde par une redirection, donc par un
     * rechargement complet : sans cette reprise, le joueur qui vendait au
     * Marché ou embauchait à la Forge se retrouvait sur la Résidence familiale
     * et devait rouvrir son onglet à chaque geste.
     *
     * L'onglet voyage par la **requête**, pas par une session ni un fragment
     * d'URL : un fragment ne parvient jamais au serveur et ne survit pas à une
     * redirection, et l'adresse obtenue reste partageable — même choix que la
     * case détaillée de la carte.
     */
    private function retourALaVille(Request $request, GameSave $partie): Response
    {
        $onglet = (string) $request->request->get('onglet', '');

        return $this->redirectToRoute('app_partie_ville', array_filter([
            'id' => $partie->getId(),
            'onglet' => '' !== $onglet ? $onglet : null,
        ], static fn (mixed $valeur): bool => null !== $valeur));
    }

    /**
     * Les onglets de l'écran de ville : **un onglet par bâtiment** (décision
     * de la joueuse). Chaque bâtiment porte ce qui relève de sa fonction — sa
     * direction, ses ouvrages, ses routes —, ce qui remplace l'ancien
     * découpage par thème où le joueur devait deviner dans quel panneau ranger
     * quoi.
     *
     * **La Résidence familiale recueille tout ce qui n'appartient à aucun
     * bâtiment** : elle est le foyer de la lignée, présente dès le premier
     * jour et jamais construite. La mission, la renommée, les chantiers et la
     * liste de ce qui reste à bâtir y vivent — les envoyer ailleurs les
     * rendrait inaccessibles à une ville qui n'a encore rien dressé.
     *
     * L'ordre est celui de `TypeDeBatiment`, stable d'un rendu à l'autre :
     * `onglets_controller.js` apparie onglets et panneaux **par rang**, et les
     * deux boucles du gabarit lisent cette même liste.
     *
     * @return list<array{cle: string, libelle: string, type: ?TypeDeBatiment, batiment: ?Building}>
     */
    private function ongletsDeLaVille(City $ville): array
    {
        $onglets = [];

        foreach (TypeDeBatiment::cases() as $type) {
            $batiment = $ville->batimentDeType($type);

            // Le foyer de la lignée est là dès le premier jour, sans chantier
            // ni entrée dans la liste des bâtiments dressés.
            if (!$type->estLeBatimentDeDepart() && null === $batiment) {
                continue;
            }

            $onglets[] = [
                'cle' => $type->value,
                'libelle' => $type->libelle(),
                'type' => $type,
                'batiment' => $batiment,
            ];
        }

        // Le mode d'essai n'est pas un bâtiment : il ferme la barre, comme
        // avant, et n'existe que pour un compte qui porte le rôle.
        if ($this->isGranted(User::ROLE_ADMIN)) {
            $onglets[] = ['cle' => 'essai', 'libelle' => 'Essai', 'type' => null, 'batiment' => null];
        }

        return $onglets;
    }

    /**
     * La mission en cours, ou null en mode Aventure — qui suit des règnes.
     */
    private function missionDe(GameSave $partie, MissionCatalogue $missions): ?Mission
    {
        return $missions->de($partie);
    }
}
