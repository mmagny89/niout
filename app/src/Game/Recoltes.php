<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que le territoire verse au stock à chaque quinzaine : l'extraction des
 * gisements et la récolte des champs (doc 01, doc 02, doc 05).
 *
 * Ne persiste rien, comme les chantiers et les expéditions : PassageDeCycle
 * réunit tout en une seule écriture.
 */
final readonly class Recoltes
{
    /**
     * Ce qu'un gisement livre par quinzaine, avant le malus de rareté.
     *
     * Valeur inventée : aucun document ne chiffre le débit d'une carrière. Le
     * doc 01 ne chiffre que la production des bâtiments, et un gisement n'en
     * est pas un.
     */
    public const int EXTRACTION_DE_REFERENCE = 5;

    /**
     * Rareté régionale : `1,0 - 0,05 × difficulté`, plancher à 0,55 (doc 01),
     * exprimée en centièmes pour rester en nombres entiers.
     */
    public static function modificateurDeRareteEnCentiemes(int $difficulte): int
    {
        return max(55, 100 - 5 * $difficulte);
    }

    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        return [
            ...$this->extraire($partie),
            ...$this->moissonner($partie),
        ];
    }

    /**
     * Les carrières et les mines en activité. Un filon qui s'épuise s'arrête de
     * lui-même, sans que le joueur ait à le fermer.
     *
     * @return list<string>
     */
    private function extraire(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $rendu = $this->extractionParGisement($ville->getDifficulte());

        /** @var array<string, int> $recolte */
        $recolte = [];
        $epuises = [];

        foreach ($ville->getZones() as $zone) {
            foreach ($zone->getGisements() as $gisement) {
                if (!$gisement->estExploitee()) {
                    continue;
                }

                $extrait = $gisement->extraire($rendu);
                $valeur = $gisement->getRessource()->value;

                if ($extrait > 0) {
                    $recolte[$valeur] = ($recolte[$valeur] ?? 0) + $extrait;
                }

                if ($gisement->estEpuise()) {
                    $epuises[] = \sprintf(
                        'Le %s (%d, %d) est épuisé.',
                        $gisement->libelle(),
                        $zone->getX(),
                        $zone->getY(),
                    );
                }
            }
        }

        if ([] === $recolte) {
            return $epuises;
        }

        $ville->crediterRessources($recolte);

        return [$this->enoncer('Les gisements ont livré', $recolte), ...$epuises];
    }

    /**
     * La moisson. Sans Grenier, elle n'a nulle part où aller : les champs
     * existent, ils travaillent, mais rien n'entre au stock (doc 01).
     *
     * @return list<string>
     */
    private function moissonner(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $champs = [];

        foreach ($ville->getZones() as $zone) {
            if ($zone->porteUnChamp()) {
                $champs[] = $zone;
            }
        }

        if ([] === $champs) {
            return [];
        }

        if (!$ville->possede(TypeDeBatiment::Grenier)) {
            return ['Vos champs ont donné, mais sans Grenier rien ne se conserve : la récolte est perdue.'];
        }

        $date = $partie->dateDeJeu();
        $parChamp = RendementDesChamps::pourUneQuinzaine($date->saison, $date->rangDansLaSaison, $partie->getCrue());

        if (0 === $parChamp) {
            return [];
        }

        /** @var array<string, int> $recolte */
        $recolte = [];

        foreach ($champs as $zone) {
            $culture = $zone->getCulture();
            \assert(null !== $culture);

            $valeur = $culture->ressource()->value;
            $recolte[$valeur] = ($recolte[$valeur] ?? 0) + $parChamp;
        }

        $ville->crediterRessources($recolte);

        $verbe = Saison::Chemou === $date->saison ? 'La moisson rentre' : 'Les champs ont donné';

        return [$this->enoncer($verbe, $recolte)];
    }

    /**
     * Extraction effective d'un gisement, rareté régionale comprise. Au moins
     * une unité : une mine en activité qui ne rendrait rien n'aurait aucun sens
     * pour le joueur, quelle que soit la dureté de la région.
     */
    private function extractionParGisement(int $difficulte): int
    {
        return max(1, intdiv(
            self::EXTRACTION_DE_REFERENCE * self::modificateurDeRareteEnCentiemes($difficulte),
            100,
        ));
    }

    /**
     * @param array<string, int> $recolte
     */
    private function enoncer(string $verbe, array $recolte): string
    {
        $parts = [];

        foreach ($recolte as $valeur => $quantite) {
            $parts[] = \sprintf('%d %s', $quantite, Ressource::from($valeur)->libelle());
        }

        return \sprintf('%s : %s.', $verbe, implode(', ', $parts));
    }
}
