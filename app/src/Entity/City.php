<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\CoutDeConstruction;
use App\Game\Divinite;
use App\Game\PalierDeFaveur;
use App\Game\Population;
use App\Game\Ressource;
use App\Game\Stockage;
use App\Game\TypeDeBatiment;
use App\Repository\CityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * La ville confiée au joueur pour une partie (doc 09).
 *
 * « Niout » (niwt) signifie « la ville » en égyptien ancien : c'est l'objet
 * central du jeu, pas un simple décor. Elle porte son stock et ses bâtiments ;
 * la carte d'exploration s'y rattachera en Phase 3.
 */
#[ORM\Entity(repositoryClass: CityRepository::class)]
class City
{
    /**
     * Bornes du niveau de difficulté régionale (doc 02, doc 11) : 0 pour le
     * Delta, région d'apprentissage, jusqu'à 9 pour le Sinaï.
     */
    public const int DIFFICULTE_MIN = 0;
    public const int DIFFICULTE_MAX = 9;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $nom;

    #[ORM\Column]
    private int $difficulte;

    /**
     * Côté de la grille d'exploration. Dérivé de la difficulté en campagne,
     * choisi par le joueur en mode Aventure (doc 11, doc 14). La carte
     * elle-même viendra en Phase 3 ; seule sa dimension est fixée ici.
     */
    #[ORM\Column]
    private int $tailleGrille;

    /**
     * @var Collection<int, Expedition>
     */
    #[ORM\OneToMany(targetEntity: Expedition::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $expeditions;

    /**
     * @var Collection<int, Zone>
     */
    #[ORM\OneToMany(targetEntity: Zone::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $zones;

    /**
     * @var Collection<int, StockDeRessource>
     */
    #[ORM\OneToMany(targetEntity: StockDeRessource::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true, indexBy: 'ressource')]
    private Collection $stock;

    /**
     * @var Collection<int, Building>
     */
    #[ORM\OneToMany(targetEntity: Building::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $batiments;

    /**
     * @var Collection<int, Chantier>
     */
    #[ORM\OneToMany(targetEntity: Chantier::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $chantiers;

    /**
     * @var Collection<int, JobOffer>
     */
    #[ORM\OneToMany(targetEntity: JobOffer::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $offres;

    /**
     * @var Collection<int, OrdreDeFabrication>
     */
    #[ORM\OneToMany(targetEntity: OrdreDeFabrication::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $ordresDeFabrication;

    /**
     * @var Collection<int, FaveurDivine>
     */
    #[ORM\OneToMany(targetEntity: FaveurDivine::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $faveurs;

    /**
     * @var Collection<int, RouteCommerciale>
     */
    #[ORM\OneToMany(targetEntity: RouteCommerciale::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $routesCommerciales;

    /**
     * @var Collection<int, Employee>
     */
    #[ORM\OneToMany(targetEntity: Employee::class, mappedBy: 'ville', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $employes;

    /**
     * La population, en trois nombres et pas un de plus (décision de la
     * joueuse) : ceux qui travaillent, ceux qui grandissent, ceux qui ont
     * fini. Aucun individu n'est suivi — ce qui compte est de savoir combien
     * de bras la ville a, et combien de bouches.
     */
    #[ORM\Column]
    private int $actifs = 0;

    #[ORM\Column]
    private int $enfants = 0;

    #[ORM\Column]
    private int $anciens = 0;

    /**
     * Partie d'essai : un million de chaque ressource, aucun plafond de
     * réserve, les dix missions ouvertes (`User::ROLE_DIVIN`).
     *
     * **Le drapeau vit sur la ville et non sur la partie**, bien qu'il marque
     * la run entière : c'est la ville qui porte le stock et les plafonds, et
     * `Stockage` ne connaît qu'elle. `GameSave::estEnModeDivin()` s'y adosse
     * pour dire la même chose à l'échelle de la partie.
     */
    #[ORM\Column]
    private bool $modeDivin = false;

    public function __construct(string $nom, int $difficulte, int $tailleGrille)
    {
        $this->nom = $nom;
        $this->difficulte = $difficulte;
        $this->tailleGrille = $tailleGrille;
        $this->zones = new ArrayCollection();
        $this->expeditions = new ArrayCollection();
        $this->stock = new ArrayCollection();
        $this->batiments = new ArrayCollection();
        $this->chantiers = new ArrayCollection();
        $this->offres = new ArrayCollection();
        $this->employes = new ArrayCollection();
        $this->ordresDeFabrication = new ArrayCollection();
        $this->routesCommerciales = new ArrayCollection();
        $this->faveurs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * Le nom précédé de sa préposition élidée : « d'Avaris », « de Memphis ».
     *
     * Quatre des onze villes du jeu commencent par une voyelle — Avaris,
     * Akhetaton, Éléphantine, Ouadi Hammamat. Écrire « de Avaris » partout
     * serait une faute visible à chaque écran.
     */
    public function avecPreposition(): string
    {
        $premiere = mb_strtolower(mb_substr($this->nom, 0, 1));

        return \in_array($premiere, ['a', 'e', 'é', 'è', 'i', 'o', 'u', 'y', 'h'], true)
            ? "d'".$this->nom
            : 'de '.$this->nom;
    }

    public function getDifficulte(): int
    {
        return $this->difficulte;
    }

    /**
     * Plafond de niveau des bâtiments dans cette région (doc 01) : une région
     * plus difficile autorise des bâtiments plus hauts, ce qui compense la
     * rareté de ses ressources et allonge la partie.
     */
    public function niveauMaxRegional(): int
    {
        return 5 + $this->difficulte;
    }

    public function getTailleGrille(): int
    {
        return $this->tailleGrille;
    }

    /**
     * @return Collection<int, StockDeRessource>
     */
    public function getStock(): Collection
    {
        return $this->stock;
    }

    public function quantite(Ressource $ressource): int
    {
        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource() === $ressource) {
                return $ligne->getQuantite();
            }
        }

        return 0;
    }

    /**
     * Raccourcis vers ce que la barre de jeu affiche en permanence. Ils évitent
     * aux gabarits d'appeler quantite() avec une énumération.
     */
    public function getDeben(): int
    {
        return $this->quantite(Ressource::Deben);
    }

    public function getActifs(): int
    {
        return $this->actifs;
    }

    public function getEnfants(): int
    {
        return $this->enfants;
    }

    public function getAnciens(): int
    {
        return $this->anciens;
    }

    /**
     * Ceux qui ne travaillent pas : les enfants d'un côté, les anciens de
     * l'autre. Le joueur ne voit que ce total.
     */
    public function getInactifs(): int
    {
        return $this->enfants + $this->anciens;
    }

    public function population(): int
    {
        return $this->actifs + $this->enfants + $this->anciens;
    }

    /**
     * Installe des habitants — les volontaires du pharaon au départ, puis ceux
     * que le joueur fera venir.
     */
    public function accueillir(int $actifs, int $enfants, int $anciens): static
    {
        $this->actifs += $actifs;
        $this->enfants += $enfants;
        $this->anciens += $anciens;

        return $this;
    }

    /**
     * Une maisonnée quitte la ville — le pendant d'`accueillir()`, employé au
     * renvoi d'un chef, qui repart avec les siens.
     *
     * La ville ne sait pas qui était qui : il faut donc trancher d'où les
     * inactifs se retirent. **Sur les anciens d'abord, les enfants ensuite** —
     * c'est ce qui préserve les bras de demain, un enfant devenant actif là où
     * un ancien ne le redevient jamais. Prendre sur les enfants d'abord
     * transformerait chaque renvoi en trou démographique différé.
     */
    public function laisserPartir(int $actifs, int $inactifs): static
    {
        $this->actifs = max(0, $this->actifs - $actifs);

        $surLesAnciens = min($this->anciens, $inactifs);
        $this->anciens -= $surLesAnciens;
        $this->enfants = max(0, $this->enfants - ($inactifs - $surLesAnciens));

        return $this;
    }

    /**
     * Applique le bilan d'une année : des enfants entrent dans la vie active,
     * des actifs passent la main, et la mort prend sa part (`Demographie`).
     */
    public function appliquerLeBilanDeLAnnee(
        int $enfantsDevenusActifs,
        int $actifsDevenusAnciens,
        int $decesEnfants,
        int $decesActifs,
        int $decesAnciens,
    ): static {
        $this->enfants = max(0, $this->enfants - $enfantsDevenusActifs - $decesEnfants);
        $this->actifs = max(0, $this->actifs + $enfantsDevenusActifs - $actifsDevenusAnciens - $decesActifs);
        $this->anciens = max(0, $this->anciens + $actifsDevenusAnciens - $decesAnciens);

        return $this;
    }

    /**
     * Ce que la ville mange par quinzaine : une ration par actif, une
     * demi-ration par inactif. Le total se calcule en demi-rations et ne se
     * convertit qu'ici, une seule fois — voir `Population`.
     */
    public function consommationDeNourriture(): int
    {
        return Population::vivresPourDemiRations($this->actifs * 2 + $this->getInactifs());
    }

    /**
     * Combien de maisonnées la ville peut loger : celles du Quartier
     * d'habitation (`20 × niveau`, doc 01), plus celle du joueur, que la
     * Résidence familiale abrite d'emblée.
     */
    public function capaciteEnFoyers(): int
    {
        $quartier = $this->batimentDeType(TypeDeBatiment::QuartierDHabitation);

        return 1 + Population::FAMILLES_PAR_NIVEAU_DE_QUARTIER * (null === $quartier ? 0 : $quartier->getNiveau());
    }

    public function foyersOccupes(): int
    {
        return Population::foyersPour($this->population());
    }

    /**
     * Combien de maisonnées la ville pourrait encore loger. Zéro veut dire
     * qu'il faut bâtir avant d'espérer un habitant de plus — c'est le
     * diagnostic que l'écran doit rendre lisible.
     */
    public function foyersLibres(): int
    {
        return max(0, $this->capaciteEnFoyers() - $this->foyersOccupes());
    }

    public function manqueDeLogements(): bool
    {
        return 0 === $this->foyersLibres();
    }

    /**
     * Tout ce que la ville a de mangeable, toutes ressources confondues : c'est
     * là-dessus que se paient les provisions d'une expédition (doc 04).
     */
    public function getNourriture(): int
    {
        $total = 0;

        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource()->estNourriture()) {
                $total += $ligne->getQuantite();
            }
        }

        return $total;
    }

    /**
     * Le stock trié pour l'affichage : chaque ressource sous son propre nom, la
     * monnaie en tête. Rien n'est agrégé — un compteur « bois » qui
     * additionnerait roseaux et cèdre cacherait au joueur ce qu'il possède
     * réellement.
     *
     * @return list<StockDeRessource>
     */
    public function stockAffichable(): array
    {
        $lignes = [];

        foreach ($this->stock as $ligne) {
            if ($ligne->getQuantite() > 0 || $ligne->getRessource()->estLaMonnaie()) {
                $lignes[] = $ligne;
            }
        }

        usort($lignes, static function (StockDeRessource $a, StockDeRessource $b): int {
            if ($a->getRessource()->estLaMonnaie()) {
                return -1;
            }

            if ($b->getRessource()->estLaMonnaie()) {
                return 1;
            }

            return $a->getRessource()->libelle() <=> $b->getRessource()->libelle();
        });

        return $lignes;
    }

    /**
     * Crédite le stock. Sert à la dotation royale du départ (doc 13), puis aux
     * récoltes, à la pêche et aux achats.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     */
    /**
     * Crédite ce qui **tient dans les réserves**, et rien de plus (`Stockage`).
     *
     * Le plafonnement est fait ici, et non chez l'appelant, pour qu'aucun
     * chemin ne puisse l'oublier — c'est le seul point d'entrée du stock. Pour
     * savoir ce qui aura été refusé, demander `surplusRefuse()` **avant** de
     * créditer : une fois le crédit passé, l'information n'existe plus.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     */
    public function crediterRessources(array $ressources): static
    {
        $refuse = $this->surplusRefuse($ressources);

        foreach ($ressources as $valeur => $quantite) {
            $tient = $quantite - ($refuse[$valeur] ?? 0);

            if ($tient <= 0) {
                continue;
            }

            $this->ligneDe(Ressource::from($valeur))->ajouter($tient);
        }

        return $this;
    }

    /**
     * Ce qui ne rentrerait pas, ressource par ressource — les lignes qui
     * tiennent en entier en sont absentes.
     *
     * Les ressources d'une même réserve se partagent son plafond : dix roseaux
     * rangés, c'est dix de moins pour l'argile. Elles sont donc servies dans
     * l'ordre où on les présente, ce qui n'a d'importance que sur la dernière
     * place disponible.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     *
     * @return array<string, int> valeur de Ressource => quantité refusée
     */
    public function surplusRefuse(array $ressources): array
    {
        $place = [];
        $refuse = [];

        foreach ($ressources as $valeur => $quantite) {
            if ($quantite <= 0) {
                continue;
            }

            $ressource = Ressource::from($valeur);
            $plafond = Stockage::plafondPour($this, $ressource);

            if (null === $plafond) {
                continue;
            }

            // La réserve est commune : on tient le compte de ce qui reste au
            // fil des lignes, sans quoi chacune croirait disposer de toute la
            // place libre.
            $reserve = $ressource->estNourriture() ? 'vivres' : 'materiaux';
            $place[$reserve] ??= max(0, $plafond - Stockage::occupationPour($this, $ressource));

            $tient = min($quantite, $place[$reserve]);
            $place[$reserve] -= $tient;

            if ($tient < $quantite) {
                $refuse[$valeur] = $quantite - $tient;
            }
        }

        return $refuse;
    }

    /**
     * @return Collection<int, OrdreDeFabrication>
     */
    public function getOrdresDeFabrication(): Collection
    {
        return $this->ordresDeFabrication;
    }

    public function ajouterOrdreDeFabrication(OrdreDeFabrication $ordre): static
    {
        if (!$this->ordresDeFabrication->contains($ordre)) {
            $this->ordresDeFabrication->add($ordre);
        }

        return $this;
    }

    public function retirerOrdreDeFabrication(OrdreDeFabrication $ordre): static
    {
        $this->ordresDeFabrication->removeElement($ordre);

        return $this;
    }

    /**
     * Un atelier ne tient qu'un ordre à la fois : c'est un lieu, pas une file.
     * L'Atelier et la Forge en ont chacun le leur.
     */
    public function ordreDeFabricationDe(TypeDeBatiment $batiment): ?OrdreDeFabrication
    {
        foreach ($this->ordresDeFabrication as $ordre) {
            if ($ordre->getBatiment() === $batiment) {
                return $ordre;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, RouteCommerciale>
     */
    public function getRoutesCommerciales(): Collection
    {
        return $this->routesCommerciales;
    }

    public function ajouterRouteCommerciale(RouteCommerciale $route): static
    {
        if (!$this->routesCommerciales->contains($route)) {
            $this->routesCommerciales->add($route);
        }

        return $this;
    }

    public function routeVers(string $partenaire): ?RouteCommerciale
    {
        foreach ($this->routesCommerciales as $route) {
            if ($route->getPartenaire() === $partenaire) {
                return $route;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, FaveurDivine>
     */
    public function getFaveurs(): Collection
    {
        return $this->faveurs;
    }

    /**
     * Ce que pense un dieu, qu'on l'ait déjà honoré ou non.
     *
     * Une divinité sans ligne en base est **neutre**, pas absente : c'est ce
     * qui permet de n'écrire une ligne qu'au premier geste du joueur.
     */
    public function faveurEnvers(Divinite $divinite): int
    {
        return $this->faveurDe($divinite)?->getFaveur() ?? Divinite::FAVEUR_DE_DEPART;
    }

    public function palierDe(Divinite $divinite): PalierDeFaveur
    {
        return PalierDeFaveur::pour($this->faveurEnvers($divinite));
    }

    public function faveurDe(Divinite $divinite): ?FaveurDivine
    {
        foreach ($this->faveurs as $faveur) {
            if ($faveur->getDivinite() === $divinite) {
                return $faveur;
            }
        }

        return null;
    }

    /**
     * La ligne de ce dieu, créée au besoin. C'est le seul chemin par lequel
     * une faveur naît : partout ailleurs, on lit `faveurEnvers()`.
     */
    public function suivreLaFaveurDe(Divinite $divinite): FaveurDivine
    {
        $faveur = $this->faveurDe($divinite);

        if (null === $faveur) {
            $faveur = new FaveurDivine($this, $divinite);
            $this->faveurs->add($faveur);
        }

        return $faveur;
    }

    /**
     * Les dieux que la ville porte au-dessus du neutre — ce que le niveau du
     * Temple viendra plafonner.
     *
     * @return list<Divinite>
     */
    public function divinitesHonorees(): array
    {
        $honorees = [];

        foreach ($this->faveurs as $faveur) {
            if ($faveur->getPalier()->estAuDessusDuNeutre()) {
                $honorees[] = $faveur->getDivinite();
            }
        }

        return $honorees;
    }

    public function estEnModeDivin(): bool
    {
        return $this->modeDivin;
    }

    /**
     * Bascule la ville en partie d'essai, ou l'en fait sortir.
     *
     * Sortir du mode ne retire rien : ce qui a été donné reste, et les
     * plafonds reprennent simplement leur effet sur ce qui **entre**. Une
     * ville qu'on redescend sur terre garde donc ses réserves débordantes
     * jusqu'à les avoir dépensées — la règle du plafond n'a jamais porté sur
     * ce qui est déjà rangé.
     */
    public function basculerLeModeDivin(bool $actif): static
    {
        $this->modeDivin = $actif;

        return $this;
    }

    public function plafondDesVivres(): int
    {
        return Stockage::plafondDesVivres($this);
    }

    public function plafondDesMateriaux(): int
    {
        return Stockage::plafondDesMateriaux($this);
    }

    public function vivresPresqueSatures(): bool
    {
        return Stockage::saturationProche($this->getNourriture(), $this->plafondDesVivres());
    }

    public function materiauxPresqueSatures(): bool
    {
        return Stockage::saturationProche($this->getMateriaux(), $this->plafondDesMateriaux());
    }

    /**
     * Ce que la ville garde en matériaux et en objets : tout ce qui n'est ni
     * un vivre ni la monnaie. C'est l'occupation de l'Entrepôt.
     */
    public function getMateriaux(): int
    {
        $total = 0;

        foreach ($this->stock as $ligne) {
            $ressource = $ligne->getRessource();

            if (!$ressource->estNourriture() && !$ressource->estLaMonnaie()) {
                $total += $ligne->getQuantite();
            }
        }

        return $total;
    }

    /**
     * Débite le stock. Renvoie false **sans rien modifier** si les moyens ne
     * suffisent pas : un chantier ne doit jamais démarrer à découvert.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     */
    public function debiterRessources(array $ressources): bool
    {
        foreach ($ressources as $valeur => $quantite) {
            if ($this->quantite(Ressource::from($valeur)) < $quantite) {
                return false;
            }
        }

        foreach ($ressources as $valeur => $quantite) {
            $this->ligneDe(Ressource::from($valeur))->retirer($quantite);
        }

        return true;
    }

    /**
     * Débite un coût de construction. Chaque ligne nomme sa ressource : le
     * débit est donc un débit ordinaire, tout ou rien.
     */
    public function payer(CoutDeConstruction $cout): bool
    {
        return $this->debiterRessources($cout->enRessources());
    }

    /**
     * Ce qui manque pour payer ce coût, en clair et prêt à afficher. Vide si la
     * ville en a les moyens.
     *
     * @return list<string>
     */
    public function manquesPour(CoutDeConstruction $cout): array
    {
        return $this->manquesDe($cout->enRessources());
    }

    /**
     * Ce qui manque pour payer ces ressources-là, en toutes lettres.
     *
     * @param array<string, int> $ressources valeur de Ressource => quantité
     *
     * @return list<string>
     */
    public function manquesDe(array $ressources): array
    {
        $manques = [];

        foreach ($ressources as $valeur => $exige) {
            $ressource = Ressource::from($valeur);
            $possede = $this->quantite($ressource);

            if ($exige > $possede) {
                $manques[] = \sprintf('%d %s', $exige - $possede, $ressource->libelle());
            }
        }

        return $manques;
    }

    /**
     * Débite des vivres, quelle qu'en soit la nature — on part avec ce qu'on a.
     * Tout ou rien, et du plus abondant au plus rare comme pour les matériaux.
     */
    public function debiterNourriture(int $quantite): bool
    {
        if ($this->getNourriture() < $quantite) {
            return false;
        }

        $vivres = [];

        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource()->estNourriture() && $ligne->getQuantite() > 0) {
                $vivres[] = $ligne;
            }
        }

        usort($vivres, static fn (StockDeRessource $a, StockDeRessource $b): int => $b->getQuantite() <=> $a->getQuantite());

        foreach ($vivres as $ligne) {
            if ($quantite <= 0) {
                break;
            }

            $pris = min($quantite, $ligne->getQuantite());
            $ligne->retirer($pris);
            $quantite -= $pris;
        }

        return true;
    }

    private function ligneDe(Ressource $ressource): StockDeRessource
    {
        foreach ($this->stock as $ligne) {
            if ($ligne->getRessource() === $ressource) {
                return $ligne;
            }
        }

        $ligne = new StockDeRessource($this, $ressource);
        $this->stock->add($ligne);

        return $ligne;
    }

    /**
     * @return Collection<int, Zone>
     */
    public function getZones(): Collection
    {
        return $this->zones;
    }

    public function ajouterZone(Zone $zone): static
    {
        if (!$this->zones->contains($zone)) {
            $this->zones->add($zone);
        }

        return $this;
    }

    /**
     * La case où se dresse la ville. Toute carte en possède une.
     */
    public function zoneDeLaVille(): ?Zone
    {
        foreach ($this->zones as $zone) {
            if ($zone->porteLaVille()) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Expedition>
     */
    public function getExpeditions(): Collection
    {
        return $this->expeditions;
    }

    public function ajouterExpedition(Expedition $expedition): static
    {
        if (!$this->expeditions->contains($expedition)) {
            $this->expeditions->add($expedition);
        }

        return $this;
    }

    public function retirerExpedition(Expedition $expedition): static
    {
        $this->expeditions->removeElement($expedition);

        return $this;
    }

    /**
     * Une case ne peut être la destination que d'une expédition à la fois — en
     * envoyer deux au même endroit serait payer deux fois le même trajet.
     */
    public function aUneExpeditionVers(Zone $zone): bool
    {
        foreach ($this->expeditions as $expedition) {
            if ($expedition->getDestination() === $zone) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vrai si la ville jouxte un point d'eau — condition du Port (doc 01).
     */
    public function jouxteUnPointDEau(): bool
    {
        $centre = $this->zoneDeLaVille();

        if (null === $centre) {
            return false;
        }

        foreach ($this->zones as $zone) {
            if ($zone->getTerrain()->estUnPointDEau() && $zone->estAdjacenteA($centre)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Building>
     */
    public function getBatiments(): Collection
    {
        return $this->batiments;
    }

    public function ajouterBatiment(Building $batiment): static
    {
        if (!$this->batiments->contains($batiment)) {
            $this->batiments->add($batiment);
        }

        return $this;
    }

    /**
     * Le bâtiment de ce type s'il est dressé, null sinon. Un type ne peut
     * exister qu'une fois par ville — c'est une contrainte d'unicité en base,
     * pas seulement une convention.
     */
    public function batimentDeType(TypeDeBatiment $type): ?Building
    {
        foreach ($this->batiments as $batiment) {
            if ($batiment->getType() === $type) {
                return $batiment;
            }
        }

        return null;
    }

    public function possede(TypeDeBatiment $type): bool
    {
        return null !== $this->batimentDeType($type);
    }

    /**
     * @return Collection<int, Chantier>
     */
    public function getChantiers(): Collection
    {
        return $this->chantiers;
    }

    /**
     * @return Collection<int, JobOffer>
     */
    public function getOffres(): Collection
    {
        return $this->offres;
    }

    public function ajouterOffre(JobOffer $offre): static
    {
        if (!$this->offres->contains($offre)) {
            $this->offres->add($offre);
        }

        return $this;
    }

    public function retirerOffre(JobOffer $offre): static
    {
        $this->offres->removeElement($offre);

        return $this;
    }

    public function offrePour(TypeDeBatiment $type): ?JobOffer
    {
        foreach ($this->offres as $offre) {
            if ($offre->getType() === $type) {
                return $offre;
            }
        }

        return null;
    }

    /**
     * @return Collection<int, Employee>
     */
    public function getEmployes(): Collection
    {
        return $this->employes;
    }

    public function ajouterEmploye(Employee $employe): static
    {
        if (!$this->employes->contains($employe)) {
            $this->employes->add($employe);
        }

        return $this;
    }

    public function retirerEmploye(Employee $employe): static
    {
        $this->employes->removeElement($employe);

        return $this;
    }

    /**
     * Les chefs d'un bâtiment donné, embauchés ou déjà à l'ouvrage.
     *
     * @return list<Employee>
     */
    public function chefsDe(TypeDeBatiment $type): array
    {
        $chefs = [];

        foreach ($this->employes as $employe) {
            if ($employe->getType() === $type) {
                $chefs[] = $employe;
            }
        }

        return $chefs;
    }

    public function ajouterChantier(Chantier $chantier): static
    {
        if (!$this->chantiers->contains($chantier)) {
            $this->chantiers->add($chantier);
        }

        return $this;
    }

    public function retirerChantier(Chantier $chantier): static
    {
        $this->chantiers->removeElement($chantier);

        return $this;
    }

    /**
     * Un même bâtiment ne peut faire l'objet que d'un chantier à la fois.
     */
    public function aUnChantierPour(TypeDeBatiment $type): bool
    {
        foreach ($this->chantiers as $chantier) {
            if ($chantier->getType() === $type) {
                return true;
            }
        }

        return false;
    }
}
