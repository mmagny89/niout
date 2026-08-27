<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Traduit un numéro de cycle en date du calendrier pharaonique (doc 05).
 *
 * Un cycle vaut une « quinzaine », soit deux semaines. Les mois égyptiens
 * comptant trente jours, un mois vaut exactement deux cycles : l'année fait
 * donc 24 cycles pour ses douze mois, plus un cycle court pour les cinq jours
 * épagomènes, soit 25 cycles.
 *
 * Les noms de mois sont authentiques, attestés notamment à Edfou et au
 * Ramesseum. Leur vocalisation reste conventionnelle, comme pour toute
 * translittération égyptienne — les voyelles n'étaient pas notées.
 */
final readonly class DateDeJeu
{
    public const int CYCLES_PAR_MOIS = 2;
    public const int MOIS_PAR_ANNEE = 12;
    public const int CYCLES_PAR_ANNEE = self::CYCLES_PAR_MOIS * self::MOIS_PAR_ANNEE + 1;

    /**
     * @var list<string> Les douze mois, dans l'ordre
     */
    private const array MOIS = [
        'Tekhi', 'Menhèt', 'Hout-Herou', 'Ka-her-ka',
        'Sef-Bédèt', 'Rekh-Our', 'Rekh-Nedjes', 'Renouèt',
        'Khensou', 'Khent-khéti', 'Ipet-hémèt', 'Oup-Renpèt',
    ];

    /**
     * Les cinq jours « au-dessus de l'année », hors mois et hors saison.
     */
    private const string MOIS_EPAGOMENE = 'Hériou-renpèt';

    private function __construct(
        public int $annee,
        /** Numéro du mois, de 1 à 12. Null pendant les jours épagomènes. */
        public ?int $numeroDeMois,
        public string $nomDeMois,
        /** Null pendant les jours épagomènes, qui n'appartiennent à aucune saison. */
        public ?Saison $saison,
    ) {
    }

    public static function pourCycle(int $cycle): self
    {
        $rangDansLAnnee = ($cycle - 1) % self::CYCLES_PAR_ANNEE + 1;
        $annee = intdiv($cycle - 1, self::CYCLES_PAR_ANNEE) + 1;

        // Le 25e cycle de l'année est la respiration des jours épagomènes.
        if ($rangDansLAnnee > self::CYCLES_PAR_MOIS * self::MOIS_PAR_ANNEE) {
            return new self($annee, null, self::MOIS_EPAGOMENE, null);
        }

        $numeroDeMois = intdiv($rangDansLAnnee - 1, self::CYCLES_PAR_MOIS) + 1;

        return new self(
            $annee,
            $numeroDeMois,
            self::MOIS[$numeroDeMois - 1],
            self::saisonDuMois($numeroDeMois),
        );
    }

    public function estJoursEpagomenes(): bool
    {
        return null === $this->saison;
    }

    /**
     * Formule affichable, du genre « Hout-Herou, an 1 — Akhèt ».
     */
    public function libelle(): string
    {
        if ($this->estJoursEpagomenes()) {
            return \sprintf('%s, an %d', $this->nomDeMois, $this->annee);
        }

        \assert(null !== $this->saison);

        return \sprintf('%s, an %d — %s', $this->nomDeMois, $this->annee, $this->saison->libelle());
    }

    private static function saisonDuMois(int $numeroDeMois): Saison
    {
        return match (true) {
            $numeroDeMois <= 4 => Saison::Akhet,
            $numeroDeMois <= 8 => Saison::Peret,
            default => Saison::Chemou,
        };
    }
}
