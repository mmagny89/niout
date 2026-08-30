<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\RouteCommerciale;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ouvrir une route, et l'attendre (doc 12).
 *
 * **Ouvrir, c'est envoyer une première caravane** (décision de la joueuse) :
 * on paie, le convoi part, et la route n'est ouverte qu'à son arrivée. Le
 * geste déclare à une cité qu'on est prêt à commercer — ce qu'on lui vendra
 * et lui achètera se règle ensuite (lot 5.6).
 *
 * **Le bâtiment décide de ce qu'on peut ouvrir** (doc 12) : l'Entrepôt arme
 * les caravanes, le Port les navires. Une ville sans quai ne commerce que par
 * la piste — ce qui donne à la géographie un poids de plus, une ville
 * intérieure n'ayant simplement pas accès à la mer.
 *
 * Ce que le partenaire vend, achète et à quel prix vit dans le contenu
 * (`CataloguePartenaires`, `PartenaireCommercial`) : rien de tout cela n'est
 * persisté, seule la clé de la route l'est.
 */
final readonly class Commerce
{
    public function __construct(
        private CataloguePartenaires $partenaires,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Engage l'ouverture d'une route : débite le coût, met la caravane en
     * chemin.
     *
     * @throws CommerceImpossible
     */
    public function ouvrir(GameSave $partie, string $cle): RouteCommerciale
    {
        $ville = $partie->getVille();
        $partenaire = $this->partenaireDe($partie, $cle);

        if (null === $partenaire) {
            throw new CommerceImpossible('Cette cité n\'est pas à votre portée.');
        }

        if (null !== $ville->routeVers($cle)) {
            throw new CommerceImpossible(\sprintf('Une route vers %s est déjà engagée.', $partenaire->nom));
        }

        $batiment = $partenaire->route->batiment();

        if (!$ville->possede($batiment)) {
            throw new CommerceImpossible(\sprintf('Il vous faut %s %s pour armer %s.', TypeDeBatiment::Forge === $batiment ? 'une' : 'un', $batiment->libelle(), 'caravane' === $partenaire->route->convoi() ? 'une caravane' : 'un navire'));
        }

        $cout = $partenaire->route->coutDOuverture();

        if (!$ville->debiterRessources([Ressource::Deben->value => $cout])) {
            throw new CommerceImpossible(\sprintf('Ouvrir cette route demande %d deben ; il vous en manque %d.', $cout, $cout - $ville->getDeben()));
        }

        $route = new RouteCommerciale($ville, $cle, $partenaire->route, $partenaire->distanceEnQuinzaines);
        $ville->ajouterRouteCommerciale($route);

        $this->entityManager->persist($route);
        $this->entityManager->flush();

        return $route;
    }

    /**
     * Rapproche les caravanes en chemin, et annonce celles qui arrivent.
     *
     * Ne persiste rien : `PassageDeCycle` réunit tout en une seule écriture.
     *
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $messages = [];

        foreach ($partie->getVille()->getRoutesCommerciales() as $route) {
            if (!$route->avancerDUnCycle()) {
                continue;
            }

            $partenaire = $this->partenaireDe($partie, $route->getPartenaire());

            $messages[] = \sprintf(
                'Votre %s atteint %s : la route est ouverte.',
                $route->getRoute()->convoi(),
                null === $partenaire ? $route->getPartenaire() : $partenaire->nom,
            );
        }

        return $messages;
    }

    /**
     * Ce que la ville peut ouvrir, et ce qui l'en empêche — de quoi remplir
     * l'écran sans que le gabarit ait à recalculer.
     *
     * @return list<array{partenaire: PartenaireCommercial, route: ?RouteCommerciale, cout: int, volume: int, realisable: bool, empechement: ?string}>
     */
    public function offrePour(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $offre = [];

        foreach ($this->partenairesDe($partie) as $partenaire) {
            $batiment = $partenaire->route->batiment();
            $niveau = $ville->batimentDeType($batiment)?->getNiveau() ?? 0;
            $cout = $partenaire->route->coutDOuverture();

            $empechement = match (true) {
                0 === $niveau => \sprintf('Il vous faut un %s.', $batiment->libelle()),
                $ville->getDeben() < $cout => \sprintf('Il vous manque %d deben.', $cout - $ville->getDeben()),
                default => null,
            };

            $offre[] = [
                'partenaire' => $partenaire,
                'route' => $ville->routeVers($partenaire->cle),
                'cout' => $cout,
                'volume' => $partenaire->volumeParConvoi($niveau),
                'realisable' => null === $empechement,
                'empechement' => $empechement,
            ];
        }

        return $offre;
    }

    /**
     * Les cités à portée de cette partie. Le mode Aventure n'a pas de mission,
     * donc pas de routes attestées : Memphis commercera quand la Phase 11 lui
     * en écrira.
     *
     * @return list<PartenaireCommercial>
     */
    public function partenairesDe(GameSave $partie): array
    {
        $mission = $partie->getMission();

        return null === $mission ? [] : $this->partenaires->pourLaMission($mission);
    }

    private function partenaireDe(GameSave $partie, string $cle): ?PartenaireCommercial
    {
        $mission = $partie->getMission();

        return null === $mission ? null : $this->partenaires->partenaire($mission, $cle);
    }
}
