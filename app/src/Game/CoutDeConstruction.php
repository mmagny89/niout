<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le coût d'un niveau de bâtiment (doc 01).
 *
 * **Chaque ligne nomme sa ressource**, sans famille ni substitution : un
 * grenier se paie en argile, en roseaux et en bois local, pas en « bois » et en
 * « pierre ». Le doc 08 ne connaît que des matériaux nommés — et un compteur
 * qui agrégerait le bois local et le cèdre sous « bois » cacherait au joueur ce
 * qu'il possède réellement, alors que l'un se ramasse au bord du fleuve et que
 * l'autre s'importe du Levant à cinq fois le prix.
 *
 * Conséquence assumée : une région qui ne porte pas un matériau ne peut pas
 * bâtir ce qui en réclame sans passer par le commerce. C'est ce qui donne son
 * poids à la géographie.
 *
 * **La fondation ne coûte pas de deben, l'amélioration si** (doc 01 révisé).
 * La brique crue d'un premier niveau relevait de matériaux locaux et d'une
 * main-d'œuvre familiale, pas d'un investissement monétaire ; le deben ne
 * rétribuait qu'un savoir-faire spécialisé — artisans, finitions, décor —,
 * c'est-à-dire ce qui s'ajoute en montant de niveau. Deux exceptions, qui
 * paient dès la fondation : le Temple (rituel de dédicace) et le Port
 * (assemblage des pontons par des spécialistes).
 */
final readonly class CoutDeConstruction
{
    /**
     * Progression du coût par niveau (doc 01) : modérée, jamais exponentielle.
     */
    private const float FACTEUR_DE_PROGRESSION = 0.4;

    /**
     * @var array<string, int> valeur de Ressource => quantité, lignes nulles exclues
     */
    private array $lignes;

    /**
     * Ce que chaque niveau au-delà du premier ajoute en deben (doc 01). Nul
     * pour un coût déjà calculé par `pourNiveau()`, qui l'a consommé.
     */
    private int $debenParNiveau;

    /**
     * @param array<string, int> $lignes valeur de Ressource => quantité
     */
    public function __construct(array $lignes = [], int $debenParNiveau = 0)
    {
        $this->lignes = array_filter($lignes, static fn (int $quantite): bool => $quantite > 0);
        $this->debenParNiveau = $debenParNiveau;
    }

    /**
     * Construit un coût à partir de ressources nommées — plus lisible, sur les
     * lignes du catalogue, que d'assembler un tableau à la main.
     *
     * `$deben` est ce que coûte la **fondation** : nul partout sauf au Temple
     * et au Port. `$debenParNiveau` est ce que coûte chaque niveau au-delà.
     */
    public static function de(
        int $deben = 0,
        int $debenParNiveau = 0,
        int $roseaux = 0,
        int $boisLocal = 0,
        int $argile = 0,
        int $calcaire = 0,
        int $lin = 0,
    ): self {
        return new self([
            Ressource::Roseaux->value => $roseaux,
            Ressource::BoisLocal->value => $boisLocal,
            Ressource::Argile->value => $argile,
            Ressource::Calcaire->value => $calcaire,
            Ressource::Lin->value => $lin,
            Ressource::Deben->value => $deben,
        ], $debenParNiveau);
    }

    /**
     * Le coût d'un niveau donné (doc 01) :
     *
     * - matériaux : `coutFondation × (1 + (N - 1) × 0,4)` ;
     * - deben : `debenFondation + debenParNiveau × (N - 1)`.
     *
     * Les deux ne suivent **pas la même loi**, et c'est voulu : les matériaux
     * croissent avec la taille du bâtiment, le deben avec le savoir-faire
     * qu'on achète pour le raffiner.
     */
    public function pourNiveau(int $niveau): self
    {
        $multiplicateur = 1 + ($niveau - 1) * self::FACTEUR_DE_PROGRESSION;
        $lignes = [];

        foreach ($this->lignes as $valeur => $quantite) {
            if (Ressource::Deben->value === $valeur) {
                continue;
            }

            $lignes[$valeur] = (int) ceil($quantite * $multiplicateur);
        }

        $deben = ($this->lignes[Ressource::Deben->value] ?? 0) + $this->debenParNiveau * ($niveau - 1);

        if ($deben > 0) {
            $lignes[Ressource::Deben->value] = $deben;
        }

        return new self($lignes);
    }

    public function estGratuit(): bool
    {
        return [] === $this->lignes;
    }

    public function quantiteDe(Ressource $ressource): int
    {
        return $this->lignes[$ressource->value] ?? 0;
    }

    /**
     * Le coût prêt à être débité d'un stock.
     *
     * @return array<string, int> valeur de Ressource => quantité
     */
    public function enRessources(): array
    {
        return $this->lignes;
    }

    /**
     * Les ressources que ce coût réclame.
     *
     * @return list<Ressource>
     */
    public function ressources(): array
    {
        return array_map(
            static fn (string $valeur): Ressource => Ressource::from($valeur),
            array_keys($this->lignes),
        );
    }

    /**
     * Détail affichable, libellés en toutes lettres.
     *
     * @return array<string, int> libellé => quantité
     */
    public function detail(): array
    {
        $detail = [];

        foreach ($this->lignes as $valeur => $quantite) {
            $detail[Ressource::from($valeur)->libelle()] = $quantite;
        }

        return $detail;
    }
}
