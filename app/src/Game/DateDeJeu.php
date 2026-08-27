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
     * Chaque saison couvre quatre mois, donc huit quinzaines (doc 05). C'est
     * l'échelle sur laquelle les cultures de Perèt mûrissent.
     */
    public const int CYCLES_PAR_SAISON = 8;

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
        /** Rang de la quinzaine dans l'année, de 1 à 25. */
        public int $rangDansLAnnee,
        /** Numéro du mois, de 1 à 12. Null pendant les jours épagomènes. */
        public ?int $numeroDeMois,
        public string $nomDeMois,
        /** Null pendant les jours épagomènes, qui n'appartiennent à aucune saison. */
        public ?Saison $saison,
        /** Rang de la quinzaine dans sa saison, de 1 à 8. Null hors saison. */
        public ?int $rangDansLaSaison,
    ) {
    }

    public static function pourCycle(int $cycle): self
    {
        $rangDansLAnnee = ($cycle - 1) % self::CYCLES_PAR_ANNEE + 1;
        $annee = intdiv($cycle - 1, self::CYCLES_PAR_ANNEE) + 1;

        // Le 25e cycle de l'année est la respiration des jours épagomènes.
        if ($rangDansLAnnee > self::CYCLES_PAR_MOIS * self::MOIS_PAR_ANNEE) {
            return new self($annee, $rangDansLAnnee, null, self::MOIS_EPAGOMENE, null, null);
        }

        $numeroDeMois = intdiv($rangDansLAnnee - 1, self::CYCLES_PAR_MOIS) + 1;

        return new self(
            $annee,
            $rangDansLAnnee,
            $numeroDeMois,
            self::MOIS[$numeroDeMois - 1],
            self::saisonDuMois($numeroDeMois),
            ($rangDansLAnnee - 1) % self::CYCLES_PAR_SAISON + 1,
        );
    }

    public function estJoursEpagomenes(): bool
    {
        return null === $this->saison;
    }

    /**
     * Vrai à la toute première quinzaine d'une année — le moment où la crue de
     * l'année se joue (doc 05).
     */
    public function ouvreUneAnnee(): bool
    {
        return 1 === $this->rangDansLAnnee;
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
