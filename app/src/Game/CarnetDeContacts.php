<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;

/**
 * Ce qu'une région servie laisse derrière soi (doc 13, lot 9.4).
 *
 * Chaque mission accomplie laisse un **contact** — la ville elle-même — qui
 * fait un prix sur les ressources caractéristiques de sa région. On a bâti chez
 * eux, on y connaît du monde : c'est la traduction commerciale de la campagne.
 *
 * **Une remise, jamais un déblocage** (arbitrage 9.0). Un contact ne rend pas
 * importable ce qui ne l'était pas : il en ferait un raccourci de progression,
 * et il faudrait recalibrer les missions tardives autour de ce que le joueur a
 * déjà fait. Il reste une commodité économique, qui se cumule avec la renommée
 * sous le plafond unique d'`AvantageDeNegoce`.
 *
 * **Rien ne se persiste.** Comme les partenaires commerciaux, le carnet se
 * déduit des missions accomplies : le nom, la région et les ressources sont du
 * contenu, et une colonne de plus ne dirait rien que `MissionCatalogue` ne
 * sache déjà.
 */
final readonly class CarnetDeContacts
{
    /**
     * Ce qu'un contact fait gagner sur les ressources de sa région, en points
     * de pourcentage — la même unité que la renommée et le Négociateur, avec
     * lesquels il se cumule sous le plafond commun.
     *
     * **Deux, comme le doc 13 le chiffre.** Neuf contacts en fin de campagne
     * pèseraient dix-huit points s'ils portaient tous sur la même ressource ;
     * en pratique les régions se partagent, et le plafond tranche le reste.
     */
    public const int AVANTAGE_PAR_CONTACT = 2;

    public function __construct(
        private Progression $progression,
        private MissionCatalogue $missions,
    ) {
    }

    /**
     * Les villes où la famille a servi, hors celle de la partie en cours : on
     * ne se fait pas de prix à soi-même.
     *
     * @return list<Mission>
     */
    public function pour(GameSave $partie): array
    {
        $enCours = $partie->getMission();
        $contacts = [];

        foreach ($this->progression->missionsAccomplies($partie->getJoueur()) as $numero) {
            if ($numero === $enCours) {
                continue;
            }

            $contacts[] = $this->missions->get($numero);
        }

        return $contacts;
    }

    /**
     * Ce que le carnet vaut sur cette ressource, en points de pourcentage.
     *
     * Une région compte **une fois**, pour ce qu'elle porte en gisement : c'est
     * là qu'on connaît les gens qui l'extraient. Deux régions qui portent la
     * même ressource comptent deux fois — deux relations valent mieux qu'une.
     */
    public function avantageSur(GameSave $partie, Ressource $ressource): int
    {
        $avantage = 0;

        foreach ($this->pour($partie) as $mission) {
            if (\in_array($ressource, $mission->geographie->ressourcesDeZone, true)) {
                $avantage += self::AVANTAGE_PAR_CONTACT;
            }
        }

        return $avantage;
    }

    /**
     * Ce que le carnet montre à l'écran : chaque ville servie et ce qu'on y
     * obtient. Une liste de noms sans effet lisible ne se jouerait pas.
     *
     * @return list<array{ville: string, region: string, ressources: list<Ressource>}>
     */
    public function lisible(GameSave $partie): array
    {
        $lignes = [];

        foreach ($this->pour($partie) as $mission) {
            $lignes[] = [
                'ville' => $mission->ville,
                'region' => $mission->region,
                'ressources' => $mission->geographie->ressourcesDeZone,
            ];
        }

        return $lignes;
    }
}
