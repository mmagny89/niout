<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les cinq paliers de renommée et ce qu'ils changent à l'attractivité de la
 * ville (doc 13).
 *
 * Le document tient l'attractivité pour l'effet principal de la renommée : une
 * famille inconnue attire « peu d'artisans/paysans », une famille illustre voit
 * la « migration abondante ». C'est donc ici que la renommée devient concrète —
 * elle décide de ce qu'il en coûte de faire venir du monde, et à partir de quel
 * palier les gens viennent d'eux-mêmes.
 *
 * Les **plages** viennent du doc 13. Les **coûts et les chances de migration**
 * sont inventés : le document décrit les paliers en mots, jamais en nombres.
 */
enum PalierDeRenommee: string
{
    case Inconnue = 'inconnue';
    case Modeste = 'modeste';
    case Reconnue = 'reconnue';
    case Respectee = 'respectee';
    case Illustre = 'illustre';

    public static function pour(int $renommee): self
    {
        return match (true) {
            $renommee < 20 => self::Inconnue,
            $renommee < 40 => self::Modeste,
            $renommee < 60 => self::Reconnue,
            $renommee < 80 => self::Respectee,
            default => self::Illustre,
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Inconnue => 'Inconnue',
            self::Modeste => 'Modeste',
            self::Reconnue => 'Reconnue',
            self::Respectee => 'Respectée',
            self::Illustre => 'Illustre',
        };
    }

    /**
     * Ce que le doc 13 dit de l'attractivité du palier, mot pour mot ou
     * presque — c'est ce que l'écran doit montrer au joueur.
     */
    public function attractivite(): string
    {
        return match ($this) {
            self::Inconnue => 'Peu d\'artisans et de paysans se présentent : les faire venir coûte cher.',
            self::Modeste => 'Le flux de travailleurs reste limité.',
            self::Reconnue => 'Un flux régulier de travailleurs vous parvient.',
            self::Respectee => 'Des familles s\'installent d\'elles-mêmes, et les autres se font moins prier.',
            self::Illustre => 'La migration est abondante : on vient chez vous sans qu\'on vous le demande.',
        };
    }

    /**
     * Ce qu'il en coûte d'aller chercher une maisonnée — vivres de route,
     * dédommagements, promesses. Plus la famille est connue, moins il faut
     * insister.
     *
     * **Valeurs inventées**, calibrées sur l'économie du Delta : trente deben
     * représentent plusieurs quinzaines de vente en début de partie, cinq
     * n'est plus qu'une formalité.
     */
    public function coutDAppel(): int
    {
        return match ($this) {
            self::Inconnue => 30,
            self::Modeste => 22,
            self::Reconnue => 15,
            self::Respectee => 9,
            self::Illustre => 5,
        };
    }

    /**
     * Chance, chaque année, qu'une maisonnée s'installe **sans qu'on l'ait
     * appelée** (doc 13 : « migration spontanée » à Respectée, « abondante »
     * à Illustre). Nulle en dessous : une ville obscure ne fait envie à
     * personne.
     */
    public function chanceDeMigrationSpontanee(): int
    {
        return match ($this) {
            self::Inconnue, self::Modeste, self::Reconnue => 0,
            self::Respectee => 50,
            self::Illustre => 90,
        };
    }
}
