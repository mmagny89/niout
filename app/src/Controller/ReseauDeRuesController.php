<?php

declare(strict_types=1);

namespace App\Controller;

use App\Game\EmplacementsDeLaVille;
use App\Game\ReseauDeRues;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * La page de contrôle du réseau de rues : le réseau dessiné **par-dessus** le visuel
 * de la ville, avec les lots et leurs portes (`docs/plan-pnj-ville-vivante.md` § 5).
 *
 * **Développement seulement** (`#[When(env: 'dev')]`) : la classe n'est même pas
 * enregistrée ailleurs, la page n'existe donc ni en test ni en production. Elle sert
 * à une chose qu'aucun test ne voit — une rue qui traverse un bâtiment, un
 * carrefour dans l'eau. À regarder à chaque fois qu'on touche à `ReseauDeRues` ou à
 * `EmplacementsDeLaVille`.
 */
#[When(env: 'dev')]
final class ReseauDeRuesController extends AbstractController
{
    #[Route('/dev/reseau-de-rues', name: 'app_dev_reseau_de_rues', methods: ['GET'])]
    public function index(): Response
    {
        $lots = [];

        foreach (EmplacementsDeLaVille::tous() as $lot) {
            $porte = ReseauDeRues::porteDuLot($lot);
            $lots[] = $lot + ['porte' => $porte, 'porteX' => ReseauDeRues::NOEUDS[$porte][0], 'porteY' => ReseauDeRues::NOEUDS[$porte][1]];
        }

        return $this->render('dev/reseau_de_rues.html.twig', [
            'noeuds' => ReseauDeRues::NOEUDS,
            'aretes' => ReseauDeRues::aretes(),
            'lots' => $lots,
            'largeur' => EmplacementsDeLaVille::LARGEUR,
            'hauteur' => EmplacementsDeLaVille::HAUTEUR,
        ]);
    }
}
