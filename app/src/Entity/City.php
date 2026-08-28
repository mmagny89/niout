<?php

declare(strict_types=1);

namespace App\Entity;

use App\Game\CoutDeConstruction;
use App\Game\Population;
use App\Game\Ressource;
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
    public function crediterRessources(array $ressources): static
    {
        foreach ($ressources as $valeur => $quantite) {
            if ($quantite <= 0) {
                continue;
            }

            $this->ligneDe(Ressource::from($valeur))->ajouter($quantite);
        }

        return $this;
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
        $manques = [];

        foreach ($cout->enRessources() as $valeur => $exige) {
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
