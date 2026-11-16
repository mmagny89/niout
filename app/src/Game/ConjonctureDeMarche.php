<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce que le monde fait aux marchés, quinzaine après quinzaine (`docs/plan-pnj-palier-a.md`
 * § 3 et 4).
 *
 * **Une fonction pure de `(graine, cité, marchandise, quinzaine)`** : aucune ligne en base,
 * aucun tirage au sort, et n'importe quelle quinzaine se rejoue à l'identique — c'est ce qui
 * la rend testable, et la prévoit (le Négociateur « voit loin »). La règle du projet, *un
 * état qui se déduit ne se stocke pas*, tranche l'architecture.
 *
 * Trois couches, sommées puis bornées :
 *  - **la météo** : un bruit de valeur sur un maillage de quatre quinzaines, interpolé
 *    linéairement. Centré sur zéro, donc elle ne dérive jamais ;
 *  - **la saison et la crue**, sur les seuls vivres : une cité regorge à la récolte, et le
 *    fleuve entier suit la crue — le joueur subit chez lui ce qu'il retrouve ailleurs ;
 *  - **un événement** (`EvenementDeMarche`), tiré **par fenêtre** de douze quinzaines : on ne
 *    rejoue jamais l'historique pour connaître le présent.
 *
 * Le total est borné à ± `AMPLITUDE` : un monde qui pèserait plus que la renommée pleine
 * écraserait ce que le joueur a bâti. Le plafond de 40 d'`AvantageDeNegoce` reste, lui, le
 * seul arbitre de la somme.
 *
 * **Valeurs inventées**, listées au § 12 du plan : à calibrer sur parties réelles.
 */
final readonly class ConjonctureDeMarche
{
    public const int AMPLITUDE = 20;

    /** Longueur d'une fenêtre d'événement, en quinzaines. */
    public const int FENETRE = 12;

    /** Un événement sur deux fenêtres, en moyenne, par cité. */
    private const int FENETRES_AVEC_EVENEMENT_SUR = 2;

    public const int DUREE_MIN = 2;
    public const int DUREE_MAX = 6;

    /** Maillage du bruit de fond, en quinzaines. */
    private const int PAS_DE_LA_METEO = 4;

    /** Amplitude du bruit de fond : ± ce nombre. */
    public const int AMPLITUDE_DE_LA_METEO = 10;

    /** Ce que pèse la saison, et la crue, sur les vivres. */
    public const int POIDS_DE_LA_SAISON = 3;
    public const int POIDS_DE_LA_CRUE = 4;

    public function pour(GameSave $partie, PartenaireCommercial $partenaire, Ressource $ressource, ?int $cycle = null): Conjoncture
    {
        return $this->calculer(
            $partie->getGraine(),
            $partie->getCrue(),
            $partenaire,
            $ressource,
            $cycle ?? $partie->getCycle(),
        );
    }

    public function calculer(
        int $graine,
        QualiteDeCrue $crue,
        PartenaireCommercial $partenaire,
        Ressource $ressource,
        int $cycle,
    ): Conjoncture {
        if (!$partenaire->acheteDe($ressource) && !$partenaire->vendDe($ressource)) {
            return new Conjoncture(0, 0, 0, null, 0);
        }

        $meteo = $this->meteo($graine, $partenaire->cle, $ressource, $cycle);
        $saisonEtCrue = $this->saisonEtCrue($crue, $partenaire, $ressource, $cycle);
        $evenement = $this->evenementEnCours($graine, $partenaire->cle, $cycle);
        $pointsDeLEvenement = null === $evenement ? 0 : $evenement->pointsSur($partenaire, $ressource);

        return new Conjoncture(
            max(-self::AMPLITUDE, min(self::AMPLITUDE, $meteo + $saisonEtCrue + $pointsDeLEvenement)),
            $meteo,
            $saisonEtCrue,
            0 === $pointsDeLEvenement ? null : $evenement,
            $pointsDeLEvenement,
        );
    }

    /**
     * L'événement qui court cette quinzaine chez ce partenaire, s'il y en a un.
     */
    public function evenementEnCours(int $graine, string $cle, int $cycle): ?EvenementDeMarche
    {
        $fenetre = intdiv($cycle, self::FENETRE);
        $tirage = self::entier($graine, $cle, 'fenetre', $fenetre);

        if (0 !== $tirage % self::FENETRES_AVEC_EVENEMENT_SUR) {
            return null;
        }

        $duree = self::DUREE_MIN + intdiv($tirage, 7) % (self::DUREE_MAX - self::DUREE_MIN + 1);
        $debut = $fenetre * self::FENETRE + intdiv($tirage, 31) % (self::FENETRE - $duree + 1);

        if ($cycle < $debut || $cycle >= $debut + $duree) {
            return null;
        }

        $evenements = EvenementDeMarche::cases();

        return $evenements[intdiv($tirage, 211) % \count($evenements)];
    }

    /**
     * Un bruit de valeur : une valeur tirée à chaque noeud du maillage, interpolée entre deux.
     */
    private function meteo(int $graine, string $cle, Ressource $ressource, int $cycle): int
    {
        $noeud = intdiv($cycle, self::PAS_DE_LA_METEO);
        $phase = $cycle % self::PAS_DE_LA_METEO;
        $a = $this->valeurDuNoeud($graine, $cle, $ressource, $noeud);
        $b = $this->valeurDuNoeud($graine, $cle, $ressource, $noeud + 1);

        return $a + intdiv(($b - $a) * $phase, self::PAS_DE_LA_METEO);
    }

    private function valeurDuNoeud(int $graine, string $cle, Ressource $ressource, int $noeud): int
    {
        return self::entier($graine, $cle, $ressource->value, $noeud) % (2 * self::AMPLITUDE_DE_LA_METEO + 1)
            - self::AMPLITUDE_DE_LA_METEO;
    }

    /**
     * Sur les seuls vivres : la récolte emplit les greniers, la crue gonfle (ou non) les
     * champs de tout le fleuve. Une cité qui **vend** des vivres et en regorge avantage la
     * famille qui lui en achète ; si elle en **achète**, l'abondance joue contre celle qui
     * lui en vend.
     */
    private function saisonEtCrue(QualiteDeCrue $crue, PartenaireCommercial $partenaire, Ressource $ressource, int $cycle): int
    {
        if (FamilleDeRessource::Vivres !== $ressource->famille()) {
            return 0;
        }

        $abondance = match (DateDeJeu::pourCycle($cycle)->saison) {
            Saison::Chemou => self::POIDS_DE_LA_SAISON,
            Saison::Peret => 0,
            Saison::Akhet => -self::POIDS_DE_LA_SAISON,
            null => 0,
        };

        if (TypeDeRoute::Fluviale === $partenaire->route) {
            $abondance += match ($crue) {
                QualiteDeCrue::Forte => self::POIDS_DE_LA_CRUE,
                QualiteDeCrue::Faible => -self::POIDS_DE_LA_CRUE,
                QualiteDeCrue::Normale => 0,
            };
        }

        return $partenaire->vendDe($ressource) ? $abondance : -$abondance;
    }

    /**
     * Un entier positif, stable d'un lancement à l'autre et d'une machine à l'autre — ce
     * que `crc32` et `mt_rand` ne garantissent pas toujours, et que `md5` tient.
     */
    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
