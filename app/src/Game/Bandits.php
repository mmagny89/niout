<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;
use App\Entity\Zone;

/**
 * Le danger sur la carte (doc 02, doc 03, lot 10.1).
 *
 * Le doc 02 range le danger parmi les cinq paramètres d'un scénario, au même
 * rang que la géographie et l'économie : « plus la région est difficile […]
 * plus de zones dangereuses ». Le doc 03 s'en sert pour calculer ce qu'une
 * bande oppose à des Medjaÿ.
 *
 * **Un attribut, pas un contenu** (arbitrage 10.0). Une case garde son gisement
 * et porte en plus des bandits : c'est le filon gardé, celui qui donne envie de
 * lever une troupe. Un contenu de zone l'aurait rendu impossible — une case
 * dangereuse n'aurait alors rien porté qui vaille la peine d'être pris.
 *
 * **Une contradiction du doc 02, tranchée.** Le document donne deux comptes qui
 * ne s'accordent pas : un tableau de poids de tirage (0 % en facile, 8 % en
 * moyen, 15 % en difficile) et une formule de paramètre
 * (`nbZonesBandits = partieEntiere(difficulté × 0,5)`). Sur la grille 12×12 de
 * la dixième mission, le premier donnerait une vingtaine de zones là où le
 * second en donne quatre. **C'est la formule qui l'emporte** : le tableau de
 * poids décrit un tirage de *contenu*, et le danger n'en est pas un depuis
 * l'arbitrage 10.0 ; la formule, elle, vit parmi les paramètres de scénario, où
 * le danger a sa place.
 */
final readonly class Bandits
{
    /**
     * Ce qu'une bande oppose, avant le facteur de région. **Valeur inventée** :
     * le doc 03 renvoie au doc 02 pour `valeurBase_zone`, que le doc 02 ne
     * chiffre nulle part.
     *
     * Calée sur les forces du doc 03 : deux fantassins valent vingt, donc une
     * chance sur deux à mains nues. L'équipement de la Forge et un troisième
     * homme font pencher la balance — c'est le calibrage voulu, une case gardée
     * doit demander une vraie troupe sans exiger une armée.
     */
    public const int DEFENSE_DE_BASE = 20;

    /**
     * Ce que chaque zone dangereuse de la région ajoute à la défense de toutes
     * les autres, en points de pourcentage (doc 03 : `× (1 + 0,15 × nbZones)`).
     *
     * **En centièmes entiers**, jamais en flottants : une probabilité en
     * virgule flottante serait le premier endroit du jeu où deux parties
     * identiques divergeraient.
     */
    public const int RENFORT_PAR_ZONE_DE_LA_REGION = 15;

    /**
     * Combien de cases une région porte, selon sa difficulté (doc 02 :
     * `partieEntiere(niveauDifficulte × 0,5)`).
     *
     * Aucune avant la difficulté 2 : les deux premières missions se jouent sans
     * danger, ce qui laisse le temps d'ouvrir une Caserne avant d'en avoir
     * besoin.
     */
    public static function nombrePour(int $difficulte): int
    {
        return intdiv(max(0, $difficulte), 2);
    }

    /**
     * Ce qu'une case gardée oppose réellement, en tenant compte des autres
     * bandes de la région.
     *
     * **Une région dangereuse est plus dure partout**, pas seulement sur ses
     * cases gardées : c'est le sens du facteur du doc 03, et c'est ce qui fait
     * du nombre de zones un curseur de difficulté régionale.
     */
    public static function defenseDe(City $ville, Zone $zone): int
    {
        if (!$zone->estGardee()) {
            return 0;
        }

        $renfort = self::RENFORT_PAR_ZONE_DE_LA_REGION * self::compterLesZonesGardees($ville);

        return intdiv($zone->getDefenseDesBandits() * (100 + $renfort), 100);
    }

    /**
     * Les cases encore tenues par une bande. Une case pacifiée ne compte plus :
     * nettoyer une case affaiblit toute la région, ce qui récompense la
     * première victoire au-delà d'elle-même.
     */
    public static function compterLesZonesGardees(City $ville): int
    {
        $gardees = 0;

        foreach ($ville->getZones() as $zone) {
            $gardees += $zone->estGardee() ? 1 : 0;
        }

        return $gardees;
    }
}
