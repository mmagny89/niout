<?php

declare(strict_types=1);

namespace App\Game;

use Random\Randomizer;

/**
 * Tire les candidats qui répondent à une offre d'emploi (doc 03).
 *
 * Toutes les valeurs viennent du document — compétence uniforme de 20 à 100,
 * ancienneté de base de 20 quinzaines, taux d'apparition des traits, tirage de
 * la spécialité à parts égales — **sauf la formule du salaire**, qui s'en
 * écarte délibérément : voir `SALAIRE_*`.
 *
 * Comme la carte, le hasard passe par un `Randomizer` injecté, semé en test.
 */
final readonly class GenerateurDeCandidat
{
    public const int COMPETENCE_MIN = 20;
    public const int COMPETENCE_MAX = 100;

    /**
     * Ancienneté probable de base, en quinzaines (doc 03 et doc 05) : une
     * dizaine de mois de jeu avant qu'un départ ne devienne probable.
     */
    public const int ANCIENNETE_DE_BASE = 20;

    /**
     * Salaire de base : `2 + compétence × 0,12`, soit 4 à 14 deben la
     * quinzaine.
     *
     * **Écart assumé au doc 03**, qui pose `5 + compétence × 0,3` — 11 à 35 or.
     * Ce barème plaçait un seul chef au-dessus de tout ce qu'une ville du Delta
     * peut gagner, pour une dotation royale de 50 : le système d'embauche
     * n'aurait jamais été rentable, donc jamais utilisé. L'écart entre un
     * mauvais et un excellent chef reste d'environ ×3 — seule l'échelle change,
     * l'arbitrage demeure. Le document lui-même reconnaît ses valeurs comme
     * « une première proposition cohérente, pas un résultat testé ».
     */
    public const int SALAIRE_PLANCHER = 2;
    public const int SALAIRE_PAR_CENT_DE_COMPETENCE = 12;

    /**
     * Taux d'apparition des traits (doc 03) : 45 % aucun, 40 % un seul,
     * 15 % deux.
     */
    private const int CHANCE_AUCUN_TRAIT = 45;
    private const int CHANCE_UN_TRAIT = 40;

    public function __construct(
        private GenerateurDeFoyer $foyers = new GenerateurDeFoyer(),
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Un candidat pour diriger ce bâtiment. Sa spécialité en dépend ; ses
     * traits, sa compétence et son foyer, non.
     */
    public function pour(TypeDeBatiment $batiment): Candidat
    {
        $competence = $this->hasard->getInt(self::COMPETENCE_MIN, self::COMPETENCE_MAX);
        $traits = $this->tirerLesTraits();
        $foyer = $this->foyers->composer();

        return new Candidat(
            // La compétence et le salaire se calculent l'un et l'autre sur les
            // valeurs de base, puis chaque trait module les deux séparément —
            // c'est l'ordre du doc 03, « avant application des traits ».
            competence: $this->borner($this->moduler($competence, $traits, 'competence')),
            salaire: max(1, $this->moduler($this->salaireDeBase($competence), $traits, 'salaire')),
            ancienneteProbable: max(1, $this->moduler(self::ANCIENNETE_DE_BASE, $traits, 'anciennete')),
            traits: $traits,
            specialite: $this->tirerLaSpecialite($batiment),
            adultes: $foyer['adultes'],
            agesDesEnfants: $foyer['agesDesEnfants'],
        );
    }

    /**
     * Les deux ou trois candidats d'une offre (doc 03).
     *
     * @return list<Candidat>
     */
    public function pourUneOffre(TypeDeBatiment $batiment): array
    {
        $candidats = [];

        for ($i = 0, $combien = $this->hasard->getInt(2, 3); $i < $combien; ++$i) {
            $candidats[] = $this->pour($batiment);
        }

        return $candidats;
    }

    private function salaireDeBase(int $competence): int
    {
        return self::SALAIRE_PLANCHER + intdiv($competence * self::SALAIRE_PAR_CENT_DE_COMPETENCE, 100);
    }

    /**
     * Applique les pourcentages des traits à une valeur de base, en entiers du
     * début à la fin.
     *
     * @param list<TraitDeCandidat> $traits
     */
    private function moduler(int $base, array $traits, string $champ): int
    {
        $pourcentage = 100;

        foreach ($traits as $trait) {
            $pourcentage += match ($champ) {
                'competence' => $trait->effetSurCompetence(),
                'salaire' => $trait->effetSurSalaire(),
                default => $trait->effetSurAnciennete(),
            };
        }

        return intdiv($base * $pourcentage, 100);
    }

    /**
     * La compétence reste dans son échelle quoi qu'en fassent les traits : un
     * « Expérimenté » à 95 ne devient pas 118, le barème d'étoiles n'irait
     * nulle part au-delà de 100.
     */
    private function borner(int $competence): int
    {
        return max(self::COMPETENCE_MIN, min(self::COMPETENCE_MAX, $competence));
    }

    /**
     * @return list<TraitDeCandidat>
     */
    private function tirerLesTraits(): array
    {
        $tirage = $this->hasard->getInt(1, 100);

        if ($tirage <= self::CHANCE_AUCUN_TRAIT) {
            return [];
        }

        $premier = $this->unTraitAuHasard();

        if ($tirage <= self::CHANCE_AUCUN_TRAIT + self::CHANCE_UN_TRAIT) {
            return [$premier];
        }

        // Le second se tire parmi ceux qui restent compatibles : Ambitieux et
        // Fidèle se contrediraient sur l'ancienneté, Travailleur acharné et
        // Économe sur le salaire (doc 03).
        $possibles = array_values(array_filter(
            TraitDeCandidat::cases(),
            static fn (TraitDeCandidat $trait): bool => $trait !== $premier && !$trait->estIncompatibleAvec($premier),
        ));

        // Il en reste toujours au moins six sur huit, mais un candidat à un
        // seul trait vaut mieux qu'une erreur si la table venait à changer.
        if ([] === $possibles) {
            return [$premier];
        }

        return [$premier, $possibles[$this->hasard->getInt(0, \count($possibles) - 1)]];
    }

    private function unTraitAuHasard(): TraitDeCandidat
    {
        $tous = TraitDeCandidat::cases();

        return $tous[$this->hasard->getInt(0, \count($tous) - 1)];
    }

    private function tirerLaSpecialite(TypeDeBatiment $batiment): ?SpecialiteDeChef
    {
        $possibles = SpecialiteDeChef::pour($batiment);

        if ([] === $possibles) {
            return null;
        }

        return $possibles[$this->hasard->getInt(0, \count($possibles) - 1)];
    }
}
