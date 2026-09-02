<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Family;
use App\Entity\GameSave;
use App\Entity\User;
use App\Entity\Zone;
use App\Enum\GameMode;
use App\Form\NouvellePartieType;
use App\Game\AlphabetDesScribes;
use App\Game\AppelDHabitants;
use App\Game\AppelImpossible;
use App\Game\CartoucheRoyal;
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
use App\Game\EtatDeLaVille;
use App\Game\ExploitationImpossible;
use App\Game\Exploitations;
use App\Game\ExplorationImpossible;
use App\Game\Explorations;
use App\Game\Fabrication;
use App\Game\FabricationImpossible;
use App\Game\FilRouge;
use App\Game\GeographieDeLaPartie;
use App\Game\GeographieDeRegion;
use App\Game\Inscription;
use App\Game\LanceurDePartie;
use App\Game\LeconDeNiout;
use App\Game\Legs;
use App\Game\Marche;
use App\Game\Mecontentement;
use App\Game\Mission;
use App\Game\MissionCatalogue;
use App\Game\MissionFermee;
use App\Game\ModeDivin;
use App\Game\Negligence;
use App\Game\ObjectifDeMission;
use App\Game\ObjectifsDeMission;
use App\Game\OffrandeImpossible;
use App\Game\Offrandes;
use App\Game\PassageDeCycle;
use App\Game\PlafondDePartiesAtteint;
use App\Game\Population;
use App\Game\Progression;
use App\Game\Prospection;
use App\Game\QueteImpossible;
use App\Game\QuetesDeChantier;
use App\Game\Recette;
use App\Game\RecrutementImpossible;
use App\Game\Recrutements;
use App\Game\Ressource;
use App\Game\Rivaux;
use App\Game\RoleDExploration;
use App\Game\Salaires;
use App\Game\SensDEchange;
use App\Game\SigneAlphabetique;
use App\Game\SpecialiteDeChef;
use App\Game\SymboleHieroglyphique;
use App\Game\Temple;
use App\Game\TranscriptionDuNom;
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

            return $this->redirectToRoute('app_compte');
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

                return $this->redirectToRoute('app_partie_commande', ['id' => $partie->getId()]);
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
        Request $request,
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
        Rivaux $rivaux,
        MissionCatalogue $missions,
        Offrandes $offrandes,
        EtatDeLaVille $etat,
        GeographieDeLaPartie $geographies,
    ): Response {
        $ville = $partie->getVille();
        $geographie = $geographies->pour($partie);
        $onglets = $this->ongletsDeLaVille($ville);
        $ongletActif = $this->ongletDemande($onglets, $request->query->get('onglet'));
        $mission = $this->missionDe($partie, $missions);
        $inscription = $ville->possede(TypeDeBatiment::MaisonDesScribes)
            ? $dechiffrage->proposition($partie)
            : null;

        return $this->render('partie/ville.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'onglets' => $onglets,
            // Le bon comme le mauvais, sur les deux écrans : un joueur ne doit
            // pas changer de page pour savoir où en est sa ville.
            'signaux' => $etat->signaux($partie),
            'ennuis' => $etat->ennuis($partie),
            'bonnesNouvelles' => $etat->bonnesNouvelles($partie),
            'autonomie' => $etat->autonomieEnVivres($partie),
            'quinzainesDeVivresInquietantes' => EtatDeLaVille::QUINZAINES_DE_VIVRES_INQUIETANTES,
            // L'onglet ouvert au chargement. Une action de la ville se solde
            // par une redirection, donc par un rechargement complet : sans
            // cette reprise, vendre au Marché renvoyait sur la Résidence
            // familiale, et il fallait rouvrir son onglet à chaque geste.
            'ongletActif' => $ongletActif,
            'chantiers' => $ville->getChantiers(),
            'batimentsDresses' => $this->batimentsTriesParLibelle($ville),
            'offres' => $catalogue->pour($ville),
            'aUnMarche' => $ville->possede(TypeDeBatiment::Marche),
            'etal' => $ville->possede(TypeDeBatiment::Marche) ? $marche->etalPour($partie) : [],
            // Le débouché de la quinzaine se lit **avant** la vente : découvrir
            // la borne par un refus serait la subir au lieu de la jouer.
            'plafondDuMarche' => Marche::plafondDeLaQuinzaine($partie),
            'venteRestante' => $marche->venteRestante($partie),
            'niveauDuMarche' => $ville->batimentDeType(TypeDeBatiment::Marche)?->getNiveau() ?? 0,
            // La renommée était nulle part à l'écran : ce qu'elle change — le
            // prix d'un appel, la migration spontanée, l'arrivée d'un rival —
            // se subissait sans se comprendre.
            'palier' => $partie->getFamille()->palier(),
            'palierSuivant' => $partie->getFamille()->palier()->suivant(),
            'seuilDuPalierSuivant' => $partie->getFamille()->palier()->suivant()?->seuilDEntree() ?? Family::RENOMMEE_MAX,
            'renommeeMax' => Family::RENOMMEE_MAX,
            'coutDUnAppel' => $appels->cout($partie),
            'directions' => $this->directionsDesBatiments($partie, $recrutements),
            'ateliers' => $this->ateliersDeLaVille($partie, $fabrication),
            'routes' => $commerce->offrePour($partie),
            'etals' => $this->etalsDesRoutesOuvertes($partie, $commerce),
            'effectifs' => Effectifs::repartir($ville, $partie->getCycle()),
            // Le récapitulatif du territoire, rangé sous le bâtiment qui
            // gouverne chaque exploitation : le joueur ne savait pas s'il
            // produisait, et devait cliquer case par case pour le savoir.
            'exploitations' => $this->exploitationsParGouvernant($partie),
            'brasDisponibles' => Effectifs::brasDisponibles($ville, $partie->getCycle()),
            // Embaucher un chef ouvre des postes : sans ce bilan, le joueur
            // voyait son rendement baisser ailleurs sans comprendre que ses
            // bras étaient partis tenir le nouveau bâtiment.
            'mainDoeuvre' => Effectifs::bilan($ville, $partie->getCycle()),
            // Ce qu'un niveau de Quartier ajoute, pour que l'écran dise le
            // remède avec un chiffre plutôt qu'en général.
            'famillesParNiveauDeQuartier' => Population::FAMILLES_PAR_NIVEAU_DE_QUARTIER,
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
            'cleDeLecture' => CleDeLecture::pour($ville, $partie->getCycle()),
            // L'alphabet des scribes : la seconde piste du doc 10, celle des
            // sons. Elle ne se mélange jamais à la clé de lecture, alors même
            // que six dessins leur sont communs.
            'alphabet' => AlphabetDesScribes::pour($ville),
            'prochainSigneDeLAlphabet' => AlphabetDesScribes::prochainSigne($ville),
            'signesDeLAlphabetEnTout' => \count(SigneAlphabetique::cases()),
            'signesParNiveauDAlphabet' => AlphabetDesScribes::SIGNES_PAR_NIVEAU,
            // La leçon fondatrice, mêlée au rendu comme les jetons du
            // déchiffrage : dans l'ordre, elle se lirait dans la source.
            'leconDeNiout' => self::melangerLesSignesDeNiout(),
            'nioutDejaEcrite' => $ville->aEcritNiout(),
            // Le nom de la famille écrit à la manière des musées. La
            // transcription est **entière** dès la Maison des scribes dressée
            // — ce sont les scribes qui écrivent —, et l'écran montre en
            // retrait les signes que la ville n'a pas encore appris : les
            // cacher la rendrait invisible jusqu'au niveau 6 ou 7.
            'nomTranscrit' => TranscriptionDuNom::pour($partie->getFamille()->getNom()),
            'signesConnus' => AlphabetDesScribes::pour($ville),
            'prochainSigne' => CleDeLecture::prochainSigne($ville, $partie->getCycle()),
            'signesEnTout' => \count(SymboleHieroglyphique::cases()),
            'inscription' => $inscription,
            'filRouge' => FilRouge::court($partie) ? FilRouge::acte($partie) : null,
            // Les objectifs sont affichés dès le premier jour (doc 09) : la
            // transparence évite de découvrir tardivement des conditions
            // qu'on n'a pas pu anticiper.
            'mission' => $mission,
            'quete' => $ville->getQueteDeChantier(),
            'objectifs' => array_map(
                static fn (ObjectifDeMission $objectif): array => [
                    'objectif' => $objectif,
                    'avancement' => $objectif->avancement($partie),
                    'atteint' => $objectif->estAtteint($partie),
                ],
                null !== $mission ? ObjectifsDeMission::pour($mission) : [],
            ),
            // Les jetons sont mélangés **au rendu** : les laisser dans l'ordre
            // gravé donnerait la réponse par la seule lecture du HTML.
            'melange' => $inscription instanceof Inscription ? $this->melangerLesSignes($inscription) : [],
            'inscriptionsLues' => \count($ville->inscriptionsDechiffrees()),
            'rival' => $ville->getRival(),
            'prixDeLAccord' => $rivaux->prixDeLAccord($partie),
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
            // **Un onglet, un bâtiment** : les énigmes se rangent là où on les
            // entend, plutôt que toutes dans le panneau des scribes. C'est
            // `Enigme::lieu()` qui décide, pas l'écran.
            'enigmesDesScribes' => $this->enigmesDe($partie, $enigmes, TypeDeBatiment::MaisonDesScribes),
            'enigmesDuTemple' => $this->enigmesDe($partie, $enigmes, TypeDeBatiment::Temple),
            'enigmesDeLAuberge' => $this->enigmesDe($partie, $enigmes, TypeDeBatiment::Auberge),
            'aUneAuberge' => $ville->possede(TypeDeBatiment::Auberge),
            ...$this->donneesDuTemple($partie, $offrandes, $geographie),
            // Sans Nil, il n'y a ni crue ni saison d'inondation (doc 02) : la
            // barre de jeu n'annonce pas une crue dans un désert.
            'connaitLaCrue' => $geographie->connaitLaCrue(),
        ]);
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
                ? \sprintf('%s Vous recevez %d deben.', $verdict['explication'], $verdict['recompense'])
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

            return $this->retourALaVille($request, $partie);
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
        Rivaux $rivaux,
        MissionCatalogue $missions,
        Prospection $prospection,
        EtatDeLaVille $etat,
        GeographieDeLaPartie $geographies,
    ): Response {
        $ville = $partie->getVille();
        $zones = $this->zonesTrieesPourLIsometrie($ville);
        $detaillee = $this->zoneDemandee($zones, $request->query->get('zone'));

        return $this->render('partie/carte.html.twig', [
            'partie' => $partie,
            'ville' => $ville,
            'zones' => $zones,
            'zoneDetaillee' => $detaillee,
            'signaux' => $etat->signaux($partie),
            'connaitLaCrue' => $geographies->connaitLaCrue($partie),
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
            // **On ne propose pas un départ qui ne peut rien rapporter** :
            // tous les témoignages versés, l'émissaire ne ramènerait qu'un
            // « rien appris de neuf » payé trente deben. Même règle que la
            // prospection — le bouton disparaît plutôt que de mentir.
            'peutEnvoyerUnEmissaire' => $detaillee instanceof Zone
                && $detaillee->estDecouverte()
                && !$detaillee->porteLaVille()
                && $ville->possede(TypeDeBatiment::MaisonDesScribes)
                && !$ville->aUneExpeditionVers($detaillee)
                && $enquetes->resteUnTemoignageARecueillir($partie),
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
            // Le prospecteur sonde une case déjà reconnue. On ne propose le
            // départ que si quelque chose peut en sortir : un filon épuisé à
            // rouvrir, ou de la place pour un nouveau que le terrain accepte.
            // Annoncer un départ qui ne peut rien rapporter serait un piège.
            'filonsAProspecter' => $detaillee instanceof Zone && $detaillee->estDecouverte()
                ? $prospection->filonsPossibles($partie, $detaillee)
                : [],
            'peutProspecter' => $detaillee instanceof Zone
                && $detaillee->estDecouverte()
                && !$ville->aUneExpeditionVers($detaillee)
                && [] !== $prospection->filonsPossibles($partie, $detaillee),
            'coutDuProspecteur' => $detaillee instanceof Zone
                ? $explorations->coutVers($partie, $detaillee, RoleDExploration::Prospecteur)
                : RoleDExploration::Prospecteur->cout(),
            'provisionsDuProspecteur' => $detaillee instanceof Zone
                ? $explorations->provisionsVers($partie, $detaillee, RoleDExploration::Prospecteur)
                : RoleDExploration::Prospecteur->provisions(),
            'dureeDuProspecteur' => $detaillee instanceof Zone
                ? $explorations->dureeVers($partie, $detaillee)
                : null,
            // Toutes les cases ne se valent pas : une veine encore exploitée
            // se retrouve à coup sûr, du sable vierge tient du pari. L'écran
            // dit lequel des deux **avant** l'engagement.
            'chancesDeProspecter' => $detaillee instanceof Zone
                ? $prospection->chancesSur($partie, $detaillee)
                : 0,
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

        try {
            $expedition = $explorations->envoyer($partie, $destination, $role);
            $this->addFlash('succes', \sprintf(
                '%s part%s. Il sera sur place dans %d cycle%s.',
                $role->libelle(),
                match ($role) {
                    RoleDExploration::Emissaire => '',
                    RoleDExploration::Prospecteur => ' sonder la case',
                    default => ' en reconnaissance',
                },
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

        return $this->retourALaVille($request, $partie);
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
        $mission = $this->missionDe($partie, $missions);

        return $this->render('partie/commande.html.twig', [
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
     * @return list<string>
     */
    private static function melangerLesConclusions(Enquete $enquete): array
    {
        return self::melanger($enquete->conclusions());
    }

    /**
     * Mélange une liste de propositions pour le rendu : la bonne est toujours
     * la première du catalogue, et se lirait sinon dans la source de la page.
     *
     * @param list<string> $propositions
     *
     * @return list<string>
     */
    private static function melanger(array $propositions): array
    {
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
    /**
     * Les quatre signes de la leçon fondatrice, mêlés **au rendu** — dans
     * l'ordre, la réponse se lirait dans la source de la page. Même parade que
     * pour les jetons du déchiffrage et les propositions d'une énigme.
     *
     * @return list<SigneAlphabetique>
     */
    private static function melangerLesSignesDeNiout(): array
    {
        $signes = LeconDeNiout::SIGNES;
        shuffle($signes);

        return $signes;
    }

    /**
     * Les jetons d'une inscription, mêlés **au rendu** : dans l'ordre gravé,
     * la réponse se lirait dans la source de la page.
     *
     * @return list<SymboleHieroglyphique>
     */
    private function melangerLesSignes(Inscription $inscription): array
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
     * @return array<string, array{type: TypeDeBatiment, niveau: int, lotsMaximum: int, recettes: list<array{recette: Recette, matieres: array<string, int>, realisable: bool, empechement: ?string}>, ordre: ?\App\Entity\OrdreDeFabrication}>
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

            $ateliers[$type->value] = [
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
     * Le récapitulatif des exploitations du territoire, **rangé par bâtiment
     * gouvernant** : les champs au Grenier, les carrières à l'Entrepôt, les
     * pêcheries au Port.
     *
     * Le joueur ne savait pas s'il produisait. Une carrière ouverte, une
     * carrière jamais ouverte et une carrière épuisée se ressemblaient sur la
     * carte, case par case, et rien ne les réunissait — il fallait cliquer
     * chaque case pour faire le compte. Chaque ligne dit donc son état, ce
     * qu'il reste dans le filon, et si elle produit **cette quinzaine**.
     *
     * Les filons dormants et taris y figurent au même titre que ceux en
     * activité : c'est justement ce qu'on cherche à voir.
     *
     * @return array<string, list<array{zone: Zone, ressource: ?Ressource, etat: string, restant: ?int, affectes: int, requis: int, rendement: int, produit: bool}>>
     */
    private function exploitationsParGouvernant(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $equipages = Effectifs::repartirLeTerritoire($ville, $partie->getCycle());
        $recap = [];

        foreach ($ville->getZones() as $zone) {
            if (!$zone->estDecouverte()) {
                continue;
            }

            foreach ($this->exploitationsDe($zone) as $ligne) {
                $ressource = $ligne['ressource'];
                $cle = Effectifs::cleDe($zone, $ressource);
                $equipage = $equipages[$cle] ?? null;

                $recap[Effectifs::batimentGouvernant($ressource)->value][] = [
                    'zone' => $zone,
                    'ressource' => $ressource,
                    'etat' => $ligne['etat'],
                    'restant' => $ligne['restant'],
                    'affectes' => $equipage['affectes'] ?? 0,
                    'requis' => $equipage['requis'] ?? 0,
                    'rendement' => $equipage['rendement'] ?? 0,
                    // Ce qui compte pour le joueur : est-ce que ça rend quelque
                    // chose cette quinzaine ? Une exploitation ouverte sans un
                    // seul bras ne produit rien.
                    'produit' => 'en_activite' === $ligne['etat'] && ($equipage['affectes'] ?? 0) > 0,
                ];
            }
        }

        return $recap;
    }

    /**
     * Ce qu'une case porte d'exploitable — un champ, des filons, ou rien.
     *
     * @return list<array{ressource: ?Ressource, etat: string, restant: ?int}>
     */
    private function exploitationsDe(Zone $zone): array
    {
        $lignes = [];

        if ($zone->porteUnChamp()) {
            $lignes[] = ['ressource' => null, 'etat' => 'en_activite', 'restant' => null];
        }

        foreach ($zone->getGisements() as $gisement) {
            $lignes[] = [
                'ressource' => $gisement->getRessource(),
                'etat' => match (true) {
                    // L'épuisement passe avant tout : un filon tari est fermé
                    // par `Recoltes`, mais il reste sur la carte et se rouvre
                    // par une prospection.
                    $gisement->estEpuise() => 'epuise',
                    $gisement->estExploitee() => 'en_activite',
                    default => 'dormant',
                },
                'restant' => $gisement->getRessource()->estRenouvelable() ? null : $gisement->getQuantiteRestante(),
            ];
        }

        return $lignes;
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
        if ($this->isGranted(User::ROLE_DIVIN)) {
            $onglets[] = ['cle' => 'essai', 'libelle' => 'Essai', 'type' => null, 'batiment' => null];
        }

        return $onglets;
    }

    /**
     * L'état du recrutement, **indexé par type de bâtiment** : chaque panneau
     * de bâtiment y lit sa propre direction. Une liste obligerait le gabarit à
     * la parcourir pour retrouver la sienne.
     *
     * Les trois bâtiments sans spécialité en sont écartés — Résidence
     * familiale, Quartier d'habitation, Auberge : la famille les tient
     * elle-même, leur proposer une annonce n'aurait aucun sens.
     *
     * @return array<string, array{batiment: Building, chefs: list<\App\Entity\Employee>, postesLibres: int, offre: ?\App\Entity\JobOffer}>
     */
    private function directionsDesBatiments(GameSave $partie, Recrutements $recrutements): array
    {
        $ville = $partie->getVille();
        $directions = [];

        foreach ($this->batimentsTriesParLibelle($ville) as $batiment) {
            if ([] === SpecialiteDeChef::pour($batiment->getType())) {
                continue;
            }

            $directions[$batiment->getType()->value] = [
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
    /**
     * Les énigmes qu'on entend dans ce bâtiment-là, prêtes à l'affichage.
     *
     * Les propositions sont mélangées **au rendu**, comme les jetons du
     * déchiffrage : la bonne réponse est toujours la première du catalogue, et
     * se lirait sinon dans la source de la page.
     *
     * @return list<array{enigme: Enigme, propositions: list<string>}>
     */
    private function enigmesDe(GameSave $partie, Enigmes $enigmes, TypeDeBatiment $lieu): array
    {
        return array_values(array_map(
            fn (Enigme $enigme): array => [
                'enigme' => $enigme,
                'propositions' => self::melanger($enigmes->propositionsMontrees($partie, $enigme)),
            ],
            array_filter(
                $enigmes->disponibles($partie),
                static fn (Enigme $enigme): bool => $enigme->lieu() === $lieu,
            ),
        ));
    }

    /**
     * Ce que l'onglet du Temple affiche : le panthéon, ses paliers, et ce qu'il
     * est possible d'offrir.
     *
     * @return array<string, mixed>
     */
    private function donneesDuTemple(GameSave $partie, Offrandes $offrandes, GeographieDeRegion $geographie): array
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
                // Deux manques distincts : un système à venir, et un domaine
                // qui n'existe pas dans cette région. Le second refuse
                // l'offrande, le premier l'accepte.
                'sansDomaineIci' => $divinite->estSansDomaineIci($geographie),
                'attente' => $divinite->attenteDans($geographie),
            ];
        }

        return [
            'pantheon' => $pantheon,
            'aUnTemple' => null !== $temple,
            'niveauDuTemple' => $temple?->getNiveau() ?? 0,
            'divinitesPortables' => Temple::divinitesPortables($ville),
            'plafond' => Temple::plafondDeFaveur($ville),
            'honorees' => $ville->divinitesHonorees(),
            'corbeille' => null !== $temple ? $offrandes->corbeillePour($partie) : [],
            'pointsParOffrande' => Offrandes::POINTS_PAR_OFFRANDE,
            'debenParOffrande' => Offrandes::DEBEN_PAR_OFFRANDE,
            'fete' => $partie->feteEnCours(),
            'pointsDeFete' => Offrandes::POINTS_DE_FETE,
        ];
    }

    private function missionDe(GameSave $partie, MissionCatalogue $missions): ?Mission
    {
        if (!$partie->estCampagne() || null === $partie->getMission()) {
            return null;
        }

        return $missions->get($partie->getMission());
    }
}
