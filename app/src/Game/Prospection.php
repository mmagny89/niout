<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Zone;
use Random\Randomizer;

/**
 * Sonder une case déjà reconnue pour y retrouver de la matière (doc 02, doc 04).
 *
 * **Un filon épuisé n'est pas une impasse.** Jusqu'ici, la dernière unité
 * extraite d'une carrière fermait définitivement la production de ce matériau :
 * sur une petite carte, épuiser l'unique gisement d'argile figeait la partie
 * sans qu'aucun geste ne puisse y remédier. La prospection est ce geste — elle
 * coûte, elle prend le temps d'un trajet, et elle peut échouer, mais elle
 * rouvre une voie.
 *
 * Deux issues heureuses, dans cet ordre de préférence : **rouvrir un filon
 * épuisé de la case**, parce que c'est ce qu'on est venu chercher, ou en
 * découvrir un nouveau si la case a encore de la place. Ce que le terrain ne
 * porte pas ne se trouve pas : la prospection s'appuie sur la même règle que la
 * génération de la carte, jamais sur une seconde table.
 */
final readonly class Prospection
{
    /**
     * Chances de rentrer avec quelque chose, en pourcentage.
     *
     * **Valeur inventée**, calibrée pour que la prospection soit une action
     * qu'on engage sans angoisse mais pas une formalité : deux fouilles sur
     * trois aboutissent, ce qui rend l'échec mémorable sans rendre le déblocage
     * d'un matériau vital improbable.
     */
    public const int CHANCES_DE_TROUVER = 65;

    public function __construct(
        private MissionCatalogue $missions,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * Ce qu'une fouille pourrait mettre au jour ici — vide si la case n'a plus
     * rien à donner. L'écran s'en sert pour ne pas proposer un départ qui ne
     * peut rien rapporter.
     *
     * @return list<Ressource>
     */
    public function filonsPossibles(GameSave $partie, Zone $zone): array
    {
        return [...$this->filonsARouvrir($zone), ...$this->filonsANaitre($partie, $zone)];
    }

    /**
     * Ce que le prospecteur rapporte, en une phrase destinée au journal de
     * cycle. **Il peut rentrer bredouille**, et il le dit : faire semblant
     * d'avoir trouvé serait pire que de l'avouer.
     */
    public function fouiller(GameSave $partie, Zone $zone): string
    {
        $lieu = \sprintf('(%d, %d)', $zone->getX(), $zone->getY());

        // Les filons épuisés d'abord : c'est ce qu'on est venu rouvrir. Une
        // case qui n'en porte aucun se prête alors à une découverte.
        $arouvrir = $this->filonsARouvrir($zone);
        $anaitre = $this->filonsANaitre($partie, $zone);

        if ([] === $arouvrir && [] === $anaitre) {
            return \sprintf('Votre prospecteur est rentré de %s : ce sol n\'a plus rien à donner.', $lieu);
        }

        if ($this->hasard->getInt(1, 100) > self::CHANCES_DE_TROUVER) {
            return \sprintf('Votre prospecteur a sondé %s pendant des jours, et n\'a rien trouvé.', $lieu);
        }

        $quantite = PoidsDeTirage::quantiteParGisement($partie->getVille()->getDifficulte());
        $candidats = [] !== $arouvrir ? $arouvrir : $anaitre;
        $ressource = $candidats[$this->hasard->getInt(0, \count($candidats) - 1)];

        $gisement = $zone->gisementDe($ressource);

        if (null !== $gisement) {
            $gisement->rouvrir($quantite);

            return \sprintf(
                'Votre prospecteur a retrouvé la veine en %s : le %s rend encore %d unités.',
                $lieu,
                $gisement->libelle(),
                $quantite,
            );
        }

        $zone->poserUnGisement($ressource, $quantite);

        return \sprintf(
            'Votre prospecteur a mis au jour un gisement de %s en %s : %d unités à extraire.',
            $ressource->libelle(),
            $lieu,
            $quantite,
        );
    }

    /**
     * Les filons déjà là, mais taris. Une ressource renouvelable n'en est
     * jamais : un banc de poisson se reconstitue tout seul.
     *
     * @return list<Ressource>
     */
    private function filonsARouvrir(Zone $zone): array
    {
        $tarisses = [];

        foreach ($zone->getGisements() as $gisement) {
            if ($gisement->estEpuise()) {
                $tarisses[] = $gisement->getRessource();
            }
        }

        return $tarisses;
    }

    /**
     * Ce qui pourrait naître ici : un matériau que la région porte, que le
     * terrain accepte, que la case n'a pas encore, et pour lequel il lui reste
     * de la place.
     *
     * @return list<Ressource>
     */
    private function filonsANaitre(GameSave $partie, Zone $zone): array
    {
        if (!$zone->peutPorterUnGisementDePlus()) {
            return [];
        }

        $terrain = $zone->getTerrain();

        $possibles = $terrain->estUnPointDEau()
            ? [Ressource::Poisson]
            : GenerateurDeCarte::materiauxPossiblesSur($terrain, $this->geographieDe($partie)->ressourcesDeZone);

        return array_values(array_filter(
            $possibles,
            static fn (Ressource $r): bool => null === $zone->gisementDe($r),
        ));
    }

    private function geographieDe(GameSave $partie): GeographieDeRegion
    {
        $numero = $partie->getMission();

        return $partie->estCampagne() && null !== $numero
            ? $this->missions->get($numero)->geographie
            : LanceurDePartie::geographieDuModeAventure();
    }
}
