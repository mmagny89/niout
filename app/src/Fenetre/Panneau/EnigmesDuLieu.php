<?php

declare(strict_types=1);

namespace App\Fenetre\Panneau;

use App\Entity\GameSave;
use App\Game\Enigme;
use App\Game\Enigmes;
use App\Game\TypeDeBatiment;

/**
 * Les énigmes qu'on entend dans un bâtiment.
 */
final readonly class EnigmesDuLieu
{
    public function __construct(private Enigmes $enigmes)
    {
    }

    /**
     * Les énigmes qu'on entend dans ce bâtiment-là, prêtes à l'affichage.
     *
     * Les propositions sont mélangées **au rendu**, comme les jetons du
     * déchiffrage : la bonne réponse est toujours la première du catalogue, et
     * se lirait sinon dans la source de la page.
     *
     * @return list<array{enigme: Enigme, propositions: list<string>}>
     */
    public function pour(GameSave $partie, TypeDeBatiment $lieu): array
    {
        return array_values(array_map(
            fn (Enigme $enigme): array => [
                'enigme' => $enigme,
                'propositions' => Melange::liste($this->enigmes->propositionsMontrees($partie, $enigme)),
            ],
            array_filter(
                $this->enigmes->disponibles($partie),
                static fn (Enigme $enigme): bool => $enigme->lieu() === $lieu,
            ),
        ));
    }
}
