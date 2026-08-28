<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Ce que la ville doit verser à ses employés pour une quinzaine, et ce qu'elle
 * a réellement pu payer.
 *
 * Objet de valeur, jamais persisté : la paie se recalcule à chaque quinzaine
 * depuis les effectifs. Elle circule d'une résolution à l'autre parce que
 * **les impayés cessent de travailler dans la quinzaine même** — les calculer
 * deux fois, avant et après le débit, donnerait deux résultats différents.
 */
final readonly class Paie
{
    /**
     * @param list<string> $impayes  clés des unités qui n'ont pas été payées
     * @param list<string> $messages ce qu'il faut en dire au joueur
     */
    public function __construct(
        public int $masseSalariale,
        public int $verse,
        public array $impayes,
        public array $messages = [],
    ) {
    }

    public static function vide(): self
    {
        return new self(masseSalariale: 0, verse: 0, impayes: []);
    }

    public function estImpaye(string $cle): bool
    {
        return \in_array($cle, $this->impayes, true);
    }

    public function toutEstPaye(): bool
    {
        return [] === $this->impayes;
    }

    /**
     * Ce qui manque pour honorer la quinzaine entière.
     */
    public function manque(): int
    {
        return max(0, $this->masseSalariale - $this->verse);
    }
}
