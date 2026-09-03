<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\GameSave;
use App\Entity\PresentRoyal;
use App\Entity\QueteDeChantier;
use Doctrine\ORM\EntityManagerInterface;
use Random\Randomizer;

/**
 * Les requêtes du pharaon pour ses chantiers (doc 09).
 *
 * **Jamais obligatoires**, et c'est ce qui les rend intéressantes : elles ne
 * comptent pas dans la réussite de la mission, mais elles font gagner de la
 * renommée et de la faveur. Refuser coûte deux points de renommée, rien de
 * plus — le joueur reste libre de sa stratégie.
 *
 * **Laisser filer le délai revient à refuser.** Sans cela, attendre serait
 * toujours meilleur que refuser, et le délai ne voudrait rien dire.
 *
 * Chaque quête cite un monument **réellement bâti** par le pharaon
 * commanditaire, avec ce qu'on en sait (`ChantierRoyal`).
 */
final readonly class QuetesDeChantier
{
    /**
     * Une requête tous les quatre cycles, dit le doc 09 — deux mois de jeu.
     */
    public const int CYCLES_ENTRE_DEUX = 4;

    /**
     * Ce que le pharaon réclame, et le temps qu'il laisse pour l'apporter.
     */
    public const int QUANTITE_MINIMALE = 20;
    public const int QUANTITE_MAXIMALE = 50;
    public const int DELAI_EN_QUINZAINES = 6;

    public const int RENOMMEE_GAGNEE = 5;
    public const int RENOMMEE_PERDUE = 2;
    public const int FAVEUR_GAGNEE = 10;

    /**
     * Ce que le pharaon renvoie, en centièmes de la valeur au cours de ce qui
     * a été livré (décision de la joueuse au playtest).
     *
     * **Pourquoi une contrepartie.** Honorer une demande ne rapportait que de
     * la renommée et de la faveur : la ville se dépouillait de vingt à
     * cinquante unités d'une ressource sans jamais rien voir revenir. Sur une
     * partie où le deben ne rentrait presque pas, refuser était toujours le
     * choix rationnel, et tout ce système d'ancrage historique devenait un
     * malus qu'on évitait.
     *
     * **Pourquoi pas cent.** Le présent vaut moins que le don : servir le roi
     * reste une dépense, dont la renommée et la faveur sont le vrai gain. À
     * parité, la quête serait devenue une vente sans risque et sans choix.
     */
    public const int PRESENT_EN_CENTIEMES = 60;

    /**
     * Quinzaines que met le convoi royal à remonter jusqu'à la ville. Le
     * présent n'arrive pas au clic : c'est un revenu qu'on anticipe, pas un
     * troc.
     */
    public const int QUINZAINES_DE_ROUTE = 3;

    /**
     * Ce que le roi joint au deben, quand ses magasins portent quelque chose
     * que la région ne produit pas.
     */
    public const int UNITES_JOINTES_MIN = 8;
    public const int UNITES_JOINTES_MAX = 16;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MissionCatalogue $missions,
        private Successions $successions,
        private GeographieDeLaPartie $geographies,
        private Randomizer $hasard = new Randomizer(),
    ) {
    }

    /**
     * @return list<string> ce qu'il faut en rapporter au joueur
     */
    public function avancerDUnCycle(GameSave $partie): array
    {
        $ville = $partie->getVille();

        // Ce que le roi renvoie arrive d'abord : un présent en route ne dépend
        // pas de la demande en cours, et se recevrait mal le jour même où l'on
        // en refuse une autre.
        $messages = $this->recevoirLesPresents($partie);

        $quete = $ville->getQueteDeChantier();

        if (null !== $quete) {
            if (!$quete->avancerDUnCycle()) {
                return $messages;
            }

            $ville->retirerLaQueteDeChantier();
            $partie->getFamille()->ajusterRenommee(-self::RENOMMEE_PERDUE);
            $this->entityManager->remove($quete);

            return [...$messages, \sprintf(
                'Le délai est passé : la pierre pour %s n\'est jamais partie. On le remarque.',
                $quete->getChantier()->libelle(),
            )];
        }

        if (0 !== $partie->getCycle() % self::CYCLES_ENTRE_DEUX) {
            return $messages;
        }

        return [...$messages, ...$this->reclamer($partie)];
    }

    /**
     * Les convois royaux qui entrent en ville.
     *
     * @return list<string>
     */
    private function recevoirLesPresents(GameSave $partie): array
    {
        $ville = $partie->getVille();
        $messages = [];

        foreach ($ville->getPresentsRoyaux()->toArray() as $present) {
            if (!$present->avancerDUnCycle()) {
                continue;
            }

            $ressource = $present->getRessource();
            $refuse = $ville->surplusRefuse([$ressource->value => $present->getQuantite()]);
            $ville->crediterRessources([$ressource->value => $present->getQuantite()]);
            $ville->recevoirLePresent($present);
            $this->entityManager->remove($present);

            $manque = $refuse[$ressource->value] ?? 0;

            $messages[] = \sprintf(
                'Un convoi du palais entre en ville : %d %s, en reconnaissance de %s.%s',
                $present->getQuantite(),
                $ressource->libelle(),
                $present->getChantier(),
                $manque > 0 ? \sprintf(' Vos réserves étant pleines, %d n\'ont pas pu être rangés.', $manque) : '',
            );
        }

        return $messages;
    }

    /**
     * Livre ce que le pharaon demandait.
     *
     * @throws QueteImpossible
     */
    public function livrer(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();

        if (null === $quete) {
            throw new QueteImpossible('Le pharaon ne vous demande rien.');
        }

        if (!$ville->debiterRessources([$quete->getRessource()->value => $quete->getQuantite()])) {
            throw new QueteImpossible(\sprintf('Il vous faut %d %s pour honorer cette demande.', $quete->getQuantite(), $quete->getRessource()->libelle()));
        }

        $partie->getFamille()->ajusterRenommee(self::RENOMMEE_GAGNEE);
        $divinite = $quete->getChantier()->divinite();

        if (null !== $divinite) {
            // Le monument honore un dieu : le servir vous vaut sa faveur, dans
            // les bornes que le Temple autorise.
            $faveur = $ville->suivreLaFaveurDe($divinite);
            $faveur->ajuster(min(
                self::FAVEUR_GAGNEE,
                max(0, Temple::plafondDeFaveur($ville) - $faveur->getFaveur()),
            ));
        }

        $annonce = $this->preparerLePresent($partie, $quete);

        $ville->retirerLaQueteDeChantier();
        $this->entityManager->remove($quete);
        $this->entityManager->flush();

        return \sprintf(
            'Votre chargement part pour %s. %s%s',
            $quete->getChantier()->libelle(),
            $quete->getChantier()->ceQuOnEnSait(),
            $annonce,
        );
    }

    /**
     * Ce que le roi renvoie, et qui remontera le fleuve pendant quelques
     * quinzaines.
     *
     * **Du deben d'abord**, proportionnel à la valeur de ce qui a été donné :
     * c'est la contrepartie qui rend une demande acceptable à une ville qui
     * n'a rien en caisse. **Et souvent un matériau que la région ne produit
     * pas** : les magasins royaux tenaient l'Égypte entière, et rien n'est
     * plus juste pour un camp minier du Sinaï que de recevoir du bois qu'aucun
     * de ses ouadis ne porte. C'est aussi ce qui donne à ces quêtes un intérêt
     * qui n'est pas seulement monétaire.
     *
     * @return string ce qu'on en annonce au joueur, à joindre au récit
     */
    private function preparerLePresent(GameSave $partie, QueteDeChantier $quete): string
    {
        $ville = $partie->getVille();
        $chantier = $quete->getChantier()->libelle();
        $annonces = [];

        $valeur = (PrixDuMarche::pour($quete->getRessource()) ?? 0) * $quete->getQuantite();
        $deben = intdiv($valeur * self::PRESENT_EN_CENTIEMES, 100);

        if ($deben > 0) {
            $ville->attendreUnPresentRoyal(new PresentRoyal(
                $ville,
                Ressource::Deben,
                $deben,
                self::QUINZAINES_DE_ROUTE,
                $chantier,
            ));
            $annonces[] = \sprintf('%d deben', $deben);
        }

        $jointe = $this->ceQueLaRegionNaPas($partie);

        if (null !== $jointe) {
            $quantite = $this->hasard->getInt(self::UNITES_JOINTES_MIN, self::UNITES_JOINTES_MAX);
            $ville->attendreUnPresentRoyal(new PresentRoyal(
                $ville,
                $jointe,
                $quantite,
                self::QUINZAINES_DE_ROUTE,
                $chantier,
            ));
            $annonces[] = \sprintf('%d %s', $quantite, $jointe->libelle());
        }

        if ([] === $annonces) {
            return '';
        }

        return \sprintf(
            ' Le palais annonce un présent en retour — %s —, qui vous parviendra dans %d quinzaines.',
            implode(' et ', $annonces),
            self::QUINZAINES_DE_ROUTE,
        );
    }

    /**
     * Un matériau utile que la région ne porte en aucun gisement, tiré au sort.
     * Nul si la région produit déjà tout ce dont on se sert pour bâtir.
     *
     * On ne pioche que parmi les **matériaux de construction** : renvoyer du
     * lapis-lazuli ou de l'ivoire ferait un cadeau de prestige, joli mais sans
     * emploi pour une ville qui manque de charpente.
     */
    private function ceQueLaRegionNaPas(GameSave $partie): ?Ressource
    {
        $locales = $this->geographies->pour($partie)->ressourcesDeZone;

        $manquants = array_values(array_filter(
            [
                Ressource::BoisLocal,
                Ressource::Roseaux,
                Ressource::Argile,
                Ressource::Calcaire,
                Ressource::Cuivre,
            ],
            static fn (Ressource $ressource): bool => !in_array($ressource, $locales, true),
        ));

        if ([] === $manquants) {
            return null;
        }

        return $manquants[$this->hasard->getInt(0, count($manquants) - 1)];
    }

    /**
     * Refuse la demande : deux points de renommée, et rien d'autre.
     *
     * @throws QueteImpossible
     */
    public function refuser(GameSave $partie): string
    {
        $ville = $partie->getVille();
        $quete = $ville->getQueteDeChantier();

        if (null === $quete) {
            throw new QueteImpossible('Le pharaon ne vous demande rien.');
        }

        $ville->retirerLaQueteDeChantier();
        $partie->getFamille()->ajusterRenommee(-self::RENOMMEE_PERDUE);
        $this->entityManager->remove($quete);
        $this->entityManager->flush();

        return 'Vous déclinez. Le chantier se passera de vous, et l\'on s\'en souviendra un peu.';
    }

    /**
     * Le pharaon dont on attend une requête, et la géographie où l'on ira
     * chercher ce qu'il demande.
     *
     * @return array{0: ?string, 1: ?GeographieDeRegion}
     */
    private function quiReclame(GameSave $partie): array
    {
        if (!$partie->estCampagne()) {
            $regne = $this->successions->regneEnCours($partie);

            return null === $regne
                ? [null, null]
                : [$regne->pharaon, LanceurDePartie::geographieDuModeAventure()];
        }

        $numero = $partie->getMission();

        if (null === $numero) {
            return [null, null];
        }

        $mission = $this->missions->get($numero);

        return [$mission->pharaon, $mission->geographie];
    }

    /**
     * @return list<string>
     */
    private function reclamer(GameSave $partie): array
    {
        // **Le pharaon qui réclame n'est pas le même selon le mode** : en
        // campagne c'est le commanditaire de la mission, en Aventure celui qui
        // règne (doc 14, lot 11.3). Un règne qui ne réclamerait rien serait un
        // règne muet, et le renouvellement du contenu royal est précisément ce
        // que le document attend de la succession.
        [$pharaon, $geographie] = $this->quiReclame($partie);

        if (null === $pharaon || null === $geographie) {
            return [];
        }

        $chantier = ChantierRoyal::pour($pharaon);

        if (null === $chantier) {
            return [];
        }

        // Il réclame ce que la région porte : envoyer chercher au loin ce
        // qu'on a sous les pieds n'aurait pas de sens, et rendrait la quête
        // impossible dans la moitié des missions.
        $possibles = $geographie->ressourcesDeZone;

        if ([] === $possibles) {
            return [];
        }

        $ressource = $possibles[$this->hasard->getInt(0, \count($possibles) - 1)];
        $quantite = $this->hasard->getInt(self::QUANTITE_MINIMALE, self::QUANTITE_MAXIMALE);

        $quete = new QueteDeChantier($partie->getVille(), $chantier, $ressource, $quantite, self::DELAI_EN_QUINZAINES);
        $partie->getVille()->recevoirUneQuete($quete);
        $this->entityManager->persist($quete);

        return [\sprintf(
            '%s réclame %d %s pour %s. Vous avez %d quinzaines.',
            $pharaon,
            $quantite,
            $ressource->libelle(),
            $chantier->libelle(),
            self::DELAI_EN_QUINZAINES,
        )];
    }
}
