<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Le coût d'un niveau de bâtiment (doc 01).
 *
 * **Chaque ligne nomme sa ressource**, sans famille ni substitution : un
 * grenier se paie en roseaux et en argile, pas en « bois » et en « pierre ».
 * Le doc 01 chiffre ses bâtiments dans ces deux matériaux génériques, mais le
 * doc 08 ne connaît que des matériaux nommés — et un compteur qui agrège les
 * roseaux et le cèdre sous « bois » cache au joueur ce qu'il possède réellement.
 *
 * Conséquence assumée : une région qui ne porte pas un matériau ne peut pas
 * bâtir ce qui en réclame sans passer par le commerce. C'est ce qui donne son
 * poids à la géographie.
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
     * @param array<string, int> $lignes valeur de Ressource => quantité
     */
    public function __construct(array $lignes = [])
    {
        $this->lignes = array_filter($lignes, static fn (int $quantite): bool => $quantite > 0);
    }

    /**
     * Construit un coût à partir de ressources nommées — plus lisible, sur les
     * lignes du catalogue, que d'assembler un tableau à la main.
     */
    public static function de(int $deben = 0, int $roseaux = 0, int $argile = 0, int $calcaire = 0, int $lin = 0): self
    {
        return new self([
            Ressource::Roseaux->value => $roseaux,
            Ressource::Argile->value => $argile,
            Ressource::Calcaire->value => $calcaire,
            Ressource::Lin->value => $lin,
            Ressource::Deben->value => $deben,
        ]);
    }

    /**
     * coutNiveau(N) = coutBase × (1 + (N - 1) × 0,4).
     */
    public function pourNiveau(int $niveau): self
    {
        $multiplicateur = 1 + ($niveau - 1) * self::FACTEUR_DE_PROGRESSION;
        $lignes = [];

        foreach ($this->lignes as $valeur => $quantite) {
            $lignes[$valeur] = (int) ceil($quantite * $multiplicateur);
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
