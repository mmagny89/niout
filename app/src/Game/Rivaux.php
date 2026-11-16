<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\Npc;
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

    /**
     * Combien de quinzaines un rival reste ailleurs avant de pouvoir reparaître (`docs/plan-pnj-palier-b.md`
     * § 11) : lassé, payé, démonté. **Valeurs inventées.** Un rival démonté n'est pas mort — le jeu n'a pas
     * de morts à l'écran — mais l'enquête est la plus longue des trois issues, et la plus payante.
     */
    public const int DELAI_APRES_LASSITUDE = 8;
    public const int DELAI_APRES_UN_ACCORD = 12;
    public const int DELAI_APRES_L_ENQUETE = 30;

    /** Ce que la mémoire d'un accord passé rabat du prix du suivant, en centièmes. */
    public const int RABAIS_PAR_ACCORD_PASSE = 25;

    /** Ce qu'un accord laisse de relation avec la cité qu'il disputait : un marchand payé devient un contact. */
    public const int RELATION_APRES_UN_ACCORD = 4;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CataloguePartenaires $partenaires = new CataloguePartenaires(),
        private Randomizer $hasard = new Randomizer(),
        private ?Successions $successions = null,
        private ConjonctureDeMarche $conjoncture = new ConjonctureDeMarche(),
        private ?EtatsDesCites $cites = null,
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
            $this->memoire($partie, $rival)?->seLasser($partie->getCycle(), self::DELAI_APRES_LASSITUDE);

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
        $plein = max(
            self::PRIX_MINIMAL_DE_LACCORD,
            $partie->getFamille()->getRenommee() * self::PRIX_DE_LACCORD_PAR_RENOMMEE,
        );

        // Un marchand déjà payé s'entend plus volontiers : son souvenir rabat le prix, dans une borne
        // (trois accords retenus au plus), jamais jusqu'à la gratuité.
        $rival = $partie->getVille()->getRival();
        $accords = null === $rival ? 0 : ($this->memoire($partie, $rival)?->getAccords() ?? 0);

        return max(self::PRIX_MINIMAL_DE_LACCORD, intdiv($plein * (100 - self::RABAIS_PAR_ACCORD_PASSE * min(1, $accords)), 100));
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
        $this->memoire($partie, $rival)?->etrePaye($partie->getCycle(), self::DELAI_APRES_UN_ACCORD);
        $disputee = $this->partenaireDe($partie, $rival->getPartenaire());

        if (null !== $disputee) {
            $this->cites?->obtenir($partie, $disputee)->gagnerLaRelation(self::RELATION_APRES_UN_ACCORD);
        }

        $this->entityManager->flush();

        return \sprintf('%s accepte vos %d deben et s\'écarte de votre route.', $rival->getNom(), $prix);
    }

    /**
     * **L'enquête** : on le démonte, et il ne revient pas avant longtemps
     * (`DELAI_APRES_L_ENQUETE`). La plus longue des trois issues, et la plus payante.
     */
    public function neutraliserParLEnquete(GameSave $partie): ?string
    {
        $rival = $partie->getVille()->getRival();

        if (null === $rival) {
            return null;
        }

        $partie->getVille()->chasserLeRival();
        $this->memoire($partie, $rival)?->etreDemonte($partie->getCycle(), self::DELAI_APRES_L_ENQUETE);

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

        $cycle = $partie->getCycle();
        $revenant = $this->revenant($partie, $cycle);
        $identite = $revenant?->getIdentite() ?? self::entier($partie->getGraine(), 'rival', $cycle);
        $caractere = $revenant?->getCaractere() ?? self::caractere($identite);

        $choix = DecisionDuRival::choisir($this->aspirations($partie, $routes, $cycle), $caractere, $identite)
            ?? throw new \LogicException('Des routes ouvertes donnent toujours un choix.');

        $partenaire = $this->partenaireDe($partie, $choix['cle']);
        $nom = $revenant?->getNom() ?? $this->unNom($partie, $identite);

        if (null === $revenant) {
            $this->entityManager->persist(new Npc($partie, $identite, RoleDePnj::Rival, $nom, $caractere));
        }

        $rival = new RivalCommercial(
            $ville,
            $choix['cle'],
            $nom,
            self::MALUS_MINIMAL + $identite % (self::MALUS_MAXIMAL - self::MALUS_MINIMAL + 1),
            self::QUINZAINES_MINIMALES + intdiv($identite, 11) % (self::QUINZAINES_MAXIMALES - self::QUINZAINES_MINIMALES + 1),
            $identite,
            $choix['raisons'] + ['revenant' => null !== $revenant],
        );
        $ville->installerUnRival($rival);
        $this->entityManager->persist($rival);

        return [\sprintf(
            '%s %s sur votre route %s et vous prend %d %% de ce qui passe.',
            $rival->getNom(),
            null !== $revenant ? 'est de retour : il s\'installe' : 's\'installe',
            null !== $partenaire ? 'vers '.$partenaire->nom : 'commerciale',
            $rival->getMalusEnCentiemes(),
        )];
    }

    /**
     * Ce que chaque route ouverte vaut à un marchand, de 0 à 100 : où le marché paie, où le commerce de la
     * famille est le plus vivant.
     *
     * @param list<\App\Entity\RouteCommerciale> $routes
     *
     * @return list<array{cle: string, prosperite: int, prestige: int}>
     */
    private function aspirations(GameSave $partie, array $routes, int $cycle): array
    {
        $lignes = [];

        foreach ($routes as $route) {
            $partenaire = $this->partenaireDe($partie, $route->getPartenaire());
            $ordres = $route->getOrdres();
            $favorable = 0;

            foreach ($ordres as $ordre) {
                if (null !== $partenaire) {
                    $favorable += max(0, $this->conjoncture->pour($partie, $partenaire, $ordre->getRessource(), $cycle)->points);
                }
            }

            $lignes[] = [
                'cle' => $route->getPartenaire(),
                // 20 points de conjoncture par ordre posé = le meilleur marché possible.
                'prosperite' => [] === $ordres->toArray() ? 0 : min(100, intdiv($favorable * 100, 20 * \count($ordres))),
                'prestige' => min(100, 20 * \count($ordres) + 10 * \count($route->getConvois())),
            ];
        }

        return $lignes;
    }

    /**
     * Le marchand qu'on a déjà croisé et qui peut reparaître — **jamais avant son délai**. Une fois sur deux,
     * et seulement s'il en existe un : le monde ne se limite pas à ceux qu'on connaît.
     */
    private function revenant(GameSave $partie, int $cycle): ?Npc
    {
        if (0 !== self::entier($partie->getGraine(), 'revenant', $cycle) % 2) {
            return null;
        }

        foreach ($this->entityManager->getRepository(Npc::class)->findBy(['partie' => $partie, 'role' => RoleDePnj::Rival], ['id' => 'ASC']) as $connu) {
            if ($connu->peutParaitre($cycle)) {
                return $connu;
            }
        }

        return null;
    }

    private function memoire(GameSave $partie, RivalCommercial $rival): ?Npc
    {
        return 0 === $rival->getIdentite()
            ? null
            : $this->entityManager->getRepository(Npc::class)->findOneBy(['partie' => $partie, 'role' => RoleDePnj::Rival, 'identite' => $rival->getIdentite()]);
    }

    /**
     * Le caractère d'un marchand, déduit de sa graine : quatre des huit traits existants.
     */
    private static function caractere(int $identite): TraitDeCandidat
    {
        $traits = [TraitDeCandidat::Econome, TraitDeCandidat::Ambitieux, TraitDeCandidat::Fidele, TraitDeCandidat::Croyant];

        return $traits[intdiv($identite, 5) % \count($traits)];
    }

    /**
     * Le « pourquoi » de sa route, en une phrase — rendue **à l'affichage** depuis les clés stockées.
     */
    public function raconter(RivalCommercial $rival): string
    {
        $r = $rival->getRaisons();
        $caractere = TraitDeCandidat::tryFrom((string) ($r['caractere'] ?? ''));

        $motifs = [];

        if ((int) ($r['prosperite'] ?? 0) >= 30) {
            $motifs[] = 'le marché y paie bien';
        }

        if ((int) ($r['prestige'] ?? 0) >= 30) {
            $motifs[] = 'votre commerce y est vivant';
        }

        return \sprintf(
            '%s a choisi cette route : %s%s.',
            $rival->getNom(),
            [] === $motifs ? 'c\'est la moins mauvaise qu\'il ait trouvée' : implode(' et ', $motifs),
            null !== $caractere ? ' — et il est '.mb_strtolower($caractere->libelle()) : '',
        ).(true === ($r['revenant'] ?? false) ? ' Vous le connaissez déjà.' : '');
    }

    /**
     * Des noms attestés au Nouvel Empire, portés par des gens ordinaires —
     * scribes, contremaîtres, marchands de Deir el-Médineh.
     */
    private function unNom(GameSave $partie, int $identite): string
    {
        $noms = ['Panehsy', 'Hori', 'Ipouy', 'Nakhtmin', 'Khâemouaset', 'Ramosé', 'Ounnefer', 'Pentaour'];

        // On ne croise pas deux « Hori » dans la même ville : un nom que porte
        // déjà un chef ou un Medjaÿ est écarté, sauf s'ils le sont tous.
        $bases = array_map(static fn (string $nom): string => explode(',', $nom)[0], Prenoms::pris($partie));
        $libres = array_values(array_diff($noms, $bases));
        $noms = [] === $libres ? $noms : $libres;

        return $noms[$identite % \count($noms)].' le marchand';
    }

    private static function entier(int|string ...$parties): int
    {
        return (int) hexdec(substr(md5(implode('|', $parties)), 0, 7));
    }
}
