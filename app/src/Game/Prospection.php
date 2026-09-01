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
 *
 * **Toutes les cases ne se valent pas** (décision de la joueuse) : retrouver
 * une veine tarie est certain — les galeries sont là, la géologie est connue —,
 * tandis que sonder du sable vierge tient du pari. `chancesSur()` dit l'écart,
 * et l'écran l'annonce avant le départ ; un pourcentage unique laissait croire
 * qu'une case en vaut une autre.
 */
final readonly class Prospection
{
    /**
     * **Rouvrir une veine tarie est certain.** Les galeries sont creusées, la
     * géologie est connue, on sait exactement où le filon s'est perdu : il
     * reste à le suivre. C'est le seul cas à 100 % du jeu.
     *
     * Généreux à dessein. L'épuisement doit coûter du temps et de l'argent —
     * quarante deben, quinze vivres et le trajet, puis rouvrir la carrière et
     * la rééquiper —, **jamais fermer une région** : c'est le défaut que la
     * prospection existe pour corriger. Chercher du **neuf**, en revanche,
     * reste un pari, et c'est là que le hasard a sa place.
     */
    public const int CHANCES_SUR_UNE_VEINE_TARIE = 100;

    /**
     * Trouver du neuf sur une case **déjà minéralisée** : là où il y a un
     * filon, il y en a souvent un second.
     */
    public const int CHANCES_SUR_UNE_CASE_MINERALISEE = 45;

    /**
     * Trouver du neuf sur une case vierge : on sonde à l'aveugle.
     */
    public const int CHANCES_SUR_UNE_CASE_VIERGE = 20;

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

        if ($this->hasard->getInt(1, 100) > $this->chancesSur($partie, $zone)) {
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
     * Les chances de rentrer avec quelque chose, en pourcentage, **sur cette
     * case-là**. Nulles quand il n'y a rien à y trouver — c'est ce qui fait
     * disparaître le bouton plutôt que de proposer un départ vain.
     *
     * Le terrain module ensuite : le limon d'une berge se lit à l'œil nu, le
     * sable ne rend rien sans creuser longtemps.
     */
    public function chancesSur(GameSave $partie, Zone $zone): int
    {
        $arouvrir = $this->filonsARouvrir($zone);

        if ([] === $arouvrir && [] === $this->filonsANaitre($partie, $zone)) {
            return 0;
        }

        if ([] !== $arouvrir) {
            // Rien à moduler : le terrain a déjà livré ce matériau ici, la
            // question n'est plus de savoir s'il y en a.
            return self::CHANCES_SUR_UNE_VEINE_TARIE;
        }

        return $this->modulerParLeTerrain(
            $zone->porteUnGisement() ? self::CHANCES_SUR_UNE_CASE_MINERALISEE : self::CHANCES_SUR_UNE_CASE_VIERGE,
            $zone,
        );
    }

    /**
     * Ce que le sol laisse voir. Les berges et les terres travaillées livrent
     * leurs matériaux à qui sait regarder ; le sable les enfouit, et la forêt
     * les couvre.
     *
     * Le résultat reste dans [5, 95] : on ne promet jamais une certitude — elle
     * est réservée à la veine tarie qu'on vient rouvrir — ni un départ perdu
     * d'avance, qui serait un bouton pour rien.
     */
    private function modulerParLeTerrain(int $chances, Zone $zone): int
    {
        $ecart = match ($zone->getTerrain()) {
            TypeDeTerrain::Nil, TypeDeTerrain::Fertile => 10,
            TypeDeTerrain::TerreClassique, TypeDeTerrain::Oasis => 5,
            TypeDeTerrain::Foret => -5,
            TypeDeTerrain::Desert => -15,
            default => 0,
        };

        return max(5, min(95, $chances + $ecart));
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
