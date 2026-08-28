<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;

/**
 * La masse salariale et son versement, à chaque quinzaine (doc 03, doc 05).
 *
 * C'est la **première charge récurrente en deben du jeu**, à côté de la
 * nourriture — et, avec le calibrage du lot 4.6, la principale : une quinzaine
 * de salaires coûte plus qu'un Grenier. Le poste de dépense central cesse
 * d'être la construction pour devenir l'emploi. Déplacement d'équilibre
 * assumé, et cohérent avec la phase.
 *
 * **Elle bouge toute seule** (demande de la joueuse) : elle se calcule sur la
 * composition réelle des effectifs, jamais sur un forfait. Un enfant qui entre
 * dans la vie active grossit le vivier, donc l'emploi possible ; un départ la
 * fait chuter. Elle se lit à côté de son pendant alimentaire — bras d'un côté,
 * bouches de l'autre —, les deux indicateurs de santé de la ville.
 *
 * **Un impayé s'arrête.** Aucun document ne le disait ; tranché ainsi. La
 * conséquence est à assumer : un poste impayé rend *moins* qu'un poste vacant,
 * qui tourne encore à moitié. Le joueur a donc intérêt à renvoyer un employé
 * qu'il ne peut plus payer plutôt qu'à le laisser en poste — ce qui lui donne
 * une action claire à prendre au lieu d'une spirale subie. Si l'effet paraît
 * pervers en playtest, le levier est de ramener l'impayé à moitié lui aussi.
 *
 * L'unité de paiement est **le bâtiment ou l'exploitation entière**, jamais
 * l'homme : payer trois ouvriers sur quatre ne veut rien dire pour le joueur,
 * qui raisonne en chantiers et en carrières.
 */
final readonly class Salaires
{
    /**
     * Ce que coûte un ouvrier par quinzaine. **Valeur inventée** : le doc 03
     * ne chiffre que les candidats recrutés par offre, et un travailleur n'a
     * ni compétence tirée ni candidature.
     *
     * Un deben, soit le huitième d'un chef moyen : c'est le nombre qui rend
     * l'équipage d'une carrière (2 deben) largement rentable face aux vingt
     * unités qu'elle livre, tout en pesant à l'échelle d'une ville qui en
     * emploie quinze.
     */
    public const int SALAIRE_DUN_TRAVAILLEUR = 1;

    /**
     * Règle la quinzaine : débite ce que la ville peut, et dit qui n'a pas
     * été payé.
     *
     * Les bâtiments passent avant les exploitations, dans le même ordre que la
     * répartition des bras — ce qui se sert en premier se paie en premier.
     */
    public function reglerLaQuinzaine(GameSave $partie): Paie
    {
        $ville = $partie->getVille();
        $cycle = $partie->getCycle();

        $du = $this->detailDesSalaires($ville, $cycle);

        if ([] === $du) {
            return Paie::vide();
        }

        $masse = array_sum($du);
        $bourse = $ville->getDeben();
        $verse = 0;
        $impayes = [];

        foreach ($du as $cle => $montant) {
            if ($montant <= $bourse - $verse) {
                $verse += $montant;
                continue;
            }

            $impayes[] = $cle;
        }

        if ($verse > 0) {
            $ville->debiterRessources([Ressource::Deben->value => $verse]);
        }

        return new Paie(
            masseSalariale: $masse,
            verse: $verse,
            impayes: $impayes,
            messages: $this->raconter($masse, $verse, \count($impayes)),
        );
    }

    /**
     * La masse salariale d'une quinzaine, sans rien débiter — ce que l'écran
     * annonce au joueur.
     */
    public function masseSalariale(City $ville, int $cycle): int
    {
        return array_sum($this->detailDesSalaires($ville, $cycle));
    }

    /**
     * Ce que chaque unité coûte, indexé par sa clé : `batiment:<type>` pour un
     * bâtiment, `x:y:ressource` pour une exploitation.
     *
     * @return array<string, int>
     */
    public function detailDesSalaires(City $ville, int $cycle): array
    {
        $du = [];

        foreach (Effectifs::repartir($ville, $cycle) as $valeur => $ligne) {
            $cout = $ligne['affectes'] * self::SALAIRE_DUN_TRAVAILLEUR;

            foreach ($ville->chefsDe($ligne['batiment']->getType()) as $chef) {
                if ($chef->estEnPoste($cycle)) {
                    $cout += $chef->getSalaire();
                }
            }

            if ($cout > 0) {
                $du['batiment:'.$valeur] = $cout;
            }
        }

        foreach (Effectifs::repartirLeTerritoire($ville, $cycle) as $cle => $ligne) {
            $cout = $ligne['affectes'] * self::SALAIRE_DUN_TRAVAILLEUR;

            if ($cout > 0) {
                $du[$cle] = $cout;
            }
        }

        return $du;
    }

    /**
     * @return list<string>
     */
    private function raconter(int $masse, int $verse, int $impayes): array
    {
        if (0 === $impayes) {
            return [];
        }

        return [\sprintf(
            'Vous deviez %d deben de salaires et n\'en avez versé que %d : %s le travail faute d\'être payée%s.',
            $masse,
            $verse,
            1 === $impayes ? 'une équipe cesse' : \sprintf('%d équipes cessent', $impayes),
            1 === $impayes ? '' : 's',
        )];
    }
}
