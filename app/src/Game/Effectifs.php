<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Building;
use App\Entity\City;
use App\Entity\Zone;

/**
 * Qui tient les bâtiments, et à quel rendement (doc 01, doc 05).
 *
 * Deux formules viennent du doc 01 :
 *
 * - `nbChefs(niveau) = arrondiSupérieur(niveau / 3)` — porté par
 *   `Building::nombreDeChefs()`, avancé au lot 4.3.
 * - `travailleursParChef(niveau) = travailleursDeBase + arrondiInférieur((niveau - 1) / 3)`.
 *
 * **Ce sont les chefs qui recrutent** (doc 05). Un bâtiment sans chef ne
 * réclame donc aucun travailleur — et c'est ce qui donne sa définition au
 * demi-rendement : sans personne pour l'ouvrir, il tourne à moitié.
 *
 * **Rien ne s'éteint faute d'employés** (décision de la joueuse) : le doc 01
 * ne parlait que de « capacité réduite », jamais de l'arrêt. Une partie sans
 * deben pour embaucher continue de tourner, au ralenti. Les employés cessent
 * ainsi d'être une taxe obligatoire pour devenir un investissement — c'est ce
 * qui rend la phase jouable.
 *
 * ```
 * rendement = 0,5 + 0,5 × (effectif réel / effectif requis)
 * ```
 *
 * Compté **en centièmes**, jamais en flottants : c'est la règle du projet, et
 * elle importe ici plus qu'ailleurs — ce rendement multipliera des quantités
 * de ressources, où un demi perdu à chaque quinzaine finirait par se voir.
 *
 * Rien n'est persisté : tout se dérive de la population, des niveaux et des
 * chefs en poste, comme le niveau maximal régional se dérive de la difficulté.
 */
final readonly class Effectifs
{
    /**
     * Le rendement d'un bâtiment que personne ne tient. Le plancher, jamais
     * zéro.
     */
    public const int RENDEMENT_PLANCHER = 50;
    public const int RENDEMENT_PLEIN = 100;

    /**
     * Les équipages de base du territoire (décision de la joueuse) : un homme
     * par champ, deux par carrière, un par pêcherie — un homme, un bateau.
     *
     * Ils réparent une invraisemblance que la Phase 3 avait laissée passer :
     * une carrière s'exploitait et un champ se moissonnait sans que personne
     * n'y travaille. La moitié de l'économie échappait ainsi au système
     * d'emploi.
     */
    public const int TRAVAILLEURS_PAR_CHAMP = 1;
    public const int TRAVAILLEURS_PAR_GISEMENT = 2;
    public const int TRAVAILLEURS_PAR_PECHERIE = 1;

    /**
     * Ce que chaque niveau du bâtiment gouvernant ajoute au rendement de
     * l'exploitation, en centièmes. **Valeur inventée.**.
     *
     * C'est elle qui referme la boucle du jeu : bâtir plus haut fait produire
     * plus, ce qui permet d'employer davantage, ce qui fait produire plus
     * encore. Et elle donne enfin un effet concret aux niveaux du Grenier, de
     * l'Entrepôt et du Port, qui n'en avaient aucun.
     *
     * Le marché est délibérément à double tranchant : monter le bâtiment
     * **augmente aussi l'équipage réclamé** (`equipageRequis()`). Un niveau
     * gagné sans bras pour le suivre fait donc baisser le rendement avant de
     * le faire monter — c'est le prix de la boucle, pas un défaut.
     */
    public const int BONUS_PAR_NIVEAU_GOUVERNANT = 10;

    /**
     * Combien de travailleurs un chef encadre à ce niveau (doc 01).
     */
    public static function travailleursParChef(TypeDeBatiment $type, int $niveau): int
    {
        return $type->travailleursDeBase() + intdiv($niveau - 1, 3);
    }

    /**
     * Combien de travailleurs ce bâtiment réclame en tout : ce que chacun de
     * ses chefs **en poste** encadre.
     *
     * Un chef embauché mais pas encore à l'ouvrage ne compte pas : il n'a rien
     * recruté tant qu'il n'a pas pris son poste (doc 05).
     */
    public static function travailleursRequis(Building $batiment, int $cycle): int
    {
        return self::chefsEnPoste($batiment, $cycle)
            * self::travailleursParChef($batiment->getType(), $batiment->getNiveau());
    }

    public static function chefsEnPoste(Building $batiment, int $cycle): int
    {
        $enPoste = 0;

        foreach ($batiment->getVille()->chefsDe($batiment->getType()) as $chef) {
            if ($chef->estEnPoste($cycle)) {
                ++$enPoste;
            }
        }

        return $enPoste;
    }

    /**
     * Le rendement d'un bâtiment, en centièmes, selon ce qu'il a réellement de
     * bras face à ce qu'il en réclame.
     */
    public static function rendementEnCentiemes(int $affectes, int $requis): int
    {
        if ($requis <= 0) {
            return self::RENDEMENT_PLANCHER;
        }

        return self::RENDEMENT_PLANCHER + intdiv(
            self::RENDEMENT_PLANCHER * min($affectes, $requis),
            $requis,
        );
    }

    /**
     * Les bras disponibles pour tenir les bâtiments : les actifs de la ville,
     * moins les chefs, qui en font partie et ne s'encadrent pas eux-mêmes.
     */
    public static function brasDisponibles(City $ville, int $cycle): int
    {
        $chefs = 0;

        foreach ($ville->getEmployes() as $chef) {
            if ($chef->estEnPoste($cycle)) {
                ++$chefs;
            }
        }

        return max(0, $ville->getActifs() - $chefs);
    }

    /**
     * Répartit les bras de la ville entre ses bâtiments, et rend pour chacun
     * son effectif et son rendement.
     *
     * **L'ordre de service est alphabétique**, donc stable et explicable, mais
     * arbitraire : le joueur ne peut pas encore dire quel bâtiment servir en
     * premier quand les bras manquent. C'est un manque assumé de ce lot, à
     * reprendre si le playtest montre qu'il coûte des parties.
     *
     * @return array<string, array{batiment: Building, requis: int, affectes: int, rendement: int}>
     *                                                                                              indexé par la valeur du type de bâtiment
     */
    public static function repartir(City $ville, int $cycle): array
    {
        $batiments = array_values($ville->getBatiments()->toArray());
        usort(
            $batiments,
            static fn (Building $a, Building $b): int => $a->getType()->libelle() <=> $b->getType()->libelle(),
        );

        $bras = self::brasDisponibles($ville, $cycle);
        $repartition = [];

        foreach ($batiments as $batiment) {
            $requis = self::travailleursRequis($batiment, $cycle);
            $affectes = min($requis, $bras);
            $bras -= $affectes;

            $repartition[$batiment->getType()->value] = [
                'batiment' => $batiment,
                'requis' => $requis,
                'affectes' => $affectes,
                'rendement' => self::rendementEnCentiemes($affectes, $requis),
            ];
        }

        return $repartition;
    }

    /**
     * Le bâtiment qui gouverne une exploitation : le Grenier pour les champs,
     * l'Entrepôt pour les carrières, le Port pour les pêcheries.
     *
     * Une seule règle pour les trois cas, plutôt qu'un traitement particulier
     * du Port — monter le Port fait pêcher davantage et arme plus de bateaux,
     * exactement comme monter l'Entrepôt fait creuser davantage.
     */
    public static function batimentGouvernant(?Ressource $ressource): TypeDeBatiment
    {
        if (null === $ressource) {
            return TypeDeBatiment::Grenier;
        }

        return Ressource::Poisson === $ressource ? TypeDeBatiment::Port : TypeDeBatiment::Entrepot;
    }

    /**
     * L'équipage de base d'une exploitation. `null` désigne un champ — la
     * seule exploitation qui ne porte pas de ressource extraite.
     */
    public static function equipageDeBase(?Ressource $ressource): int
    {
        if (null === $ressource) {
            return self::TRAVAILLEURS_PAR_CHAMP;
        }

        return Ressource::Poisson === $ressource
            ? self::TRAVAILLEURS_PAR_PECHERIE
            : self::TRAVAILLEURS_PAR_GISEMENT;
    }

    /**
     * L'équipage que réclame une exploitation, une fois compté ce que le
     * niveau de son bâtiment gouvernant y ajoute — même progression que
     * l'encadrement d'un chef, `+1 tous les trois niveaux` (doc 01).
     *
     * Sans le bâtiment gouvernant, l'équipage de base : on creuse toujours,
     * mais sans rien pour organiser le travail.
     */
    public static function equipageRequis(?Ressource $ressource, int $niveauGouvernant): int
    {
        return self::equipageDeBase($ressource) + max(0, intdiv($niveauGouvernant - 1, 3));
    }

    /**
     * Ce que le niveau du bâtiment gouvernant ajoute au rendement, en
     * centièmes. Cent quand il n'est pas dressé : aucun bonus, aucun malus.
     *
     * `$rendementDuBatiment` module ce **bonus**, jamais la base — et c'est ce
     * qui donne enfin un effet au personnel d'un bâtiment (lot 4.4), resté
     * sans emploi depuis que le lot 4.5 a retiré son double comptage sur le
     * stockage. Un Grenier bien tenu fait mieux rendre ses champs ; un Grenier
     * désert les laisse à eux-mêmes.
     *
     * Moduler le bonus plutôt que la base est délibéré : c'est la seule façon
     * de faire compter le personnel d'un bâtiment **sans** multiplier deux
     * planchers de 50 %, ce qui ferait tomber la chaîne à 25 % — sous le
     * « tout tourne au moins à moitié » que la règle promet.
     */
    public static function bonusDeNiveauEnCentiemes(
        int $niveauGouvernant,
        int $rendementDuBatiment = self::RENDEMENT_PLEIN,
    ): int {
        $bonus = self::BONUS_PAR_NIVEAU_GOUVERNANT * max(0, $niveauGouvernant - 1);

        return self::RENDEMENT_PLEIN + intdiv($bonus * $rendementDuBatiment, self::RENDEMENT_PLEIN);
    }

    /**
     * Répartit entre les exploitations du territoire les bras que les
     * bâtiments n'ont pas pris.
     *
     * **Les bâtiments passent avant** : ils abritent et conservent, et une
     * ville dont le Grenier est désert ne garderait rien de ce que ses champs
     * lui donnent. Comme pour les bâtiments, l'ordre à l'intérieur est stable
     * mais arbitraire — le joueur ne choisit pas encore qui servir d'abord.
     *
     * @return array<string, array{zone: Zone, ressource: ?Ressource, requis: int, affectes: int, rendement: int}>
     *                                                                                                             indexé par « x:y:ressource »
     */
    public static function repartirLeTerritoire(City $ville, int $cycle): array
    {
        $bras = self::brasDisponibles($ville, $cycle);

        foreach (self::repartir($ville, $cycle) as $ligne) {
            $bras -= $ligne['affectes'];
        }

        $repartition = [];

        $batiments = self::repartir($ville, $cycle);

        foreach (self::exploitations($ville) as $cle => $exploitation) {
            $gouvernant = self::batimentGouvernant($exploitation['ressource']);
            $niveau = self::niveauDuGouvernant($ville, $exploitation['ressource']);
            $rendementDuGouvernant = $batiments[$gouvernant->value]['rendement'] ?? self::RENDEMENT_PLEIN;
            $requis = self::equipageRequis($exploitation['ressource'], $niveau);
            $affectes = min($requis, max(0, $bras));
            $bras -= $affectes;

            $repartition[$cle] = [
                'zone' => $exploitation['zone'],
                'ressource' => $exploitation['ressource'],
                'requis' => $requis,
                'affectes' => $affectes,
                'rendement' => intdiv(
                    self::rendementEnCentiemes($affectes, $requis)
                        * self::bonusDeNiveauEnCentiemes($niveau, $rendementDuGouvernant),
                    self::RENDEMENT_PLEIN,
                ),
            ];
        }

        return $repartition;
    }

    /**
     * La clé sous laquelle une exploitation se retrouve dans la répartition.
     */
    public static function cleDe(Zone $zone, ?Ressource $ressource): string
    {
        return \sprintf('%d:%d:%s', $zone->getX(), $zone->getY(), null === $ressource ? 'champ' : $ressource->value);
    }

    /**
     * Tout ce qui travaille sur le territoire : les champs semés et les
     * gisements en activité. Un filon dormant ne réclame personne.
     *
     * @return array<string, array{zone: Zone, ressource: ?Ressource}>
     */
    private static function exploitations(City $ville): array
    {
        $zones = array_values($ville->getZones()->toArray());
        usort(
            $zones,
            static fn (Zone $a, Zone $b): int => [$a->getX(), $a->getY()] <=> [$b->getX(), $b->getY()],
        );

        $exploitations = [];

        foreach ($zones as $zone) {
            if ($zone->porteUnChamp()) {
                $exploitations[self::cleDe($zone, null)] = ['zone' => $zone, 'ressource' => null];
            }

            foreach ($zone->getGisements() as $gisement) {
                if ($gisement->estExploitee()) {
                    $ressource = $gisement->getRessource();
                    $exploitations[self::cleDe($zone, $ressource)] = ['zone' => $zone, 'ressource' => $ressource];
                }
            }
        }

        return $exploitations;
    }

    private static function niveauDuGouvernant(City $ville, ?Ressource $ressource): int
    {
        return $ville->batimentDeType(self::batimentGouvernant($ressource))?->getNiveau() ?? 0;
    }

    /**
     * Le rendement d'un bâtiment donné, ou le plancher s'il n'est pas dressé —
     * un bâtiment absent ne produit pas « rien », il n'a simplement pas de
     * rendement à faire valoir, et c'est à l'appelant de vérifier sa présence.
     */
    public static function rendementDe(City $ville, TypeDeBatiment $type, int $cycle): int
    {
        return self::repartir($ville, $cycle)[$type->value]['rendement'] ?? self::RENDEMENT_PLANCHER;
    }
}
