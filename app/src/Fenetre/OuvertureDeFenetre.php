<?php

declare(strict_types=1);

namespace App\Fenetre;

use App\Entity\GameSave;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Exception\ExceptionInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * La fenêtre ouverte au-dessus de la carte, telle que l'URL la dit.
 *
 * **L'état de la fenêtre est dans l'URL** : `carte?ouvre=/partie/12/ville?onglet=grenier`.
 * Recharger la page, revenir en arrière ou partager le lien rouvre la fenêtre
 * au même endroit. Le serveur rend alors la carte **avec la fenêtre déjà
 * remplie** — pas de chargement différé, pas de scintillement, et les tests
 * qui lisent la page voient le contenu.
 *
 * Le paramètre vient du visiteur : **on ne le suit jamais sans le valider**.
 * Il doit désigner une page de *cette* partie, qui sait se rendre en cadre
 * (`ROUTES_DE_CADRE`), et rien d'autre — ni un autre site, ni la partie d'un
 * autre joueur, ni la carte elle-même (une fenêtre qui contiendrait la carte
 * qui la contient ne finirait pas).
 *
 * Le contenu est obtenu par une **sous-requête** vers la même application, avec
 * l'en-tête `Turbo-Frame` que le navigateur enverrait : c'est la route de la
 * fenêtre qui décide de ce qu'elle rend, une seule fois, qu'on y arrive par un
 * clic ou par un rechargement.
 */
final readonly class OuvertureDeFenetre
{
    /**
     * Les routes qui savent répondre en cadre. On l'allonge à mesure que les
     * écrans passent en fenêtre.
     */
    public const array ROUTES_DE_CADRE = ['app_partie_ville'];

    public const string CADRE = 'fenetre';

    public function __construct(
        private RouterInterface $routeur,
        private HttpKernelInterface $noyau,
    ) {
    }

    /**
     * Le chemin à ouvrir, validé, ou null s'il n'y a rien à ouvrir — ou rien de
     * sûr.
     */
    public function chemin(Request $requete, GameSave $partie): ?string
    {
        $demande = $requete->query->get('ouvre');

        if (!\is_string($demande) || '' === $demande || \strlen($demande) > 300) {
            return null;
        }

        // Un chemin interne et rien d'autre : pas de schéma, pas de `//` (URL
        // relative au protocole), pas de contrôle, pas de remontée.
        if (1 !== preg_match('#^/partie/'.$partie->getId().'/[A-Za-z0-9_\-/]*(\?[A-Za-z0-9_\-=&%.]*)?$#', $demande)
            || str_contains($demande, '//')
            || str_contains($demande, '..')) {
            return null;
        }

        try {
            $route = $this->routeur->match((string) parse_url($demande, \PHP_URL_PATH));
        } catch (ExceptionInterface) {
            return null;
        }

        return \in_array($route['_route'] ?? null, self::ROUTES_DE_CADRE, true) ? $demande : null;
    }

    /**
     * Le cadre rendu, ou null si la route n'a pas répondu normalement.
     */
    public function rendre(Request $requete, string $chemin): ?string
    {
        $sous = Request::create(
            $requete->getSchemeAndHttpHost().$chemin,
            'GET',
            [],
            $requete->cookies->all(),
            [],
            array_intersect_key($requete->server->all(), array_flip(['REMOTE_ADDR', 'HTTPS', 'HTTP_USER_AGENT'])),
        );
        $sous->headers->set('Turbo-Frame', self::CADRE);

        if ($requete->hasSession()) {
            $sous->setSession($requete->getSession());
        }

        $reponse = $this->noyau->handle($sous, HttpKernelInterface::SUB_REQUEST, false);

        return Response::HTTP_OK === $reponse->getStatusCode() ? (string) $reponse->getContent() : null;
    }

    /**
     * La requête vient-elle d'un cadre de fenêtre ?
     */
    public static function estUneRequeteDeCadre(Request $requete): bool
    {
        return self::CADRE === $requete->headers->get('Turbo-Frame');
    }
}
