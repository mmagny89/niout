<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Les rôles envoyés sur le terrain (doc 04).
 *
 * L'exploration se fait en deux temps : l'éclaireur reconnaît toute case
 * inconnue, puis une action complémentaire s'impose — ou non — selon ce qu'il a
 * trouvé. Le joueur ne décide jamais à l'avance d'envoyer une grosse équipe.
 *
 * Seul l'éclaireur est jouable pour l'instant : l'émissaire suppose des PNJ, le
 * chef d'expédition des zones lourdes, et l'escorte des Medjaÿ. La première
 * mission se joue en difficulté 0, sans aucune zone à bandits — l'éclaireur y
 * suffit.
 */
enum RoleDExploration: string
{
    case Eclaireur = 'eclaireur';
    case Emissaire = 'emissaire';
    case ChefDExpedition = 'chef_expedition';

    public function libelle(): string
    {
        return match ($this) {
            self::Eclaireur => 'Éclaireur',
            self::Emissaire => 'Émissaire',
            self::ChefDExpedition => 'Chef d\'expédition',
        };
    }

    public function mission(): string
    {
        return match ($this) {
            self::Eclaireur => 'Reconnaît la case et en révèle le contenu. Rapide, peu coûteux, sans combat.',
            self::Emissaire => 'Noue le contact avec une population locale : commerce, quêtes, relations.',
            self::ChefDExpedition => 'Encadre une expédition lourde vers une mine ou une carrière éloignée.',
        };
    }

    /**
     * Solde du rôle, en or (doc 04).
     */
    public function cout(): int
    {
        return match ($this) {
            self::Eclaireur => 10,
            self::Emissaire => 30,
            self::ChefDExpedition => 50,
        };
    }

    /**
     * Rôles réellement jouables à ce stade du développement.
     *
     * @return list<self>
     */
    public static function disponibles(): array
    {
        return [self::Eclaireur];
    }
}
