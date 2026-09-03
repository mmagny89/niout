<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\RivalCommercial;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Les marchands rivaux (doc 08).
 *
 * **Reportés en bloc de la Phase 5**, décision de la joueuse, parce que l'une
 * de leurs trois issues est une enquête : les écrire sans elle revenait à les
 * réécrire ensuite. Ils trouvent leur place ici, et rendent au commerce
 * l'adversité qui lui manquait.
 *
 * **C'est la renommée qui les attire** (doc 08) : plus la famille est connue,
 * plus on vient lui disputer ses routes. Une famille obscure ne dérange
 * personne — ce qui fait de la renommée autre chose qu'un compteur qui monte.
 *
 * **Il ne détruit rien.** Il prend une part du volume qui passe sur une route,
 * et s'en va de lui-même si on le laisse faire assez longtemps. Les trois
 * issues du doc 08 sont toutes ouvertes, et l'une d'elles est de ne rien
 * faire.
 */
final readonly class Rivaux
{
    /**
     * Chance d'apparition par quinzaine, en pour mille : `renommée / 2`,
     * plafonnée à 50 ‰ — soit les 5 % du doc 08 à renommée 100, et 1 % à
     * renommée 20. En pour mille plutôt qu'en pour cent, pour rester en
     * entiers sans écraser les petites renommées à zéro.
     */
    public const int PLAFOND_EN_POUR_MILLE = 50;

    /**
     * Ce qu'il prend, en centièmes du volume d'un convoi (doc 08).
     */
    public const int MALUS_MINIMAL = 10;
    public const int MALUS_MAXIMAL = 20;

    /**
     * Combien de quinzaines il tient avant de se lasser. **Valeur inventée** :
     * assez long pour peser, assez court pour que « ignorer » reste une
     * décision et non un abandon.
     */
    public const int QUINZAINES_MINIMALES = 8;
    public const int QUINZAINES_MAXIMALES = 16;

    /**
     * Ce que coûte un accord commercial, en deben par point de renommée. Le
     * doc 08 l'indexe sur la richesse de la région ; on l'indexe sur la
     * renommée, qui est ce qui a attiré le rival — plus on est en vue, plus il
     * faut mettre pour qu'on vous laisse tranquille.
     */
    public const int PRIX_DE_LACCORD_PAR_RENOMMEE = 3;
    public const int PRIX_MINIMAL_DE_LACCORD = 40;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CataloguePartenaires $partenaires = new CataloguePartenaires(),
        private Randomizer $hasard = new Randomizer(),
        private ?Successions $successions = null,
    ) {
    }

    /**
     * Le partenaire d'une route, quel que soit le mode.
     *
     * **Le repli sur la mission 1 était un défaut** (lot 11.2) : en Aventure,
     * où il n'y a pas de mission, un rival cherchait le partenaire dans le
     * catalogue du Delta — donc ne le trouvait jamais dès que Memphis eut ses
     * propres routes. `Successions` est optionnelle pour que la classe reste
     * instanciable sans conteneur, comme le catalogue et le hasard le sont
     * déjà.
     */
    private function partenaireDe(GameSave $partie, string $cle): ?PartenaireCommercial
    {
        if (!$partie->estCampagne()) {
            return $this->partenaires->partenaireDeMemphis(
                $this->successions?->regneEnCours($partie),
                $cle,
            );
        }

        $mission = $partie->getMission();

        return null === $mission ? null : $this->partenaires->partenaire($mission, $cle);
    }

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $rival = $ville->getRival();

        if (null !== $rival) {
            if (!$rival->avancerDUnCycle()) {
                return [];
            }

            $ville->chasserLeRival();

            return [\sprintf('%s se lasse et quitte votre route.', $rival->getNom())];
        }

        if ($this->hasard->getInt(1, 1000) > $this->chanceEnPourMille($partie)) {
            return [];
        }

        return $this->installer($partie);
    }

    /**
     * La chance qu'un rival paraisse, en pour mille.
     */
    public function chanceEnPourMille(GameSave $partie): int
    {
        return min(self::PLAFOND_EN_POUR_MILLE, intdiv($partie->getFamille()->getRenommee(), 2));
    }

    /**
     * Ce qu'un rival retire au volume d'un convoi sur cette route. Zéro
     * partout ailleurs : il tient une route, pas tout le commerce.
     */
    public static function malusSur(GameSave $partie, string $partenaire): int
    {
        $rival = $partie->getVille()->getRival();

        return null !== $rival && $rival->getPartenaire() === $partenaire
            ? $rival->getMalusEnCentiemes()
            : 0;
    }

    /**
     * Ce que coûte de s'entendre avec lui.
     */
    public function prixDeLAccord(GameSave $partie): int
    {
        return max(
            self::PRIX_MINIMAL_DE_LACCORD,
            $partie->getFamille()->getRenommee() * self::PRIX_DE_LACCORD_PAR_RENOMMEE,
        );
    }

    /**
     * **L'accord commercial** : on paie, il s'en va. La plus rapide des trois
     * issues, et la seule qui coûte des deben plutôt que du temps.
     *
     * @throws CommerceImpossible
     */
    public function passerUnAccord(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $rival = $ville->getRival();

        if (null === $rival) {
            throw new CommerceImpossible('Personne ne vous dispute vos routes.');
        }

        $prix = $this->prixDeLAccord($partie);

        if (!$ville->debiterRessources([Ressource::Deben->value => $prix])) {
            throw new CommerceImpossible(\sprintf('Il vous faut %d deben pour vous entendre avec lui.', $prix));
        }

        $ville->chasserLeRival();
        $this->entityManager->flush();

        return \sprintf('%s accepte vos %d deben et s\'écarte de votre route.', $rival->getNom(), $prix);
    }

    /**
     * **L'enquête** : on le démonte, et il ne revient pas. La plus longue des
     * trois issues, et la plus payante.
     */
    public function neutraliserParLEnquete(GameSave $partie): ?string
    {
        $rival = $partie->getVille()->getRival();

        if (null === $rival) {
            return null;
        }

        $partie->getVille()->chasserLeRival();

        return \sprintf('%s quitte la route sans demander son reste.', $rival->getNom());
    }

    /**
     * @return list<string>
     */
    private function installer(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $routes = [];

        foreach ($ville->getRoutesCommerciales() as $route) {
            // Une route encore en chemin n'a rien à disputer.
            if (0 === $route->getQuinzainesAvantOuverture()) {
                $routes[] = $route;
            }
        }

        // Un rival vient concurrencer quelque chose : sans route ouverte, il
        // n'a rien à prendre et ne paraît pas.
        if ([] === $routes) {
            return [];
        }

        $route = $routes[$this->hasard->getInt(0, \count($routes) - 1)];
        $partenaire = $this->partenaireDe($partie, $route->getPartenaire());

        $rival = new RivalCommercial(
            $ville,
            $route->getPartenaire(),
            $this->unNom(),
            $this->hasard->getInt(self::MALUS_MINIMAL, self::MALUS_MAXIMAL),
            $this->hasard->getInt(self::QUINZAINES_MINIMALES, self::QUINZAINES_MAXIMALES),
        );
        $ville->installerUnRival($rival);
        $this->entityManager->persist($rival);

        return [\sprintf(
            '%s s\'installe sur votre route %s et vous prend %d %% de ce qui passe.',
            $rival->getNom(),
            null !== $partenaire ? 'vers '.$partenaire->nom : 'commerciale',
            $rival->getMalusEnCentiemes(),
        )];
    }

    /**
     * Des noms attestés au Nouvel Empire, portés par des gens ordinaires —
     * scribes, contremaîtres, marchands de Deir el-Médineh.
     */
    private function unNom(): string
    {
        $noms = ['Panehsy', 'Hori', 'Ipouy', 'Nakhtmin', 'Khâemouaset', 'Ramosé', 'Ounnefer', 'Pentaour'];

        return $noms[$this->hasard->getInt(0, \count($noms) - 1)].' le marchand';
    }
}
