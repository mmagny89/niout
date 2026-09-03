<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;
use Doctrine\ORM\EntityManagerInterface;

/**
 * La vente au Marché : écouler son surplus sur la place, contre des deben
 * (doc 01, doc 08).
 *
 * **À qui vend-on ?** Aux gens de la ville et aux voyageurs qui passent — pas
 * au vaste monde. C'est ce qui distingue le Marché de l'Entrepôt, et ce qui
 * empêche les deux d'être un doublon (décision de la joueuse) :
 *
 * - le **Marché** paie au cours de base, tout de suite, mais son débouché est
 *   borné par la quinzaine (`plafondDeLaQuinzaine()`) : une place ne peut pas
 *   absorber plus que ce que ses habitants et ses passants achètent ;
 * - l'**Entrepôt** et le **Port** vendent loin, à 150 % ou 200 % du cours, en
 *   volumes autrement plus grands — mais il faut ouvrir une route, engager la
 *   marchandise, et attendre le retour du convoi.
 *
 * Le Marché reste ainsi la source de monnaie du début de partie, sans jamais
 * devenir celle de toute la partie : **la vraie richesse passe par les
 * caravanes**. Sans lui, la monnaie n'aurait aucune source — la dotation
 * royale en donne une fois pour toutes, chaque bâtiment en consomme, et toute
 * partie finissait par se figer.
 *
 * C'est aussi ce qui donne enfin un sens à l'exploitation d'un gisement au-delà
 * de la construction : un filon de calcaire devient un revenu.
 *
 * Le Marché a longtemps été la **seule source de renommée branchée** : le
 * doc 13 accorde +1 pour un « gros contrat commercial conclu », et sans lui la
 * renommée serait restée à zéro pour toujours, rendant inertes le prix d'un
 * appel d'habitants et la migration spontanée, tous deux indexés dessus.
 *
 * Il ne l'est plus seul depuis la Phase 9 — énigmes et enquêtes résolues en
 * versent aussi (lot 9.2) —, mais il reste celui qui l'alimente le plus tôt :
 * une ville sans Maison des scribes n'a que lui.
 */
final readonly class Marche
{
    /**
     * À partir de combien de deben une vente est un « gros contrat » au sens du
     * doc 13, qui accorde alors +1 de renommée. **Valeur inventée** : le
     * document nomme le fait, jamais le seuil.
     *
     * Calibrée sur l'économie du Delta, où une vente courante rapporte une
     * poignée de deben : il faut accumuler puis écouler un vrai lot, pas
     * revendre trois roseaux. Les cent points de renommée demandent donc une
     * centaine de gros contrats — c'est l'affaire d'une partie entière, ce qui
     * est le propos du doc 13 : la renommée est un héritage, pas un compteur
     * qu'on remplit en une saison.
     */
    public const int RECETTE_DUN_GROS_CONTRAT = 40;

    /**
     * Ce qu'un habitant absorbe par quinzaine et par niveau de Marché, en
     * deben. **Valeur inventée**, calibrée sur l'ouverture : une ville de dix
     * habitants dotée d'un Marché de niveau 1 écoule quarante deben par
     * quinzaine — exactement un gros contrat, de quoi vivre sans jamais
     * s'enrichir. Monter le Marché et peupler la ville élargissent la place ;
     * s'enrichir vraiment demande les routes commerciales.
     */
    public const int DEBOUCHE_PAR_HABITANT = 4;

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Vend une quantité d'une ressource et crédite les deben correspondants.
     *
     * @return int les deben effectivement encaissés
     *
     * @throws VenteImpossible
     */
    public function vendre(GameSave $partie, Ressource $ressource, int $quantite): int
    {
        $ville = $partie->getVille();

        if (!$ville->possede(TypeDeBatiment::Marche)) {
            throw new VenteImpossible('Il vous faut un Marché pour écouler quoi que ce soit.');
        }

        if ($quantite < 1) {
            throw new VenteImpossible('Il faut vendre au moins une unité.');
        }

        $reste = $this->venteRestante($partie);

        if ($reste < 1) {
            throw new VenteImpossible('La place a fait son plein pour cette quinzaine. Attendez le prochain jour de marché, ou passez par vos routes commerciales.');
        }

        $prix = PrixDuMarche::pour($ressource);

        if (null === $prix) {
            throw new VenteImpossible(\sprintf('Le %s ne se négocie pas : c\'est la monnaie.', $ressource->libelle()));
        }

        // Ce que vaut le Marché : ses bras et la compétence de ceux qui les
        // dirigent (lot 4.8). Un Marché désert écoule à moitié prix — le
        // plancher de 50 % vaut ici comme partout ; un Marché tenu par un bon
        // Vendeur dépasse le plein tarif.
        //
        // **La renommée s'ajoute à ce coefficient, elle n'en pose pas un
        // second** (lot 9.3) : on achète moins cher et l'on vend plus cher à
        // qui l'on connaît, mais deux divisions entières enchaînées perdraient
        // des deben à chaque étape. Une multiplication, une division.
        $coefficient = $this->coefficientDeVente($partie);

        $recette = intdiv($prix * $quantite * $coefficient, Effectifs::RENDEMENT_PLEIN);

        // Le plafond se vérifie **avant** le débit, jamais après : un lot repris
        // au stock repasserait par le plafond de réserve, et un Entrepôt plein
        // le refuserait — le joueur perdrait sa marchandise pour avoir tenté
        // une vente trop grosse.
        if ($recette > $reste) {
            throw new VenteImpossible(\sprintf('La place ne peut plus absorber que %d deben cette quinzaine, et ce lot en vaut %d. Vendez-en moins, ou passez par vos routes commerciales.', $reste, $recette));
        }

        if (!$ville->debiterRessources([$ressource->value => $quantite])) {
            throw new VenteImpossible(\sprintf('Vous n\'avez pas %d %s à vendre.', $quantite, $ressource->libelle()));
        }

        $ville->crediterRessources([Ressource::Deben->value => $recette]);
        $ville->compterUneVenteAuMarche($recette);
        // Ce qui passe par le Marché compte au volume échangé, comme ce qui
        // passe par une caravane (doc 09).
        $ville->compterUnEchange($recette);

        if ($recette >= self::RECETTE_DUN_GROS_CONTRAT) {
            $partie->getFamille()->ajusterRenommee(1);
        }

        $this->entityManager->flush();

        return $recette;
    }

    /**
     * Le jour de marché : tout ce qui dépasse les seuils de garde part à
     * l'étal (`ReserveGardee`).
     *
     * **Pourquoi cette vente automatique existe.** Sans elle, le Marché ne
     * rapportait un deben que si le joueur venait cliquer, ressource par
     * ressource, à chaque quinzaine. Une partie menée normalement — explorer,
     * bâtir, avancer le temps — ne produisait donc **aucune monnaie**, alors
     * que les salaires tombaient à chaque cycle : la caisse ne pouvait que
     * descendre, et la seule stratégie viable était de ne rien entreprendre.
     *
     * **Le joueur déclare ce qu'il garde, pas ce qu'il vend** (décision de la
     * joueuse). « Sur mes cent argiles, j'en veux soixante » : le surplus part,
     * quinzaine après quinzaine, jusqu'à ce que le stock retombe à soixante et
     * s'y tienne. Un plafond de vente aurait obligé à recalculer sa consigne
     * chaque fois que la production change ; un plancher de garde se pose une
     * fois — il dit ce dont on a besoin — et le reste se règle tout seul.
     *
     * **C'est déterministe.** Le surplus part en entier, dans la limite du
     * débouché. Ajouter un tirage par-dessus le débouché — qui varie déjà avec
     * la population et le niveau du Marché — aurait rendu illisible une
     * consigne dont tout l'intérêt est qu'on la pose et qu'on l'oublie.
     *
     * **Ce que ça ne change pas.** Le débouché de la quinzaine borne toujours
     * l'ensemble, ventes à la main comprises : le Marché reste la place d'une
     * ville, jamais le vaste monde. Un gros surplus met donc plusieurs
     * quinzaines à s'écouler, et la vraie richesse continue de passer par les
     * routes commerciales.
     *
     * **Ça ne donne pas de renommée** (décision assumée). Le doc 13 accorde un
     * point pour un « gros contrat conclu » : le commerce de détail quotidien
     * n'en est pas un, et l'accorder ici ferait monter la jauge d'un point par
     * quinzaine sans que le joueur ait rien décidé — cent points en cent
     * cycles, là où le document en fait l'affaire d'une campagne entière.
     * Vendre gros, à la main, reste donc ce qui fait connaître une famille.
     *
     * L'ordre de service est celui des lignes : sur la dernière place du
     * débouché, la première ressource déclarée passe avant.
     *
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function tenirLEtal(GameSave $partie): array
    {
        $ville = $partie->getVille();

        if (!$ville->possede(TypeDeBatiment::Marche)) {
            return [];
        }

        $reste = $this->venteRestante($partie);

        if ($reste < 1 || $ville->getReservesGardees()->isEmpty()) {
            return [];
        }

        $coefficient = $this->coefficientDeVente($partie);
        $recetteTotale = 0;
        $ecoule = [];

        foreach ($ville->getReservesGardees() as $reserve) {
            if ($reste < 1) {
                break;
            }

            $ressource = $reserve->getRessource();
            $prix = PrixDuMarche::pour($ressource);

            if (null === $prix) {
                continue;
            }

            // Ce qui dépasse le seuil, et rien d'autre : en deçà, la ville ne
            // vend jamais. C'est ce que le joueur a réservé à ses chantiers et
            // à ses caravanes.
            $quantite = $reserve->surplusDans($ville);

            if ($quantite < 1) {
                continue;
            }

            // Ce que le débouché restant permet encore d'écouler de cette
            // ressource. On borne **la quantité**, pas la recette : vendre
            // puis découvrir qu'on a dépassé la place ferait perdre la
            // marchandise. Le surplus qui ne passe pas aujourd'hui repassera
            // à la quinzaine suivante — le seuil, lui, ne bouge pas.
            $tenable = intdiv($reste * Effectifs::RENDEMENT_PLEIN, max(1, $prix * $coefficient));
            $quantite = min($quantite, $tenable);

            if ($quantite < 1) {
                continue;
            }

            $recette = intdiv($prix * $quantite * $coefficient, Effectifs::RENDEMENT_PLEIN);

            if ($recette < 1 || !$ville->debiterRessources([$ressource->value => $quantite])) {
                continue;
            }

            $ville->crediterRessources([Ressource::Deben->value => $recette]);
            $ville->compterUneVenteAuMarche($recette);
            $ville->compterUnEchange($recette);

            $reste -= $recette;
            $recetteTotale += $recette;
            $ecoule[] = \sprintf('%d %s', $quantite, $ressource->libelle());
        }

        if ([] === $ecoule) {
            return [];
        }

        $this->entityManager->flush();

        return [\sprintf(
            'Jour de marché : la ville vous a pris %s, pour %d deben.',
            $this->enumerer($ecoule),
            $recetteTotale,
        )];
    }

    /**
     * Ce que vaut une vente au Marché, en centièmes du cours de base : les
     * bras de la place et la compétence de ceux qui les dirigent, plus ce que
     * la renommée de la famille vaut au négoce.
     *
     * **Un seul coefficient, pas deux multiplications enchaînées** : la
     * renommée s'ajoute à la qualité de direction, elle ne pose pas un second
     * facteur. Deux divisions entières perdraient des deben à chaque étape
     * sans que personne sache l'expliquer ensuite.
     */
    private function coefficientDeVente(GameSave $partie): int
    {
        $ville = $partie->getVille();

        $coefficient = EffetDeChef::qualiteDeDirection($ville, TypeDeBatiment::Marche, $partie->getCycle())
            + AvantageDeNegoce::deLaRenommee($partie->getFamille()->getRenommee());

        // La marge que le joueur fait payer à ses propres habitants **est un
        // facteur, pas un terme** : elle multiplie ce que vaut la vente, là où
        // la compétence et la renommée s'additionnent entre elles. Ce sont deux
        // choses différentes — l'une dit ce que le Marché sait tirer d'un lot,
        // l'autre ce qu'on décide d'en demander.
        //
        // Une seule division, comme partout : deux divisions entières
        // enchaînées perdraient des deben à chaque étape.
        return intdiv($coefficient * $ville->getMargeDuMarche(), City::MARGE_DE_BASE);
    }

    /**
     * @param list<string> $elements
     */
    private function enumerer(array $elements): string
    {
        if (count($elements) < 2) {
            return $elements[0] ?? '';
        }

        $dernier = array_pop($elements);

        return implode(', ', $elements).' et '.$dernier;
    }

    /**
     * Ce que la place peut absorber en une quinzaine, en deben : ses habitants
     * et ses passants, autant que le Marché en accueille. Nul sans Marché.
     */
    public static function plafondDeLaQuinzaine(GameSave $partie): int
    {
        $ville = $partie->getVille();
        $marche = $ville->batimentDeType(TypeDeBatiment::Marche);

        if (null === $marche) {
            return 0;
        }

        return $ville->population() * $marche->getNiveau() * self::DEBOUCHE_PAR_HABITANT;
    }

    /**
     * Ce qu'il reste à écouler dans la quinzaine. Exposé pour que l'écran
     * annonce la borne **avant** la vente, plutôt que de la révéler par un
     * refus.
     */
    public function venteRestante(GameSave $partie): int
    {
        return max(0, self::plafondDeLaQuinzaine($partie) - $partie->getVille()->getVenduAuMarche());
    }

    /**
     * Ce que la ville peut mettre en vente : ses lignes de stock non vides qui
     * ont un cours. Le deben en est naturellement exclu : il est la monnaie.
     *
     * @return list<array{ressource: Ressource, quantite: int, prix: int}>
     */
    public function etalPour(GameSave $partie): array
    {
        $etal = [];

        foreach ($partie->getVille()->getStock() as $ligne) {
            $ressource = $ligne->getRessource();
            $prix = PrixDuMarche::pour($ressource);

            if (null === $prix || $ligne->getQuantite() < 1) {
                continue;
            }

            $etal[] = [
                'ressource' => $ressource,
                'quantite' => $ligne->getQuantite(),
                'prix' => $prix,
            ];
        }

        usort($etal, static fn (array $a, array $b): int => $b['prix'] <=> $a['prix']);

        return $etal;
    }
}
