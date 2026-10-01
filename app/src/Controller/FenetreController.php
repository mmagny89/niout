<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\GameSave;
use App\Fenetre\BatimentsDeLaCite;
use App\Fenetre\OuvertureDeFenetre;
use App\Game\GeographieDeLaPartie;
use App\Security\Voter\PartieVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tout ce que la carte ouvre **au-dessus d'elle** : la barre de jeu, qui se
 * recharge seule, et les écrans qui passent en fenêtre.
 *
 * **Un contrôleur à part, et ce n'est pas un détail** : `PartieController`
 * compte plus de deux mille lignes, et y ajouter les routes de la fenêtre avant
 * de l'avoir allégé aggraverait ce que le chantier veut réparer. Voir
 * `docs/plan-fenetres.md`.
 *
 * Chaque route de fenêtre répond de deux façons. **Avec l'en-tête
 * `Turbo-Frame: fenetre`** — le clic d'un lien, la sous-requête d'un
 * rechargement — elle rend le contenu du cadre, seul. **Sans** — l'URL tapée,
 * un lien partagé —, elle renvoie vers la carte avec la fenêtre ouverte :
 * un écran de fenêtre n'existe pas hors de la carte.
 */
#[Route('/partie')]
final class FenetreController extends AbstractController
{
    /**
     * Les écrans à partir desquels le bouton de cycle peut ramener le joueur.
     * Même liste que `PartieController::routeDeRetour()`.
     */
    private const array RETOURS = ['app_partie_carte', 'app_partie_ville', 'app_partie_cite'];

    /**
     * La barre de jeu, rendue seule : c'est ce que la fenêtre recharge après une
     * action, pour que le deben et les réserves disent la vérité sans que la
     * page bouge.
     *
     * Le bouton de cycle a besoin de savoir où ramener le joueur : l'écran, la
     * case, la fenêtre ouverte. La page les passe en paramètres, puisque cette
     * requête ne les connaît pas.
     */
    #[Route('/{id}/barre', name: 'app_partie_barre', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function barre(Request $requete, GameSave $partie, GeographieDeLaPartie $geographies): Response
    {
        $retour = $requete->query->get('retour');
        $onglet = $requete->query->get('onglet');
        $zone = $requete->query->get('zone');
        $ouvre = $requete->query->get('ouvre');

        return $this->render('partie/_barre_cadre.html.twig', [
            'partie' => $partie,
            'connaitLaCrue' => $geographies->connaitLaCrue($partie),
            'retourDuCycle' => \in_array($retour, self::RETOURS, true) ? $retour : 'app_partie_carte',
            'ongletDuCycle' => \is_string($onglet) && 1 === preg_match('/^[a-z_]{1,40}$/', $onglet) ? $onglet : null,
            'zoneDuCycle' => \is_string($zone) && 1 === preg_match('/^\d{1,3}-\d{1,3}$/', $zone) ? $zone : null,
            'ouvreDuCycle' => \is_string($ouvre) ? $ouvre : null,
        ]);
    }

    /**
     * La cité : un carré par bâtiment construit, chacun menant à son onglet.
     */
    #[Route('/{id}/cite', name: 'app_partie_cite', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(PartieVoter::VOIR, subject: 'partie')]
    public function cite(Request $requete, GameSave $partie, BatimentsDeLaCite $cite): Response
    {
        if (!OuvertureDeFenetre::estUneRequeteDeCadre($requete)) {
            return $this->redirectToRoute('app_partie_carte', [
                'id' => $partie->getId(),
                'ouvre' => $this->generateUrl('app_partie_cite', ['id' => $partie->getId()]),
            ]);
        }

        return $this->render('fenetre/cite.html.twig', [
            'partie' => $partie,
            'ville' => $partie->getVille(),
            'batiments' => $cite->pour($partie),
            'enChantier' => $cite->enChantier($partie),
        ]);
    }
}
