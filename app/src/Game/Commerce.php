<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Convoi;
use App\Entity\GameSave;
use App\Entity\OrdreCommercial;
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
     * Pose un ordre permanent sur une route ouverte : « je vends du lin à 5 »,
     * « j'achète du cèdre jusqu'à 19 ».
     *
     * Rien n'est débité ici : un ordre est une annonce, pas une transaction.
     * Ce sont les convois qui l'exécuteront, au fil des quinzaines (lot 5.7).
     *
     * @throws CommerceImpossible
     */
    public function poserUnOrdre(
        GameSave $partie,
        string $cle,
        Ressource $ressource,
        SensDEchange $sens,
        int $prix,
        int $quantiteParConvoi,
    ): OrdreCommercial {
        $ville = $partie->getVille();
        $route = $ville->routeVers($cle);
        $partenaire = $this->partenaireDe($partie, $cle);

        if (null === $route || null === $partenaire) {
            throw new CommerceImpossible('Aucune route vers cette cité.');
        }

        if (!$route->estOuverte()) {
            throw new CommerceImpossible(\sprintf('Votre %s n\'est pas encore arrivée à %s.', $route->getRoute()->convoi(), $partenaire->nom));
        }

        if (!$partenaire->traite($sens, $ressource)) {
            throw new CommerceImpossible(\sprintf('%s ne %s pas de %s.', $partenaire->nom, SensDEchange::Vendre === $sens ? 'prend' : 'vend', $ressource->libelle()));
        }

        if ($prix < 1 || $quantiteParConvoi < 1) {
            throw new CommerceImpossible('Un ordre porte un prix et une quantité, tous deux d\'au moins un.');
        }

        if (null !== $route->ordrePour($ressource)) {
            throw new CommerceImpossible(\sprintf('Un ordre porte déjà sur le %s vers %s. Retirez-le d\'abord.', $ressource->libelle(), $partenaire->nom));
        }

        $ordre = new OrdreCommercial($route, $ressource, $sens, $prix, $quantiteParConvoi);
        $route->ajouterOrdre($ordre);

        $this->entityManager->persist($ordre);
        $this->entityManager->flush();

        return $ordre;
    }

    /**
     * Retire une annonce. Rien ne se rembourse : un ordre n'a jamais rien
     * coûté.
     */
    public function retirerUnOrdre(OrdreCommercial $ordre): void
    {
        $ordre->getRoute()->retirerOrdre($ordre);

        $this->entityManager->remove($ordre);
        $this->entityManager->flush();
    }

    /**
     * Ce qu'une route ouverte peut porter, dans les deux sens, avec le prix
     * conseillé et l'empressement qu'il produit — de quoi que l'écran montre
     * au joueur l'effet de son prix **avant** qu'il ne le pose.
     *
     * @return list<array{ressource: Ressource, sens: SensDEchange, ordre: ?OrdreCommercial, plancher: int, plafond: int, empressement: int}>
     */
    public function etalDe(GameSave $partie, RouteCommerciale $route): array
    {
        $partenaire = $this->partenaireDe($partie, $route->getPartenaire());

        if (null === $partenaire) {
            return [];
        }

        $avantage = $this->avantageDeNegoce($partie);
        $etal = [];

        foreach ([SensDEchange::Vendre, SensDEchange::Acheter] as $sens) {
            $ressources = SensDEchange::Vendre === $sens ? $partenaire->achete : $partenaire->vend;

            foreach ($ressources as $ressource) {
                $cours = PrixDuMarche::pour($ressource) ?? 1;
                $ordre = $route->ordrePour($ressource);

                [$plancher, $plafond] = SensDEchange::Vendre === $sens
                    ? [$cours, $partenaire->prixMaximumALaVente($ressource, $avantage) ?? $cours]
                    : [$partenaire->prixMinimumALAchat($ressource, $avantage) ?? $cours, intdiv($cours * PartenaireCommercial::PRIX_GENEREUX_A_LACHAT, 100)];

                $etal[] = [
                    'ressource' => $ressource,
                    'sens' => $sens,
                    'ordre' => $ordre,
                    'plancher' => $plancher,
                    'plafond' => max($plancher, $plafond),
                    'empressement' => null === $ordre
                        ? 0
                        : $partenaire->empressement($sens, $ressource, $ordre->getPrix(), $avantage),
                ];
            }
        }

        return $etal;
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
            $partenaire = $this->partenaireDe($partie, $route->getPartenaire());
            $nom = null === $partenaire ? $route->getPartenaire() : $partenaire->nom;

            if ($route->avancerDUnCycle()) {
                $messages[] = \sprintf(
                    'Votre %s atteint %s : la route est ouverte.',
                    $route->getRoute()->convoi(),
                    $nom,
                );

                continue;
            }

            if (!$route->estOuverte() || null === $partenaire) {
                continue;
            }

            // Les retours d'abord : une caravane qui rentre libère la route
            // pour la suivante, dans la même quinzaine.
            $messages = [
                ...$messages,
                ...$this->faireRentrerLesConvois($partie, $route, $nom),
                ...$this->fairePartirLesConvois($partie, $route, $partenaire),
            ];
        }

        return $messages;
    }

    /**
     * Règle les convois qui rentrent : la vente rapporte ses deben, l'achat sa
     * marchandise.
     *
     * @return list<string>
     */
    private function faireRentrerLesConvois(GameSave $partie, RouteCommerciale $route, string $nom): array
    {
        $ville = $partie->getVille();
        $messages = [];

        foreach ($route->getConvois()->toArray() as $convoi) {
            if (!$convoi->avancerDUnCycle()) {
                continue;
            }

            // L'affaire est faite : c'est maintenant qu'elle compte au
            // volume échangé de la mission (lot 8.1), et pas au départ, où la
            // marchandise n'était qu'engagée.
            $ville->compterUnEchange($convoi->valeur());

            if (SensDEchange::Vendre === $convoi->getSens()) {
                $ville->crediterRessources([Ressource::Deben->value => $convoi->valeur()]);
                $messages[] = \sprintf(
                    'Votre %s revient de %s : %d %s vendus, %d deben en caisse.',
                    $route->getRoute()->convoi(),
                    $nom,
                    $convoi->getQuantite(),
                    $convoi->getRessource()->libelle(),
                    $convoi->valeur(),
                );
            } else {
                $livraison = [$convoi->getRessource()->value => $convoi->getQuantite()];
                $perdu = $ville->surplusRefuse($livraison);
                $ville->crediterRessources($livraison);

                $messages[] = \sprintf(
                    'Un %s de %s décharge %d %s.',
                    $route->getRoute()->convoi(),
                    $nom,
                    $convoi->getQuantite(),
                    $convoi->getRessource()->libelle(),
                );

                if ([] !== $perdu) {
                    $messages[] = \sprintf(
                        'Vos réserves débordent : %d %s se perdent faute de place.',
                        array_sum($perdu),
                        $convoi->getRessource()->libelle(),
                    );
                }
            }

            // Le convoi reste en place, rentré : il repartira chargé à neuf
            // (`fairePartirLesConvois`) ou sera défait faute d'ordre.
        }

        return $messages;
    }

    /**
     * Charge et fait partir ce que les ordres permettent.
     *
     * **Tout est débité au départ** : la marchandise pour une vente, les deben
     * pour un achat. C'est ce qui fait de l'aller un engagement, et non une
     * intention qu'on pourrait défaire en vendant au Marché entre-temps.
     *
     * @return list<string>
     */
    private function fairePartirLesConvois(
        GameSave $partie,
        RouteCommerciale $route,
        PartenaireCommercial $partenaire,
    ): array {
        $ville = $partie->getVille();
        $niveau = $ville->batimentDeType($partenaire->route->batiment())?->getNiveau() ?? 0;
        // Un rival installé sur cette route prend sa part de ce qui passe
        // (doc 08). Il rogne le volume, il n'interdit rien.
        $volume = $this->apresLeRival($partie, $route->getPartenaire(), $partenaire->volumeParConvoi($niveau));
        $avantage = $this->avantageDeNegoce($partie);
        $trajet = $this->trajetVers($partenaire, $ville, $partie->getCycle());
        $messages = [];

        $recharges = [];

        foreach ($route->getOrdres() as $ordre) {
            $ressource = $ordre->getRessource();
            $enPlace = $route->convoiPour($ressource);

            // Un seul convoi en chemin par ressource : la caravane doit
            // revenir avant que la suivante ne parte.
            if (null !== $enPlace && !$enPlace->estRentre()) {
                continue;
            }

            $quantite = $this->quantiteAuDepart($ville, $partenaire, $ordre, $volume, $avantage);

            if ($quantite < 1) {
                continue;
            }

            $recharges[$ressource->value] = true;

            if (SensDEchange::Vendre === $ordre->getSens()) {
                $ville->debiterRessources([$ressource->value => $quantite]);
            } else {
                $ville->debiterRessources([Ressource::Deben->value => $quantite * $ordre->getPrix()]);
            }

            if (null !== $enPlace) {
                $enPlace->repartir($quantite, $ordre->getPrix(), $trajet);
            } else {
                $convoi = new Convoi(
                    $route,
                    $ressource,
                    $ordre->getSens(),
                    $quantite,
                    $ordre->getPrix(),
                    $trajet,
                );
                $route->ajouterConvoi($convoi);
                $this->entityManager->persist($convoi);
            }

            $messages[] = \sprintf(
                'Un %s part pour %s : %d %s %s.',
                $route->getRoute()->convoi(),
                $partenaire->nom,
                $quantite,
                $ressource->libelle(),
                SensDEchange::Vendre === $ordre->getSens() ? 'à vendre' : 'à quérir',
            );
        }

        // Les caravanes rentrées que rien ne recharge sont défaites : l'ordre
        // a été retiré, ou la ville n'a plus de quoi l'honorer.
        foreach ($route->getConvois()->toArray() as $convoi) {
            if ($convoi->estRentre() && !isset($recharges[$convoi->getRessource()->value])) {
                $route->retirerConvoi($convoi);
                $this->entityManager->remove($convoi);
            }
        }

        return $messages;
    }

    /**
     * Ce qu'un convoi peut réellement emporter : le moins de ce que le joueur
     * autorise, de ce que le convoi porte, de ce que l'empressement du
     * partenaire consent, et de ce que la ville a les moyens d'engager.
     */
    private function quantiteAuDepart(
        \App\Entity\City $ville,
        PartenaireCommercial $partenaire,
        OrdreCommercial $ordre,
        int $volume,
        int $avantage = 0,
    ): int {
        $empressement = $partenaire->empressement($ordre->getSens(), $ordre->getRessource(), $ordre->getPrix(), $avantage);

        if ($empressement <= 0) {
            return 0;
        }

        $quantite = min(
            $ordre->getQuantiteParConvoi(),
            intdiv($volume * $empressement, 100),
        );

        // Ni le stock ni la bourse ne descendent sous zéro : un ordre
        // permanent ne vide jamais la ville sans prévenir.
        return SensDEchange::Vendre === $ordre->getSens()
            ? min($quantite, $ville->quantite($ordre->getRessource()))
            : min($quantite, intdiv($ville->getDeben(), max(1, $ordre->getPrix())));
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
                'volume' => $this->apresLeRival($partie, $partenaire->cle, $partenaire->volumeParConvoi($niveau)),
                'realisable' => null === $empechement,
                'empechement' => $empechement,
            ];
        }

        return $offre;
    }

    /**
     * Tout ce qui élargit la fourchette d'un partenaire : le Négociateur et la
     * réputation de la famille, plafonnés ensemble (`AvantageDeNegoce`).
     *
     * **C'est le seul point d'entrée du commerce.** La renommée ne pose pas son
     * propre coefficient : elle s'ajoute ici, dans un facteur qui existait
     * déjà, et le plafond porte sur la somme — trois plafonds séparés se
     * cumulent et n'en plafonnent aucun (arbitrage 9.0).
     */
    public function avantageDeNegoce(GameSave $partie): int
    {
        return AvantageDeNegoce::total(
            $partie->getFamille()->getRenommee(),
            $this->avantageDuNegociateur($partie->getVille(), $partie->getCycle()),
        );
    }

    /**
     * Ce qu'un **Négociateur** en poste à l'Entrepôt arrache aux partenaires
     * (doc 03) : une fourchette plus large, des deux côtés.
     */
    public function avantageDuNegociateur(\App\Entity\City $ville, int $cycle): int
    {
        return EffetDeChef::chefSpecialise($ville, TypeDeBatiment::Entrepot, SpecialiteDeChef::EntrepotNegociateur, $cycle)
            ? EffetDeChef::BONUS_NEGOCIATEUR
            : 0;
    }

    /**
     * Le trajet réel vers ce partenaire, raccourci par un **Logisticien** en
     * poste à l'Entrepôt (doc 03).
     *
     * **Jamais moins d'une quinzaine** : une route reste une route, et la
     * distance doit continuer de décider de la fréquence des convois — c'est
     * elle qui fait qu'une cité lointaine commerce rarement.
     */
    /**
     * Ce qui reste du volume une fois le rival servi. **Jamais moins d'une
     * unité tant que la route porte quelque chose** : un rival gêne, il ne
     * ferme pas une route.
     */
    private function apresLeRival(GameSave $partie, string $partenaire, int $volume): int
    {
        $malus = Rivaux::malusSur($partie, $partenaire);

        if (0 === $malus) {
            return $volume;
        }

        return max(1, $volume - intdiv($volume * $malus, 100));
    }

    public function trajetVers(PartenaireCommercial $partenaire, \App\Entity\City $ville, int $cycle): int
    {
        $distance = $partenaire->distanceEnQuinzaines;

        // Deux raccourcis possibles, et ils s'additionnent au lieu de se
        // composer : le Logisticien connaît les relais, Sobek veille sur ce qui
        // va par l'eau — et lui seul, une piste caravanière ne le regarde pas.
        $raccourci = EffetDeFaveur::raccourciDeSobek($ville, $partenaire->route);

        if (EffetDeChef::chefSpecialise($ville, TypeDeBatiment::Entrepot, SpecialiteDeChef::EntrepotLogisticien, $cycle)) {
            $raccourci += EffetDeChef::RACCOURCI_DU_LOGISTICIEN;
        }

        // Jamais sous une quinzaine : une route reste une route, et c'est la
        // distance qui décide de la fréquence des convois.
        return max(1, $distance - intdiv($distance * $raccourci, 100));
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
