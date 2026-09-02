<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\Medjay;

/**
 * Ce qu'une sortie a donné (doc 03).
 *
 * **Le joueur n'a rien à décider pendant** : le combat se résout d'un bloc, et
 * c'est ce résultat qu'on lui rapporte. Son engagement était en amont — qui
 * envoyer, avec quelles armes, sous quel dieu.
 *
 * Les scores et la probabilité y figurent parce qu'un combat perdu doit
 * pouvoir s'expliquer. Une défaite qu'on ne comprend pas se subit ; une
 * défaite dont on voit qu'on partait à trois contre vingt s'apprend.
 */
final readonly class ResultatDeCombat
{
    /**
     * @param list<Medjay> $blesses ceux qui reviennent, hors de combat un temps
     * @param list<string> $tombes  la spécialisation de ceux qui ne reviennent pas
     */
    public function __construct(
        public bool $victoire,
        public int $scoreAttaque,
        public int $scoreDefense,
        public int $chancesSurCent,
        public int $butin,
        public array $blesses,
        public array $tombes,
    ) {
    }

    public function aPerduDesHommes(): bool
    {
        return [] !== $this->tombes;
    }
}
