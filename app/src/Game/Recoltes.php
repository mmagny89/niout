<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que le territoire verse au stock à chaque quinzaine : l'extraction des
 * gisements et la récolte des champs (doc 01, doc 02, doc 05).
 *
 * **Rien ne travaille sans personne** (lot 4.5) : chaque champ semé et chaque
 * gisement en activité réclame son équipage, et rend au prorata de ce qu'il
 * en a — jamais moins de la moitié, la famille s'en occupant elle-même. C'est
 * ce qui fait entrer le territoire dans le système d'emploi : jusqu'au lot
 * 4.5, une carrière rapportait autant à une ville déserte qu'à une ville
 * pourvue, et la moitié de l'économie échappait ainsi aux salaires.
 *
 * Ne persiste rien, comme les chantiers et les expéditions : PassageDeCycle
 * réunit tout en une seule écriture.
 */
final readonly class Recoltes
{
    /**
     * Ce qu'une exploitation livre par quinzaine, avant le malus de rareté.
     *
     * Valeurs inventées : aucun document ne chiffre le débit d'une carrière.
     * Le doc 01 ne chiffre que la production des bâtiments, et un gisement
     * n'en est pas un.
     *
     * **Calibrées à dix unités par ouvrier** (lot 4.6) : deux hommes sur une
     * carrière, un seul sur une barque. C'est l'équipage qui diffère, jamais
     * la productivité d'un homme — sans quoi le choix entre creuser et pêcher
     * se ferait sur un barème arbitraire plutôt que sur la carte.
     */
    public const int EXTRACTION_DE_REFERENCE = 20;
    public const int PECHE_DE_REFERENCE = 10;

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
    /**
     * @return list<string> Ce qui s'est produit, à rapporter au joueur
     *
     * `$humeur` est le malus de mécontentement, en centièmes. Il est
     * **délibérément distinct** du rendement d'effectif : le plancher de 50 %
     * vaut pour le manque de bras, pas pour une ville en colère, qui peut
     * descendre plus bas.
     */
    public function avancerDUnCycle(
        GameSave $partie,
        ?Paie $paie = null,
        int $humeur = Effectifs::RENDEMENT_PLEIN,
    ): array {
        $paie ??= Paie::vide();

        return [
            ...$this->extraire($partie, $paie, $humeur),
            ...$this->moissonner($partie, $paie, $humeur),
        ];
    }

    /**
     * Les carrières et les mines en activité. Un filon qui s'épuise s'arrête de
     * lui-même, sans que le joueur ait à le fermer.
     *
     * @return list<string>
     */
    private function extraire(GameSave $partie, Paie $paie, int $humeur): array
    {
        $ville = $partie->getVille();
        $equipages = Effectifs::repartirLeTerritoire($ville, $partie->getCycle());

        // Deux paniers, pour deux gestes distincts : on creuse une carrière,
        // on ne creuse pas un banc de poisson. Le stock, lui, ne fait pas la
        // différence — seul le message adressé au joueur la fait.
        /** @var array<string, int> $extraction */
        $extraction = [];
        /** @var array<string, int> $peche */
        $peche = [];
        $epuises = [];

        foreach ($ville->getZones() as $zone) {
            foreach ($zone->getGisements() as $gisement) {
                if (!$gisement->estExploitee()) {
                    continue;
                }

                $ressource = $gisement->getRessource();

                // Le rendement de l'équipage s'applique à ce qu'on demande au
                // filon, pas à ce qu'on en tire : deux hommes en moins font
                // creuser moins, ils n'évaporent pas le calcaire déjà remonté.
                // C'est aussi ce qui fait durer plus longtemps un gisement mal
                // tenu, ce qui est juste.
                $cle = Effectifs::cleDe($zone, $ressource);

                // Une équipe qu'on n'a pas payée ne descend pas au fond.
                if ($paie->estImpaye($cle)) {
                    continue;
                }

                $rendement = intdiv(
                    ($equipages[$cle]['rendement'] ?? Effectifs::RENDEMENT_PLANCHER) * $humeur,
                    Effectifs::RENDEMENT_PLEIN,
                );
                $rendu = $this->extractionParGisement($ville->getDifficulte(), $ressource);
                $demande = max(1, intdiv($rendu * $rendement, Effectifs::RENDEMENT_PLEIN));
                $extrait = $gisement->extraire($demande);

                if ($extrait > 0) {
                    if (Ressource::Poisson === $ressource) {
                        $peche[$ressource->value] = ($peche[$ressource->value] ?? 0) + $extrait;
                    } else {
                        $extraction[$ressource->value] = ($extraction[$ressource->value] ?? 0) + $extrait;
                    }
                }

                // **Un filon épuisé se ferme de lui-même**, et le dit une
                // seule fois. Tant qu'il restait « en activité » sur un vide,
                // il retenait son équipage — qui manquait ailleurs — et le
                // message revenait à chaque quinzaine. Le filon reste sur la
                // carte : une prospection peut y retrouver la veine.
                if ($gisement->estEpuise()) {
                    $gisement->fermer();
                    $epuises[] = \sprintf(
                        'Le %s (%d, %d) est épuisé : la carrière ferme, et ses %d bras repassent au service de la ville.',
                        $gisement->libelle(),
                        $zone->getX(),
                        $zone->getY(),
                        $equipages[$cle]['requis'] ?? Effectifs::TRAVAILLEURS_PAR_GISEMENT,
                    );
                }
            }
        }

        $messages = [];

        $perdu = [];

        if ([] !== $extraction) {
            $perdu = $this->fusionner($perdu, $ville->surplusRefuse($extraction));
            $ville->crediterRessources($extraction);
            $messages[] = $this->enoncer('Les gisements ont livré', $extraction);
        }

        if ([] !== $peche) {
            $perdu = $this->fusionner($perdu, $ville->surplusRefuse($peche));
            $ville->crediterRessources($peche);
            $messages[] = $this->enoncer('La pêche a rapporté', $peche);
        }

        return [...$messages, ...$epuises, ...$this->direCeQuiDeborde($perdu)];
    }

    /**
     * La moisson. Sans Grenier, elle n'a nulle part où aller : les champs
     * existent, ils travaillent, mais rien n'entre au stock (doc 01).
     *
     * **Le Grenier pèse sur la récolte par ses champs, pas deux fois.** Le lot
     * 4.4 réduisait en plus ce qu'il conservait, faute d'un autre endroit où
     * la règle mordait. Depuis que le lot 4.5 lui donne les champs à
     * gouverner — leur équipage et leur bonus de niveau —, ce second
     * modificateur faisait payer deux fois le même manque de bras : deux
     * planchers de 50 % qui se multiplient tombent à 25 %, sous le « tout
     * tourne au moins à moitié » que la règle promet. Un seul canal, donc, et
     * c'est le plus riche.
     *
     * @return list<string>
     */
    private function moissonner(GameSave $partie, Paie $paie, int $humeur): array
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

        // Le cycle terrestre avance dans tous les cas — semis, pousse et
        // repos se poursuivent même sans Grenier, seule la conservation de la
        // récolte en dépend. Un champ du Nil n'a pas de compteur propre : sa
        // case reste inchangée, seule la saison le fait avancer.
        foreach ($champs as $zone) {
            $zone->avancerLeCycleAgricole();
        }

        if (!$ville->possede(TypeDeBatiment::Grenier)) {
            return ['Vos champs ont donné, mais sans Grenier rien ne se conserve : la récolte est perdue.'];
        }

        $date = $partie->dateDeJeu();
        $equipages = Effectifs::repartirLeTerritoire($ville, $partie->getCycle());

        /** @var array<string, int> $recolte */
        $recolte = [];

        foreach ($champs as $zone) {
            $culture = $zone->getCulture();
            \assert(null !== $culture);

            $quantite = TypeDeTerrain::Nil === $zone->getTerrain()
                ? RendementDesChamps::pourUneQuinzaine($date->saison, $date->rangDansLaSaison, $partie->getCrue())
                // Le compteur vient d'avancer : la quinzaine qui s'achève est
                // donc celle d'avant, seule pertinente pour ce qui vient de
                // mûrir.
                : CycleAgricoleTerrestre::pourUneQuinzaine(
                    ($zone->getQuinzainesDepuisSemis() ?? 1) - 1,
                    // Osiris ne fait pas rendre plus, il fait revenir plus tôt.
                    EffetDeFaveur::jachereRaccourcie($partie->getVille()),
                );

            if ($quantite <= 0) {
                continue;
            }

            $cle = Effectifs::cleDe($zone, null);

            // Une équipe impayée ne moissonne pas — à la différence d'un champ
            // simplement dépeuplé, que la famille reprend à moitié.
            if ($paie->estImpaye($cle)) {
                continue;
            }

            // Un champ sans bras donne encore, mais moitié moins : la famille
            // le moissonne elle-même.
            $rendement = intdiv(
                ($equipages[$cle]['rendement'] ?? Effectifs::RENDEMENT_PLANCHER) * $humeur,
                Effectifs::RENDEMENT_PLEIN,
            );
            $quantite = intdiv($quantite * $rendement, Effectifs::RENDEMENT_PLEIN);

            if ($quantite <= 0) {
                continue;
            }

            $valeur = $culture->ressource()->value;
            $recolte[$valeur] = ($recolte[$valeur] ?? 0) + $quantite;
        }

        if ([] === $recolte) {
            return [];
        }

        $perdu = $ville->surplusRefuse($recolte);
        $ville->crediterRessources($recolte);

        $verbe = Saison::Chemou === $date->saison ? 'La moisson rentre' : 'Les champs ont donné';

        return [$this->enoncer($verbe, $recolte), ...$this->direCeQuiDeborde($perdu)];
    }

    /**
     * Extraction effective d'un gisement, rareté régionale comprise. Au moins
     * une unité : une mine en activité qui ne rendrait rien n'aurait aucun sens
     * pour le joueur, quelle que soit la dureté de la région.
     */
    private function extractionParGisement(int $difficulte, Ressource $ressource): int
    {
        // La rareté régionale pèse sur ce qu'on arrache au sol, jamais sur ce
        // qu'on tire de l'eau : un banc de poisson n'est pas un filon plus ou
        // moins riche.
        $reference = Ressource::Poisson === $ressource
            ? self::PECHE_DE_REFERENCE
            : intdiv(self::EXTRACTION_DE_REFERENCE * self::modificateurDeRareteEnCentiemes($difficulte), 100);

        return max(1, $reference);
    }

    /**
     * Ce qui n'a pas tenu dans les réserves, dit au joueur.
     *
     * **Un plafond qui fait disparaître une moisson en silence est une règle
     * qu'on subit sans comprendre.** L'écran prévient de la saturation avant
     * qu'elle ne coûte quelque chose ; ce message-ci constate ce qu'elle a
     * déjà coûté.
     *
     * @param array<string, int> $perdu
     *
     * @return list<string>
     */
    private function direCeQuiDeborde(array $perdu): array
    {
        if ([] === $perdu) {
            return [];
        }

        $parts = [];
        foreach ($perdu as $valeur => $quantite) {
            $parts[] = \sprintf('%d %s', $quantite, Ressource::from($valeur)->libelle());
        }

        return [\sprintf(
            'Vos réserves débordent : %s %s faute de place.',
            implode(', ', $parts),
            1 === \count($parts) && 1 === reset($perdu) ? 'se perd' : 'se perdent',
        )];
    }

    /**
     * @param array<string, int> $premier
     * @param array<string, int> $second
     *
     * @return array<string, int>
     */
    private function fusionner(array $premier, array $second): array
    {
        foreach ($second as $valeur => $quantite) {
            $premier[$valeur] = ($premier[$valeur] ?? 0) + $quantite;
        }

        return $premier;
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
