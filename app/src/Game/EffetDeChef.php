<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\Employee;

/**
 * Ce que la compétence d'un chef change réellement (doc 01, doc 03).
 *
 * Le doc 01 pose que « la compétence d'un chef influence la production du
 * bâtiment ». Encore faut-il que le bâtiment produise quelque chose : la
 * plupart n'ont d'effet qu'à partir de la Phase 5. Ce lot ne touche donc que
 * les trois qui produisent déjà — Grenier, Entrepôt et Port par les
 * exploitations qu'ils gouvernent, Marché par ses prix.
 *
 * **La compétence ne crée aucun multiplicateur nouveau.** Elle passe par la
 * *qualité de direction* d'un bâtiment, aux côtés de son effectif — c'est le
 * point à ne pas défaire. Empiler un facteur de plus sur la base ferait
 * retomber la chaîne de production sous le « tout tourne au moins à moitié »
 * que la règle promet, comme le double comptage retiré au lot 4.5.
 *
 * **Un mauvais chef reste meilleur que pas de chef.** Un bâtiment sans
 * personne tourne au plancher de 50 % ; le pire des chefs, une fois son
 * équipe au complet, rend 98 % — presque neutre, jamais punitif. C'est ce qui
 * fait de l'embauche un pari sur le mieux, et non un risque de faire pire que
 * rien.
 */
final readonly class EffetDeChef
{
    /**
     * Bornes du facteur de compétence, en centièmes. **Valeurs inventées** :
     * le doc 03 chiffre les spécialités, jamais la compétence elle-même.
     *
     * De 98 % pour une compétence de 20 à 130 % pour une compétence de 100 —
     * un excellent chef vaut donc un tiers de production de plus qu'un
     * médiocre, du même ordre que l'écart de salaire entre les deux (×3 sur
     * une base faible). L'arbitrage se tient : payer plus cher rapporte, sans
     * que le cinq étoiles écrase tout.
     */
    public const int FACTEUR_PLANCHER = 90;
    public const int FACTEUR_AMPLITUDE = 40;

    /**
     * Ce qu'une spécialité ajoute au facteur, en points de centièmes.
     * Le doc 03 chiffre le Pêcheur à +20 % et le Marché à ±10 % ; le
     * Gestionnaire du Grenier n'est décrit qu'en mots (« perd moins de grain
     * à la conservation »), sa valeur est inventée dans le même ordre.
     */
    public const int BONUS_PECHEUR = 20;
    public const int BONUS_GESTIONNAIRE = 15;
    public const int BONUS_VENDEUR = 10;

    /**
     * Ce qu'une spécialité d'atelier ajoute **sur son propre ouvrage**. Un
     * Brasseur ne fait pas de meilleurs papyrus : le bonus ne s'applique qu'à
     * ce que la spécialité nomme.
     */
    public const int BONUS_DATELIER = 20;

    /**
     * Ce que le Négociateur de l'Entrepôt élargit à la fourchette d'un
     * partenaire, en centièmes du cours (doc 03 : « obtient de meilleurs prix
     * des caravanes »). **Valeur inventée.**.
     */
    public const int BONUS_NEGOCIATEUR = 25;

    /**
     * Ce que le Logisticien retire au trajet, en centièmes (doc 03 :
     * « raccourcit les trajets de caravane »). **Valeur inventée**, bornée à
     * un quart : une route reste une route, et la distance doit continuer de
     * décider de la fréquence des convois.
     */
    public const int RACCOURCI_DU_LOGISTICIEN = 25;

    /**
     * Ce que le Dévot du Temple ajoute à chaque offrande, en points de faveur
     * (doc 03 : « attire davantage la faveur d'une divinité »). **Valeur
     * inventée**, du même ordre qu'une offrande de vingt deben : le chef vaut
     * la peine d'être payé sans rendre les offrandes accessoires.
     */
    public const int BONUS_DU_DEVOT = 5;

    /**
     * Quinzaines de répit qu'un chef **pieux** ajoute au délai de grâce avant
     * qu'un dieu ne se détourne (doc 03, trait « Pieux »). Sa maisonnée
     * entretient les rites quotidiens : la ville oublie ses dieux moins vite.
     */
    public const int REPIT_DUN_CHEF_PIEUX = 5;

    /**
     * Combien de chefs **pieux** la ville emploie, tous bâtiments confondus.
     * Le trait n'est pas une spécialité du Temple : un contremaître dévot
     * fait dire les prières sur son chantier comme ailleurs.
     */
    public static function chefsPieux(City $ville, int $cycle): int
    {
        $pieux = 0;

        foreach ($ville->getEmployes() as $chef) {
            if ($chef->estEnPoste($cycle) && \in_array(TraitDeCandidat::Croyant, $chef->traits(), true)) {
                ++$pieux;
            }
        }

        return $pieux;
    }

    /**
     * La qualité de direction d'un bâtiment, en centièmes : ce que valent
     * ensemble ses bras et la compétence de ceux qui les dirigent.
     *
     * C'est **le seul canal** par lequel un chef agit sur une production. Un
     * bâtiment sans chef vaut son seul rendement d'effectif.
     */
    public static function qualiteDeDirection(
        City $ville,
        TypeDeBatiment $type,
        int $cycle,
        ?Recette $recette = null,
    ): int {
        $rendement = Effectifs::rendementDe($ville, $type, $cycle);
        $facteur = self::facteurDesChefs($ville, $type, $cycle, $recette);

        return intdiv($rendement * $facteur, Effectifs::RENDEMENT_PLEIN);
    }

    /**
     * Le facteur apporté par les chefs en poste d'un bâtiment, spécialités
     * comprises. Cent — neutre — quand il n'y en a aucun.
     *
     * Plusieurs chefs se **moyennent** plutôt que de s'additionner : un
     * bâtiment de haut niveau en emploie jusqu'à trois, et les cumuler ferait
     * du niveau un multiplicateur déguisé, alors qu'il a déjà son propre effet.
     */
    public static function facteurDesChefs(
        City $ville,
        TypeDeBatiment $type,
        int $cycle,
        ?Recette $recette = null,
    ): int {
        $facteurs = [];

        foreach ($ville->chefsDe($type) as $chef) {
            if ($chef->estEnPoste($cycle)) {
                $facteurs[] = self::facteurDe($chef, $recette);
            }
        }

        if ([] === $facteurs) {
            return Effectifs::RENDEMENT_PLEIN;
        }

        return intdiv(array_sum($facteurs), \count($facteurs));
    }

    /**
     * Ce que vaut un chef donné, compétence et spécialité comprises.
     */
    public static function facteurDe(Employee $chef, ?Recette $recette = null): int
    {
        return self::facteurDeCompetence($chef->getCompetence())
            + self::bonusDeSpecialite($chef->getSpecialite(), $recette);
    }

    /**
     * Le chef en poste de ce bâtiment qui porte cette spécialité, s'il existe.
     *
     * Sert aux spécialités dont l'effet ne passe pas par la production — le
     * Négociateur et le Logisticien de l'Entrepôt, qui agissent sur le
     * commerce et non sur ce que le bâtiment fabrique.
     */
    public static function chefSpecialise(City $ville, TypeDeBatiment $type, SpecialiteDeChef $specialite, int $cycle): bool
    {
        foreach ($ville->chefsDe($type) as $chef) {
            if ($chef->estEnPoste($cycle) && $chef->getSpecialite() === $specialite) {
                return true;
            }
        }

        return false;
    }

    /**
     * La compétence, ramenée en centièmes de production.
     *
     * `90 + compétence × 0,4`, soit 98 % à 20 de compétence et 130 % à 100.
     */
    public static function facteurDeCompetence(int $competence): int
    {
        return self::FACTEUR_PLANCHER + intdiv($competence * self::FACTEUR_AMPLITUDE, 100);
    }

    /**
     * Ce que la spécialité ajoute — et seulement pour celles dont l'effet
     * existe déjà. Les autres sont tirées et affichées, mais dorment jusqu'à
     * leur phase (`SpecialiteDeChef::agitDeja()`).
     */
    public static function bonusDeSpecialite(?SpecialiteDeChef $specialite, ?Recette $recette = null): int
    {
        if (null === $specialite) {
            return 0;
        }

        // Une spécialité d'atelier ne vaut que sur son propre ouvrage.
        if (null !== $recette && $specialite->favorise($recette)) {
            return self::BONUS_DATELIER;
        }

        return match ($specialite) {
            SpecialiteDeChef::PortPecheur => self::BONUS_PECHEUR,
            SpecialiteDeChef::GrenierGestionnaire => self::BONUS_GESTIONNAIRE,
            SpecialiteDeChef::MarcheVendeur => self::BONUS_VENDEUR,
            default => 0,
        };
    }
}
