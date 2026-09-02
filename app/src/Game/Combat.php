<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\GameSave;
use App\Entity\Medjay;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * La résolution automatique d'une sortie (doc 03).
 *
 * **Aucun contrôle pendant le combat**, à la manière de Pharaon et Caesar : le
 * joueur agit **en amont** — qui envoyer, avec quelles armes, sous quel dieu —
 * et la sortie se résout d'un bloc. C'est ce qui la rend compatible avec le
 * principe fondateur du jeu, où rien ne se joue en temps réel et où le coût est
 * du temps de jeu actif, jamais une attente.
 *
 * La formule du document, à la lettre :
 *
 * ```
 * scoreAttaque        = Σ(force × qualité d'équipement) × terrain × faveur
 * probabilitéVictoire = scoreAttaque / (scoreAttaque + scoreDéfense)
 * ```
 *
 * La qualité d'équipement est déjà dans `Medjay::force()` (lot 10.3) : elle n'y
 * entre donc pas deux fois.
 *
 * **Tout se compte en centièmes entiers.** Une probabilité en virgule flottante
 * serait le premier endroit du jeu où deux parties identiques divergeraient —
 * c'est la discipline du projet depuis les rendements, et elle vaut ici plus
 * qu'ailleurs, le hasard s'y ajoutant déjà.
 */
final readonly class Combat
{
    /**
     * Ce que le terrain fait au score d'attaque, en centièmes (doc 03).
     *
     * Le désert avantage le **défenseur** : celui qui connaît les points d'eau
     * et les creux tient contre plus nombreux que lui. À l'inverse, un assaut
     * porté par le fleuve pendant Akhèt profite de la crue, qui met les barques
     * partout où elles ne vont pas le reste de l'année.
     */
    public const int TERRAIN_NEUTRE = 100;
    public const int TERRAIN_DESERT = 85;
    public const int TERRAIN_FLUVIAL_EN_AKHET = 115;

    /**
     * Ce que Sekhmet ajoute ou retire au score (doc 03, doc 07). Elle décide du
     * sort de tous — Isis, elle, protège l'homme, et n'intervient donc pas ici
     * mais sur les pertes.
     */
    public const int FAVEUR_ACQUISE = 110;
    public const int FAVEUR_HOSTILE = 90;

    /**
     * Les pertes, en points de pourcentage des hommes engagés (doc 03).
     */
    public const int BLESSES_EN_VICTOIRE = 10;
    public const int BLESSES_EN_DEFAITE_MIN = 20;
    public const int BLESSES_EN_DEFAITE_MAX = 30;
    public const int MORTS_EN_VICTOIRE_MIN = 2;
    public const int MORTS_EN_VICTOIRE_MAX = 5;
    public const int MORTS_EN_DEFAITE_MIN = 10;
    public const int MORTS_EN_DEFAITE_MAX = 15;

    /**
     * Combien de quinzaines un blessé met à se remettre (doc 03).
     */
    public const int QUINZAINES_DE_CONVALESCENCE = 2;

    /**
     * Ce qu'on trouve dans le camp d'une bande vaincue, en centièmes de ce
     * qu'elle opposait. **Valeur inventée** : le document dit « butin
     * proportionnel au score » sans donner la proportion. La moitié — de quoi
     * payer un homme de plus, jamais de quoi financer la campagne.
     */
    public const int BUTIN_POUR_CENT_DE_LA_DEFENSE = 50;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Envoie une troupe prendre une case, et résout la sortie.
     *
     * @param list<Medjay> $troupe
     *
     * @throws CombatImpossible
     */
    public function livrer(GameSave $partie, Zone $zone, array $troupe): ResultatDeCombat
    {
        $ville = $partie->getVille();

        if ($zone->getVille() !== $ville) {
            throw new CombatImpossible('Cette case n\'est pas de votre territoire.');
        }

        if (!$zone->estDecouverte()) {
            throw new CombatImpossible('On n\'attaque pas une case qu\'on n\'a pas reconnue.');
        }

        if (!$zone->estGardee()) {
            throw new CombatImpossible('Personne ne tient cette case.');
        }

        $troupe = array_values(array_filter(
            $troupe,
            static fn (Medjay $medjay): bool => $medjay->getVille() === $ville
                && $medjay->estDisponible($partie->getCycle()),
        ));

        if ([] === $troupe) {
            throw new CombatImpossible('Vous n\'avez aucun homme en état de partir.');
        }

        $attaque = $this->scoreDattaque($partie, $zone, $troupe);
        $defense = Bandits::defenseDe($ville, $zone);

        // La formule du doc 03, en centièmes : on tire sur cent, pas sur une
        // probabilité flottante.
        $chances = intdiv($attaque * 100, max(1, $attaque + $defense));
        $victoire = $this->hasard->getInt(1, 100) <= $chances;

        $butin = 0;

        if ($victoire) {
            // La case est prise, et elle le reste (arbitrage 10.0). Toute la
            // région en est affaiblie : `Bandits::defenseDe()` compte les
            // bandes encore tenues.
            $zone->pacifier();

            $butin = intdiv($defense * self::BUTIN_POUR_CENT_DE_LA_DEFENSE, 100);
            $ville->crediterRessources([Ressource::Deben->value => $butin]);

            foreach ($troupe as $medjay) {
                $medjay->gagnerDeLexperience();
            }
        }

        [$blesses, $tombes] = $this->compterLesPertes($partie, $troupe, $victoire);

        $this->entityManager->flush();

        return new ResultatDeCombat(
            victoire: $victoire,
            scoreAttaque: $attaque,
            scoreDefense: $defense,
            chancesSurCent: $chances,
            butin: $butin,
            blesses: $blesses,
            tombes: $tombes,
        );
    }

    /**
     * Ce que la troupe pèse, terrain et faveur compris.
     *
     * **Une seule division** malgré trois facteurs enchaînés : deux divisions
     * entières de suite rogneraient la force à chaque étape, d'une façon que
     * personne ne saurait plus expliquer six mois après (discipline du lot 6.3).
     *
     * @param list<Medjay> $troupe
     */
    public function scoreDattaque(GameSave $partie, Zone $zone, array $troupe): int
    {
        $brut = 0;

        foreach ($troupe as $medjay) {
            $force = $medjay->force();

            // L'archer tire à découvert : le désert le sert, là même où il
            // dessert la troupe dans son ensemble.
            if (TypeDeTerrain::Desert === $zone->getTerrain()) {
                $force += intdiv($force * $medjay->getSpecialisation()->bonusEnDesert(), 100);
            }

            $brut += $force;
        }

        return intdiv(
            $brut * $this->facteurDeTerrain($partie, $zone) * $this->facteurDeFaveur($partie->getVille()),
            100 * 100,
        );
    }

    /**
     * Ce que le terrain vaut à l'attaquant, en centièmes (doc 03).
     */
    public function facteurDeTerrain(GameSave $partie, Zone $zone): int
    {
        if (TypeDeTerrain::Desert === $zone->getTerrain()) {
            return self::TERRAIN_DESERT;
        }

        // L'assaut porté par le fleuve pendant la crue : les barques passent
        // là où il n'y a pas d'eau le reste de l'année.
        if ($zone->getTerrain()->estUnPointDEau() && Saison::Akhet === $partie->dateDeJeu()->saison) {
            return self::TERRAIN_FLUVIAL_EN_AKHET;
        }

        return self::TERRAIN_NEUTRE;
    }

    /**
     * Ce que Sekhmet ajoute ou retire, en centièmes (doc 03, doc 07).
     */
    public function facteurDeFaveur(City $ville): int
    {
        return match ($ville->palierDe(Divinite::Sekhmet)) {
            PalierDeFaveur::Devoue, PalierDeFaveur::Favorable => self::FAVEUR_ACQUISE,
            PalierDeFaveur::Hostile => self::FAVEUR_HOSTILE,
            PalierDeFaveur::Neutre => self::TERRAIN_NEUTRE,
        };
    }

    /**
     * Qui revient blessé, et qui ne revient pas (doc 03).
     *
     * **Le bouclier du fantassin couvre toute la troupe** : sa présence réduit
     * les pertes de 30 %, ce qui est sa raison d'être — il ne frappe pas fort,
     * il ramène les autres.
     *
     * **Isis réduit la mort permanente**, jamais la blessure, et jamais l'issue
     * du combat : le doc 07 la distingue de Sekhmet parce qu'elle protège
     * l'homme quand l'autre décide du sort de tous. C'est ici, et nulle part
     * ailleurs, qu'elle cesse d'être une divinité sans effet.
     *
     * @param list<Medjay> $troupe
     *
     * @return array{0: list<Medjay>, 1: list<string>}
     */
    private function compterLesPertes(GameSave $partie, array $troupe, bool $victoire): array
    {
        $risqueDeBlessure = $victoire
            ? $this->hasard->getInt(0, self::BLESSES_EN_VICTOIRE)
            : $this->hasard->getInt(self::BLESSES_EN_DEFAITE_MIN, self::BLESSES_EN_DEFAITE_MAX);

        $risqueDeMort = $victoire
            ? $this->hasard->getInt(self::MORTS_EN_VICTOIRE_MIN, self::MORTS_EN_VICTOIRE_MAX)
            : $this->hasard->getInt(self::MORTS_EN_DEFAITE_MIN, self::MORTS_EN_DEFAITE_MAX);

        $abri = $this->abriDesBoucliers($troupe);
        $risqueDeBlessure -= intdiv($risqueDeBlessure * $abri, 100);
        $risqueDeMort -= intdiv($risqueDeMort * $abri, 100);

        $protection = EffetDeFaveur::protectionDIsis($partie->getVille());
        $risqueDeMort -= intdiv($risqueDeMort * $protection, 100);

        $blesses = [];
        $tombes = [];

        foreach ($troupe as $medjay) {
            if ($this->hasard->getInt(1, 100) <= $risqueDeMort) {
                // Un mort emporte son expérience avec lui : c'est le vrai
                // enjeu de la perte définitive, pas le coût du recrutement.
                $tombes[] = $medjay->getSpecialisation()->libelle();
                $medjay->getVille()->getMedjays()->removeElement($medjay);
                $this->entityManager->remove($medjay);

                continue;
            }

            if ($this->hasard->getInt(1, 100) <= $risqueDeBlessure) {
                $medjay->blesser($partie->getCycle(), self::QUINZAINES_DE_CONVALESCENCE);
                $blesses[] = $medjay;
            }
        }

        return [$blesses, $tombes];
    }

    /**
     * Ce que les boucliers épargnent à la troupe, en centièmes. **Ils ne se
     * cumulent pas** : dix fantassins ne rendent pas la troupe invulnérable —
     * un mur de boucliers en vaut un, pas dix.
     *
     * @param list<Medjay> $troupe
     */
    private function abriDesBoucliers(array $troupe): int
    {
        $abri = 0;

        foreach ($troupe as $medjay) {
            $abri = max($abri, $medjay->getSpecialisation()->reductionDesPertes());
        }

        return $abri;
    }
}
