<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Tout ce que la ville a lancé et qui n'est pas encore rentré, en une seule
 * liste (décision de la joueuse au playtest).
 *
 * **Le problème qu'il résout.** Ce qui est en route était éparpillé : les
 * chantiers sur la Résidence, l'ouvrage de l'Atelier dans son onglet, celui de
 * la Forge dans le sien, les expéditions sur la carte, les convois sous
 * l'Entrepôt, les présents du roi nulle part. Pour savoir ce qu'il restait à
 * attendre avant d'avancer d'une quinzaine, il fallait ouvrir six panneaux et
 * s'en souvenir.
 *
 * **Ce qu'il n'est pas.** Il ne remplace aucun de ces panneaux et ne porte
 * aucune action : chaque chose garde son écran, avec son détail et ses
 * boutons. Celui-ci ne répond qu'à une question — *qu'est-ce que j'attends, et
 * pour combien de temps ?* — et il faut qu'elle se lise d'un coup d'œil.
 *
 * **Tout est ramené à des quinzaines restantes**, et trié du plus proche au
 * plus lointain : c'est la seule grandeur qui permette de comparer un chantier,
 * un convoi et une caravane. Ce qui aboutit à la prochaine quinzaine se lit en
 * haut.
 *
 * Rien n'est persisté : la liste se déduit de l'état, comme le carnet de
 * contacts ou l'acte d'un fil rouge.
 */
final readonly class TravauxEnCours
{
    public function __construct(
        private Commerce $commerce,
    ) {
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    public function pour(GameSave $partie): array
    {
        $travaux = [
            ...$this->chantiers($partie),
            ...$this->ateliers($partie),
            ...$this->expeditions($partie),
            ...$this->convois($partie),
            ...$this->presents($partie),
        ];

        // Du plus proche au plus lointain : ce qui aboutit à la prochaine
        // quinzaine est ce que le joueur veut voir en premier.
        usort($travaux, static fn (array $a, array $b): int => $a['quinzaines'] <=> $b['quinzaines']);

        return $travaux;
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    private function chantiers(GameSave $partie): array
    {
        $travaux = [];

        foreach ($partie->getVille()->getChantiers() as $chantier) {
            $travaux[] = [
                'categorie' => 'Chantier',
                'libelle' => $chantier->getType()->libelle(),
                'detail' => $chantier->estUneAmelioration()
                    ? \sprintf('vers le niveau %d', $chantier->getNiveauVise())
                    : 'fondation',
                'quinzaines' => $chantier->cyclesRestants(),
            ];
        }

        return $travaux;
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    private function ateliers(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $travaux = [];

        foreach (Recette::batimentsQuiFabriquent() as $type) {
            $ordre = $ville->ordreDeFabricationDe($type);

            if (null === $ordre) {
                // Une consigne à l'arrêt n'est pas un travail en cours, mais
                // c'est exactement ce qu'on veut voir ici : un atelier qui ne
                // produit plus sans qu'on l'ait décidé.
                $consigne = $ville->consigneDeFabricationDe($type);

                if (null !== $consigne && $consigne->estEnAttenteDeMatieres()) {
                    $travaux[] = [
                        'categorie' => $type->libelle(),
                        'libelle' => $consigne->getRecette()->libelle(),
                        'detail' => 'à l\'arrêt, faute de matières',
                        'quinzaines' => 0,
                    ];
                }

                continue;
            }

            $travaux[] = [
                'categorie' => $type->libelle(),
                'libelle' => $ordre->getRecette()->libelle(),
                'detail' => \sprintf('%d pièces à l\'achèvement', $ordre->piecesAttendues()),
                'quinzaines' => $ordre->cyclesRestants(),
            ];
        }

        return $travaux;
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    private function expeditions(GameSave $partie): array
    {
        $travaux = [];

        foreach ($partie->getVille()->getExpeditions() as $expedition) {
            $travaux[] = [
                'categorie' => 'Expédition',
                'libelle' => $expedition->getRole()->libelle(),
                'detail' => \sprintf(
                    'vers la case %d, %d',
                    $expedition->getDestination()->getX(),
                    $expedition->getDestination()->getY(),
                ),
                'quinzaines' => $expedition->cyclesRestants(),
            ];
        }

        return $travaux;
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    private function convois(GameSave $partie): array
    {
        $travaux = [];

        foreach ($partie->getVille()->getRoutesCommerciales() as $route) {
            $partenaire = $this->commerce->partenaireDe($partie, $route->getPartenaire());

            foreach ($route->getConvois() as $convoi) {
                $travaux[] = [
                    'categorie' => 'Convoi',
                    'libelle' => $partenaire->nom ?? $route->getPartenaire(),
                    'detail' => \sprintf(
                        '%s %d %s',
                        SensDEchange::Vendre === $convoi->getSens() ? 'vend' : 'achète',
                        $convoi->getQuantite(),
                        $convoi->getRessource()->libelle(),
                    ),
                    'quinzaines' => $convoi->getQuinzainesAvantRetour(),
                ];
            }
        }

        return $travaux;
    }

    /**
     * @return list<array{categorie: string, libelle: string, detail: string, quinzaines: int}>
     */
    private function presents(GameSave $partie): array
    {
        $travaux = [];

        foreach ($partie->getVille()->getPresentsRoyaux() as $present) {
            $travaux[] = [
                'categorie' => 'Présent du roi',
                'libelle' => \sprintf('%d %s', $present->getQuantite(), $present->getRessource()->libelle()),
                'detail' => \sprintf('pour %s', $present->getChantier()),
                'quinzaines' => $present->getQuinzainesAvantArrivee(),
            ];
        }

        return $travaux;
    }
}
