<?php

declare(strict_types=1);

namespace App\Game;

use App\Entity\City;

/**
 * Le Charrier : une réquisition auprès de l'armée du pharaon (doc 03, doc 01).
 *
 * **Ce n'est pas un Medjaÿ, et c'est tout le propos.** Les Medjaÿ étaient un
 * corps de sécurité intérieure, armé d'arc et de bouclier ; le char de guerre
 * appartenait à la *mesha*, l'armée professionnelle de l'État, un corps
 * entièrement distinct. Le jeu tient cette distinction historique jusque dans
 * son code : le Charrier n'a pas d'entité, il ne rejoint jamais l'effectif, il
 * ne progresse pas, et il disparaît avec l'expédition qui l'a demandé.
 *
 * C'est aussi ce qui le rend cohérent en jeu : une famille de marchands, même
 * prospère, n'entretient pas sa propre force de chars. On la loue pour une
 * sortie, cher, et l'on rend les hommes.
 *
 * Le document écrit « 100 or ». Le projet compte en **deben** — l'Égypte
 * pharaonique n'a pas de monnaie d'or, et les docs 09 et 13 ont déjà été relus
 * ainsi (arbitrage 10.0).
 */
final readonly class Charrier
{
    /**
     * Sa force au combat (doc 03). Deux fois et demie un fantassin : une unité
     * d'élite, et le prix va avec.
     */
    public const int FORCE = 25;

    /**
     * Ce qu'une réquisition coûte, **par expédition** et non par quinzaine : il
     * n'y a aucun entretien, puisqu'il n'y a rien à entretenir.
     */
    public const int COUT_PAR_EXPEDITION = 100;

    /**
     * Le niveau de Caserne qui ouvre le droit de réquisitionner (doc 01), et
     * celui de Forge qui permet de les équiper (doc 03).
     */
    public const int NIVEAU_DE_CASERNE_REQUIS = 7;
    public const int NIVEAU_DE_FORGE_REQUIS = 4;

    /**
     * Ce qui empêche d'en réquisitionner, ou null si la voie est libre. Dit
     * **avant** la demande, jamais découvert par un refus.
     */
    public static function empechement(City $ville): ?string
    {
        $caserne = $ville->batimentDeType(TypeDeBatiment::Caserne)?->getNiveau() ?? 0;
        $forge = $ville->batimentDeType(TypeDeBatiment::Forge)?->getNiveau() ?? 0;

        return match (true) {
            $caserne < self::NIVEAU_DE_CASERNE_REQUIS => \sprintf(
                'Le pharaon ne prête ses chars qu\'aux villes qui tiennent une Caserne de niveau %d.',
                self::NIVEAU_DE_CASERNE_REQUIS,
            ),
            $forge < self::NIVEAU_DE_FORGE_REQUIS => \sprintf(
                'Vous devez pouvoir les équiper : une Forge de niveau %d est requise.',
                self::NIVEAU_DE_FORGE_REQUIS,
            ),
            default => null,
        };
    }

    public static function disponiblePour(City $ville): bool
    {
        return null === self::empechement($ville);
    }
}
