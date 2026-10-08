<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce qu'une quinzaine a changé, dit d'un coup d'œil : ce qui a bougé dans la bourse, les
 * greniers, la population et la renommée, puis le journal rangé par catégorie.
 *
 * Remplace la pile de messages qu'on ne lisait pas : dix lignes d'égale importance ne
 * disent pas si la quinzaine a été bonne. **Les écarts viennent de deux photographies de
 * l'état**, avant et après, et non du texte du journal — ils ne peuvent donc pas diverger
 * de ce que la ville possède vraiment.
 *
 * Le résultat n'est fait que de scalaires et de tableaux : il voyage dans la session le
 * temps d'une redirection (message flash).
 */
final class RecapitulatifDeQuinzaine
{
    /**
     * @return array{deben: int, vivres: int, habitants: int, materiaux: int, renommee: int}
     */
    public function photographier(GameSave $partie): array
    {
        $ville = $partie->getVille();

        return [
            'deben' => $ville->getDeben(),
            'vivres' => $ville->getNourriture(),
            'habitants' => $ville->population(),
            'materiaux' => $ville->getMateriaux(),
            'renommee' => $partie->getFamille()->getRenommee(),
        ];
    }

    /**
     * @param array{deben: int, vivres: int, habitants: int, materiaux: int, renommee: int} $avant
     * @param list<array{categorie: CategorieDEvenement, texte: string}>                    $evenements
     *
     * @return array{
     *     date: string,
     *     ecarts: list<array{icone: string, libelle: string, ecart: int, valeur: int}>,
     *     groupes: list<array{icone: string, libelle: string, textes: list<string>}>
     * }
     */
    public function composer(array $avant, GameSave $partie, array $evenements): array
    {
        $apres = $this->photographier($partie);
        $grandeurs = [
            'deben' => ['deben', 'Deben'],
            'vivres' => ['prestige', 'Vivres'],
            'habitants' => ['habitants', 'Habitants'],
            'materiaux' => ['chantier', 'Matériaux et ouvrages'],
            'renommee' => ['pharaon', 'Renommée'],
        ];

        $ecarts = [];
        foreach ($grandeurs as $cle => [$icone, $libelle]) {
            $ecart = $apres[$cle] - $avant[$cle];
            if (0 !== $ecart) {
                $ecarts[] = ['icone' => $icone, 'libelle' => $libelle, 'ecart' => $ecart, 'valeur' => $apres[$cle]];
            }
        }

        // Dans l'ordre des catégories, pas dans celui des services : un joueur cherche
        // « la santé » ou « le commerce », pas la séquence où le code les résout.
        $groupes = [];
        foreach (CategorieDEvenement::cases() as $categorie) {
            $textes = [];
            foreach ($evenements as $evenement) {
                if ($evenement['categorie'] === $categorie) {
                    $textes[] = $evenement['texte'];
                }
            }
            if ([] !== $textes) {
                $groupes[] = ['icone' => $categorie->icone(), 'libelle' => $categorie->libelle(), 'textes' => $textes];
            }
        }

        return [
            'date' => $partie->dateDeJeu()->libelle(),
            'ecarts' => $ecarts,
            'groupes' => $groupes,
        ];
    }
}
